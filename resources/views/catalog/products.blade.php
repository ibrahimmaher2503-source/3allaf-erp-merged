<?php

use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Product Masters')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $brandFilter = 'all';
    public string $statusFilter = 'all';
    public string $productTypeFilter = 'all';
    public string $colourFilter = '';
    public string $ageFilter = '';
    public string $genderFilter = 'all';
    public string $characterFilter = '';

    public bool $showProductModal = false;
    public ?int $editingProductId = null;
    public array $productForm = [
        'item_code' => '',
        'name_ar' => '',
        'name_en' => '',
        'product_type' => 'standard',
        'category_id' => '',
        'brand_id' => '',
        'status' => 'active',
    ];
    public ?int $productVersion = null;

    public bool $showBarcodeModal = false;
    public ?int $barcodeProductId = null;
    public string $barcodeProductLabel = '';
    public array $barcodeForm = [
        'source' => 'supplier',
        'barcode' => '',
        'supplier_code' => '',
    ];
    public string $allocationKey = '';
    public array $barcodeRecords = [];

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.view');
    }

    public function updatingSearch(string $value): void
    {
        $this->search = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBrandFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProductTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingColourFilter(string $value): void
    {
        $this->colourFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingAgeFilter(string $value): void
    {
        $this->ageFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function updatingGenderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCharacterFilter(string $value): void
    {
        $this->characterFilter = Str::limit($value, 100, '');
        $this->resetPage();
    }

    public function openCreateProductModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingProductId = null;
        $this->productVersion = null;
        $this->productForm = [
            'item_code' => '',
            'name_ar' => '',
            'name_en' => '',
            'product_type' => 'standard',
            'category_id' => '',
            'brand_id' => '',
            'status' => 'active',
        ];
        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function openEditProductModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $product = Product::query()->findOrFail($id);
        $this->editingProductId = $product->id;
        $this->productVersion = $product->lock_version;
        $this->productForm = [
            'item_code' => $product->item_code,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'product_type' => $product->product_type,
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) ($product->brand_id ?? ''),
            'status' => $product->status,
        ];
        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function saveProduct(SaveProductAction $action): void
    {
        Gate::authorize($this->editingProductId ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $validated = $this->validate([
            'productForm.item_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
                Rule::unique('products', 'item_code')->ignore($this->editingProductId),
            ],
            'productForm.name_ar' => ['required', 'string', 'max:255'],
            'productForm.name_en' => ['required', 'string', 'max:255'],
            'productForm.product_type' => ['required', 'in:standard,composite,service'],
            'productForm.category_id' => ['required', 'integer', 'exists:categories,id'],
            'productForm.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'productForm.status' => ['required', 'in:active,inactive'],
        ])['productForm'];

        try {
            $action->execute($validated, $this->editingProductId, $this->productVersion);
            Flux::toast(variant: 'success', text: $this->editingProductId ? __('Product identity updated successfully.') : __('Product created successfully.'));
            $this->showProductModal = false;
        } catch (\Throwable $exception) {
            $this->addError('productForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function toggleProductStatus(int $id, SaveProductAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Product status updated successfully.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function openBarcodeModal(int $productId): void
    {
        Gate::authorize('products_categories_brands.edit');
        $product = Product::query()->findOrFail($productId);
        $this->barcodeProductId = $product->id;
        $this->barcodeProductLabel = $product->item_code.' · '.(app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en);
        $this->refreshBarcodeRecords();
        $this->barcodeForm = ['source' => 'supplier', 'barcode' => '', 'supplier_code' => ''];
        $this->allocationKey = (string) Str::uuid();
        $this->resetValidation();
        $this->showBarcodeModal = true;
    }

    public function addBarcode(AddBarcodeAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        $rules = [
            'barcodeForm.source' => ['required', 'in:supplier,local'],
            'barcodeForm.barcode' => ['nullable', 'string', 'max:64'],
            'barcodeForm.supplier_code' => ['nullable', 'regex:/^\d{4}$/'],
        ];

        if ($this->barcodeForm['source'] === 'supplier') {
            $rules['barcodeForm.barcode'] = ['required', 'string', 'max:64', 'regex:/^\S+$/'];
        } else {
            $rules['barcodeForm.supplier_code'] = ['required', 'regex:/^\d{4}$/'];
        }

        $validated = $this->validate($rules)['barcodeForm'];

        try {
            if ($validated['source'] === 'supplier') {
                $barcode = $action->addSupplierBarcode($this->barcodeProductId, $validated['barcode']);
                $message = __('Supplier barcode :barcode added.', ['barcode' => $barcode->barcode]);
            } else {
                $barcode = $action->allocateLocalBarcode($this->barcodeProductId, $validated['supplier_code'], $this->allocationKey);
                $message = __('Local barcode :barcode allocated.', ['barcode' => $barcode->barcode]);
                $this->allocationKey = (string) Str::uuid();
            }

            Flux::toast(variant: 'success', text: $message);
            $this->barcodeForm['barcode'] = '';
            $this->refreshBarcodeRecords();
        } catch (\Throwable $exception) {
            $this->addError('barcodeForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function deactivateBarcode(int $id, AddBarcodeAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->deactivate($id);
            $this->refreshBarcodeRecords();
            Flux::toast(variant: 'success', text: __('Barcode deactivated without changing historical identity.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    private function refreshBarcodeRecords(): void
    {
        if (! $this->barcodeProductId) {
            $this->barcodeRecords = [];

            return;
        }

        $this->barcodeRecords = Product::query()->findOrFail($this->barcodeProductId)->barcodes()
            ->orderByDesc('is_primary')
            ->orderBy('barcode')
            ->get()
            ->map(fn (\App\Modules\Catalog\Models\Barcode $barcode): array => [
                'id' => $barcode->id,
                'barcode' => $barcode->barcode,
                'source' => $barcode->source,
                'status' => $barcode->status,
            ])->all();
    }

    public function render()
    {
        $term = mb_strtolower(trim(Str::limit($this->search, 100, '')));
        $query = Product::query()->with([
            'category:id,code,name_ar,name_en',
            'brand:id,code,name_ar,name_en',
            'barcodes' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('barcode'),
        ]);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($scope) use ($term, $like): void {
                $scope->whereRaw('LOWER(item_code) = ?', [$term])
                    ->orWhereHas('barcodes', fn ($barcode) => $barcode->whereRaw('LOWER(barcode) = ?', [$term]))
                    ->orWhereRaw('LOWER(model_number) = ?', [$term])
                    ->orWhereRaw('LOWER(name_ar) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(keywords_ar, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(keywords_en, \'\')) LIKE ?', [$like]);
            });
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category_id', (int) $this->categoryFilter);
        }

        if ($this->brandFilter !== 'all') {
            $query->where('brand_id', (int) $this->brandFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->productTypeFilter !== 'all') {
            $query->where('product_type', $this->productTypeFilter);
        }

        foreach ([['colour', $this->colourFilter], ['target_age', $this->ageFilter], ['character', $this->characterFilter]] as [$field, $value]) {
            if (trim((string) $value) !== '') {
                $query->whereRaw('LOWER('.$field.') LIKE ?', ['%'.mb_strtolower(trim((string) $value)).'%']);
            }
        }

        if ($this->genderFilter !== 'all') {
            $query->where('suitable_gender', $this->genderFilter);
        }

        if ($term !== '') {
            $query->orderByRaw(
                'CASE WHEN LOWER(item_code) = ? THEN 0 WHEN EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND LOWER(barcodes.barcode) = ?) THEN 1 ELSE 2 END',
                [$term, $term],
            );
        }

        $products = $query->orderBy('item_code')->paginate(12);

        return view('catalog.products', [
            'products' => $products,
            'categories' => Category::query()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en', 'status']),
            'brands' => Brand::query()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en', 'status']),
            'activeCategories' => Category::query()->active()->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'activeBrands' => Brand::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'productTypes' => ['standard', 'composite', 'service'],
            'genderOptions' => ['unisex', 'female', 'male'],
        ]);
    }
}; ?>

<section class="catalog-screen w-full">
    <x-page-header
        :title="__('Product Masters')"
        :description="__('Browse stable identity, full product-card types, reportable attributes, exact barcode search, and protected media.')"
    >
        <x-slot:actions>
            @can('products_categories_brands.create')
                <flux:button icon="plus" variant="primary" wire:click="openCreateProductModal">{{ __('Add Product') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <flux:callout class="catalog-scope-note" variant="info" icon="information-circle" title="{{ __('TSK-011 product-card extension') }}">
        {{ __('Item codes remain immutable and independent from barcodes. Product types and attributes are catalog metadata only; protected images use the shared Attachment Foundation. No stock, price, label, import, or supplier-history effect is created here.') }}
    </flux:callout>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Catalog action could not be completed') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <div class="catalog-filter-card rounded-xl p-4 sm:p-5">
        <div class="catalog-filter-heading mb-4">
            <div>
                <flux:heading size="sm">{{ __('Search') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-text-muted">{{ __('Exact code and barcode matches are prioritized before name matches.') }}</flux:text>
            </div>
            <span class="hidden rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary sm:inline-flex">{{ __('12 per page') }}</span>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Exact code/barcode or Arabic/English name...')" />
        <flux:select wire:model.live="categoryFilter" :label="__('Category')">
            <flux:select.option value="all">{{ __('All categories') }}</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option :value="$category->id">{{ $category->code }} &middot; {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="brandFilter" :label="__('Brand')">
            <flux:select.option value="all">{{ __('All brands') }}</flux:select.option>
            @foreach ($brands as $brand)
                <flux:select.option :value="$brand->id">{{ $brand->code }} &middot; {{ app()->getLocale() === 'ar' ? $brand->name_ar : $brand->name_en }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter" :label="__('Status')">
            <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="productTypeFilter" :label="__('Product type')">
            <flux:select.option value="all">{{ __('All product types') }}</flux:select.option>
            @foreach ($productTypes as $type)
                <flux:select.option :value="$type">{{ __(ucfirst($type)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="genderFilter" :label="__('Gender')">
            <flux:select.option value="all">{{ __('All genders') }}</flux:select.option>
            @foreach ($genderOptions as $gender)
                <flux:select.option :value="$gender">{{ __(ucfirst($gender)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="colourFilter" :label="__('Colour')" :placeholder="__('Filter colour')" />
        <flux:input wire:model.live.debounce.300ms="ageFilter" :label="__('Target age')" :placeholder="__('Filter age')" />
        <flux:input wire:model.live.debounce.300ms="characterFilter" :label="__('Character')" :placeholder="__('Filter character')" />
        </div>
    </div>

    <div wire:loading.flex role="status" aria-live="polite" class="catalog-loading">
        <flux:icon name="arrow-path" class="size-4 animate-spin" />
        {{ __('Loading catalog...') }}
    </div>

    @if ($products->isEmpty())
        <flux:card class="space-y-3 p-10 text-center">
            <flux:icon name="cube" class="mx-auto size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('No products found') }}</flux:heading>
            <flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('Create a local identity record or adjust the search and filters. No inventory or pricing data is created here.') }}</flux:text>
            @can('products_categories_brands.create')
                <flux:button class="mx-auto" icon="plus" variant="primary" wire:click="openCreateProductModal">{{ __('Create first product') }}</flux:button>
            @endcan
        </flux:card>
    @else
        <div class="catalog-table-frame">
            <flux:table aria-label="{{ __('Product masters') }}">
                <flux:table.columns>
                    <flux:table.column>{{ __('Item code') }}</flux:table.column>
                    <flux:table.column>{{ __('Product name') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Category / brand') }}</flux:table.column>
                    <flux:table.column>{{ __('Barcodes') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($products as $product)
                        <flux:table.row :key="$product->id">
                            <flux:table.cell class="whitespace-nowrap"><span class="catalog-code-chip">{{ $product->item_code }}</span></flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-text-primary">{{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</div>
                                <div class="catalog-secondary-line">{{ app()->getLocale() === 'ar' ? $product->name_en : $product->name_ar }}</div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="sky">{{ __(ucfirst($product->product_type)) }}</flux:badge><div class="mt-1 text-xs text-text-muted">{{ $product->colour ?: __('No colour') }}</div></flux:table.cell>
                            <flux:table.cell class="text-xs">
                                <div>{{ $product->category?->code }} · {{ app()->getLocale() === 'ar' ? $product->category?->name_ar : $product->category?->name_en }}</div>
                                @if ($product->brand)
                                    <div class="text-zinc-500">{{ $product->brand->code }} · {{ app()->getLocale() === 'ar' ? $product->brand->name_ar : $product->brand->name_en }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex max-w-72 flex-wrap gap-1">
                                    @forelse ($product->barcodes as $barcode)
                                        <span class="catalog-code-chip">{{ $barcode->barcode }}</span>
                                    @empty
                                        <span class="text-xs text-text-muted">{{ __('None yet') }}</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$product->status === 'active' ? 'emerald' : 'zinc'">{{ __($product->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @can('products_categories_brands.edit')
                                    <div class="catalog-actions">
                                        <flux:button size="xs" variant="subtle" icon="eye" href="{{ route('catalog.products.show', ['product' => $product]) }}" title="{{ __('View details') }}" aria-label="{{ __('View details') }}" />
                                        <flux:button size="xs" variant="subtle" icon="arrow-top-right-on-square" href="{{ route('catalog.products.edit', ['product' => $product]) }}" title="{{ __('Full product card') }}" aria-label="{{ __('Full product card') }}" />
                                        <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditProductModal({{ $product->id }})" title="{{ __('Edit identity') }}" aria-label="{{ __('Edit identity') }}" />
                                        <flux:button size="xs" variant="subtle" icon="tag" wire:click="openBarcodeModal({{ $product->id }})" title="{{ __('Manage barcodes') }}" aria-label="{{ __('Manage barcodes') }}" />
                                        <flux:button size="xs" variant="subtle" :icon="$product->status === 'active' ? 'pause' : 'play'" wire:click="toggleProductStatus({{ $product->id }})" title="{{ $product->status === 'active' ? __('Deactivate') : __('Activate') }}" aria-label="{{ $product->status === 'active' ? __('Deactivate') : __('Activate') }}" />
                                    </div>
                                @else
                                    <span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        {{ $products->links() }}
    @endif

    <flux:modal wire:model="showProductModal" class="max-w-2xl">
        <div class="catalog-modal-section space-y-1">
            <flux:heading size="lg">{{ $editingProductId ? __('Edit product identity') : __('Create product identity') }}</flux:heading>
            <flux:subheading>{{ __('This quick editor preserves the identity slice. Use the full product-card editor for descriptions, types, attributes, and protected media.') }}</flux:subheading>
        </div>
        <form wire:submit="saveProduct" novalidate class="space-y-4">
            <div>
                <flux:input wire:model="productForm.item_code" :label="__('Immutable item code')" :disabled="$editingProductId !== null" required />
                <flux:text class="mt-1 text-xs text-text-muted">{{ __('This identity value cannot be changed after the product is created.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="productForm.name_ar" :label="__('Arabic product name')" required />
                <flux:input wire:model="productForm.name_en" :label="__('English product name')" required />
            </div>
            <flux:select wire:model="productForm.product_type" :label="__('Product type')" required>
                <flux:select.option value="standard">{{ __('Standard') }}</flux:select.option>
                <flux:select.option value="composite">{{ __('Composite') }}</flux:select.option>
                <flux:select.option value="service">{{ __('Service') }}</flux:select.option>
            </flux:select>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="productForm.category_id" :label="__('Category')" required>
                    <flux:select.option value="">{{ __('Select active category...') }}</flux:select.option>
                    @foreach ($activeCategories as $category)
                        <flux:select.option :value="$category->id">{{ $category->code }} · {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="productForm.brand_id" :label="__('Brand')">
                    <flux:select.option value="">{{ __('No brand') }}</flux:select.option>
                    @foreach ($activeBrands as $brand)
                        <flux:select.option :value="$brand->id">{{ $brand->code }} · {{ app()->getLocale() === 'ar' ? $brand->name_ar : $brand->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:select wire:model="productForm.status" :label="__('Status')" required>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="subtle" wire:click="$set('showProductModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveProduct">{{ __('Save product') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showBarcodeModal" class="max-w-2xl">
        <div class="catalog-modal-section space-y-1">
            <flux:heading size="lg">{{ __('Barcode identity') }}</flux:heading>
            <flux:subheading>{{ $barcodeProductLabel }}</flux:subheading>
        </div>
        <div class="rounded-lg border border-info/15 bg-info/5 p-3 text-sm text-text-primary">
            {{ __('Supplier barcodes are preserved as supplied. Local barcodes concatenate a four-digit supplier code and a six-digit sequential serial, with no invented check digit.') }}
        </div>
        @if ($barcodeProductId)
            <div class="catalog-modal-section space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:text class="font-medium">{{ __('Current barcodes') }}</flux:text>
                    <span class="text-xs text-text-muted">{{ count($barcodeRecords) }} {{ __('linked') }}</span>
                </div>
                @forelse ($barcodeRecords as $barcode)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-3 py-2.5 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="catalog-code-chip">{{ $barcode['barcode'] }}</span>
                            <flux:badge size="sm" color="{{ $barcode['source'] === 'local' ? 'sky' : 'zinc' }}">{{ __($barcode['source'] === 'local' ? 'Local' : 'Supplier') }}</flux:badge>
                            <flux:badge size="sm" color="{{ $barcode['status'] === 'active' ? 'emerald' : 'zinc' }}">{{ __($barcode['status'] === 'active' ? 'Active' : 'Inactive') }}</flux:badge>
                        </div>
                        @if ($barcode['status'] === 'active')
                            <flux:button size="xs" variant="subtle" color="red" wire:click="deactivateBarcode({{ $barcode['id'] }})" wire:confirm="{{ __('Deactivate this barcode while preserving its historical identity?') }}">{{ __('Deactivate') }}</flux:button>
                        @endif
                    </div>
                @empty
                    <flux:text class="text-zinc-500">{{ __('No barcode is linked yet.') }}</flux:text>
                @endforelse
            </div>
        @endif
        <form wire:submit="addBarcode" novalidate class="space-y-4">
            <div>
                <flux:heading size="sm">{{ __('Add barcode') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-text-muted">{{ __('Choose the source before entering or allocating the identity.') }}</flux:text>
            </div>
            <flux:select wire:model.live="barcodeForm.source" :label="__('Barcode source')" required>
                <flux:select.option value="supplier">{{ __('Supplier / international') }}</flux:select.option>
                <flux:select.option value="local">{{ __('Locally generated') }}</flux:select.option>
            </flux:select>
            @if ($barcodeForm['source'] === 'supplier')
                <flux:input wire:model="barcodeForm.barcode" :label="__('Supplier barcode')" placeholder="{{ __('Enter the supplied value') }}" required />
            @else
                <flux:input wire:model="barcodeForm.supplier_code" :label="__('Four-digit supplier code')" inputmode="numeric" maxlength="4" placeholder="1001" required />
                <flux:text class="text-xs text-zinc-500">{{ __('The next six-digit serial is allocated inside the transaction. Replayed requests reuse the same allocation key.') }}</flux:text>
            @endif
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="subtle" wire:click="$set('showBarcodeModal', false)">{{ __('Close') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addBarcode">{{ $barcodeForm['source'] === 'local' ? __('Allocate barcode') : __('Add barcode') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
