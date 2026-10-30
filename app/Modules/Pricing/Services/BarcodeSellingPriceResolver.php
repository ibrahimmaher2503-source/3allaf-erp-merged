<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Collection;

final class BarcodeSellingPriceResolver
{
    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, string>
     */
    public function resolveForStore(array $productIds, Store $store): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            return collect();
        }

        $listResolver = app(PriceListResolver::class);
        $list = $listResolver->listForOutlet($store);
        $effective = app(EffectivePriceResolver::class)->resolveForStore($productIds, $store->id);
        $products = Product::query()->sellable()->whereKey($productIds)->get()->keyBy('id');

        return collect($productIds)->mapWithKeys(function (int $productId) use ($effective, $list, $listResolver, $products): array {
            $price = $effective->get($productId);
            if ($price?->exists) {
                return [$productId => (string) $price->amount];
            }

            $product = $products->get($productId);

            return $product === null ? [] : [$productId => $listResolver->resolve($product, $list)->finalPrice];
        });
    }
}
