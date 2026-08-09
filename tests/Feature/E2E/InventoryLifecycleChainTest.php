<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\RequestStockTransferApprovalAction;
use App\Modules\Inventory\Actions\ResolveTransferDifferenceAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryAdjustmentLine;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferLine;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Backend business-chain E2E: opening stock -> Transfer -> Receipt (with a
 * shortage) -> Difference resolution -> Adjustment -> Count -> Reconciliation,
 * tracing one product across two stores through the full inventory lifecycle
 * in a single continuous flow. Every prior test (InventoryWorkflowIntegrityTest)
 * exercises one stage in isolation with its own throwaway balance; none proves
 * the balance carries correctly from stage to stage the way real warehouse
 * operations chain together, or that a denied cross-store actor causes no
 * mutation partway through the chain.
 *
 * Scenario ID: E2E-15/16. Requirements: INV-01, INV-03..09, NFR-01..03/06.
 * This is a backend/Pest business-integration chain, NOT a browser E2E run —
 * see testing/results/PRODUCTION-RELEASE-GATE.md gate #9 for that distinction.
 */
final class InventoryLifecycleChainTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_stock_flows_through_transfer_difference_adjustment_and_count_reconciliation(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('INV-CHAIN-BR');
        $source = $this->store($branch, 'INV-CHAIN-SRC', 'warehouse');
        $destination = $this->store($branch, 'INV-CHAIN-DST', 'selling');
        $foreignBranch = $this->branch('INV-CHAIN-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'INV-CHAIN-FGN-ST', 'warehouse');

        $manager = $this->userWith('inv-chain-manager', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$source->id, $destination->id]);
        $secondManager = $this->userWith('inv-chain-manager-2', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$source->id, $destination->id]);
        $counter = $this->userWith('inv-chain-counter', ['stock-counter'], branchIds: [$branch->id], storeIds: [$destination->id]);
        $foreignManager = $this->userWith('inv-chain-foreign-manager', ['warehouse-manager'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);

        $category = Category::query()->create(['code' => 'INV-CHAIN-CAT', 'name_ar' => 'تصنيف', 'name_en' => 'Chain Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'INV-CHAIN-PROD', 'name_ar' => 'منتج', 'name_en' => 'Chain Product', 'category_id' => $category->id, 'status' => 'active']);

        // --- OPENING STOCK at the source (warehouse) store -----------------
        $this->actingAs($manager);
        app(PostInventoryMovement::class)->execute($product->id, $source->id, '20', 'opening_adjustment', '8.0000', 'INV-CHAIN-OPENING-1');
        self::assertSame('20.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $source->id)->value('on_hand'));

        // A denied cross-store actor must cause no mutation before the real
        // chain proceeds — a foreign-branch warehouse manager cannot dispatch
        // stock the destination store never requested from a store they are
        // not scoped to.
        $forgedTransfer = StockTransfer::query()->create([
            'transfer_number' => 'INV-CHAIN-FORGED', 'source_store_id' => $source->id,
            'destination_store_id' => $foreignStore->id, 'status' => 'submitted',
            'requested_by' => $manager->id, 'idempotency_key' => 'INV-CHAIN-FORGED-KEY', 'lock_version' => 1,
        ]);
        $movementCountBeforeDeniedAttempt = StockMovement::query()->count();
        $this->actingAs($foreignManager);
        try {
            app(ApproveStockTransferAction::class)->execute($forgedTransfer->id);
            self::fail('A warehouse manager scoped to a foreign store approved a transfer touching a store they cannot access.');
        } catch (AuthorizationException) {
            self::addToAssertionCount(1);
        }
        self::assertSame('submitted', $forgedTransfer->fresh()->status, 'The denied cross-store attempt must leave the transfer state unchanged.');
        self::assertSame($movementCountBeforeDeniedAttempt, StockMovement::query()->count(), 'The denied cross-store attempt must post no stock movement.');

        // --- TRANSFER: source -> destination, with a receipt shortage -----
        $this->actingAs($manager);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'INV-CHAIN-TR-1', 'source_store_id' => $source->id,
            'destination_store_id' => $destination->id, 'status' => 'submitted',
            'requested_by' => $manager->id, 'idempotency_key' => 'INV-CHAIN-TR-1-KEY', 'lock_version' => 1,
        ]);
        $line = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $product->id,
            'quantity_requested' => '10', 'unit_cost' => '8',
        ]);

        app(RequestStockTransferApprovalAction::class)->execute($transfer->id);
        $this->actingAs($secondManager);
        self::assertSame('approved', app(ApproveStockTransferAction::class)->execute($transfer->id)->status);
        self::assertSame('in_transit', app(DispatchStockTransferAction::class)->execute($transfer->id)->status);
        self::assertSame('10.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $source->id)->value('on_hand'), '20 opening - 10 dispatched.');

        $received = app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '8'], 'shortage', 'Two units short on arrival');
        self::assertSame('difference_review', $received->status);
        self::assertSame('8.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $destination->id)->value('on_hand'));

        $resolved = app(ResolveTransferDifferenceAction::class)->execute($transfer->id, 'shortage', 'Two units short on arrival');
        self::assertSame('received', $resolved->status);
        self::assertSame('resolved', $resolved->difference_status);
        self::assertNotNull(AuditLog::query()->where('event', 'receive_stock_transfer')->where('source_id', (string) $transfer->id)->first());

        // --- ADJUSTMENT at the destination: correct a known damage, with
        // separation of duties (creator cannot self-approve) -----------------
        $this->actingAs($manager);
        $adjustment = InventoryAdjustment::query()->create([
            'adjustment_number' => 'INV-CHAIN-ADJ-1', 'store_id' => $destination->id,
            'adjustment_type' => 'exit', 'status' => 'draft', 'reason_code' => 'damage',
            'reason_notes' => 'One unit damaged on receipt', 'created_by' => $manager->id,
            'idempotency_key' => 'INV-CHAIN-ADJ-1-KEY',
        ]);
        InventoryAdjustmentLine::query()->create([
            'inventory_adjustment_id' => $adjustment->id, 'product_id' => $product->id,
            'quantity_delta' => '-1', 'unit_cost' => '8',
        ]);
        self::assertSame('submitted', app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id)->status);

        try {
            app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
            self::fail('The adjustment creator approved their own adjustment.');
        } catch (InvalidArgumentException) {
            self::assertSame('submitted', $adjustment->fresh()->status);
        }

        $this->actingAs($secondManager);
        self::assertSame('approved', app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id)->status);
        $afterAdjustment = StockBalance::query()->where('product_id', $product->id)->where('store_id', $destination->id)->value('on_hand');
        self::assertSame('7.000000', (string) $afterAdjustment, '8 received - 1 damaged.');
        self::assertNotNull(AuditLog::query()->where('event', 'approve_inventory_adjustment')->where('source_id', (string) $adjustment->id)->first());

        // --- COUNT: a stock counter finds a further variance, an intervening
        // sale happens during the count window, reconciliation posts the net
        // difference as a further adjustment and preserves an uncounted item -
        $this->actingAs($counter);
        $count = StockCount::query()->create([
            'count_number' => 'INV-CHAIN-CNT-1', 'count_type' => 'partial', 'scope_type' => 'store',
            'branch_id' => $branch->id, 'store_id' => $destination->id, 'status' => 'in_progress',
            'reference_at' => now()->subMinute(), 'created_by' => $counter->id, 'assigned_to' => $counter->id,
            'idempotency_key' => 'INV-CHAIN-CNT-1-KEY',
        ]);
        // `reference_at` (a minute ago) predates this whole chain, so the
        // count's baseline is the true balance at that instant — zero, since
        // the destination store had no stock before the transfer — and every
        // movement since (the +8 receipt, the -1 adjustment, the -1 sale
        // below) is picked up by SubmitStockCountAction's "since reference_at"
        // window to produce `expected_quantity`.
        $countedLine = StockCountLine::query()->create([
            'stock_count_id' => $count->id, 'product_id' => $product->id,
            'reference_on_hand' => '0', 'counted_quantity' => '6', 'is_counted' => true,
            'input_method' => 'manual',
        ]);
        $uncounted = Product::query()->create(['item_code' => 'INV-CHAIN-UNCNT', 'name_ar' => 'غير معدود', 'name_en' => 'Uncounted', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $uncounted->id, 'store_id' => $destination->id, 'on_hand' => '3', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '4', 'total_value' => '12', 'version' => 1]);
        StockCountLine::query()->create(['stock_count_id' => $count->id, 'product_id' => $uncounted->id, 'reference_on_hand' => '3', 'is_counted' => false]);

        $this->actingAs($manager);
        app(PostInventoryMovement::class)->execute($product->id, $destination->id, '-1', 'sale', null, 'INV-CHAIN-COUNT-WINDOW-SALE');

        $this->actingAs($counter);
        $countedLine = $countedLine->fresh();
        self::assertSame('submitted', app(SubmitStockCountAction::class)->execute($count->id)->status);
        self::assertSame('6.000000', (string) $countedLine->fresh()->expected_quantity, '0 reference + 8 receipt - 1 adjustment - 1 intervening sale.');
        self::assertSame('0.000000', (string) $countedLine->fresh()->variance_quantity, 'The count matched expected once the intervening sale is accounted for.');

        $this->actingAs($manager);
        self::assertSame('reconciled', app(ReconcileStockCountAction::class)->execute($count->id)->status);
        self::assertSame('6.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $destination->id)->value('on_hand'));
        self::assertSame('3.000000', (string) StockBalance::query()->where('product_id', $uncounted->id)->where('store_id', $destination->id)->value('on_hand'), 'An uncounted item must be preserved untouched by reconciliation.');
        self::assertNotNull(AuditLog::query()->where('event', 'reconcile_stock_count')->where('source_id', (string) $count->id)->first());

        // The full chain: opening + dispatch + receipt + adjustment + sale +
        // (zero-variance) reconciliation movement for the traced product.
        self::assertSame(
            ['opening_adjustment', 'transfer_dispatch', 'transfer_receipt', 'inventory_exit', 'sale'],
            StockMovement::query()->where('product_id', $product->id)->orderBy('id')->pluck('movement_type')->all(),
        );
    }
}
