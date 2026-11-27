<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\CustomerProductPrice;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EffectivePriceResolver
{
    public function __construct(private readonly PriceListResolver $lists) {}

    public function resolve(int $productId, int $storeId, ?Carbon $at = null, ?int $productUnitId = null, ?int $customerId = null): ?PriceLine
    {
        $at ??= now();
        // One POS request resolves the same line several times (cart, checkout, posting checks).
        // The result is remembered for this request; saving any price record clears it.
        $key = 'pricing.resolved.'.implode(':', [$productId, $storeId, $productUnitId ?? 'base', $customerId ?? 'none', $at->format('Y-m-d H:i')]);

        return RequestMemo::remember($key, fn (): ?PriceLine => $this->resolveFresh($productId, $storeId, $at, $productUnitId, $customerId));
    }

    private function resolveFresh(int $productId, int $storeId, \DateTimeInterface $at, ?int $productUnitId, ?int $customerId): ?PriceLine
    {
        // Store, product and customer are read-only here and repeat for every cart line and
        // POS component, so they are remembered for the current request. Prices are always read fresh.
        $store = RequestMemo::remember('pricing.store.'.$storeId, fn (): ?Store => Store::query()->with(['priceList', 'branch.defaultPriceList'])->find($storeId));
        $product = RequestMemo::remember('pricing.product.'.$productId, fn (): ?Product => Product::query()->sellable()->with(['productUnits.unit', 'barcodes'])->find($productId));
        if ($store === null || $product === null) {
            return null;
        }

        $unit = $productUnitId === null
            ? $product->productUnits->firstWhere('is_base_unit', true)
            : $product->productUnits->firstWhere('id', $productUnitId);
        if (! $unit instanceof ProductUnit || ! $unit->is_sale_unit) {
            return null;
        }

        $customer = $customerId === null ? null : RequestMemo::remember('pricing.customer.'.$customerId, fn (): ?Customer => Customer::query()->with(['createdStore', 'priceList'])->find($customerId));
        if ($customer !== null && (int) $customer->createdStore?->company_id !== (int) $store->company_id) {
            $customer = null;
        }

        $special = $customer === null ? null : CustomerProductPrice::query()
            ->where('company_id', $store->company_id)
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->where('product_unit_id', $unit->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at))
            ->latest('id')->first();
        if ($special !== null) {
            return $this->line($product, $store, $unit, (string) $special->price, 'customer_special', __('سعر خاص للعميل'), $customer->price_list_id, $special->id, null, $special);
        }

        if ($customer?->priceList?->isEffective($at) && (int) $customer->priceList->company_id === (int) $store->company_id) {
            $resolved = $this->lists->resolveForUnit($product, $unit, $customer->priceList, $at);

            return $this->line($product, $store, $unit, $resolved->finalPrice, 'customer_price_list', str_starts_with(app()->getLocale(), 'ar') ? $customer->priceList->name_ar : $customer->priceList->name_en, $resolved->priceListId, null, $resolved->overrideId ?? $resolved->baseOverrideId, $customer->priceList);
        }

        if ($unit->is_base_unit) {
            $legacy = RequestMemo::remember('pricing.legacy.'.$storeId.'.'.$productId.'.'.$at->format('Y-m-d H:i'),
                fn (): ?PriceLine => $this->legacyLines([$productId], $storeId, $at)->get($productId));
            if ($legacy !== null) {
                return $this->decorate($legacy, $unit, 'approved_store_price', __('سعر منفذ معتمد'), $legacy->version?->price_list_id, null, null, $legacy);
            }
        }

        try {
            $list = $this->lists->listForOutlet($store, $at);
            $resolved = $this->lists->resolveForUnit($product, $unit, $list, $at);

            return $this->line($product, $store, $unit, $resolved->finalPrice, 'outlet_price_list', str_starts_with(app()->getLocale(), 'ar') ? $list->name_ar : $list->name_en, $resolved->priceListId, null, $resolved->overrideId ?? $resolved->baseOverrideId, $list);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pre-loads, for a whole cart, the product cards, approved store price lines and list overrides
     * in a few queries instead of several per line. Results stay identical to per-line resolution.
     *
     * @param  array<int, int>  $productIds
     */
    public function warm(array $productIds, int $storeId, ?Carbon $at = null): void
    {
        $at ??= now();
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return;
        }
        $store = RequestMemo::remember('pricing.store.'.$storeId, fn (): ?Store => Store::query()->with(['priceList', 'branch.defaultPriceList'])->find($storeId));
        if ($store === null) {
            return;
        }
        $products = Product::query()->sellable()->with(['productUnits.unit', 'barcodes'])->whereIn('id', $productIds)->get()->keyBy('id');
        $legacy = $this->legacyLines($productIds, $storeId, $at);
        foreach ($productIds as $productId) {
            RequestMemo::put('pricing.product.'.$productId, $products->get($productId));
            RequestMemo::put('pricing.legacy.'.$storeId.'.'.$productId.'.'.$at->format('Y-m-d H:i'), $legacy->get($productId));
        }
        $listIds = [];
        try {
            $listIds[] = $this->lists->listForOutlet($store, $at)->id;
        } catch (\Throwable) {
        }
        $baseId = RequestMemo::remember('pricing.base-list.'.$store->company_id, fn (): ?\App\Modules\Pricing\Models\PriceList => PriceList::query()->where('company_id', $store->company_id)->where('list_number', 0)->first())?->id;
        $listIds[] = $baseId;
        $this->lists->warmOverrides((int) $store->company_id, array_filter($listIds), $productIds);
    }

    /**
     * Newest approved, currently effective store price line per product.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, PriceLine>
     */
    private function legacyLines(array $productIds, int $storeId, \DateTimeInterface $at): Collection
    {
        return PriceLine::query()
            ->with(['version.priceList', 'product', 'store'])
            ->whereIn('product_id', $productIds)->where('store_id', $storeId)
            ->whereHas('version', fn ($query) => $query->where('state', PriceVersionState::Approved->value)
                ->where(fn ($scope) => $scope->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
                ->where(fn ($scope) => $scope->whereNull('effective_to')->orWhere('effective_to', '>', $at)))
            ->orderByDesc('price_version_id')->get()
            ->unique('product_id')->keyBy('product_id');
    }

    public function isPriced(int $productId, int $storeId, ?Carbon $at = null, ?int $productUnitId = null, ?int $customerId = null): bool
    {
        return $this->resolve($productId, $storeId, $at, $productUnitId, $customerId) !== null;
    }

    /** @param array<int, int> $productIds @return Collection<int, PriceLine> */
    public function resolveForStore(array $productIds, int $storeId, ?Carbon $at = null, ?int $customerId = null): Collection
    {
        return collect(array_values(array_unique(array_map('intval', $productIds))))
            ->mapWithKeys(fn (int $id) => ($price = $this->resolve($id, $storeId, $at, null, $customerId)) === null ? [] : [$id => $price]);
    }

    public function identity(PriceLine $price): string
    {
        return implode(':', [$price->price_source, $price->source_key, $price->product_unit_id, $price->amount, $price->minimum_selling_price]);
    }

    public function lockSource(PriceLine $price): ?Model
    {
        return match ($price->price_source) {
            'customer_special' => CustomerProductPrice::query()->lockForUpdate()->find($price->customer_product_price_id),
            'approved_store_price' => PriceLine::query()->lockForUpdate()->find($price->getKey()),
            default => $price->product_price_override_id
                ? ProductPriceOverride::query()->lockForUpdate()->find($price->product_price_override_id)
                : Product::query()->lockForUpdate()->find($price->product_id),
        };
    }

    private function line(Product $product, Store $store, ProductUnit $unit, string $amount, string $source, string $label, ?int $priceListId, ?int $specialId, ?int $overrideId, Model $sourceModel): PriceLine
    {
        $line = new PriceLine(['product_id' => $product->id, 'store_id' => $store->id, 'amount' => $amount, 'reference_amount' => $amount, 'open_price_allowed' => (bool) $product->open_price]);
        $line->setRelation('product', $product);
        $line->setRelation('store', $store);

        return $this->decorate($line, $unit, $source, $label, $priceListId, $specialId, $overrideId, $sourceModel);
    }

    private function decorate(PriceLine $line, ProductUnit $unit, string $source, string $label, ?int $priceListId, ?int $specialId, ?int $overrideId, Model $sourceModel): PriceLine
    {
        $line->setAttribute('product_unit_id', $unit->id);
        $line->setAttribute('price_list_id', $priceListId);
        $line->setAttribute('customer_product_price_id', $specialId);
        $line->setAttribute('product_price_override_id', $overrideId);
        $line->setAttribute('minimum_selling_price', $unit->minimum_selling_price);
        $line->setAttribute('price_source', $source);
        $line->setAttribute('price_label', $label);
        $line->setAttribute('source_key', (string) $sourceModel->getKey());
        $line->setAttribute('source_updated_at', optional($sourceModel->updated_at)->toISOString());

        return $line;
    }
}
