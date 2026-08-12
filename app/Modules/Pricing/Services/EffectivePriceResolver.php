<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\JoinClause;

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

    /**
     * Resolve the current effective price for a bounded product set without
     * issuing one query per product (for discovery/read-only POS surfaces).
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, PriceLine>
     */
    public function resolveForStore(array $productIds, int $storeId, ?Carbon $at = null): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            return collect();
        }

        $at ??= now();

        $effectiveVersions = PriceLine::query()
            ->select('price_lines.product_id')
            ->selectRaw('MAX(price_lines.price_version_id) as price_version_id')
            ->join('price_versions', 'price_versions.id', '=', 'price_lines.price_version_id')
            ->where('price_lines.store_id', $storeId)
            ->whereIn('price_lines.product_id', $productIds)
            ->where('price_versions.state', PriceVersionState::Approved->value)
            ->where(fn ($query) => $query->whereNull('price_versions.effective_from')->orWhere('price_versions.effective_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('price_versions.effective_to')->orWhere('price_versions.effective_to', '>', $at))
            ->groupBy('price_lines.product_id');

        return PriceLine::query()
            ->select('price_lines.*')
            ->joinSub($effectiveVersions, 'effective_price_versions', function (JoinClause $join): void {
                $join->on('effective_price_versions.product_id', '=', 'price_lines.product_id')
                    ->on('effective_price_versions.price_version_id', '=', 'price_lines.price_version_id');
            })
            ->where('price_lines.store_id', $storeId)
            ->get()
            ->keyBy('product_id');
    }
}
