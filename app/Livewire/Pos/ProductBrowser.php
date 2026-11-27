<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\ScaleLabelBarcode;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class ProductBrowser extends Component
{
    public string $search = '';

    public ?int $categoryId = null;

    public ?int $openFamilyId = null;

    /** @var array<int, int> */
    public array $selectedValues = [];

    public ?int $selectedVariantId = null;

    public string $quantity = '1';

    /** Last add-to-cart refusal; kept as state because Livewire drops the error bag on the next request. */
    public string $selectionError = '';

    public int $cartRevision = 0;

    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    #[On('pos-cart-updated')]
    public function refreshProducts(): void
    {
        $this->cartRevision++;
    }

    /** Code of the last scan that reached the cart, to ignore the same scan arriving twice. */
    public string $lastScannedCode = '';

    public float $lastScannedAt = 0;

    /** 'enter' (scan handler / form submit) or 'live' (search box sync without Enter). */
    public string $lastScannedVia = '';

    public function submitSearch(PosCartAction $cart): void
    {
        $term = trim($this->search);
        if ($term === '') {
            // A scanner fires both the scan handler and the form's Enter. The second, empty
            // submit must not re-render, or it wipes the message from the first (e.g. out of stock).
            $this->skipRender();

            return;
        }
        if ($this->scanToCart($term, $cart, allowPrefix: true, via: 'enter')) {
            $this->search = '';
        }
    }

    /**
     * Scanners that do not send Enter only fill the search box. A complete numeric barcode
     * (8+ digits) that identifies one product, or a scale label, goes straight to the cart.
     */
    public function updatedSearch(string $value, PosCartAction $cart): void
    {
        $term = trim($value);
        if (preg_match('/\A\d{8,14}\z/', $term) !== 1) {
            return;
        }
        if ($this->scanToCart($term, $cart, allowPrefix: false, via: 'live')) {
            $this->search = '';
        }
    }

    /**
     * Adds the scanned product without making the cashier pick it from the results: an exact
     * item code, model number or barcode, else (with Enter) the single product whose barcode or
     * item code starts with the scanned text, else a scale label. A product with options opens
     * its option picker. Returns true when the scan was handled (added, opened or refused).
     */
    private function scanToCart(string $term, PosCartAction $cart, bool $allowPrefix, string $via): bool
    {
        // One physical scan can arrive twice: through Enter and through the search-box sync.
        // Only that pair is ignored; scanning the same item again (two bags) adds it again.
        if ($term === $this->lastScannedCode && $via !== $this->lastScannedVia && microtime(true) - $this->lastScannedAt < 1.2) {
            return true;
        }
        $this->scanVia = $via;
        $match = $this->uniqueProduct(fn ($query) => $query->where('item_code', $term)->orWhere('model_number', $term)
            ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->active()->where('barcode', $term)));
        if ($match === false && $allowPrefix) {
            $prefix = addcslashes($term, '%_\\').'%';
            $match = $this->uniqueProduct(fn ($query) => $query->where('item_code', 'like', $prefix)
                ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->active()->where('barcode', 'like', $prefix)));
        }
        if ($match instanceof Product) {
            $this->rememberScan($term);
            if ($match->has_variations) {
                $this->openFamily((int) $match->id);

                return true;
            }
            $this->addProduct((int) $match->id, $cart);

            return true;
        }
        if ($match === null) {
            return false; // several products match: leave the results for the cashier
        }
        if ($this->addScaleLabel($term, $cart)) {
            $this->rememberScan($term);

            return true;
        }

        return false;
    }

    /**
     * @param  \Closure(Builder<Product>): mixed  $criteria
     * @return Product|false|null the product, false when none matches, null when several match
     */
    private function uniqueProduct(\Closure $criteria): Product|false|null
    {
        // Active simple products, variation SKUs and families (a family opens its option picker).
        $matches = Product::query()->where('status', 'active')
            ->where($criteria)->limit(2)->get(['id', 'has_variations', 'parent_product_id']);

        return match ($matches->count()) {
            0 => false,
            1 => $matches->first(),
            default => null,
        };
    }

    private string $scanVia = '';

    private function rememberScan(string $term): void
    {
        $this->lastScannedCode = $term;
        $this->lastScannedAt = microtime(true);
        $this->lastScannedVia = $this->scanVia;
    }

    /**
     * A weighed item's label (e.g. 2000001016473 = PLU 1, 1.647 kg) is not a product barcode;
     * it is read with the scale's barcode settings and added with its weight.
     * Returns true when the code was a scale label (added, or refused with a message).
     */
    private function addScaleLabel(string $code, PosCartAction $cart): bool
    {
        /** @var User $user */
        $user = auth()->user();
        $context = app(PosContextResolver::class)->resolve($user);
        if (! $context->isReady() || $context->store === null) {
            return false;
        }
        $this->selectionError = '';
        try {
            $line = ScaleLabelBarcode::resolve($code, (int) $context->store->id, $context->store->company_id === null ? null : (int) $context->store->company_id);
            if ($line === null) {
                return false;
            }
            Gate::authorize('pos_sales.create');
            $cart->add(request(), $user, $line['product_id'], $line['quantity'], $line['product_unit_id']);
        } catch (\Throwable $exception) {
            $this->selectionError = (string) UserSafeError::message($exception);

            return true;
        }
        $this->dispatch('pos-product-added');
        $this->dispatch('notify', message: __('Weighed item added: :quantity', ['quantity' => $line['quantity']]));
        $this->dispatch('pos-product-search-focus');

        return true;
    }

    public function chooseCategory(?int $id): void
    {
        $this->categoryId = $id;
    }

    public function openFamily(int $familyId, ?int $variantId = null): void
    {
        $family = Product::query()->whereNull('parent_product_id')->where('has_variations', true)->where('status', 'active')->findOrFail($familyId);
        $this->openFamilyId = $family->id;
        $this->selectedValues = [];
        $this->selectedVariantId = null;
        $this->quantity = '1';
        if ($variantId) {
            $variant = $family->variants()->with('variantValues')->findOrFail($variantId);
            foreach ($variant->variantValues as $selection) {
                $this->selectedValues[$selection->product_option_group_id] = $selection->product_option_value_id;
            }
            $this->selectedVariantId = $variant->id;
        }
    }

    public function selectValue(int $groupId, int $valueId): void
    {
        $this->selectedValues[$groupId] = $valueId;
        $family = Product::query()->with(['familyOptionGroups', 'variants.variantValues'])->findOrFail($this->openFamilyId);
        $this->selectedVariantId = null;
        if (count($this->selectedValues) !== $family->familyOptionGroups->count()) {
            return;
        }
        $selected = collect($this->selectedValues)->sortKeys();
        $match = $family->variants->first(function (Product $variant) use ($selected): bool {
            $values = $variant->variantValues->pluck('product_option_value_id', 'product_option_group_id')->sortKeys();

            return $values->all() === $selected->all();
        });
        $this->selectedVariantId = $match?->id;
    }

    public function addProduct(int $productId, PosCartAction $cart): bool
    {
        Gate::authorize('pos_sales.create');
        $this->selectionError = '';
        /** @var User $user */ $user = auth()->user();
        $context = app(PosContextResolver::class)->resolve($user);
        if (! $context->isReady()) {
            $this->selectionError = (string) ($context->disabledReason ?? __('POS is not ready for selling.'));

            return false;
        }
        try {
            $cart->add(request(), $user, $productId, $this->quantity);
            $this->dispatch('pos-product-added');
            $this->dispatch('notify', message: __('Product added to cart.'));
            $this->dispatch('pos-product-search-focus');
            $this->openFamilyId = null;
            $this->selectedVariantId = null;
            $this->quantity = '1';

            return true;
        } catch (\Throwable $exception) {
            $this->selectionError = (string) (UserSafeError::message($exception));

            return false;
        }
    }

    public function addSelected(PosCartAction $cart): void
    {
        if (! $this->selectedVariantId) {
            $this->selectionError = (string) (__('Choose one value from every option group.'));

            return;
        }
        $this->addProduct($this->selectedVariantId, $cart);
    }

    public function render(EffectivePriceResolver $prices, PosContextResolver $contextResolver): View
    {
        /** @var User $user */ $user = auth()->user();
        $context = $contextResolver->resolve($user);
        $store = $context->store;
        $query = Product::query()->active()->familiesAndSimple()->with(['category:id,name_ar,name_en', 'images.attachment'])->withCount(['variants as active_variants_count' => fn ($q) => $q->where('status', 'active')])->orderBy('item_code')->limit(24);
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if (trim($this->search) !== '') {
            $raw = trim($this->search);
            $prefix = addcslashes($raw, '%_\\').'%';
            $contains = '%'.addcslashes($raw, '%_\\').'%';
            $query->where(function ($q) use ($prefix, $contains): void {
                $q->where('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)
                    ->orWhere('name_ar', 'like', $contains)->orWhere('name_en', 'like', $contains)
                    ->orWhereHas('barcodes', fn ($b) => $b->active()->where('barcode', 'like', $prefix))
                    ->orWhereHas('variants', fn ($v) => $v->where('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)->orWhereHas('barcodes', fn ($b) => $b->active()->where('barcode', 'like', $prefix))->orWhereHas('variantValues.value', fn ($value) => $value->where('name_ar', 'like', $contains)->orWhere('name_en', 'like', $contains)));
            });
        }
        $showCategories = $this->categoryId === null && trim($this->search) === '';
        $products = $showCategories ? collect() : $query->get();
        $simpleIds = $products->where('has_variations', false)->pluck('id');
        $simpleStock = $store ? StockBalance::query()->where('store_id', $store->id)->whereIn('product_id', $simpleIds)->get()->keyBy('product_id') : collect();
        $cartQuantities = collect(request()->session()->get('pos.cart', []))->mapWithKeys(fn (array $line): array => [(int) ($line['product_id'] ?? 0) => (string) ($line['quantity'] ?? '0')]);
        $familyIds = $products->where('has_variations', true)->pluck('id');
        $familyVariants = Product::query()->whereIn('parent_product_id', $familyIds)->where('status', 'active')->get(['id', 'parent_product_id']);
        $drawer = $this->openFamilyId && $store
            ? Product::query()->with(['images.attachment', 'familyOptionGroups.values', 'variants' => fn ($q) => $q->with(['images.attachment', 'barcodes', 'variantValues.group', 'variantValues.value'])->orderBy('variant_sort_order')])->find($this->openFamilyId)
            : null;
        $displayPrices = collect();
        if ($store !== null) {
            $productIds = $simpleIds->concat($familyVariants->pluck('id'))->concat($drawer?->variants->pluck('id') ?? [])->all();
            $at = Carbon::instance(now());
            $customerId = request()->session()->get('pos.customer_id');
            $prices->warm($productIds, $store->id, $at);
            $displayPrices = $prices->resolveForStore($productIds, $store->id, $at, $customerId ? (int) $customerId : null);
        }
        $familyRanges = $familyVariants->groupBy('parent_product_id')->map(function ($variants) use ($displayPrices): ?object {
            $amounts = $variants->map(fn (Product $variant): mixed => $displayPrices->get($variant->id)?->amount)->filter()->values();

            return $amounts->isEmpty() ? null : (object) ['min_price' => $amounts->sort(fn ($a, $b): int => bccomp((string) $a, (string) $b, 4))->first(), 'max_price' => $amounts->sort(fn ($a, $b): int => bccomp((string) $b, (string) $a, 4))->first()];
        })->filter();
        $familyStock = $store ? DB::table('stock_balances')->join('products', 'products.id', '=', 'stock_balances.product_id')->whereIn('products.parent_product_id', $familyIds)->where('stock_balances.store_id', $store->id)->whereRaw('(stock_balances.on_hand - stock_balances.reserved) > 0')->pluck('products.parent_product_id')->flip() : collect();
        $familyChildImages = ProductImage::query()->with(['attachment', 'product:id,parent_product_id'])->where('status', 'active')->whereHas('product', fn ($q) => $q->whereIn('parent_product_id', $familyIds)->where('status', 'active'))->orderByRaw("CASE WHEN role = 'main' THEN 0 ELSE 1 END")->orderBy('sort_order')->get()->groupBy(fn (ProductImage $image) => $image->product?->parent_product_id)->map->first();
        $drawerStock = collect();
        $optionAvailability = [];
        if ($drawer !== null && $store !== null) {
            $drawerStock = StockBalance::query()->where('store_id', $store->id)->whereIn('product_id', $drawer->variants->pluck('id'))->get()->keyBy('product_id');
            foreach ($drawer->familyOptionGroups as $group) {
                foreach ($group->values as $value) {
                    $optionAvailability[$group->id][$value->id] = $drawer->variants->contains(function (Product $variant) use ($group, $value, $displayPrices, $drawerStock): bool {
                        if ($variant->status !== 'active' || ! $displayPrices->has($variant->id)) {
                            return false;
                        }
                        $stock = $drawerStock->get($variant->id);
                        if (! $stock || bccomp(bcsub((string) $stock->on_hand, (string) $stock->reserved, 6), '0', 6) <= 0) {
                            return false;
                        }
                        $values = $variant->variantValues->pluck('product_option_value_id', 'product_option_group_id');
                        if ((int) $values->get($group->id) !== $value->id) {
                            return false;
                        }
                        foreach ($this->selectedValues as $selectedGroup => $selectedValue) {
                            if ((int) $selectedGroup !== $group->id && (int) $values->get((int) $selectedGroup) !== (int) $selectedValue) {
                                return false;
                            }
                        }

                        return true;
                    });
                }
            }
        }
        $categories = Category::query()->active()->whereHas('products', fn ($q) => $q->active()->familiesAndSimple())->orderBy('sort_order')->orderBy('id')->get();

        return view('livewire.pos.product-browser', compact('context', 'store', 'products', 'categories', 'showCategories', 'displayPrices', 'simpleStock', 'cartQuantities', 'familyRanges', 'familyStock', 'familyChildImages', 'drawer', 'drawerStock', 'optionAvailability'));
    }
}
