<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Services\PosCalculationService;
use InvalidArgumentException;

final class PosCartSnapshot
{
    public function __construct(private readonly EffectivePriceResolver $prices, private readonly PosCalculationService $calculator) {}

    /** @return array<string, mixed> */
    public function build(Store $store): array
    {
        $cart = collect(request()->session()->get('pos.cart', []));
        $ids = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $products = Product::query()->with(['parent.images.attachment', 'images.attachment', 'variantValues.group', 'variantValues.value'])->whereIn('id', $ids)->get()->keyBy('id');
        $prices = $this->prices->resolveForStore($ids->all(), (int) $store->id);
        $lines = [];
        $error = null;
        foreach ($cart as $cartLine) {
            $product = $products->get((int) ($cartLine['product_id'] ?? 0));
            $price = $product ? $prices->get($product->id) : null;
            if (! $product?->isSellable() || ! $price) {
                $error = __('One or more cart SKUs became inactive, unpriced, or invalid. The cart was preserved.');

                continue;
            }
            $lines[] = ['product' => $product, 'quantity' => (string) $cartLine['quantity'], 'unit_price' => (string) ($cartLine['open_price_amount'] ?? $price->amount), 'discount_amount' => (string) ($cartLine['discount_amount'] ?? '0.00'), 'price' => $price, 'cart' => $cartLine];
        }
        $taxApplicable = (bool) request()->session()->get('pos.tax_applicable', false);
        $tax = TaxSetting::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))->get();
        $taxSetting = $tax->count() === 1 ? $tax->first() : null;
        try {
            $preview = $lines === [] ? null : $this->calculator->calculate(array_map(fn (array $line): array => ['quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount_amount' => $line['discount_amount']], $lines), '0.00', $taxApplicable ? ['applicable' => true, 'rate' => $taxSetting?->rate, 'inclusive' => (bool) ($taxSetting?->is_tax_inclusive ?? false)] : ['applicable' => false]);
        } catch (InvalidArgumentException $exception) {
            $preview = null;
            $error = $exception->getMessage();
        }

        return compact('cart', 'products', 'lines', 'preview', 'error', 'taxApplicable', 'taxSetting');
    }
}
