<?php

use App\Modules\Catalog\Actions\ManageProductMediaAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
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
        'suitable_gender' => '',
        'colour' => '',
        'size' => '',
        'character' => '',
        'key_points_ar' => '',
        'key_points_en' => '',
        'keywords_ar' => '',
        'keywords_en' => '',
        'fractional_quantity' => false,
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
        $product = $product->load(['category', 'brand', 'images.attachment']);
        $this->productForm = [
            'item_code' => $product->item_code,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'description_ar' => $product->description_ar ?? '',
            'description_en' => $product->description_en ?? '',
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
            'suitable_gender' => $product->suitable_gender ?? '',
            'colour' => $product->colour ?? '',
            'size' => $product->size ?? '',
            'character' => $product->character ?? '',
            'key_points_ar' => $product->key_points_ar ?? '',
            'key_points_en' => $product->key_points_en ?? '',
            'keywords_ar' => $product->keywords_ar ?? '',
            'keywords_en' => $product->keywords_en ?? '',
            'fractional_quantity' => (bool) $product->fractional_quantity,
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
            'productForm.suitable_gender' => ['nullable', 'string', 'max:30'],
            'productForm.colour' => ['nullable', 'string', 'max:100'],
            'productForm.size' => ['nullable', 'string', 'max:100'],
            'productForm.character' => ['nullable', 'string', 'max:100'],
            'productForm.key_points_ar' => ['nullable', 'string', 'max:4000'],
            'productForm.key_points_en' => ['nullable', 'string', 'max:4000'],
            'productForm.keywords_ar' => ['nullable', 'string', 'max:2000'],
            'productForm.keywords_en' => ['nullable', 'string', 'max:2000'],
            'productForm.fractional_quantity' => ['boolean'],
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
        <flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left">{{ __('Back to products') }}</flux:button>
    </x-slot:actions>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" title="{{ __('Saved') }}">{{ session('status') }}</flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Product card could not be saved') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-5" novalidate>
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
                <flux:select wire:model="productForm.brand_id" :label="__('Brand')">
                    <flux:select.option value="">{{ __('No brand') }}</flux:select.option>
                    @foreach ($brands as $brand)
                        <flux:select.option :value="$brand->id">{{ $brand->code }} · {{ app()->getLocale() === 'ar' ? $brand->name_ar : $brand->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
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
                <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Service product boundary') }}">{{ __('Services do not create stock balances. Reorder threshold is disabled for this type; appointment and fulfillment workflows are outside TSK-011.') }}</flux:callout>
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
                <flux:input wire:model="productForm.target_age" :label="__('Target age')" />
                <flux:input wire:model="productForm.suitable_gender" :label="__('Suitable gender')" />
                <flux:input wire:model="productForm.colour" :label="__('Colour')" />
                <flux:input wire:model="productForm.size" :label="__('Size')" />
                <flux:input wire:model="productForm.character" :label="__('Character')" />
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
            <flux:checkbox wire:model="productForm.fractional_quantity" :label="__('Allow fractional quantity later')" description="{{ __('This flag is stored for future inventory policy; TSK-011 creates no stock.') }}" />
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
            <flux:button href="{{ $isEditing ? route('catalog.products.show', ['product' => $product]) : route('catalog.products') }}" variant="subtle">{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ $isEditing ? __('Save product card') : __('Create product card') }}</flux:button>
        </div>
    </form>
</x-app.page>
