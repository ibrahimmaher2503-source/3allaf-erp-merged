<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosContextResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    public function submitSearch(PosCartAction $cart): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }
        $barcode = Barcode::query()->active()->where('barcode', $term)->with('product.parent')->first();
        if ($barcode?->product?->isSellable()) {
            $this->addProduct($barcode->product->id, $cart);
            $this->search = '';

            return;
        }
        $variant = Product::query()->sellable()->whereNotNull('parent_product_id')->where('item_code', $term)->with(['variantValues', 'parent'])->first();
        if ($variant) {
            $this->openFamily($variant->parent_product_id, $variant->id);
        }
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

    public function addProduct(int $productId, PosCartAction $cart): void
    {
        Gate::authorize('pos_sales.create');
        /** @var User $user */ $user = auth()->user();
        $context = app(PosContextResolver::class)->resolve($user);
        if (! $context->isReady()) {
            $this->addError('selection', $context->disabledReason ?? __('POS is not ready for selling.'));

            return;
        }
        try {
            $cart->add(request(), $user, $productId, $this->quantity);
            $this->dispatch('pos-cart-updated');
            $this->dispatch('notify', message: __('Product added to cart.'));
            $this->openFamilyId = null;
            $this->selectedVariantId = null;
            $this->quantity = '1';
        } catch (\Throwable $exception) {
            $this->addError('selection', $exception->getMessage());
        }
    }

    public function addSelected(PosCartAction $cart): void
    {
        if (! $this->selectedVariantId) {
            $this->addError('selection', __('Choose one value from every option group.'));

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
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('item_code', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)
                    ->orWhereHas('barcodes', fn ($b) => $b->active()->where('barcode', 'like', $term))
                    ->orWhereHas('variants', fn ($v) => $v->where('item_code', 'like', $term)->orWhereHas('barcodes', fn ($b) => $b->active()->where('barcode', 'like', $term))->orWhereHas('variantValues.value', fn ($value) => $value->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)));
            });
        }
        $products = $query->get();
        $simpleIds = $products->where('has_variations', false)->pluck('id');
        $simplePrices = $store ? $prices->resolveForStore($simpleIds->all(), $store->id) : collect();
        $simpleStock = $store ? StockBalance::query()->where('store_id', $store->id)->whereIn('product_id', $simpleIds)->get()->keyBy('product_id') : collect();
        $familyIds = $products->where('has_variations', true)->pluck('id');
        $familyRanges = $store ? DB::table('price_lines')->join('products', 'products.id', '=', 'price_lines.product_id')->whereIn('products.parent_product_id', $familyIds)->where('products.status', 'active')->where('price_lines.store_id', $store->id)->whereNotNull('price_lines.active_key')->groupBy('products.parent_product_id')->selectRaw('products.parent_product_id, MIN(price_lines.amount) min_price, MAX(price_lines.amount) max_price')->get()->keyBy('parent_product_id') : collect();
        $familyStock = $store ? DB::table('stock_balances')->join('products', 'products.id', '=', 'stock_balances.product_id')->whereIn('products.parent_product_id', $familyIds)->where('stock_balances.store_id', $store->id)->whereRaw('(stock_balances.on_hand - stock_balances.reserved) > 0')->pluck('products.parent_product_id')->flip() : collect();
        $familyChildImages = ProductImage::query()->with(['attachment', 'product:id,parent_product_id'])->where('status', 'active')->whereHas('product', fn ($q) => $q->whereIn('parent_product_id', $familyIds)->where('status', 'active'))->orderByRaw("CASE WHEN role = 'main' THEN 0 ELSE 1 END")->orderBy('sort_order')->get()->groupBy(fn (ProductImage $image) => $image->product?->parent_product_id)->map->first();
        $drawer = null;
        $drawerPrices = collect();
        $drawerStock = collect();
        $optionAvailability = [];
        if ($this->openFamilyId && $store) {
            $drawer = Product::query()->with(['images.attachment', 'familyOptionGroups.values', 'variants' => fn ($q) => $q->with(['images.attachment', 'barcodes', 'variantValues.group', 'variantValues.value'])->orderBy('variant_sort_order')])->find($this->openFamilyId);
            if ($drawer) {
                $drawerPrices = $prices->resolveForStore($drawer->variants->pluck('id')->all(), $store->id);
                $drawerStock = StockBalance::query()->where('store_id', $store->id)->whereIn('product_id', $drawer->variants->pluck('id'))->get()->keyBy('product_id');
                foreach ($drawer->familyOptionGroups as $group) {
                    foreach ($group->values as $value) {
                        $optionAvailability[$group->id][$value->id] = $drawer->variants->contains(function (Product $variant) use ($group, $value, $drawerPrices, $drawerStock): bool {
                            if ($variant->status !== 'active' || ! $drawerPrices->has($variant->id)) {
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
        }
        $categories = Category::query()->active()->whereHas('products', fn ($q) => $q->active()->familiesAndSimple())->orderBy('sort_order')->limit(12)->get();

        return view('livewire.pos.product-browser', compact('context', 'store', 'products', 'categories', 'simplePrices', 'simpleStock', 'familyRanges', 'familyStock', 'familyChildImages', 'drawer', 'drawerPrices', 'drawerStock', 'optionAvailability'));
    }
}
