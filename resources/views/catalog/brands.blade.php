<?php

use App\Modules\Catalog\Actions\SaveBrandAction;
use App\Modules\Catalog\Models\Brand;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Brand Masters')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $showBrandModal = false;
    public ?int $editingBrandId = null;
    public array $brandForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active'];

    public function mount(): void
    {
        Gate::authorize('products_categories_brands.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateBrandModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingBrandId = null;
        $this->brandForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'status' => 'active'];
        $this->resetValidation();
        $this->showBrandModal = true;
    }

    public function openEditBrandModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $brand = Brand::query()->findOrFail($id);
        $this->editingBrandId = $brand->id;
        $this->brandForm = ['code' => $brand->code, 'name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'status' => $brand->status];
        $this->resetValidation();
        $this->showBrandModal = true;
    }

    public function saveBrand(SaveBrandAction $action): void
    {
        Gate::authorize($this->editingBrandId ? 'products_categories_brands.edit' : 'products_categories_brands.create');
        $validated = $this->validate([
            'brandForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('brands', 'code')->ignore($this->editingBrandId)],
            'brandForm.name_ar' => ['required', 'string', 'max:255'],
            'brandForm.name_en' => ['required', 'string', 'max:255'],
            'brandForm.status' => ['required', 'in:active,inactive'],
        ])['brandForm'];

        try {
            $action->execute($validated, $this->editingBrandId);
            Flux::toast(variant: 'success', text: $this->editingBrandId ? __('Brand updated successfully.') : __('Brand created successfully.'));
            $this->showBrandModal = false;
        } catch (\Throwable $exception) {
            $this->addError('brandForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function toggleBrandStatus(int $id, SaveBrandAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        $brand = Brand::query()->findOrFail($id);

        try {
            $action->execute($brand->only(['code', 'name_ar', 'name_en']) + ['status' => $brand->status === 'active' ? 'inactive' : 'active'], $id);
            Flux::toast(variant: 'success', text: __('Brand status updated successfully.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function render()
    {
        $query = Brand::query()->withCount('products');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope->where('code', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('catalog.brands', ['brands' => $query->orderBy('code')->paginate(15)]);
    }
}; ?>

<section class="catalog-screen w-full">
    <x-page-header :title="__('Brand Masters')" :description="__('Maintain bilingual brand identity with dependency-aware status changes.')" data-guide="brands-header">
        <x-slot:actions>@can('products_categories_brands.create')<flux:button icon="plus" variant="primary" wire:click="openCreateBrandModal" data-guide="brands-add-action">{{ __('Add brand') }}</flux:button>@endcan</x-slot:actions>
    </x-page-header>

    <flux:callout class="catalog-scope-note" variant="info" icon="tag" title="{{ __('Brand foundation') }}">{{ __('Brand master records are global catalog identity. Full supplier, terms, product media, and product-type behavior remain outside TSK-010.') }}</flux:callout>
    @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Brand action could not be completed') }}"><ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>@endif

    <div class="catalog-filter-card rounded-xl p-4 sm:p-5" data-guide="brands-filters"><div class="catalog-filter-heading mb-4"><div><flux:heading size="sm">{{ __('Search brands') }}</flux:heading><flux:text class="mt-1 text-xs text-text-muted">{{ __('Find a brand by its code or bilingual name.') }}</flux:text></div></div><div class="grid grid-cols-1 gap-3 md:grid-cols-2"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search brands')" :placeholder="__('Code or Arabic/English name...')" /><flux:select wire:model.live="statusFilter" :label="__('Status')"><flux:select.option value="all">{{ __('All statuses') }}</flux:select.option><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select></div></div>
    <div wire:loading.flex role="status" aria-live="polite" class="catalog-loading"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Loading brands...') }}</div>

    @if ($brands->isEmpty())
        <flux:card class="space-y-3 p-10 text-center" data-guide="brands-empty"><flux:icon name="tag" class="mx-auto size-12 text-zinc-400" /><flux:heading size="lg">{{ __('No brands found') }}</flux:heading><flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('Create a brand before assigning it to a product.') }}</flux:text></flux:card>
    @else
        <div class="catalog-table-frame" data-guide="brands-table"><flux:table aria-label="{{ __('Brand masters') }}"><flux:table.columns><flux:table.column>{{ __('Code') }}</flux:table.column><flux:table.column>{{ __('Brand name') }}</flux:table.column><flux:table.column>{{ __('Products') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column>{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>@foreach ($brands as $brand)<flux:table.row :key="$brand->id"><flux:table.cell><span class="catalog-code-chip">{{ $brand->code }}</span></flux:table.cell><flux:table.cell><div class="font-medium text-text-primary">{{ app()->getLocale() === 'ar' ? $brand->name_ar : $brand->name_en }}</div><div class="catalog-secondary-line">{{ app()->getLocale() === 'ar' ? $brand->name_en : $brand->name_ar }}</div></flux:table.cell><flux:table.cell><span class="font-mono text-xs text-text-muted">{{ number_format($brand->products_count) }}</span></flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$brand->status === 'active' ? 'emerald' : 'zinc'">{{ __($brand->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell><flux:table.cell class="whitespace-nowrap">@can('products_categories_brands.edit')<div class="catalog-actions"><flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditBrandModal({{ $brand->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" /><flux:button size="xs" variant="subtle" :icon="$brand->status === 'active' ? 'pause' : 'play'" wire:click="toggleBrandStatus({{ $brand->id }})" title="{{ $brand->status === 'active' ? __('Deactivate') : __('Activate') }}" aria-label="{{ $brand->status === 'active' ? __('Deactivate') : __('Activate') }}" /></div>@else<span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>@endcan</flux:table.cell></flux:table.row>@endforeach</flux:table.rows></flux:table></div>
        <div data-guide="brands-pagination">{{ $brands->links() }}</div>
    @endif

    <flux:modal wire:model="showBrandModal" class="max-w-xl"><div class="catalog-modal-section space-y-1"><flux:heading size="lg">{{ $editingBrandId ? __('Edit brand') : __('Create brand') }}</flux:heading><flux:subheading>{{ __('Both Arabic and English names are required for catalog identity.') }}</flux:subheading></div><form wire:submit="saveBrand" novalidate class="space-y-4"><flux:input wire:model="brandForm.code" :label="__('Brand code')" required /><div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="brandForm.name_ar" :label="__('Arabic name')" required /><flux:input wire:model="brandForm.name_en" :label="__('English name')" required /></div><flux:select wire:model="brandForm.status" :label="__('Status')" required><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select><div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end"><flux:button type="button" variant="subtle" wire:click="$set('showBrandModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBrand">{{ __('Save brand') }}</flux:button></div></form></flux:modal>
</section>
