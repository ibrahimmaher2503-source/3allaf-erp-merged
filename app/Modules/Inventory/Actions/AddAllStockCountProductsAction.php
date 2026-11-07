<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AddAllStockCountProductsAction
{
    public function execute(int $countId, int $storeId): int
    {
        Gate::authorize('stock_counts.edit');

        return DB::transaction(function () use ($countId, $storeId): int {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Products can only be added while counting is active.'));
            }
            if ($count->scope_type === 'random') {
                throw new InvalidArgumentException(__('Random counts include only individually selected or scanned products.'));
            }
            abort_unless($count->locations()->where('store_id', $storeId)->exists(), 404);
            app(AssertInventoryStoreScope::class)->execute($storeId);
            $before = $count->lines()->where('store_id', $storeId)->count();

            Product::query()->sellable()->select('id')->chunkById(300, function ($products) use ($count, $storeId): void {
                $ids = $products->pluck('id');
                $balances = StockBalance::query()->where('store_id', $storeId)->whereIn('product_id', $ids)
                    ->lockForUpdate()->get(['product_id', 'on_hand'])->pluck('on_hand', 'product_id');
                $watermarks = StockMovement::query()->where('store_id', $storeId)->whereIn('product_id', $ids)
                    ->selectRaw('product_id, MAX(id) AS last_id')->groupBy('product_id')->pluck('last_id', 'product_id');
                $now = now();
                $rows = $products->map(fn (Product $product): array => [
                    'stock_count_id' => $count->id,
                    'store_id' => $storeId,
                    'product_id' => $product->id,
                    'reference_on_hand' => $balances[$product->id] ?? '0',
                    'reference_movement_id' => $watermarks[$product->id] ?? 0,
                    'is_counted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                $count->lines()->insertOrIgnore($rows);
            });

            $added = $count->lines()->where('store_id', $storeId)->count() - $before;
            app(RecordAuditEvent::class)->execute('inventory', 'add_all_stock_count_products', $count, null, ['added_lines' => $added], storeId: $storeId);

            return $added;
        });
    }
}
