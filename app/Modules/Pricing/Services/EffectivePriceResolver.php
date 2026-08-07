<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Support\Carbon;

final class EffectivePriceResolver
{
    public function resolve(int $productId, int $storeId, ?Carbon $at = null): ?PriceLine
    {
        $at ??= now();

        return PriceLine::query()
            ->with(['version.priceList', 'product', 'store'])
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->whereHas('version', function ($query) use ($at): void {
                $query->where('state', PriceVersionState::Approved->value)
                    ->where(function ($scope) use ($at): void {
                        $scope->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
                    })
                    ->where(function ($scope) use ($at): void {
                        $scope->whereNull('effective_to')->orWhere('effective_to', '>', $at);
                    });
            })
            ->orderByDesc('price_version_id')
            ->first();
    }

    public function isPriced(int $productId, int $storeId, ?Carbon $at = null): bool
    {
        return $this->resolve($productId, $storeId, $at) !== null;
    }
}
