<?php

use App\Modules\Catalog\Actions\ManageProductMediaAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Product Card')] class extends Component {
    use WithFileUploads;

    public ?int $productId = null;
    public ?int $productVersion = null;
    public bool $isEditing = false;
    public mixed $mediaUpload = null;
    public string $mediaRole = 'additional';

    public array $productForm = [
        'item_code' => '',
        'name_ar' => '',
        'name_en' => '',
        'description_ar' => '',
        'description_en' => '',
        'short_description_ar' => '', 'short_description_en' => '', 'full_description_ar' => '', 'full_description_en' => '',
        'meta_title_ar' => '', 'meta_title_en' => '', 'meta_description_ar' => '', 'meta_description_en' => '', 'seo_slug' => '', 'publish_visibility' => '', 'sort_order' => '',
        'model_number' => '',
        'product_type' => 'standard',
        'status' => 'active',
        'unit_of_measure' => '',
        'category_id' => '',
        'brand_id' => '',
        'reorder_threshold' => '',
        'dimension_length' => '',
        'dimension_width' => '',
        'dimension_height' => '',
        'dimension_unit' => '',
        'weight' => '',
        'target_age' => '',
        'age_label_id' => '',
        'suitable_gender' => '',
        'gender_id' => '',
        'colour' => '',
        'colour_id' => '',
        'size' => '',
        'character' => '',
        'character_id' => '',
        'key_points_ar' => '',
        'key_points_en' => '',
        'keywords_ar' => '',
        'keywords_en' => '',
        'fractional_quantity' => false,
        'average_cost' => '', 'sale_price' => '', 'battery_required' => false, 'battery_details' => '',
        'preferred_supplier_id' => '', 'age_label_ids' => [], 'character_ids' => [], 'colour_ids' => [], 'gender_ids' => [],
    ];

    public function mount(?Product $product = null): void
    {
        Gate::authorize($product?->exists ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        if ($product?->exists) {
            $this->productId = $product->id;
            $this->isEditing = true;
            $this->productVersion = $product->lock_version;
            $this->loadProduct($product);
        }
    }

    public function lookupOptions(): array
    {
        return ['ages' => AgeLabel::query()->where('status', 'active')->orderBy('sort_order')->get(), 'characters' => Character::query()->where('status', 'active')->orderBy('sort_order')->get(), 'colours' => Colour::query()->where('status', 'active')->orderBy('sort_order')->get(), 'genders' => Gender::query()->where('status', 'active')->orderBy('sort_order')->get()];
    }

    public function save(SaveProductAction $action): void
    {
        Gate::authorize($this->isEditing ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $validated = $this->validate($this->rules())['productForm'];

        try {
            $product = $action->execute($validated, $this->productId, $this->productVersion);

            if (! $this->isEditing) {
                session()->flash('status', __('Product card created successfully. Add protected images from this page.'));
                $this->redirectRoute('catalog.products.edit', ['product' => $product], navigate: true);

                return;
            }

            $this->productVersion = $product->lock_version;
            $this->loadProduct($product);
            Flux::toast(variant: 'success', text: __('Product card saved successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('productForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function uploadImage(ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        if ($this->productId === null) {
            $this->addError('mediaUpload', __('Save the product card before adding protected images.'));

            return;
        }

        if ($this->mediaUpload === null) {
            $this->addError('mediaUpload', __('The image upload was rejected before Laravel could receive it. Check the configured local upload limit.'));

            return;
        }

        $this->validate([
            'mediaUpload' => ['required', 'file'],
            'mediaRole' => ['required', Rule::in(['main', 'additional'])],
        ]);

        try {
            $product = Product::query()->findOrFail($this->productId);
            $action->upload($product, $this->mediaUpload, $this->mediaRole);
            $this->mediaUpload = null;
            Flux::toast(variant: 'success', text: __('Protected product image added successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('mediaUpload', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function setMainImage(int $imageId, ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->setMain(Product::query()->findOrFail($this->productId), $imageId);
            Flux::toast(variant: 'success', text: __('Main image updated successfully.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function removeImage(int $imageId, ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->revoke(Product::query()->findOrFail($this->productId), $imageId);
            Flux::toast(variant: 'success', text: __('Product image removed while preserving attachment history.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function moveAdditionalImage(int $imageId, string $direction, ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        $product = Product::query()->findOrFail($this->productId);
        $ids = $product->images()->where('role', 'additional')->orderBy('sort_order')->pluck('id')->all();
        $position = array_search($imageId, $ids, true);

        if ($position === false) {
            return;
        }

        $target = $direction === 'up' ? $position - 1 : $position + 1;
        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];
        $action->reorder($product, $ids);
    }

    private function loadProduct(Product $product): void
    {
        $product = $product->load(['category', 'brand', 'images.attachment', 'preferredProductSupplier', 'ages', 'characters', 'colours', 'genders']);
        $this->productForm = [
            'item_code' => $product->item_code,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'description_ar' => $product->description_ar ?? '',
            'description_en' => $product->description_en ?? '',
            'short_description_ar' => $product->short_description_ar ?? '', 'short_description_en' => $product->short_description_en ?? '', 'full_description_ar' => $product->full_description_ar ?? '', 'full_description_en' => $product->full_description_en ?? '',
            'meta_title_ar' => $product->meta_title_ar ?? '', 'meta_title_en' => $product->meta_title_en ?? '', 'meta_description_ar' => $product->meta_description_ar ?? '', 'meta_description_en' => $product->meta_description_en ?? '', 'seo_slug' => $product->seo_slug ?? '', 'publish_visibility' => $product->publish_visibility ?? '', 'sort_order' => $product->sort_order ?? '',
            'model_number' => $product->model_number ?? '',
            'product_type' => $product->product_type,
            'status' => $product->status,
            'unit_of_measure' => $product->unit_of_measure ?? '',
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) ($product->brand_id ?? ''),
            'reorder_threshold' => $product->reorder_threshold ?? '',
            'dimension_length' => $product->dimension_length ?? '',
            'dimension_width' => $product->dimension_width ?? '',
            'dimension_height' => $product->dimension_height ?? '',
            'dimension_unit' => $product->dimension_unit ?? '',
            'weight' => $product->weight ?? '',
            'target_age' => $product->target_age ?? '',
            'age_label_id' => (string) ($product->age_label_id ?? ''),
            'suitable_gender' => $product->suitable_gender ?? '',
            'gender_id' => (string) ($product->gender_id ?? ''),
            'colour' => $product->colour ?? '',
            'colour_id' => (string) ($product->colour_id ?? ''),
            'size' => $product->size ?? '',
            'character' => $product->character ?? '',
            'character_id' => (string) ($product->character_id ?? ''),
            'key_points_ar' => $product->key_points_ar ?? '',
            'key_points_en' => $product->key_points_en ?? '',
            'keywords_ar' => $product->keywords_ar ?? '',
            'keywords_en' => $product->keywords_en ?? '',
            'fractional_quantity' => (bool) $product->fractional_quantity,
            'average_cost' => $product->average_cost ?? '', 'sale_price' => $product->sale_price ?? '',
            'battery_required' => (bool) $product->battery_required, 'battery_details' => $product->battery_details ?? '',
            'preferred_supplier_id' => (string) ($product->preferredProductSupplier?->supplier_id ?? ''),
            'age_label_ids' => $product->ages->pluck('id')->all(), 'character_ids' => $product->characters->pluck('id')->all(), 'colour_ids' => $product->colours->pluck('id')->all(), 'gender_ids' => $product->genders->pluck('id')->all(),
        ];
        $this->productVersion = $product->lock_version;
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'productForm.item_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('products', 'item_code')->ignore($this->productId)],
            'productForm.name_ar' => ['required', 'string', 'max:255'],
            'productForm.name_en' => ['required', 'string', 'max:255'],
            'productForm.description_ar' => ['nullable', 'string', 'max:5000'],
            'productForm.description_en' => ['nullable', 'string', 'max:5000'],
            'productForm.short_description_ar' => ['nullable', 'string', 'max:1000'], 'productForm.short_description_en' => ['nullable', 'string', 'max:1000'], 'productForm.full_description_ar' => ['nullable', 'string', 'max:10000'], 'productForm.full_description_en' => ['nullable', 'string', 'max:10000'],
            'productForm.meta_title_ar' => ['nullable', 'string', 'max:255'], 'productForm.meta_title_en' => ['nullable', 'string', 'max:255'], 'productForm.meta_description_ar' => ['nullable', 'string', 'max:1000'], 'productForm.meta_description_en' => ['nullable', 'string', 'max:1000'], 'productForm.seo_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'], 'productForm.publish_visibility' => ['nullable', Rule::in(['catalog', 'hidden'])], 'productForm.sort_order' => ['nullable', 'integer', 'min:0'],
            'productForm.model_number' => ['nullable', 'string', 'max:100'],
            'productForm.product_type' => ['required', Rule::in(['standard', 'composite', 'service'])],
            'productForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'productForm.unit_of_measure' => ['nullable', 'string', 'max:50'],
            'productForm.category_id' => ['required', 'integer', 'exists:categories,id'],
            'productForm.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'productForm.reorder_threshold' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_length' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_width' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_height' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_unit' => ['nullable', 'string', 'max:20'],
            'productForm.weight' => ['nullable', 'numeric', 'min:0'],
            'productForm.target_age' => ['nullable', 'string', 'max:100'],
            'productForm.age_label_id' => ['nullable', 'integer', 'exists:age_labels,id'],
            'productForm.suitable_gender' => ['nullable', 'string', 'max:30'],
            'productForm.gender_id' => ['nullable', 'integer', 'exists:genders,id'],
            'productForm.colour' => ['nullable', 'string', 'max:100'],
            'productForm.colour_id' => ['nullable', 'integer', 'exists:colours,id'],
            'productForm.size' => ['nullable', 'string', 'max:100'],
            'productForm.character' => ['nullable', 'string', 'max:100'],
            'productForm.character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'productForm.key_points_ar' => ['nullable', 'string', 'max:4000'],
            'productForm.key_points_en' => ['nullable', 'string', 'max:4000'],
            'productForm.keywords_ar' => ['nullable', 'string', 'max:2000'],
            'productForm.keywords_en' => ['nullable', 'string', 'max:2000'],
            'productForm.fractional_quantity' => ['boolean'],
            'productForm.average_cost' => ['nullable', 'numeric', 'min:0'], 'productForm.sale_price' => ['nullable', 'numeric', 'min:0'],
            'productForm.battery_required' => ['boolean'], 'productForm.battery_details' => ['nullable', 'string', 'max:255'],
            'productForm.preferred_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'productForm.age_label_ids' => ['array'], 'productForm.age_label_ids.*' => ['integer', 'exists:age_labels,id'],
            'productForm.character_ids' => ['array'], 'productForm.character_ids.*' => ['integer', 'exists:characters,id'],
            'productForm.colour_ids' => ['array'], 'productForm.colour_ids.*' => ['integer', 'exists:colours,id'],
            'productForm.gender_ids' => ['array'], 'productForm.gender_ids.*' => ['integer', 'exists:genders,id'],
        ];
    }

    public function render()
    {
        $product = $this->productId === null ? null : Product::query()
            ->with(['category', 'brand', 'barcodes', 'images.attachment'])
            ->findOrFail($this->productId);

        return view('catalog.product-form', [
            'product' => $product,
            'categories' => Category::query()->active()->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'brands' => Brand::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'canViewCost' => auth()->user()?->hasPermission('products_categories_brands.cost_view') ?? false,
        ]);
    }
}; ?>

<x-app.page
    :title="$isEditing ? __('Edit product card') : __('Create product card')"
    :description="__('Maintain bilingual product identity, approved type, reportable attributes, and protected media in one focused card.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="product-form-header"
>
    <x-slot:actions>
        <flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
    </x-slot:actions>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" title="{{ __('Saved') }}">{{ session('status') }}</flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Product card could not be saved') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    @if ($categories->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Product entry needs an active category') }}">
            <div class="space-y-2">
                <p>{{ __('A product must belong to an active category. Create the category hierarchy first; authorized catalog users can configure it.') }}</p>
                <flux:button href="{{ route('catalog.categories') }}" variant="subtle" icon="arrow-top-right-on-square" wire:navigate>{{ __('Configure categories') }}</flux:button>
            </div>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-5" novalidate>
        <flux:card class="space-y-4 p-5"><flux:heading size="lg">{{ __('Commercial and descriptive master fields') }}</flux:heading><div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="productForm.average_cost" type="number" min="0" step="0.01" :label="__('Cost price')" /><flux:input wire:model="productForm.sale_price" type="number" min="0" step="0.01" :label="__('Sale price')" /><flux:input wire:model="productForm.battery_details" :label="__('Battery details')" /><flux:checkbox wire:model="productForm.battery_required" :label="__('Requires batteries')" /></div><flux:text class="text-sm text-text-muted">{{ __('Master data only; no financial posting or POS pricing behavior is changed.') }}</flux:text></flux:card>
        <flux:card class="catalog-form-card space-y-6 p-4 sm:p-6" data-guide="product-form-identity">
            <div class="flex flex-col gap-2 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Basic identity') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-text-muted">{{ __('Keep the internal item code stable. Supplier, barcode, and descriptive changes never rewrite it.') }}</flux:text>
                </div>
                @if ($isEditing)
                    <span class="catalog-code-chip self-start">{{ $productForm['item_code'] }}</span>
                @endif
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="productForm.item_code" :label="__('Immutable item code')" :disabled="$isEditing" required />
                <flux:input wire:model="productForm.model_number" :label="__('Model / item number')" />
                <flux:input wire:model="productForm.name_ar" :label="__('Arabic product name')" dir="rtl" required />
                <flux:input wire:model="productForm.name_en" :label="__('English product name')" dir="ltr" required />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:textarea wire:model="productForm.description_ar" :label="__('Arabic description')" rows="4" dir="rtl" />
                <flux:textarea wire:model="productForm.description_en" :label="__('English description')" rows="4" dir="ltr" />
            </div>
        </flux:card>

        <flux:card class="catalog-form-card space-y-6 p-4 sm:p-6" data-guide="product-form-classification">
            <div>
                <flux:heading size="lg">{{ __('Classification and type') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-text-muted">{{ __('Only the approved standard, composite, and service types are available. Composite component lines are deferred until their policy is sufficiently defined.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <flux:select wire:model="productForm.category_id" :label="__('Category')" required>
                    <flux:select.option value="">{{ __('Select active category...') }}</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->code }} · {{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($categories->isEmpty())
                    <flux:text class="text-xs text-danger">{{ __('No active categories are available. Configure one before saving this card.') }}</flux:text>
                @endif
                <flux:select wire:model="productForm.brand_id" :label="__('Brand')">
                    <flux:select.option value="">{{ __('No brand') }}</flux:select.option>
                    @foreach ($brands as $brand)
                        <flux:select.option :value="$brand->id">{{ $brand->code }} · {{ app()->getLocale() === 'ar' ? $brand->name_ar : $brand->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($brands->isEmpty())
                    <flux:text class="text-xs text-text-muted">{{ __('No active brands exist. Brand is optional; configure one only when this product needs a brand.') }} <a class="font-medium underline" href="{{ route('catalog.brands') }}" wire:navigate>{{ __('Configure brands') }}</a></flux:text>
                @endif
                <flux:select wire:model.live="productForm.product_type" :label="__('Product type')" required>
                    <flux:select.option value="standard">{{ __('Standard') }}</flux:select.option>
                    <flux:select.option value="composite">{{ __('Composite') }}</flux:select.option>
                    <flux:select.option value="service">{{ __('Service') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="productForm.status" :label="__('Status')" required>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
            @if ($productForm['product_type'] === 'composite')
                <flux:callout variant="info" icon="information-circle" title="{{ __('Composite type boundary') }}">{{ __('The product type is stored and audited. Component lines, assembly, and bundle pricing are deferred because the approved Phase 1 data contract does not define them.') }}</flux:callout>
            @elseif ($productForm['product_type'] === 'service')
                <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Service product') }}">{{ __('Services do not create stock balances, so stock thresholds are not required for this type.') }}</flux:callout>
            @endif
        </flux:card>

        <flux:card class="catalog-form-card space-y-6 p-4 sm:p-6" data-guide="product-form-attributes">
            <div>
                <flux:heading size="lg">{{ __('Physical and merchandising attributes') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-text-muted">{{ __('These values are searchable/reportable attributes. They do not create variants, balances, or independent barcode identities.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <flux:input wire:model="productForm.unit_of_measure" :label="__('Unit of measure')" placeholder="{{ __('Owner-configurable') }}" />
                <flux:input wire:model="productForm.reorder_threshold" :label="__('Reorder threshold')" type="number" min="0" step="0.001" :disabled="$productForm['product_type'] === 'service'" />
                @php($lookups = $this->lookupOptions())
                <flux:select wire:model="productForm.age_label_id" :label="__('Age label')"><flux:select.option value="">{{ __('Legacy text fallback') }}</flux:select.option>@foreach($lookups['ages'] as $lookup)<flux:select.option value="{{ $lookup->id }}">{{ app()->getLocale() === 'ar' ? $lookup->name_ar : $lookup->name_en }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="productForm.target_age" :label="__('Legacy target age')" />
                <flux:select wire:model="productForm.gender_id" :label="__('Gender')"><flux:select.option value="">{{ __('Legacy text fallback') }}</flux:select.option>@foreach($lookups['genders'] as $lookup)<flux:select.option value="{{ $lookup->id }}">{{ app()->getLocale() === 'ar' ? $lookup->name_ar : $lookup->name_en }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="productForm.suitable_gender" :label="__('Legacy gender')" />
                <flux:select wire:model="productForm.colour_id" :label="__('Colour')"><flux:select.option value="">{{ __('Legacy text fallback') }}</flux:select.option>@foreach($lookups['colours'] as $lookup)<flux:select.option value="{{ $lookup->id }}">{{ app()->getLocale() === 'ar' ? $lookup->name_ar : $lookup->name_en }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="productForm.size" :label="__('Size')" />
                <flux:select wire:model="productForm.character_id" :label="__('Character')"><flux:select.option value="">{{ __('Legacy text fallback') }}</flux:select.option>@foreach($lookups['characters'] as $lookup)<flux:select.option value="{{ $lookup->id }}">{{ app()->getLocale() === 'ar' ? $lookup->name_ar : $lookup->name_en }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="productForm.character" :label="__('Legacy character')" />
                <flux:input wire:model="productForm.weight" :label="__('Weight')" type="number" min="0" step="0.001" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:textarea wire:model="productForm.key_points_ar" :label="__('Key points Arabic')" rows="3" dir="rtl" />
                <flux:textarea wire:model="productForm.key_points_en" :label="__('Key points English')" rows="3" dir="ltr" />
                <flux:textarea wire:model="productForm.keywords_ar" :label="__('Search keywords Arabic')" rows="3" dir="rtl" />
                <flux:textarea wire:model="productForm.keywords_en" :label="__('Search keywords English')" rows="3" dir="ltr" />
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <flux:input wire:model="productForm.dimension_length" :label="__('Length')" type="number" min="0" step="0.001" />
                <flux:input wire:model="productForm.dimension_width" :label="__('Width')" type="number" min="0" step="0.001" />
                <flux:input wire:model="productForm.dimension_height" :label="__('Height')" type="number" min="0" step="0.001" />
                <flux:input wire:model="productForm.dimension_unit" :label="__('Dimension unit')" placeholder="cm" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['age_label_ids', $lookups['ages'], __('Age label')],
                    ['character_ids', $lookups['characters'], __('Character')],
                    ['colour_ids', $lookups['colours'], __('Colour')],
                    ['gender_ids', $lookups['genders'], __('Gender')],
                ] as [$field, $options, $label])
                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-semibold text-text-primary">{{ $label }}</legend>
                        @if ($options->isEmpty())
                            <p class="text-sm text-text-muted">{{ __('No active lookup values are available.') }}</p>
                        @else
                            <p class="mb-3 text-sm text-text-muted">{{ __('Select every applicable value. Leave empty when it does not apply.') }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($options as $lookup)
                                    <label class="flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-text-primary">
                                        <input wire:model="productForm.{{ $field }}" type="checkbox" value="{{ $lookup->id }}" class="rounded border-border text-primary focus:ring-primary" />
                                        <span>{{ app()->getLocale() === 'ar' ? $lookup->name_ar : $lookup->name_en }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                @endforeach
            </div>
            <flux:checkbox wire:model="productForm.fractional_quantity" :label="__('Allow fractional quantity')" description="{{ __('Use this for products sold by weight, length, or another fractional unit.') }}" />
        </flux:card>

        <flux:card class="catalog-form-card space-y-5 p-4 sm:p-6" data-guide="product-form-web-seo">
            <div>
                <flux:heading size="lg">{{ __('Product web & SEO') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-text-muted">{{ __('Optional catalog content stays on this product card.') }}</flux:text>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:textarea wire:model="productForm.short_description_ar" :label="__('Short description Arabic')" rows="3" dir="rtl" />
                <flux:textarea wire:model="productForm.short_description_en" :label="__('Short description English')" rows="3" dir="ltr" />
                <flux:textarea wire:model="productForm.full_description_ar" :label="__('Full description Arabic')" rows="5" dir="rtl" />
                <flux:textarea wire:model="productForm.full_description_en" :label="__('Full description English')" rows="5" dir="ltr" />
                <flux:input wire:model="productForm.meta_title_ar" :label="__('Meta title Arabic')" dir="rtl" />
                <flux:input wire:model="productForm.meta_title_en" :label="__('Meta title English')" dir="ltr" />
                <flux:textarea wire:model="productForm.meta_description_ar" :label="__('Meta description Arabic')" rows="3" dir="rtl" />
                <flux:textarea wire:model="productForm.meta_description_en" :label="__('Meta description English')" rows="3" dir="ltr" />
                <flux:input wire:model="productForm.seo_slug" :label="__('SEO URL slug')" placeholder="toy-name" dir="ltr" />
                <flux:select wire:model="productForm.publish_visibility" :label="__('Website visibility')"><flux:select.option value="">{{ __('Not published') }}</flux:select.option><flux:select.option value="catalog">{{ __('Catalog') }}</flux:select.option><flux:select.option value="hidden">{{ __('Hidden') }}</flux:select.option></flux:select>
                <flux:input wire:model="productForm.sort_order" :label="__('Website display order')" type="number" min="0" />
            </div>
        </flux:card>

        <flux:card class="catalog-form-card space-y-5 p-4 sm:p-6" data-guide="product-form-media">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Protected product media') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-text-muted">{{ __('Private JPEG, PNG, and WebP files only. One main image and up to four additional images are retained through the shared Attachment Foundation.') }}</flux:text>
                </div>
                <flux:badge size="sm" color="sky">{{ __('1 main + 4 additional') }}</flux:badge>
            </div>
            @if (! $isEditing)
                <flux:callout variant="info" icon="information-circle">{{ __('Save the card first, then upload protected images without losing the draft.') }}</flux:callout>
            @else
                <div x-data="{ mediaError: '', maxBytes: 8 * 1024 * 1024, oversizedMessage: @js(__('The image is larger than the configured 8 MB local limit.')), validateMediaFile(event) { const file = event.target.files[0]; if (!file) { return; } if (file.size > this.maxBytes) { this.mediaError = this.oversizedMessage; event.target.value = ''; event.stopImmediatePropagation(); return; } this.mediaError = ''; } }" x-on:change.capture="if ($event.target.type === 'file') { validateMediaFile($event); }" class="space-y-3">
                    <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                        <flux:input wire:model="mediaUpload" type="file" accept="image/jpeg,image/png,image/webp" :label="__('Image file')" />
                    <flux:select wire:model="mediaRole" :label="__('Image role')">
                        <flux:select.option value="main">{{ __('Main image') }}</flux:select.option>
                        <flux:select.option value="additional">{{ __('Additional image') }}</flux:select.option>
                    </flux:select>
                    <flux:button class="md:col-span-2 md:justify-self-end" type="button" icon="arrow-up-tray" variant="primary" wire:click="uploadImage" wire:loading.attr="disabled" wire:target="uploadImage,mediaUpload">{{ __('Upload protected image') }}</flux:button>
                    </div>
                    <flux:text x-show="mediaError" x-cloak class="text-sm text-danger" x-text="mediaError"></flux:text>
                </div>
                <div wire:loading wire:target="mediaUpload" class="catalog-loading">{{ __('Uploading and validating image...') }}</div>
                @if ($errors->has('mediaUpload')) <flux:text class="text-sm text-danger">{{ $errors->first('mediaUpload') }}</flux:text> @endif
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    @forelse ($product?->images ?? [] as $image)
                        <article class="catalog-media-card">
                            <div class="catalog-media-frame">
                                <img src="{{ route('catalog.products.media', ['product' => $product, 'attachment' => $image->attachment]) }}" alt="{{ $image->attachment->original_filename }}" loading="lazy" />
                            </div>
                            <div class="space-y-2 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <flux:badge size="sm" color="{{ $image->role === 'main' ? 'emerald' : 'zinc' }}">{{ __($image->role === 'main' ? 'Main image' : 'Additional image') }}</flux:badge>
                                    <span class="text-xs text-text-muted">{{ $image->attachment->extension }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    @if ($image->role !== 'main')
                                        <flux:button size="xs" variant="subtle" wire:click="setMainImage({{ $image->id }})">{{ __('Make main') }}</flux:button>
                                        <flux:button size="xs" variant="subtle" wire:click="moveAdditionalImage({{ $image->id }}, 'up')" aria-label="{{ __('Move image up') }}">↑</flux:button>
                                        <flux:button size="xs" variant="subtle" wire:click="moveAdditionalImage({{ $image->id }}, 'down')" aria-label="{{ __('Move image down') }}">↓</flux:button>
                                    @endif
                                    <flux:button size="xs" variant="subtle" color="red" wire:click="removeImage({{ $image->id }})" wire:confirm="{{ __('Remove this protected image while preserving its attachment history?') }}">{{ __('Remove') }}</flux:button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-5"><x-state.empty :title="__('No product images yet')" :message="__('Upload one main image or up to four additional images. Protected delivery is source-authorized.')" icon="photo" /></div>
                    @endforelse
                </div>
            @endif
        </flux:card>

        <div class="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-xl border border-border bg-surface/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:justify-end">
            <flux:button href="{{ $isEditing ? route('catalog.products.show', ['product' => $product]) : route('catalog.products') }}" variant="subtle" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:text wire:dirty class="self-center text-xs text-text-muted">{{ __('Unsaved changes') }}</flux:text>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ $isEditing ? __('Save product card') : __('Create product card') }} <span wire:loading wire:target="save">{{ __('Saving...') }}</span></flux:button>
        </div>
    </form>

    @if ($isEditing && $product && ! $product->isVariant())
        <livewire:catalog::product-variations :product="$product" :key="'product-variations-'.$product->id" />
    @endif
</x-app.page>
