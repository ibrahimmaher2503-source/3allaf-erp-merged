<?php

namespace App\Modules\Inventory\Support;

use Illuminate\Database\Eloquent\Builder;

final class LowStockRule
{
    public static function apply(Builder $query): Builder
    {
        return $query
            ->whereRaw('(stock_balances.on_hand - stock_balances.reserved) > 0')
            ->whereHas('product', fn (Builder $product): Builder => $product
                ->where('product_type', 'standard')
                ->whereNotNull('reorder_threshold')
                ->whereRaw('products.reorder_threshold >= (stock_balances.on_hand - stock_balances.reserved)'));
    }
}
