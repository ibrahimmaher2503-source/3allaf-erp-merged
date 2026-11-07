<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Services\PosCalculationService;
use App\Support\UserSafeError;
use InvalidArgumentException;

final class PosCartSnapshot
{
    public function __construct(private readonly EffectivePriceResolver $prices, private readonly PosCalculationService $calculator) {}

    /** @return array<string, mixed> */
    public function build(PosContext $context): array
    {
        if (! $context->isReady() || $context->store === null) {
            return [
                'cart' => collect(request()->session()->get('pos.cart', [])),
                'products' => collect(),
                'lines' => [],
                'preview' => null,
                'error' => $context->disabledReason ?? __('POS is disabled until you open an assigned cashier shift.'),
                'taxApplicable' => false,
                'taxSetting' => null,
                'giftCardSale' => null,
            ];
        }

        $store = $context->store;
        $cart = collect(request()->session()->get('pos.cart', []));
        $pendingGiftCard = request()->session()->get('pos.gift_card_sale');
        $giftCardSale = null;
        if ($cart->isEmpty() && is_array($pendingGiftCard)
            && (int) ($pendingGiftCard['user_id'] ?? 0) === (int) auth()->id()
            && (int) ($pendingGiftCard['store_id'] ?? 0) === (int) $store->id
            && (int) ($pendingGiftCard['shift_id'] ?? 0) === (int) $context->shift?->id
            && (string) ($pendingGiftCard['open_order_uuid'] ?? '') === (string) request()->session()->get(PosOpenOrderManager::SESSION_KEY)) {
            $giftCardSale = GiftCard::query()->visibleTo(auth()->user())->where('identifier', (string) ($pendingGiftCard['identifier'] ?? ''))
                ->where('status', 'unsold')->where('branch_id', $store->branch_id)->first();
        }
        $ids = $cart->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $products = Product::query()->with(['parent.images.attachment', 'images.attachment', 'variantValues.group', 'variantValues.value', 'productUnits.unit'])->whereIn('id', $ids)->get()->keyBy('id');
        $customerId = request()->session()->get('pos.customer_id');
        $balances = StockBalance::query()->where('store_id', $store->id)->whereIn('product_id', $ids)->get()->keyBy('product_id');
        $lines = [];
        $error = null;
        foreach ($cart as $cartLine) {
            $product = $products->get((int) ($cartLine['product_id'] ?? 0));
            $productUnit = filled($cartLine['product_unit_id'] ?? null) ? $product?->productUnits->firstWhere('id', (int) $cartLine['product_unit_id']) : $product?->productUnits->firstWhere('is_base_unit', true);
            $price = $product ? $this->prices->resolve($product->id, (int) $store->id, productUnitId: $productUnit?->id, customerId: $customerId ? (int) $customerId : null) : null;
            if (! $product?->isSellable() || ! $price) {
                $error = __('One or more cart SKUs became inactive, unpriced, or invalid. The cart was preserved.');

                continue;
            }
            $unitPrice = isset($cartLine['open_price_amount']) ? (string) $cartLine['open_price_amount'] : (string) $price->amount;
            $balance = $balances->get($product->id);
            $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 6) : '0';
            $factor = (string) ($productUnit?->conversion_factor ?? '1');
            $available = bccomp($factor, '0', 6) > 0 ? bcdiv($available, $factor, 6) : '0.000000';
            $lines[] = ['product' => $product, 'product_unit' => $productUnit, 'quantity' => (string) $cartLine['quantity'], 'available_quantity' => $available, 'unit_price' => $unitPrice, 'discount_amount' => (string) ($cartLine['discount_amount'] ?? '0.00'), 'price' => $price, 'cart' => $cartLine];
        }
        $taxApplicable = (bool) request()->session()->get('pos.tax_applicable', false);
        $order = request()->hasSession() ? app(PosOpenOrderManager::class)->active(request(), auth()->user(), $context, false) : null;
        $discountType = $order?->invoice_discount_type;
        $discountValue = (string) ($order?->invoice_discount_value ?? '0.00');
        $effectiveTaxes = TaxSetting::query()->where('company_id', $store->company_id)->where('status', 'active')->where('is_default', true)->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))->limit(2)->get();
        $taxSetting = $effectiveTaxes->count() === 1 && $effectiveTaxes->first()?->rate !== null ? $effectiveTaxes->first() : null;
        if ($taxApplicable && $taxSetting === null) {
            $error = __('Tax cannot be enabled until exactly one effective company default tax rule with a configured rate exists.');
        }
        try {
            if ($giftCardSale !== null) {
                $preview = $this->calculator->calculate([['quantity' => '1', 'unit_price' => (string) $giftCardSale->issued_value, 'discount_amount' => '0.00']]);

                return [...compact('cart', 'products', 'lines', 'preview', 'error', 'taxSetting', 'discountType', 'discountValue', 'giftCardSale'), 'taxApplicable' => false];
            }
            $calculationLines = array_map(fn (array $line): array => ['quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount_amount' => $line['discount_amount']], $lines);
            $beforeDiscount = $lines === [] ? null : $this->calculator->calculate($calculationLines);
            $invoiceDiscount = InvoiceDiscount::amount($discountType, $discountValue, (string) ($beforeDiscount['taxable_base'] ?? '0.00'));
            $preview = $lines === [] ? null : $this->calculator->calculate($calculationLines, $invoiceDiscount, $taxApplicable ? ['applicable' => true, 'rate' => $taxSetting?->rate, 'inclusive' => (bool) ($taxSetting?->is_tax_inclusive ?? false)] : ['applicable' => false]);
        } catch (InvalidArgumentException $exception) {
            $preview = null;
            $error = UserSafeError::message($exception);
        }

        return [...compact('cart', 'products', 'lines', 'preview', 'error', 'taxApplicable', 'taxSetting', 'discountType', 'discountValue'), 'giftCardSale' => null];
    }
}
