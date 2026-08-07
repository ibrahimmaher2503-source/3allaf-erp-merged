<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryAdjustmentLine;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferLine;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('DemoInventorySeeder requires local Demo Auth.');
        }

        $admin = User::query()->where('username', 'demo-admin')->firstOrFail();
        $reviewer = User::query()->where('username', 'demo-reviewer')->firstOrFail();
        $counter = User::query()->where('username', 'demo-branch-manager')->firstOrFail();
        $sellingStore = Store::query()->where('code', 'DEMO-SELL')->firstOrFail();
        $warehouseStore = Store::query()->where('code', 'DEMO-WH')->firstOrFail();
        $productOne = Product::query()->where('item_code', 'DEMO-PROD-001')->firstOrFail();
        $productTwo = Product::query()->where('item_code', 'DEMO-PROD-002')->firstOrFail();

        DB::transaction(function () use ($admin, $reviewer, $counter, $sellingStore, $warehouseStore, $productOne, $productTwo): void {
            $this->openingMovement($productOne->id, $sellingStore->id, '5.000000', '25.0000', 'DEMO-OPENING:SELL:001');
            $this->openingMovement($productTwo->id, $sellingStore->id, '4.000000', '22.5000', 'DEMO-OPENING:SELL:002');
            $this->openingMovement($productOne->id, $warehouseStore->id, '2.000000', '25.0000', 'DEMO-OPENING:WH:001');
            if (StockMovement::query()->whereIn('movement_type', ['transfer_dispatch', 'transfer_receipt', 'inventory_exit', 'inventory_entry', 'count_reconciliation'])->exists()) {
                $this->rebuildBalancesFromMovements();
            } else {
                $this->balance($productOne->id, $sellingStore->id, '5.000000', '2.000000', '25.0000', '125.0000');
                $this->balance($productTwo->id, $sellingStore->id, '4.000000', '0.000000', '22.5000', '90.0000');
                $this->balance($productOne->id, $warehouseStore->id, '2.000000', '0.000000', '25.0000', '50.0000');
            }

            $transfer = StockTransfer::query()->firstOrCreate(
                ['idempotency_key' => 'DEMO-TRANSFER-001'],
                [
                    'transfer_number' => 'DEMO-TR-0001',
                    'source_store_id' => $warehouseStore->id,
                    'destination_store_id' => $sellingStore->id,
                    'status' => 'submitted',
                    'difference_status' => null,
                    'reason_code' => 'store_replenishment',
                    'reason_notes' => 'DEMO ONLY replenishment walkthrough.',
                    'requested_by' => $reviewer->id,
                    'approved_by' => null,
                    'dispatched_by' => null,
                    'received_by' => null,
                    'approved_at' => null,
                    'dispatched_at' => null,
                    'received_at' => null,
                    'lock_version' => 1,
                    'notes' => 'DEMO ONLY. Try approve, dispatch, then receive with a difference reason.',
                ],
            );
            StockTransferLine::query()->firstOrCreate(
                ['stock_transfer_id' => $transfer->id, 'product_id' => $productOne->id],
                ['quantity_requested' => '1.000000', 'quantity_dispatched' => '0.000000', 'quantity_received' => '0.000000', 'unit_cost' => '25.0000', 'difference_quantity' => '0.000000', 'difference_type' => null, 'difference_reason' => null],
            );

            $adjustment = InventoryAdjustment::query()->firstOrCreate(
                ['idempotency_key' => 'DEMO-ADJUSTMENT-001'],
                [
                    'adjustment_number' => 'DEMO-ADJ-0001',
                    'store_id' => $sellingStore->id,
                    'adjustment_type' => 'exit',
                    'status' => 'draft',
                    'reason_code' => 'demo_damage_review',
                    'reason_notes' => 'DEMO ONLY. Negative stock remains blocked by default.',
                    'allow_negative' => false,
                    'created_by' => $reviewer->id,
                    'submitted_by' => null,
                    'approved_by' => null,
                    'reversed_by' => null,
                    'submitted_at' => null,
                    'approved_at' => null,
                    'reversed_at' => null,
                    'lock_version' => 1,
                    'notes' => 'DEMO ONLY. Submit then approve as a separate persona if desired.',
                ],
            );
            InventoryAdjustmentLine::query()->firstOrCreate(
                ['inventory_adjustment_id' => $adjustment->id, 'product_id' => $productTwo->id],
                ['quantity_delta' => '-1.000000', 'unit_cost' => '22.5000', 'before_on_hand' => null, 'after_on_hand' => null],
            );

            $count = StockCount::query()->firstOrCreate(
                ['idempotency_key' => 'DEMO-COUNT-001'],
                [
                    'count_number' => 'DEMO-COUNT-0001',
                    'count_type' => 'partial',
                    'scope_type' => 'store',
                    'branch_id' => $sellingStore->branch_id,
                    'store_id' => $sellingStore->id,
                    'category_id' => null,
                    'supplier_id' => null,
                    'status' => 'in_progress',
                    'reference_at' => now()->subMinutes(10),
                    'submitted_at' => null,
                    'reconciled_at' => null,
                    'created_by' => $admin->id,
                    'assigned_to' => $counter->id,
                    'approved_by' => null,
                    'lock_version' => 1,
                    'notes' => 'DEMO ONLY. Product 2 is intentionally uncounted and must never be auto-zeroed.',
                ],
            );
            StockCountLine::query()->firstOrCreate(
                ['stock_count_id' => $count->id, 'product_id' => $productOne->id],
                ['reference_on_hand' => '5.000000', 'movement_quantity_after_reference' => '0.000000', 'expected_quantity' => '5.000000', 'counted_quantity' => '4.000000', 'variance_quantity' => null, 'is_counted' => true, 'input_method' => 'manual', 'recount_number' => 0, 'counted_at' => now()->subMinutes(2), 'notes' => 'DEMO variance: -1 after reconciliation.'],
            );
            StockCountLine::query()->firstOrCreate(
                ['stock_count_id' => $count->id, 'product_id' => $productTwo->id],
                ['reference_on_hand' => '4.000000', 'movement_quantity_after_reference' => '0.000000', 'expected_quantity' => '4.000000', 'counted_quantity' => null, 'variance_quantity' => null, 'is_counted' => false, 'input_method' => null, 'recount_number' => 0, 'counted_at' => null, 'notes' => 'DEMO intentionally uncounted; review required.'],
            );
        });
    }

    private function openingMovement(int $productId, int $storeId, string $quantity, string $unitCost, string $key): void
    {
        StockMovement::query()->firstOrCreate($this->key($key), [
            'product_id' => $productId,
            'store_id' => $storeId,
            'movement_type' => 'demo_opening_balance',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $this->multiply($quantity, $unitCost),
            'consumed_cost' => '0.0000',
            'source_type' => self::class,
            'source_id' => null,
            'source_line_id' => null,
            'posted_at' => now()->subMinutes(30),
            'created_by' => null,
        ]);
    }

    private function balance(int $productId, int $storeId, string $onHand, string $reserved, string $averageCost, string $totalValue): void
    {
        StockBalance::query()->firstOrCreate(
            ['product_id' => $productId, 'store_id' => $storeId],
            ['on_hand' => $onHand, 'reserved' => $reserved, 'in_transit' => '0.000000', 'average_cost' => $averageCost, 'total_value' => $totalValue, 'version' => 1],
        );
    }

    private function rebuildBalancesFromMovements(): void
    {
        StockBalance::query()->each(function (StockBalance $balance): void {
            $movementSum = (float) StockMovement::query()->where('product_id', $balance->product_id)->where('store_id', $balance->store_id)->sum('quantity');
            $balance->update([
                'on_hand' => number_format($movementSum, 6, '.', ''),
                'total_value' => number_format($movementSum * (float) $balance->average_cost, 4, '.', ''),
                'version' => max(1, (int) $balance->version + 1),
            ]);
        });
    }

    /** @return numeric-string */
    private function multiply(string $left, string $right): string
    {
        return bcmul($left, $right, 4); // @phpstan-ignore-line
    }

    /** @return array{ idempotency_key: string } */
    private function key(string $key): array
    {
        return ['idempotency_key' => $key];
    }
}
