<?php

use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\ManageProductMediaAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
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
    public string $supplierBarcode = '';
    public string $stage = 'basic';
    public array $productForm = [
        'item_code' => '',
        'barcode_registration_type' => 'international',
        'open_price' => false,
        'sell_online' => false,
        'weight_unit' => 'kg',
        'name_ar' => '',
        'name_en' => '',
        'description_ar' => '',
        'description_en' => '',
        'short_description_ar' => '', 'short_description_en' => '', 'full_description_ar' => '', 'full_description_en' => '',
        'seo_slug' => '', 'publish_visibility' => '', 'sort_order' => '',
        'model_number' => '',
        'product_type' => 'standard',
        'status' => 'active',
        'unit_of_measure' => '',
        'category_id' => '',
        'brand_id' => '',
        'tax_setting_id' => '',
        'reorder_threshold' => '',
        'dimension_length' => '',
        'dimension_width' => '',
        'dimension_height' => '',
        'dimension_unit' => '',
        'weight' => '',
        'key_points_ar' => '',
        'key_points_en' => '',
        'keywords_ar' => '',
        'keywords_en' => '',
        'fractional_quantity' => false,
        'average_cost' => '', 'sale_price' => '', 'battery_required' => false, 'battery_details' => '',
        'preferred_supplier_id' => '',
        'supplier_item_code' => '',
        'filter_value_ids' => [],
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

    public function localProductCodePreview(): ?string
    {
        if (($this->isEditing && filled($this->productForm['item_code'])) || $this->productForm['barcode_registration_type'] !== 'local' || blank($this->productForm['preferred_supplier_id'])) {
            return null;
        }

        try {
            return app(ReserveProductIdentityAction::class)->preview((int) $this->productForm['preferred_supplier_id']);
        } catch (Throwable) {
            return null;
        }
    }

    public function localProductCodePreviewError(): ?string
    {
        if (($this->isEditing && filled($this->productForm['item_code'])) || $this->productForm['barcode_registration_type'] !== 'local' || blank($this->productForm['preferred_supplier_id'])) {
            return null;
        }

        try {
            app(ReserveProductIdentityAction::class)->preview((int) $this->productForm['preferred_supplier_id']);

            return null;
        } catch (\InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            return \App\Support\UserSafeError::message($exception);
        }
    }

    public function save(SaveProductAction $action, AddBarcodeAction $barcodeAction): void
    {
        $this->persistProduct(false, $action, $barcodeAction);
    }

    public function saveDraft(SaveProductAction $action, AddBarcodeAction $barcodeAction): void
    {
        $this->persistProduct(true, $action, $barcodeAction);
    }

    private function persistProduct(bool $saveAsDraft, SaveProductAction $action, AddBarcodeAction $barcodeAction): void
    {
        Gate::authorize($this->isEditing ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            $this->productForm['fractional_quantity'] = filter_var(
                $this->productForm['fractional_quantity'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
            $needsBarcode = ! $saveAsDraft && ($this->productId === null || ! Product::query()->whereKey($this->productId)->whereHas('barcodes', fn ($query) => $query->where('status', 'active'))->exists());
            $validated = $this->validate([
                ...$this->rules($saveAsDraft),
                'supplierBarcode' => [Rule::requiredIf(fn (): bool => $needsBarcode && $this->productForm['barcode_registration_type'] === 'international'), 'nullable', 'string', 'max:64', 'regex:/^\S+$/', Rule::unique('barcodes', 'barcode')],
            ], $this->productValidationMessages(), $this->productValidationAttributes())['productForm'];
            $validated['_save_as_draft'] = $saveAsDraft;
            if (! $saveAsDraft && $validated['barcode_registration_type'] === 'local' && blank($validated['item_code'])) {
                try {
                    app(ReserveProductIdentityAction::class)->preview((int) $validated['preferred_supplier_id']);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['productForm.preferred_supplier_id' => $exception->getMessage()]);
                }
            }
            $product = DB::transaction(function () use ($action, $barcodeAction, $saveAsDraft, $validated) {
                $product = $action->execute($validated, $this->productId, $this->productVersion);
                if (! $saveAsDraft && (blank($product->item_code) || $product->status !== $validated['status'])) {
                    throw ValidationException::withMessages([
                        'productForm' => __('The final save did not complete the product. No draft was created; review the highlighted fields and try again.'),
                    ]);
                }
                $hasActiveBarcode = $product->barcodes()->where('status', 'active')->exists();
                if (! $saveAsDraft && ! $hasActiveBarcode && $validated['barcode_registration_type'] === 'local') {
                    $supplier = Supplier::query()->findOrFail((int) $validated['preferred_supplier_id']);
                    $barcodeAction->allocateLocalBarcode($product->id, $supplier->code, 'product-card:'.$product->id);
                } elseif (! $saveAsDraft && $validated['barcode_registration_type'] === 'international' && filled($this->supplierBarcode)) {
                    $barcodeAction->addSupplierBarcode($product->id, $this->supplierBarcode);
                }

                return $product;
            });
            $this->supplierBarcode = '';

            if (! $this->isEditing) {
                session()->flash('status', $saveAsDraft ? __('Product draft saved. Resume it when the remaining required data is available.') : __('Product card created successfully. Add protected images from this page.'));
                $this->redirectRoute('catalog.products.edit', ['product' => $product], navigate: true);

                return;
            }

            $persisted = Product::query()->findOrFail($product->id);
            $this->loadProduct($persisted);
            if ($persisted->sale_price !== null && bccomp((string) $persisted->sale_price, '0', 4) > 0) {
                \App\Modules\Reporting\Models\Alert::query()
                    ->where('alert_type', 'unpriced_product')->where('source_id', (string) $persisted->id)
                    ->where('status', '!=', 'resolved')->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
            app(\App\Modules\Platform\Support\ProductPricingReadiness::class)->snapshot();
            Flux::toast(variant: 'success', text: $saveAsDraft ? __('Product draft saved successfully.') : __('Product card saved successfully.'));
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $field = str((string) $exception->validator->errors()->keys()[0])->afterLast('.')->toString();
            $this->dispatch('product-validation-failed', field: $field);
            Flux::toast(variant: 'danger', text: __('Please correct the highlighted field.'));
        } catch (\Throwable $exception) {
            $this->addError('productForm', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
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
            $this->addError('mediaUpload', __('Select an image and wait for its upload to finish before saving it.'));

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
            $this->dispatch('product-image-saved');
            Flux::toast(variant: 'success', text: __('Protected product image added successfully.'));
        } catch (\Throwable $exception) {
            $this->addError('mediaUpload', \App\Support\UserSafeError::message($exception));
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function setMainImage(int $imageId, ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->setMain(Product::query()->findOrFail($this->productId), $imageId);
            Flux::toast(variant: 'success', text: __('Main image updated successfully.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
        }
    }

    public function removeImage(int $imageId, ManageProductMediaAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $action->revoke(Product::query()->findOrFail($this->productId), $imageId);
            Flux::toast(variant: 'success', text: __('Product image removed while preserving attachment history.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: \App\Support\UserSafeError::message($exception));
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
        $product = $product->load(['category', 'brand', 'images.attachment', 'preferredProductSupplier', 'filterValues']);
        $this->productForm = [
            'item_code' => $product->item_code,
            'barcode_registration_type' => $product->barcode_registration_type ?? 'international',
            'open_price' => (bool) $product->open_price,
            'sell_online' => (bool) $product->sell_online,
            'weight_unit' => $product->weight_unit ?? 'kg',
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'description_ar' => $product->description_ar ?? '',
            'description_en' => $product->description_en ?? '',
            'short_description_ar' => $product->short_description_ar ?? '', 'short_description_en' => $product->short_description_en ?? '', 'full_description_ar' => $product->full_description_ar ?? '', 'full_description_en' => $product->full_description_en ?? '',
            'seo_slug' => $product->seo_slug ?? '', 'publish_visibility' => $product->publish_visibility ?? '', 'sort_order' => $product->sort_order ?? '',
            'model_number' => $product->model_number ?? '',
            'product_type' => $product->product_type,
            'status' => $product->status,
            'unit_of_measure' => $product->unit_of_measure ?? '',
            'category_id' => (string) $product->category_id,
            'brand_id' => (string) ($product->brand_id ?? ''),
            'tax_setting_id' => (string) ($product->tax_setting_id ?? ''),
            'reorder_threshold' => $product->reorder_threshold ?? '',
            'dimension_length' => $product->dimension_length ?? '',
            'dimension_width' => $product->dimension_width ?? '',
            'dimension_height' => $product->dimension_height ?? '',
            'dimension_unit' => $product->dimension_unit ?? '',
            'weight' => $product->weight ?? '',
            'key_points_ar' => $product->key_points_ar ?? '',
            'key_points_en' => $product->key_points_en ?? '',
            'keywords_ar' => $product->keywords_ar ?? '',
            'keywords_en' => $product->keywords_en ?? '',
            'fractional_quantity' => (bool) $product->fractional_quantity,
            'average_cost' => auth()->user()?->hasPermission('products_categories_brands.cost_view') ? ($product->average_cost ?? '') : '', 'sale_price' => $product->sale_price ?? '',
            'battery_required' => (bool) $product->battery_required, 'battery_details' => $product->battery_details ?? '',
            'preferred_supplier_id' => (string) ($product->preferredProductSupplier?->supplier_id ?? ''),
            'supplier_item_code' => (string) ($product->preferredProductSupplier?->supplier_item_code ?? ''),
        ];
        $this->productForm['filter_value_ids'] = $product->filterValues->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $this->productVersion = $product->lock_version;
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $saveAsDraft): array
    {
        $companyId = (int) app(AuthorizedCompanyContext::class)->resolve(auth()->user(), request()->input('company_id'))->id;
        $savedCost = $this->productId === null ? null : Product::query()->whereKey($this->productId)->value('average_cost');
        return [
            'productForm.item_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('products', 'item_code')->ignore($this->productId)],
            'productForm.barcode_registration_type' => ['required', Rule::in(['international', 'local'])],
            'productForm.open_price' => ['boolean'],
            'productForm.sell_online' => ['boolean'],
            'productForm.weight_unit' => ['nullable', Rule::in(['g', 'kg'])],
            'productForm.name_ar' => ['required', 'string', 'max:255'],
            'productForm.name_en' => ['nullable', 'string', 'max:255'],
            'productForm.description_ar' => ['nullable', 'string', 'max:5000'],
            'productForm.description_en' => ['nullable', 'string', 'max:5000'],
            'productForm.short_description_ar' => ['nullable', 'string', 'max:1000'], 'productForm.short_description_en' => ['nullable', 'string', 'max:1000'], 'productForm.full_description_ar' => ['nullable', 'string', 'max:10000'], 'productForm.full_description_en' => ['nullable', 'string', 'max:10000'],
            'productForm.seo_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'], 'productForm.publish_visibility' => ['nullable', Rule::in(['catalog', 'hidden'])], 'productForm.sort_order' => ['nullable', 'integer', 'min:0'],
            'productForm.model_number' => [$saveAsDraft ? 'nullable' : 'required', 'string', 'max:100'],
            'productForm.product_type' => ['required', Rule::in($this->isEditing && $this->productForm['product_type'] === 'digital' ? ['standard', 'composite', 'service', 'digital'] : ['standard', 'composite', 'service'])],
            'productForm.status' => ['required', Rule::in(['active', 'inactive'])],
            'productForm.unit_of_measure' => ['nullable', 'string', 'max:50'],
            'productForm.category_id' => ['required', 'integer', 'exists:categories,id'],
            'productForm.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'productForm.tax_setting_id' => ['nullable', 'integer', Rule::exists('tax_settings', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active')->where(fn ($dates) => $dates->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($dates) => $dates->whereNull('effective_to')->orWhere('effective_to', '>=', now())))],
            'productForm.reorder_threshold' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_length' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_width' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_height' => ['nullable', 'numeric', 'min:0'],
            'productForm.dimension_unit' => ['nullable', 'string', 'max:20'],
            'productForm.weight' => ['nullable', 'numeric', 'min:0'],
            'productForm.key_points_ar' => ['nullable', 'string', 'max:4000'],
            'productForm.key_points_en' => ['nullable', 'string', 'max:4000'],
            'productForm.keywords_ar' => ['nullable', 'string', 'max:2000'],
            'productForm.keywords_en' => ['nullable', 'string', 'max:2000'],
            'productForm.fractional_quantity' => ['required', 'boolean'],
            'productForm.average_cost' => [$saveAsDraft || ($savedCost !== null && is_numeric($savedCost) && (float) $savedCost >= 0) ? 'nullable' : 'required', 'numeric', 'min:0'], 'productForm.sale_price' => [$saveAsDraft ? 'nullable' : 'required', 'numeric', 'min:0.01'],
            'productForm.battery_required' => ['boolean'], 'productForm.battery_details' => ['nullable', 'string', 'max:255'],
            'productForm.preferred_supplier_id' => [! $saveAsDraft && blank($this->productForm['item_code']) ? 'required' : 'nullable', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'productForm.supplier_item_code' => ['nullable', 'string', 'max:100'],
            'productForm.filter_value_ids' => ['array'],
            'productForm.filter_value_ids.*' => ['integer', 'distinct', 'exists:product_option_values,id'],
        ];
    }

    private function productValidationMessages(): array
    {
        return str_starts_with(app()->getLocale(), 'ar') ? [
            'required' => 'حقل :attribute مطلوب.',
            'numeric' => 'يجب أن يحتوي حقل :attribute على رقم صحيح.',
            'integer' => 'يجب أن يحتوي حقل :attribute على عدد صحيح.',
            'boolean' => 'قيمة :attribute غير صحيحة.',
            'min.numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
            'exists' => 'القيمة المختارة في حقل :attribute غير متاحة.',
            'unique' => 'قيمة :attribute مستخدمة بالفعل.',
            'regex' => 'صيغة :attribute غير صحيحة.',
            'in' => 'القيمة المختارة في حقل :attribute غير صحيحة.',
        ] : [];
    }

    private function productValidationAttributes(): array
    {
        if (! str_starts_with(app()->getLocale(), 'ar')) return [];
        return [
            'productForm.item_code' => 'رمز بطاقة المنتج', 'productForm.barcode_registration_type' => 'نوع تسجيل الباركود',
            'productForm.name_ar' => 'الاسم العربي', 'productForm.name_en' => 'الاسم الإنجليزي',
            'productForm.model_number' => 'رقم الموديل', 'productForm.product_type' => 'نوع المنتج',
            'productForm.status' => 'الحالة', 'productForm.category_id' => 'الفئة', 'productForm.brand_id' => 'العلامة التجارية', 'productForm.tax_setting_id' => 'ضريبة المنتج',
            'productForm.average_cost' => 'سعر التكلفة', 'productForm.sale_price' => 'سعر البيع الأساسي للمستهلك',
            'productForm.fractional_quantity' => 'نوع كمية المنتج', 'productForm.preferred_supplier_id' => 'المورد المفضل',
            'supplierBarcode' => 'الباركود الدولي',
        ];
    }

    public function render()
    {
        $companyId = (int) app(AuthorizedCompanyContext::class)->resolve(auth()->user(), request()->input('company_id'))->id;
        $product = $this->productId === null ? null : Product::query()
            ->with(['category', 'brand', 'barcodes', 'images.attachment', 'filterValues'])
            ->findOrFail($this->productId);

        $savedFilterIds = $product?->filterValues->pluck('id')->all() ?? [];

        return view('catalog.product-form', [
            'product' => $product,
            'categories' => Category::query()->active()->with('parent:id,code,name_ar,name_en')->orderBy('sort_order')->orderBy('code')->get(['id', 'parent_id', 'code', 'name_ar', 'name_en']),
            'brands' => Brand::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'taxSettings' => TaxSetting::query()->forCompany($companyId)->where('status', 'active')->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en', 'rate']),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
            'filterGroups' => ProductOptionGroup::query()
                ->where(fn ($query) => $query->where('status', 'active')->orWhereHas('values', fn ($values) => $values->whereIn('id', $savedFilterIds)))
                ->with(['values' => fn ($query) => $query->where(fn ($values) => $values->where('status', 'active')->orWhereIn('id', $savedFilterIds))])
                ->orderBy('sort_order')->orderBy('id')->get(),
            'canViewCost' => auth()->user()?->hasPermission('products_categories_brands.cost_view') ?? false,
        ]);
    }
}; ?>

<x-app.page
    :title="$isEditing ? __('Edit product card') : __('Add Product Card')"
    max-width="7xl"
    class="catalog-screen product-form-page"
    data-guide="product-form-header"
>
    <x-slot:actions>
        <flux:button href="{{ route('catalog.products') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
    </x-slot:actions>

    <x-setup.product-pricing-readiness :readiness="app(\App\Modules\Platform\Support\ProductPricingReadiness::class)->snapshot()" compact />

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

    <nav class="catalog-product-section-nav" aria-label="{{ __('Product sections') }}">
        @foreach ([['#product-identity', __('Basic identity')], ['#product-classification', __('Classification and type')], ['#product-pricing', __('Commercial and descriptive master fields')]] as [$href, $label])
            <a href="{{ $href }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form wire:submit="save" class="flex flex-col gap-4 sm:gap-5" novalidate x-data x-on:product-validation-failed.window="$nextTick(() => { const field = document.getElementById('product-field-' + $event.detail.field); if (field) { field.scrollIntoView({ behavior: 'smooth', block: 'center' }); field.focus(); } })">
        <flux:card id="product-identity" class="order-1 catalog-form-card space-y-5 p-4 sm:p-5" data-guide="product-form-identity">
            <div class="flex items-center justify-between gap-3 border-b border-border pb-3">
                <flux:heading size="lg">{{ __('Basic identity') }}</flux:heading>
                <x-context-help :title="__('Product identity help')" :label="__('Open product identity help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('Complete barcode, model, identity, cost, consumer price, category, product type, and the applicable supplier before activating the Product Card.') }}</li><li>{{ __('The internal item code remains permanent.') }}</li><li>{{ __('Supplier code, model, and barcode stay separate and are all searchable.') }}</li><li>{{ __('Scanner input works in any barcode search field; camera capture is not required.') }}</li></ul></x-context-help>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="productForm.barcode_registration_type" :label="__('Barcode registration type')" required>
                    <flux:select.option value="international">{{ __('International barcode') }}</flux:select.option>
                    <flux:select.option value="local">{{ __('Local generated barcode') }}</flux:select.option>
                </flux:select>
                @if ($productForm['barcode_registration_type'] === 'local' && blank($productForm['item_code']))
                    <flux:select wire:model.live="productForm.preferred_supplier_id" :label="__('Supplier')" required>
                        <flux:select.option value="">{{ __('Select supplier first') }}</flux:select.option>
                        @foreach ($suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $supplier->name_ar : $supplier->name_en }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="rounded-lg border border-border bg-surface-muted/30 p-3 text-sm">
                        <span class="block text-xs text-text-muted">{{ __('Generated product-code preview') }}</span>
                        @php($previewError = $this->localProductCodePreviewError())
                        <strong class="font-mono" dir="ltr">{{ $this->localProductCodePreview() ?? ($previewError ? '—' : __('Select supplier first')) }}</strong>
                        @if ($previewError)
                            <span class="mt-1 block text-xs font-semibold text-rose-600" role="alert">{{ $previewError }}</span>
                        @endif
                    </div>
                @endif
                @if ($productForm['barcode_registration_type'] === 'international')
                    <flux:input wire:model="supplierBarcode" :label="__('International barcode')" inputmode="numeric" dir="ltr" required />
                @else
                    <x-context-help :title="__('Local product code help')" :label="__('Open contextual help')"><p>{{ __('The local product code uses the four-digit identity from a canonical SUP-#### supplier code, or the first four digits of a numeric supplier code, followed by a hyphen and the shared six-digit prefix sequence. Leading zeros are preserved, and the international barcode remains a separate identity.') }}</p><p>{{ __('The final unique code is allocated atomically when the product is completed.') }}</p></x-context-help>
                @endif
                <flux:input id="product-field-model_number" wire:model="productForm.model_number" :label="__('Model / item number')" required />
                @if ($isEditing)
                    <flux:input wire:model="productForm.item_code" :label="__('Product Card code')" disabled dir="ltr" />
                @endif
                <flux:input id="product-field-name_ar" wire:model="productForm.name_ar" :label="__('Arabic product name')" dir="rtl" required />
                <flux:input wire:model="productForm.name_en" :label="__('English product name')" dir="ltr" />
            </div>
            @if ($isEditing && $product)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface-muted/40 p-3 text-sm text-text-muted">
                    <span>{{ __('Manage barcodes after saving the product card.') }}</span>
                    <flux:button href="{{ route('catalog.products.show', ['product' => $product]) }}" variant="subtle" size="sm" wire:navigate>{{ __('Product barcode management') }}</flux:button>
                </div>
            @endif
        </flux:card>

        <flux:card id="product-classification" class="order-2 catalog-form-card space-y-5 p-4 sm:p-5" data-guide="product-form-classification">
            <div>
                <flux:heading size="lg">{{ __('Classification and type') }}</flux:heading>
                <x-context-help :title="__('Classification and type help')" :label="__('Open contextual help')"><p>{{ __('Choose the category and product type used for this item.') }}</p></x-context-help>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <flux:select id="product-field-category_id" wire:model="productForm.category_id" :label="__('Category / subcategory')" required>
                    <flux:select.option value="">{{ __('Select active category...') }}</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->parent ? (str_starts_with(app()->getLocale(), 'ar') ? $category->parent->name_ar : $category->parent->name_en).' / ' : '' }}{{ str_starts_with(app()->getLocale(), 'ar') ? $category->name_ar : $category->name_en }} · {{ $category->code }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($categories->isEmpty())
                    <flux:text class="text-xs text-danger">{{ __('No active categories are available. Configure one before saving this card.') }}</flux:text>
                @endif
                <flux:select wire:model="productForm.brand_id" :label="__('Brand')">
                    <flux:select.option value="">{{ __('No brand') }}</flux:select.option>
                    @foreach ($brands as $brand)
                        <flux:select.option :value="$brand->id">{{ $brand->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $brand->name_ar : $brand->name_en }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($brands->isEmpty())
                    <flux:text class="text-xs text-text-muted">{{ __('No active brands exist. Brand is optional; configure one only when this product needs a brand.') }} <a class="font-medium underline" href="{{ route('catalog.brands') }}" wire:navigate>{{ __('Configure brands') }}</a></flux:text>
                @endif
                <flux:select wire:model="productForm.tax_setting_id" :label="__('Product-specific tax')">
                    <flux:select.option value="">{{ __('Use company default tax') }}</flux:select.option>
                    @foreach ($taxSettings as $taxSetting)
                        <flux:select.option :value="$taxSetting->id">{{ $taxSetting->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $taxSetting->name_ar : $taxSetting->name_en }} @if($taxSetting->rate !== null)({{ number_format((float) $taxSetting->rate, 2) }}%)@endif</flux:select.option>
                    @endforeach
                </flux:select>
                @if(filled($productForm['item_code']) || $productForm['barcode_registration_type'] !== 'local')
                    <flux:select wire:model="productForm.preferred_supplier_id" :label="__('Supplier')">
                        <flux:select.option value="">{{ __('No supplier') }}</flux:select.option>
                        @foreach ($suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $supplier->name_ar : $supplier->name_en }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="productForm.supplier_item_code" :label="__('Supplier product / local code')" dir="ltr" />
                <flux:select wire:model.live="productForm.product_type" :label="__('Product type')" required>
                    <flux:select.option value="standard">{{ __('Standard') }}</flux:select.option>
                    <flux:select.option value="service">{{ __('Service') }}</flux:select.option>
                    <flux:select.option value="composite">{{ __('Composite') }}</flux:select.option>
                    @if ($isEditing && $productForm['product_type'] === 'digital')
                        <flux:select.option value="digital">{{ __('Digital product (legacy)') }}</flux:select.option>
                    @endif
                </flux:select>
                <flux:select wire:model="productForm.status" :label="__('Status')" required>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
            @if ($productForm['product_type'] === 'service')
                <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Service product') }}">{{ __('Services do not create stock balances, so stock thresholds are not required for this type.') }}</flux:callout>
            @elseif ($productForm['product_type'] === 'digital')
                <flux:callout variant="info" icon="information-circle" title="{{ __('Digital product') }}">{{ __('Digital products do not use reorder thresholds.') }}</flux:callout>
            @endif
        </flux:card>

        <flux:card id="product-pricing" class="order-3 catalog-form-card space-y-5 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Commercial and descriptive master fields') }}</flux:heading>
                <x-context-help :title="__('Product pricing help')" :label="__('Open product pricing help')"><ul class="list-disc space-y-2 ps-5"><li>{{ __('The base consumer selling price is the fallback price when no effective branch or store price-list value applies.') }}</li><li>{{ __('At sale time, the assigned active store price list is used first, then the branch default price list, then the base consumer selling price.') }}</li><li>{{ __('Approved purchasing updates cost; cost is never used as the selling price.') }}</li><li>{{ __('Open-price rules remain in the approved Pricing workflow.') }}</li></ul></x-context-help>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @if ($canViewCost || ! $isEditing)<div><flux:input id="product-field-average_cost" wire:model="productForm.average_cost" type="number" min="0" step="0.01" :label="__('Cost price')" /><x-context-help :title="__('Cost price')" :label="__('Open contextual help')"><p>{{ __('Cost is updated by approved purchasing and is not the selling price.') }}</p></x-context-help></div>@endif
                <div id="base-consumer-price"><flux:input id="product-field-sale_price" wire:model="productForm.sale_price" type="number" min="0.01" step="0.01" :label="__('Base consumer selling price')" /><x-context-help :title="__('Base consumer selling price')" :label="__('Open contextual help')"><p>{{ __('Required for sellable products and must be greater than zero.') }}</p></x-context-help></div>
                <flux:checkbox wire:model="productForm.open_price" :label="__('Open-price product')" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface-muted/40 p-3 text-sm text-text-muted">
                <span>{{ __('Open-price rules are configured in Pricing and enforced in POS.') }}</span>
                @can('pricing_labels.view')
                    <flux:button href="{{ route('pricing.index') }}" variant="subtle" size="sm" wire:navigate>{{ __('Open pricing') }}</flux:button>
                @endcan
            </div>
        </flux:card>

        @if($isEditing)
        <div class="order-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30"><div><div class="flex items-center gap-2"><h2 class="text-base font-semibold text-text-primary sm:text-lg">{{ __('Stage B — Optional additional data') }}</h2><x-context-help :title="__('Optional product data help')" :label="__('Open contextual help')"><p>{{ __('The required Product Card is saved. Images, Product Filters, dimensions, online visibility, descriptions, and battery data are optional enrichment.') }}</p></x-context-help></div></div><flux:button href="{{ route('catalog.product-options') }}" variant="subtle" icon="swatch" wire:navigate>{{ __('Product Filters') }}</flux:button></div>
        <details id="product-attributes" class="order-4 catalog-form-card catalog-product-disclosure" data-guide="product-form-attributes">
            <summary>
                <flux:heading size="lg">{{ __('Physical and merchandising attributes') }}</flux:heading>
                <x-context-help :title="__('Physical and merchandising attributes help')" :label="__('Open contextual help')"><p>{{ __('These values are searchable/reportable attributes. They do not create variants, balances, or independent barcode identities.') }}</p></x-context-help>
            </summary>
            <div class="catalog-product-disclosure__content">
                <div class="mb-4 grid gap-4 md:grid-cols-2">
                    <flux:textarea wire:model="productForm.description_ar" :label="__('Arabic description')" rows="3" dir="rtl" />
                    <flux:textarea wire:model="productForm.description_en" :label="__('English description')" rows="3" dir="ltr" />
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <flux:input wire:model="productForm.unit_of_measure" :label="__('Unit of measure')" placeholder="{{ __('Owner-configurable') }}" />
                    <flux:input wire:model="productForm.reorder_threshold" :label="__('Reorder threshold')" type="number" min="0" step="1" :disabled="in_array($productForm['product_type'], ['service', 'digital'], true)" />
                    <flux:input wire:model="productForm.weight" :label="__('Weight')" type="number" min="0" step="0.001" />
                    <flux:select wire:model="productForm.weight_unit" :label="__('Weight unit')"><flux:select.option value="kg">kg</flux:select.option><flux:select.option value="g">g</flux:select.option></flux:select>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <flux:input wire:model="productForm.dimension_length" :label="__('Length')" type="number" min="0" step="0.001" />
                    <flux:input wire:model="productForm.dimension_width" :label="__('Width')" type="number" min="0" step="0.001" />
                    <flux:input wire:model="productForm.dimension_height" :label="__('Height')" type="number" min="0" step="0.001" />
                    <flux:input wire:model="productForm.dimension_unit" :label="__('Dimension unit')" placeholder="cm" />
                </div>
                @php($volume = collect(['dimension_length', 'dimension_width', 'dimension_height'])->contains(fn ($field) => blank($productForm[$field])) ? null : (float) $productForm['dimension_length'] * (float) $productForm['dimension_width'] * (float) $productForm['dimension_height'])
                <div class="mt-4 rounded-xl border border-border bg-surface-muted/40 p-3 text-sm text-text-muted">
                    <span class="font-medium text-text-primary">{{ __('Calculated volume') }}:</span>
                    {{ $volume === null ? '—' : rtrim(rtrim(number_format($volume, 3, '.', ''), '0'), '.') }} {{ $productForm['dimension_unit'] ? $productForm['dimension_unit'].'³' : '' }}
                </div>
                <div class="mt-4 space-y-4" id="product-field-filter_value_ids">
                    <flux:heading size="lg">{{ __('Product Filters') }}</flux:heading>
                    @forelse ($filterGroups as $group)
                        <fieldset wire:key="product-filter-group-{{ $group->id }}" class="rounded-xl border border-border p-3">
                            <legend class="px-1 text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar : $group->name_en }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @forelse ($group->values as $value)
                                    <flux:checkbox wire:key="product-filter-value-{{ $value->id }}" wire:model="productForm.filter_value_ids" value="{{ $value->id }}" :label="str_starts_with(app()->getLocale(), 'ar') ? $value->name_ar : $value->name_en" :disabled="($group->status !== 'active' || $value->status !== 'active') && ! in_array((string) $value->id, $productForm['filter_value_ids'], true)" />
                                @empty
                                    <flux:text>{{ __('No filter values configured.') }}</flux:text>
                                @endforelse
                            </div>
                        </fieldset>
                    @empty
                        <flux:text>{{ __('No product filters configured.') }}</flux:text>
                    @endforelse
                    <flux:error name="productForm.filter_value_ids" />
                    <flux:error name="productForm.filter_value_ids.*" />
                </div>
                <input type="hidden" wire:model="productForm.fractional_quantity" value="0">
                <div class="mt-4 grid gap-4 md:grid-cols-2"><flux:checkbox wire:model="productForm.sell_online" :label="__('Sell online')" /><flux:checkbox wire:model="productForm.battery_required" :label="__('Uses battery')" /><flux:input wire:model="productForm.battery_details" :label="__('Battery details')" /></div>
            </div>
        </details>

        <details class="order-5 catalog-form-card catalog-product-disclosure" data-guide="product-form-web-seo">
            <summary>
                <flux:heading size="lg">{{ __('Product web & SEO') }}</flux:heading>
                <x-context-help :title="__('Product web and SEO help')" :label="__('Open contextual help')"><p>{{ __('Optional catalog content stays on this product card.') }}</p></x-context-help>
            </summary>
            <div class="catalog-product-disclosure__content">
                <div class="grid gap-4 md:grid-cols-2">
                <flux:textarea wire:model="productForm.short_description_ar" :label="__('Short description Arabic')" rows="3" dir="rtl" />
                <flux:textarea wire:model="productForm.short_description_en" :label="__('Short description English')" rows="3" dir="ltr" />
                <flux:textarea wire:model="productForm.full_description_ar" :label="__('Full description Arabic')" rows="5" dir="rtl" />
                <flux:textarea wire:model="productForm.full_description_en" :label="__('Full description English')" rows="5" dir="ltr" />
                <flux:input wire:model="productForm.seo_slug" :label="__('SEO URL slug')" placeholder="toy-name" dir="ltr" />
                <flux:select wire:model="productForm.publish_visibility" :label="__('Available online')"><flux:select.option value="">{{ __('Not published') }}</flux:select.option><flux:select.option value="catalog">{{ __('Available online') }}</flux:select.option><flux:select.option value="hidden">{{ __('Not available online') }}</flux:select.option></flux:select>
                <flux:input wire:model="productForm.sort_order" :label="__('Website display order')" type="number" min="0" />
                </div>
            </div>
        </details>

        <details id="product-media" class="order-6 catalog-form-card catalog-product-disclosure" data-guide="product-form-media">
            <summary>
                <div>
                    <flux:heading size="lg">{{ __('Protected product media') }}</flux:heading>
                    <x-context-help :title="__('Protected product media help')" :label="__('Open contextual help')"><p>{{ __('Private JPEG, PNG, and WebP files only. One main image and up to four additional images are retained through the shared Attachment Foundation.') }}</p></x-context-help>
                </div>
            </summary>
            <div class="catalog-product-disclosure__content">
                <div class="mb-4"><flux:badge size="sm" color="sky">{{ __('1 main + 4 additional') }}</flux:badge></div>
            @if (! $isEditing)
                <flux:callout variant="info" icon="information-circle">{{ __('Save the card first, then upload protected images without losing the draft.') }}</flux:callout>
            @else
                <div x-data="{ mediaError: '', uploading: false, uploadReady: false, maxBytes: @js(config('attachments.limits.product_image')),  oversizedMessage: @js(__('The image is larger than the configured 8 MB local limit.')), validateMediaFile(event) { this.uploadReady = false; const file = event.target.files[0]; if (!file) { return; } if (file.size > this.maxBytes) { this.mediaError = this.oversizedMessage; event.target.value = ''; event.stopImmediatePropagation(); return; } this.mediaError = ''; } }" x-on:livewire-upload-start="uploading = true; uploadReady = false" x-on:livewire-upload-finish="uploading = false; uploadReady = true" x-on:livewire-upload-error="uploading = false; uploadReady = false" x-on:livewire-upload-cancel="uploading = false; uploadReady = false" x-on:product-image-saved.window="uploadReady = false" x-on:change.capture="if ($event.target.type === 'file') { validateMediaFile($event); }" class="space-y-3">
                    <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                        <flux:input wire:model="mediaUpload" type="file" accept="image/jpeg,image/png,image/webp" :label="__('Image file')" />
                    <flux:select wire:model="mediaRole" :label="__('Image role')">
                        <flux:select.option value="main">{{ __('Main image') }}</flux:select.option>
                        <flux:select.option value="additional">{{ __('Additional image') }}</flux:select.option>
                    </flux:select>
                    <flux:button class="md:col-span-2 md:justify-self-end" type="button" icon="arrow-up-tray" variant="primary" wire:click="uploadImage" disabled x-bind:disabled="uploading || ! uploadReady" wire:loading.attr="disabled" wire:target="uploadImage,mediaUpload">{{ __('Upload protected image') }}</flux:button>
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
            </div>
        </details>

        @else
            <div class="order-4 rounded-xl border border-border bg-surface-muted/40 p-4">
                <h2 class="text-base font-semibold text-text-primary sm:text-lg">{{ __('Stage B — Optional additional data') }}</h2>
                <x-context-help :title="__('Optional product content help')" :label="__('Open contextual help')"><p>{{ __('Descriptions are optional and may be entered now. Images, Product Filters, dimensions, online visibility, and battery data become available after the required Product Card is saved.') }}</p></x-context-help>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <flux:textarea wire:model="productForm.description_ar" :label="__('Arabic description')" rows="3" dir="rtl" />
                    <flux:textarea wire:model="productForm.description_en" :label="__('English description')" rows="3" dir="ltr" />
                </div>
            </div>
        @endif
        <div class="order-7 sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-xl border border-border bg-surface/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:justify-end">
            <flux:button href="{{ $isEditing ? route('catalog.products.show', ['product' => $product]) : route('catalog.products') }}" variant="subtle" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:text wire:dirty class="self-center text-xs text-text-muted">{{ __('Unsaved changes') }}</flux:text>
            <x-actions.loading-button type="button" action="saveDraft" :label="__('Save draft')" />
            <x-actions.loading-button type="submit" action="save" :label="__('Save')" />
        </div>
    </form>

    @if ($isEditing && $product)
        @can('pricing_lists.view')<livewire:pricing::product-matrix :product="$product" :key="'product-pricing-'.$product->id" />@endcan
    @endif

    @if ($isEditing && $product?->has_variations && ! $product->isVariant())
        <livewire:catalog::product-variations :product="$product" :key="'product-variations-'.$product->id" />
    @endif
</x-app.page>
