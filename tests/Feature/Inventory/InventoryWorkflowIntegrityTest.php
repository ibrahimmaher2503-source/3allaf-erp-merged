<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
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
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: INV-01..INV-09, NFR-01..NFR-03, NFR-06.
 * Test cases: TC-INV-003..011 and TC-CNT-001..010.
 */
final class InventoryWorkflowIntegrityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_transfer_lifecycle_posts_source_destination_and_difference_without_duplicate_receipt(): void
    {
        $scenario = $this->inventoryScenario();
        $this->actingAs($scenario['manager']);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-TEST-001',
            'source_store_id' => $scenario['source']->id,
            'destination_store_id' => $scenario['destination']->id,
            'status' => 'submitted',
            'requested_by' => $scenario['manager']->id,
            'idempotency_key' => 'TR-IDEMP-001',
            'lock_version' => 1,
        ]);
        $line = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $scenario['product']->id,
            'quantity_requested' => '4',
            'unit_cost' => '5',
        ]);

        self::assertSame('approved', app(ApproveStockTransferAction::class)->execute($transfer->id)->status);
        self::assertSame('in_transit', app(DispatchStockTransferAction::class)->execute($transfer->id)->status);
        self::assertSame('6.000000', (string) StockBalance::query()->where('store_id', $scenario['source']->id)->value('on_hand'));
        self::assertSame('4.000000', (string) StockBalance::query()->where('store_id', $scenario['destination']->id)->value('in_transit'));

        $received = app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '3'], 'shortage', 'One unit not received');
        self::assertSame('difference_review', $received->status);
        self::assertSame('under_review', $received->difference_status);
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $scenario['destination']->id)->value('on_hand'));
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $scenario['destination']->id)->value('in_transit'));

        $resolved = app(ResolveTransferDifferenceAction::class)->execute($transfer->id, 'shortage', 'One unit not received');
        self::assertSame('received', $resolved->status);
        self::assertSame('resolved', $resolved->difference_status);
        self::assertSame(1, StockMovement::query()->where('movement_type', 'transfer_dispatch')->count());
        self::assertSame(1, StockMovement::query()->where('movement_type', 'transfer_receipt')->count());
        self::assertSame(1, AuditLog::query()->where('event', 'receive_stock_transfer')->count());

        $this->expectException(InvalidArgumentException::class);
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '3'], null, null);
    }

    public function test_transfer_receipt_rejects_invalid_difference_and_does_not_post_partial_changes(): void
    {
        $scenario = $this->inventoryScenario();
        $this->actingAs($scenario['manager']);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-TEST-002', 'source_store_id' => $scenario['source']->id,
            'destination_store_id' => $scenario['destination']->id, 'status' => 'in_transit',
            'requested_by' => $scenario['manager']->id, 'idempotency_key' => 'TR-IDEMP-002',
        ]);
        $line = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $scenario['product']->id,
            'quantity_requested' => '2', 'quantity_dispatched' => '2', 'unit_cost' => '5',
        ]);

        try {
            app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '3'], null, null);
            self::fail('A receipt above the dispatched quantity must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame('in_transit', $transfer->fresh()->status);
            self::assertSame(0, StockMovement::query()->count());
            self::assertSame(0, StockBalance::query()->where('store_id', $scenario['destination']->id)->count());
        }
    }

    public function test_adjustment_requires_reason_and_separation_of_duties_then_posts_and_audits(): void
    {
        $scenario = $this->inventoryScenario();
        $creator = $scenario['manager'];
        $approver = $this->userWith('inventory-approver', ['warehouse-manager'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id]);
        $this->actingAs($creator);
        $adjustment = InventoryAdjustment::query()->create([
            'adjustment_number' => 'ADJ-TEST-001', 'store_id' => $scenario['source']->id,
            'adjustment_type' => 'entry', 'status' => 'draft', 'reason_code' => '',
            'created_by' => $creator->id, 'idempotency_key' => 'ADJ-IDEMP-001',
        ]);
        $line = InventoryAdjustmentLine::query()->create([
            'inventory_adjustment_id' => $adjustment->id, 'product_id' => $scenario['product']->id,
            'quantity_delta' => '2', 'unit_cost' => '5',
        ]);

        try {
            app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
            self::fail('An adjustment without a reason must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame('draft', $adjustment->fresh()->status);
        }

        $adjustment->update(['reason_code' => 'opening_correction', 'reason_notes' => 'Documented correction']);
        self::assertSame('submitted', app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id)->status);

        try {
            app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
            self::fail('The adjustment creator must not self-approve.');
        } catch (InvalidArgumentException) {
            self::assertSame('submitted', $adjustment->fresh()->status);
        }

        $this->actingAs($approver);
        self::assertSame('approved', app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id)->status);
        self::assertSame('12.000000', (string) StockBalance::query()->where('store_id', $scenario['source']->id)->value('on_hand'));
        self::assertSame(1, StockMovement::query()->where('movement_type', 'inventory_entry')->count());
        self::assertSame(1, AuditLog::query()->where('event', 'approve_inventory_adjustment')->count());
        self::assertSame($line->id, $adjustment->fresh('lines')->lines->sole()->id);
    }

    public function test_count_reconciles_after_intervening_sale_and_preserves_uncounted_item(): void
    {
        $scenario = $this->inventoryScenario();
        $counter = $this->userWith('inventory-counter', ['stock-counter'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id]);
        $manager = $scenario['manager'];
        $referenceAt = now()->subMinute();
        $count = StockCount::query()->create([
            'count_number' => 'CNT-TEST-001', 'count_type' => 'partial', 'scope_type' => 'store',
            'branch_id' => $scenario['branch']->id, 'store_id' => $scenario['source']->id,
            'status' => 'in_progress', 'reference_at' => $referenceAt, 'created_by' => $counter->id,
            'assigned_to' => $counter->id, 'idempotency_key' => 'CNT-IDEMP-001',
        ]);
        $countedLine = StockCountLine::query()->create([
            'stock_count_id' => $count->id, 'product_id' => $scenario['product']->id,
            'reference_on_hand' => '10', 'counted_quantity' => '11', 'is_counted' => true,
            'input_method' => 'manual',
        ]);
        $uncounted = Product::query()->create([
            'item_code' => 'INV-UNCNT', 'name_ar' => 'غير معدود', 'name_en' => 'Uncounted',
            'category_id' => $scenario['category']->id, 'status' => 'active',
        ]);
        StockBalance::query()->create(['product_id' => $uncounted->id, 'store_id' => $scenario['source']->id, 'on_hand' => '7', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '5', 'total_value' => '35', 'version' => 1]);
        StockCountLine::query()->create(['stock_count_id' => $count->id, 'product_id' => $uncounted->id, 'reference_on_hand' => '7', 'is_counted' => false]);

        $this->actingAs($manager);
        app(PostInventoryMovement::class)->execute($scenario['product']->id, $scenario['source']->id, '2', 'sale', null, 'COUNT-WINDOW-SALE');
        $this->actingAs($counter);
        self::assertSame('submitted', app(SubmitStockCountAction::class)->execute($count->id)->status);
        $countedLine = $countedLine->fresh();
        self::assertSame('12.000000', (string) $countedLine->expected_quantity);
        self::assertSame('-1.000000', (string) $countedLine->variance_quantity);

        $this->actingAs($manager);
        self::assertSame('reconciled', app(ReconcileStockCountAction::class)->execute($count->id)->status);
        self::assertSame('11.000000', (string) StockBalance::query()->where('product_id', $scenario['product']->id)->where('store_id', $scenario['source']->id)->value('on_hand'));
        self::assertSame('7.000000', (string) StockBalance::query()->where('product_id', $uncounted->id)->where('store_id', $scenario['source']->id)->value('on_hand'));
        self::assertSame(1, StockMovement::query()->where('movement_type', 'count_reconciliation')->count());
        self::assertSame(1, AuditLog::query()->where('event', 'reconcile_stock_count')->count());
        self::assertSame(1, InventoryAdjustment::query()->count());
    }

    public function test_inventory_action_fails_closed_for_out_of_scope_store(): void
    {
        $scenario = $this->inventoryScenario();
        $outsideBranch = $this->branch('INV-OUTSIDE');
        $outsideStore = $this->store($outsideBranch, 'INV-OUTSIDE-ST');
        $scoped = $this->userWith('inventory-scoped', ['warehouse-manager'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id]);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-TEST-003', 'source_store_id' => $scenario['source']->id,
            'destination_store_id' => $outsideStore->id, 'status' => 'submitted',
            'requested_by' => $scoped->id, 'idempotency_key' => 'TR-IDEMP-003',
        ]);
        StockTransferLine::query()->create(['stock_transfer_id' => $transfer->id, 'product_id' => $scenario['product']->id, 'quantity_requested' => '1', 'unit_cost' => '5']);

        $this->actingAs($scoped);
        $this->expectException(AuthorizationException::class);
        app(ApproveStockTransferAction::class)->execute($transfer->id);
    }

    public function test_fractional_quantity_is_rejected_for_a_product_without_fractional_configuration(): void
    {
        $scenario = $this->inventoryScenario();
        $this->actingAs($scenario['manager']);

        $this->expectException(InvalidArgumentException::class);
        app(PostInventoryMovement::class)->execute($scenario['product']->id, $scenario['source']->id, '0.500000', 'inventory_entry', '5', 'INV-FRACTIONAL-BLOCK-001');
    }

    public function test_identical_idempotency_replay_returns_the_original_movement_once(): void
    {
        $scenario = $this->inventoryScenario();
        $this->actingAs($scenario['manager']);
        $action = app(PostInventoryMovement::class);

        $first = $action->execute($scenario['product']->id, $scenario['source']->id, '1', 'inventory_entry', '5', 'INV-IDEMPOTENT-SAME-001');
        $replay = $action->execute($scenario['product']->id, $scenario['source']->id, '1', 'inventory_entry', '5', 'INV-IDEMPOTENT-SAME-001');

        self::assertTrue($first->is($replay));
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame('11.000000', (string) StockBalance::query()->where('store_id', $scenario['source']->id)->value('on_hand'));
    }

    public function test_invalid_decimal_boundaries_are_rejected_without_creating_inventory_rows(): void
    {
        $scenario = $this->inventoryScenario();
        $this->actingAs($scenario['manager']);
        $action = app(PostInventoryMovement::class);

        foreach (['', '1e3', '+1', 'NaN', '0x10'] as $index => $invalidQuantity) {
            try {
                $action->execute($scenario['product']->id, $scenario['source']->id, $invalidQuantity, 'inventory_entry', '5', 'INV-DECIMAL-BOUNDARY-'.$index);
                self::fail('Invalid decimal input should be rejected: '.$invalidQuantity);
            } catch (InvalidArgumentException) {
                // Expected validation boundary.
            }
        }

        self::assertSame(0, StockMovement::query()->count());
        self::assertSame('10.000000', (string) StockBalance::query()->where('store_id', $scenario['source']->id)->value('on_hand'));
    }

    /** @return array{branch: Branch, source: Store, destination: Store, manager: User, category: Category, product: Product} */
    private function inventoryScenario(): array
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('INV-WORKFLOW');
        $source = $this->store($branch, 'INV-SOURCE', 'warehouse');
        $destination = $this->store($branch, 'INV-DEST', 'selling');
        $manager = $this->userWith('inventory-manager', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$source->id, $destination->id]);
        $category = Category::query()->create(['code' => 'INV-WORKFLOW-CAT', 'name_ar' => 'مخزون', 'name_en' => 'Inventory', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'INV-WORKFLOW-PROD', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $source->id, 'on_hand' => '10', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '5', 'total_value' => '50', 'version' => 1]);

        return compact('branch', 'source', 'destination', 'manager', 'category', 'product');
    }
}
