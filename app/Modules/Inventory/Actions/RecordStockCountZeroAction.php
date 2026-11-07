<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\ResolveStockCountProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordStockCountZeroAction
{
    public function execute(int $countId, int $storeId, string $lookup): StockCountLine
    {
        Gate::authorize('stock_counts.participate');

        return DB::transaction(function () use ($countId, $storeId, $lookup): StockCountLine {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Count input is closed for this session.'));
            }
            if (! $count->members()->where('user_id', Auth::id())->exists()) {
                throw new InvalidArgumentException(__('You are not on this count team. Ask the count manager to assign you.'));
            }
            if (! $count->locations()->where('store_id', $storeId)->exists()) {
                throw new InvalidArgumentException(__('This location is outside the count session.'));
            }
            app(AssertInventoryStoreScope::class)->execute($storeId);

            $product = app(ResolveStockCountProduct::class)->resolve($lookup);

            $line = $count->lines()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
            if ($line === null) {
                if ($count->count_type === 'full' || in_array($count->scope_type, ['category', 'supplier'], true)) {
                    throw new InvalidArgumentException(__('This product is outside the count-session scope.'));
                }
                $balance = StockBalance::query()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
                $watermark = StockMovement::query()->where('store_id', $storeId)->where('product_id', $product->id)->max('id') ?? 0;
                $line = $count->lines()->create(['store_id' => $storeId, 'product_id' => $product->id, 'reference_on_hand' => $balance?->on_hand ?? '0', 'reference_movement_id' => $watermark, 'is_counted' => false]);
            }
            if ($line->completed_at !== null && $line->explicit_zero) {
                return $line;
            }

            return app(CompleteStockCountLineAction::class)->execute($line->id, true);
        });
    }
}
