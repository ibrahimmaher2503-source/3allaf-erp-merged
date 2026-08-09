<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\RequestStockTransferApprovalAction;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * FAIL-INV-003 / FAIL-INV-004 (testing/results/FAILURE-RECOVERY-SCENARIOS.md) —
 * mid-transaction failure injection for multi-line inventory actions.
 *
 * Both `ReceiveStockTransferAction` and `ReconcileStockCountAction` loop over
 * several lines inside ONE `DB::transaction()`, posting a stock movement per
 * line. Every prior test in this suite only ever exercises the *first* line
 * failing (which trivially leaves nothing to roll back) or every line
 * succeeding. Neither proves the actual atomicity claim: if line 2 of 2
 * fails, does line 1's already-applied write get rolled back too, or does
 * a real crash mid-loop leave a half-posted transfer/count in the database?
 *
 * These tests force a REAL failure (a genuine `PostInventoryMovement`
 * business-rule rejection — fractional quantity on a non-fractional
 * product) on the second of two lines, so no exception is fabricated or
 * mocked; the fault is a real validation path a real user could trigger.
 * They then assert every write from the FIRST (already-succeeded) line was
 * rolled back along with the second line's failure — proving
 * `DB::transaction()` atomicity across the whole action, not just the
 * failing statement.
 */
final class InventoryFaultInjectionAtomicityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    /**
     * Positive control for the rollback test below. `ReceiveStockTransferAction`
     * has no unconditional pre-loop write (unlike `ReconcileStockCountAction`'s
     * adjustment header), so "zero movements after a line-2 failure" only
     * proves a rollback if line A's write actually happens when nothing
     * fails — otherwise a fluke of `$transfer->lines` iteration order (line
     * B evaluated before line A, throwing before A ever runs) would produce
     * the identical "zero movements" result without proving atomicity at
     * all. This test establishes that both lines DO post when uninterrupted,
     * so the rollback test's zero-state can only be a genuine rollback.
     */
    public function test_two_transfer_receipt_lines_both_post_when_uninterrupted(): void
    {
        $scenario = $this->twoProductScenario('FAIL-INV-003-CTRL');
        $this->actingAs($scenario['manager']);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-FAULT-CTRL-001', 'source_store_id' => $scenario['source']->id,
            'destination_store_id' => $scenario['destination']->id, 'status' => 'submitted',
            'requested_by' => $scenario['manager']->id, 'idempotency_key' => 'TR-FAULT-CTRL-IDEMP-001',
        ]);
        $lineA = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $scenario['productA']->id,
            'quantity_requested' => '4', 'unit_cost' => '5',
        ]);
        $lineB = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $scenario['productB']->id,
            'quantity_requested' => '4', 'unit_cost' => '5',
        ]);

        app(RequestStockTransferApprovalAction::class)->execute($transfer->id);
        $approver = $this->userWith('transfer-fault-approver-control', ['warehouse-manager'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id, $scenario['destination']->id]);
        $this->actingAs($approver);
        app(ApproveStockTransferAction::class)->execute($transfer->id);
        app(DispatchStockTransferAction::class)->execute($transfer->id);

        $received = app(ReceiveStockTransferAction::class)->execute($transfer->id, [$lineA->id => '4', $lineB->id => '4'], null, null);

        self::assertSame('received', $received->status);
        self::assertSame(2, StockMovement::query()->where('movement_type', 'transfer_receipt')->count(), 'Both lines must post when nothing fails.');
        self::assertSame('4.000000', (string) StockBalance::query()->where('product_id', $scenario['productA']->id)->where('store_id', $scenario['destination']->id)->value('on_hand'));
        self::assertSame('4.000000', (string) StockBalance::query()->where('product_id', $scenario['productB']->id)->where('store_id', $scenario['destination']->id)->value('on_hand'));
        self::assertSame('4.000000', (string) $lineB->fresh()->quantity_received);
    }

    public function test_a_failure_on_the_second_transfer_receipt_line_rolls_back_the_first_lines_already_applied_movement(): void
    {
        $scenario = $this->twoProductScenario('FAIL-INV-003');
        $this->actingAs($scenario['manager']);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-FAULT-001', 'source_store_id' => $scenario['source']->id,
            'destination_store_id' => $scenario['destination']->id, 'status' => 'submitted',
            'requested_by' => $scenario['manager']->id, 'idempotency_key' => 'TR-FAULT-IDEMP-001',
        ]);
        $lineA = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $scenario['productA']->id,
            'quantity_requested' => '4', 'unit_cost' => '5',
        ]);
        $lineB = StockTransferLine::query()->create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $scenario['productB']->id,
            'quantity_requested' => '4', 'unit_cost' => '5',
        ]);

        app(RequestStockTransferApprovalAction::class)->execute($transfer->id);
        $approver = $this->userWith('transfer-fault-approver', ['warehouse-manager'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id, $scenario['destination']->id]);
        $this->actingAs($approver);
        app(ApproveStockTransferAction::class)->execute($transfer->id);
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $destinationBalanceABefore = StockBalance::query()->where('product_id', $scenario['productA']->id)->where('store_id', $scenario['destination']->id)->value('on_hand');
        self::assertSame('0.000000', (string) $destinationBalanceABefore, 'Sanity check: nothing received at the destination yet.');
        // `ReceiveStockTransferAction` has no explicit orderBy on `$transfer->lines`
        // (StockTransfer::lines() is a plain hasMany), so the "zero movements"
        // assertion below only proves a genuine rollback of line A's write if
        // line A is actually iterated BEFORE line B — otherwise line B could
        // simply throw before line A is ever reached, which would also leave
        // zero movements without proving anything about rollback. Assert the
        // iteration order this specific run will use before relying on it.
        self::assertSame([$lineA->id, $lineB->id], $transfer->fresh('lines')->lines->pluck('id')->all(), 'Iteration order must be [A, B] for this test\'s rollback claim to be meaningful.');

        try {
            // Line A (fractional-allowed product) receives cleanly first;
            // line B (fractional NOT allowed) then receives a fractional
            // quantity — a real PostInventoryMovement rejection, not a mock.
            app(ReceiveStockTransferAction::class)->execute($transfer->id, [$lineA->id => '4', $lineB->id => '2.5'], null, null);
            self::fail('A fractional receipt against a non-fractional product must be rejected.');
        } catch (InvalidArgumentException) {
            // expected — the assertions below are the actual proof.
        }

        self::assertSame('in_transit', $transfer->fresh()->status, 'The transfer must remain in its pre-receipt state; the whole receipt call failed.');
        self::assertSame('0.000000', (string) $lineA->fresh()->quantity_received, 'Line A must show no receipt at all (column default 0) — its write was rolled back with line B\'s failure.');
        self::assertSame('0.000000', (string) $lineB->fresh()->quantity_received);
        self::assertSame(0, StockMovement::query()->where('movement_type', 'transfer_receipt')->count(), 'Zero receipt movements — including line A\'s, which "succeeded" before line B failed inside the same transaction.');
        self::assertSame('0.000000', (string) StockBalance::query()->where('product_id', $scenario['productA']->id)->where('store_id', $scenario['destination']->id)->value('on_hand'), 'Line A\'s destination balance must be unchanged: a real lost-update-style partial commit would show 4 here.');
        self::assertSame('4.000000', (string) StockBalance::query()->where('product_id', $scenario['productA']->id)->where('store_id', $scenario['destination']->id)->value('in_transit'), 'In-transit for line A must also be unchanged (still dispatched, not yet received).');
        self::assertSame(0, AuditLog::query()->where('event', 'receive_stock_transfer')->where('source_id', (string) $transfer->id)->count(), 'No audit event for a receipt that never actually committed.');
    }

    public function test_a_failure_on_the_second_count_variance_line_rolls_back_the_adjustment_header_and_the_first_lines_movement(): void
    {
        $scenario = $this->twoProductScenario('FAIL-INV-004');
        $counter = $this->userWith('inv-fault-counter', ['stock-counter'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['source']->id]);
        $manager = $scenario['manager'];

        $count = StockCount::query()->create([
            'count_number' => 'CNT-FAULT-001', 'count_type' => 'partial', 'scope_type' => 'store',
            'branch_id' => $scenario['branch']->id, 'store_id' => $scenario['source']->id,
            'status' => 'in_progress', 'reference_at' => now()->subMinute(), 'created_by' => $counter->id,
            'assigned_to' => $counter->id, 'idempotency_key' => 'CNT-FAULT-IDEMP-001',
        ]);
        // Product A: whole-unit variance, will post cleanly and be the
        // "already-applied write" whose rollback this test proves.
        StockCountLine::query()->create([
            'stock_count_id' => $count->id, 'product_id' => $scenario['productA']->id,
            'reference_on_hand' => '10', 'counted_quantity' => '12', 'is_counted' => true, 'input_method' => 'manual',
        ]);
        // Product B: fractional_quantity=false, but the count is off by a
        // fractional amount — a real PostInventoryMovement rejection.
        StockCountLine::query()->create([
            'stock_count_id' => $count->id, 'product_id' => $scenario['productB']->id,
            'reference_on_hand' => '10', 'counted_quantity' => '10.5', 'is_counted' => true, 'input_method' => 'manual',
        ]);

        $this->actingAs($counter);
        app(SubmitStockCountAction::class)->execute($count->id);

        $this->actingAs($manager);
        try {
            app(ReconcileStockCountAction::class)->execute($count->id);
            self::fail('A fractional variance against a non-fractional product must be rejected.');
        } catch (InvalidArgumentException) {
            // expected — the assertions below are the actual proof.
        }

        self::assertSame('submitted', $count->fresh()->status, 'The count must remain submitted; reconciliation never actually committed.');
        self::assertSame(0, InventoryAdjustment::query()->where('adjustment_number', 'DEMO-COUNT-ADJ-'.$count->id)->count(), 'The InventoryAdjustment HEADER — created before the line loop — must be rolled back too, not left as an orphan/partial adjustment.');
        self::assertSame(0, InventoryAdjustmentLine::query()->count(), 'Zero adjustment lines, including product A\'s, which was created before product B\'s failure.');
        self::assertSame(0, StockMovement::query()->where('movement_type', 'count_reconciliation')->count(), 'Zero reconciliation movements — product A\'s already-posted movement must be gone too.');
        self::assertSame('10.000000', (string) StockBalance::query()->where('product_id', $scenario['productA']->id)->where('store_id', $scenario['source']->id)->value('on_hand'), 'Product A\'s balance must be unchanged: a real partial commit would show 12 here.');
        self::assertSame(0, AuditLog::query()->where('event', 'reconcile_stock_count')->where('source_id', (string) $count->id)->count(), 'No audit event for a reconciliation that never actually committed.');
    }

    /** @return array{branch: Branch, source: Store, destination: Store, manager: User, category: Category, productA: Product, productB: Product} */
    private function twoProductScenario(string $tag): array
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch($tag.'-BR');
        $source = $this->store($branch, $tag.'-SRC', 'warehouse');
        $destination = $this->store($branch, $tag.'-DST', 'selling');
        $manager = $this->userWith($tag.'-manager', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$source->id, $destination->id]);
        $category = Category::query()->create(['code' => $tag.'-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $productA = Product::query()->create(['item_code' => $tag.'-PROD-A', 'name_ar' => 'أ', 'name_en' => 'Product A', 'category_id' => $category->id, 'status' => 'active', 'fractional_quantity' => true]);
        $productB = Product::query()->create(['item_code' => $tag.'-PROD-B', 'name_ar' => 'ب', 'name_en' => 'Product B', 'category_id' => $category->id, 'status' => 'active', 'fractional_quantity' => false]);
        StockBalance::query()->create(['product_id' => $productA->id, 'store_id' => $source->id, 'on_hand' => '10', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '5', 'total_value' => '50', 'version' => 1]);
        StockBalance::query()->create(['product_id' => $productB->id, 'store_id' => $source->id, 'on_hand' => '10', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '5', 'total_value' => '50', 'version' => 1]);

        return compact('branch', 'source', 'destination', 'manager', 'category', 'productA', 'productB');
    }
}
