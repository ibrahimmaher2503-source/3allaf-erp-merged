<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Platform\Models\Store;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $source = Store::query()->where('code', 'QA-SAHIL-WH')->firstOrFail();
            $outlet = Store::query()->where('code', 'QA-SAHIL-OUTLET')->firstOrFail();
            $cairo = Store::query()->where('code', 'QA-CAIRO-WH')->firstOrFail();
            $priced = Product::query()->where('item_code', 'QA-PRICE-400')->firstOrFail();
            $pos = Product::query()->where('item_code', 'QA-POS-64')->firstOrFail();
            $purchase = Product::query()->where('item_code', 'QA-PURCHASE')->firstOrFail();

            Auth::login($administrator);
            try {
                $this->openingAdjustment($source, [
                    ['product_id' => $priced->id, 'quantity_delta' => '8.000000', 'unit_cost' => '100.0000'],
                    ['product_id' => $pos->id, 'quantity_delta' => '20.000000', 'unit_cost' => '20.0000'],
                    ['product_id' => $purchase->id, 'quantity_delta' => '12.000000', 'unit_cost' => '45.0000'],
                ], 'qa-demo-source-opening');
                $transfer = app(CreateStockTransferDraftAction::class)->execute(
                    $source->id,
                    $outlet->id,
                    [['product_id' => $pos->id, 'quantity_requested' => '5.000000']],
                    'qa_replenishment',
                    fake()->sentence(),
                    'qa-demo-outlet-transfer',
                );
                $transfer = app(SubmitStockTransferAction::class)->execute($transfer->id);
                if ($transfer->status === 'submitted') {
                    $transfer = app(ApproveStockTransferAction::class)->execute($transfer->id);
                }
                $transfer = app(DispatchStockTransferAction::class)->execute($transfer->id);
                $line = $transfer->fresh('lines')->lines->firstOrFail();
                app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id => '5.000000'], null, null);

                app(CreateStockTransferDraftAction::class)->execute(
                    $source->id,
                    $cairo->id,
                    [['product_id' => $purchase->id, 'quantity_requested' => '3.000000']],
                    'qa_replenishment',
                    fake()->sentence(),
                    'qa-demo-cairo-transfer-draft',
                );
            } finally {
                Auth::logout();
            }
        });
    }

    /** @param array<int, array{product_id: int, quantity_delta: string, unit_cost: string}> $lines */
    private function openingAdjustment(Store $store, array $lines, string $key): void
    {
        $adjustment = app(SaveInventoryAdjustmentAction::class)->execute([
            'store_id' => $store->id,
            'adjustment_type' => 'entry',
            'reason_code' => 'opening_stock',
            'reason_notes' => fake()->sentence(),
            'idempotency_key' => $key,
        ], $lines);
        app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
    }
}
