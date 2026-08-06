<?php

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Models\Category;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Category Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public array $categoryForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'parent_id' => '',
        'status' => 'active',
        'sort_order' => 0,
    ];

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

    public function openCreateCategoryModal(): void
    {
        Gate::authorize('products_categories_brands.create');
        $this->editingCategoryId = null;
        $this->categoryForm = ['code' => '', 'name_ar' => '', 'name_en' => '', 'parent_id' => '', 'status' => 'active', 'sort_order' => 0];
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function openEditCategoryModal(int $id): void
    {
        Gate::authorize('products_categories_brands.edit');
        $category = Category::query()->findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryForm = [
            'code' => $category->code,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'parent_id' => (string) ($category->parent_id ?? ''),
            'status' => $category->status,
            'sort_order' => $category->sort_order,
        ];
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function saveCategory(SaveCategoryAction $action): void
    {
        Gate::authorize($this->editingCategoryId ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        $validated = $this->validate([
            'categoryForm.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', Rule::unique('categories', 'code')->ignore($this->editingCategoryId)],
            'categoryForm.name_ar' => ['required', 'string', 'max:255'],
            'categoryForm.name_en' => ['required', 'string', 'max:255'],
            'categoryForm.parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categoryForm.status' => ['required', 'in:active,inactive'],
            'categoryForm.sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ])['categoryForm'];

        try {
            $action->execute($validated, $this->editingCategoryId);
            Flux::toast(variant: 'success', text: $this->editingCategoryId ? __('Category updated successfully.') : __('Category created successfully.'));
            $this->showCategoryModal = false;
        } catch (Throwable $exception) {
            $this->addError('categoryForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function toggleCategoryStatus(int $id, SaveCategoryAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');
        $category = Category::query()->findOrFail($id);

        try {
            $action->execute($category->only(['code', 'name_ar', 'name_en', 'parent_id', 'sort_order']) + ['status' => $category->status === 'active' ? 'inactive' : 'active'], $id);
            Flux::toast(variant: 'success', text: __('Category status updated successfully.'));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function bulkToggleCategoryStatus(SaveCategoryAction $action): void
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $category = Category::query()->findOrFail($id);
                $action->execute($category->only(['code', 'name_ar', 'name_en', 'parent_id', 'sort_order']) + ['status' => $category->status === 'active' ? 'inactive' : 'active'], $id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Category status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function render()
    {
        $query = Category::query()->with('parent');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope->where('code', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('catalog.categories', [
            'categories' => $query->orderByRaw('COALESCE(parent_id, 0)')->orderBy('sort_order')->orderBy('code')->paginate(15),
            'parentOptions' => Category::query()->where('status', 'active')->when($this->editingCategoryId, fn ($q) => $q->whereKeyNot($this->editingCategoryId))->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Category Masters')"
    :description="__('Maintain a bounded, ordered category hierarchy with server-side cycle and dependency guards.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="categories-header"
>
    <x-slot:actions>
        @can('products_categories_brands.create')
            <flux:button icon="plus" variant="primary" wire:click="openCreateCategoryModal" data-guide="categories-add-action">{{ __('Add category') }}</flux:button>
        @endcan
    </x-slot:actions>

    <flux:callout class="catalog-scope-note" variant="info" icon="squares-2x2" title="{{ __('Hierarchy rules') }}">
        {{ __('Root and child categories are supported. Self-parenting, descendant cycles, inactive parents for active children, and deactivation with active product or child dependencies are blocked on the server.') }}
    </flux:callout>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Category action could not be completed') }}">
            <ul class="list-disc space-y-1 ps-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <div class="catalog-filter-card rounded-xl p-4 sm:p-5" data-guide="categories-filters">
        <div class="catalog-filter-heading mb-4">
            <div>
                <flux:heading size="sm">{{ __('Search categories') }}</flux:heading>
                <flux:text class="mt-1 text-xs text-text-muted">{{ __('Browse root categories and their child relationships.') }}</flux:text>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search categories')" :placeholder="__('Code or Arabic/English name...')" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div wire:loading.flex role="status" aria-live="polite" class="catalog-loading"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Loading hierarchy...') }}</div>

    @if ($categories->isEmpty())
        <flux:card class="space-y-3 p-10 text-center" data-guide="categories-empty">
            <flux:icon name="squares-2x2" class="mx-auto size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('No categories found') }}</flux:heading>
            <flux:text class="mx-auto max-w-lg text-zinc-500">{{ __('Create a root category, then add child categories from the same form.') }}</flux:text>
        </flux:card>
    @else
        <div class="catalog-table-frame" data-guide="categories-table">
            <x-tables.bulk-actions
                :page-ids="$categories->pluck('id')->all()"
                :selected-ids="$selectedIds"
                :selected-count="count($selectedIds)"
                :page-count="$categories->count()"
            >
                <x-slot:actions>
                    @can('products_categories_brands.edit')
                        <flux:button type="button" size="sm" variant="subtle" wire:click="bulkToggleCategoryStatus" wire:confirm="{{ __('Toggle status for the selected categories?') }}">{{ __('Toggle status') }}</flux:button>
                    @endcan
                </x-slot:actions>
            </x-tables.bulk-actions>
            <flux:table aria-label="{{ __('Category hierarchy') }}">
                <flux:table.columns>
                    <flux:table.column><span class="sr-only">{{ __('Select') }}</span></flux:table.column>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Category name') }}</flux:table.column>
                    <flux:table.column>{{ __('Parent / level') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($categories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell><input type="checkbox" value="{{ $category->id }}" wire:model.live="selectedIds" aria-label="{{ __('Select category :code', ['code' => $category->code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" /></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap"><span class="catalog-code-chip">{{ $category->code }}</span></flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-start gap-2 {{ $category->parent ? 'ps-4' : '' }}">
                                    <span class="catalog-tree-marker {{ $category->parent ? 'is-child' : '' }}" aria-hidden="true"></span>
                                    <div class="min-w-0"><div class="font-medium text-text-primary">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</div><div class="catalog-secondary-line">{{ app()->getLocale() === 'ar' ? $category->name_en : $category->name_ar }}</div></div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs">@if ($category->parent)<span class="text-text-muted">{{ __('Child of') }}</span> <span class="font-mono">{{ $category->parent->code }}</span>@else<flux:badge size="sm" color="zinc">{{ __('Root') }}</flux:badge>@endif</flux:table.cell>
                            <flux:table.cell><span class="font-mono text-xs text-text-muted">{{ $category->sort_order }}</span></flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$category->status === 'active' ? 'emerald' : 'zinc'">{{ __($category->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">@can('products_categories_brands.edit')<div class="catalog-actions"><flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditCategoryModal({{ $category->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" /><flux:button size="xs" variant="subtle" :icon="$category->status === 'active' ? 'pause' : 'play'" wire:click="toggleCategoryStatus({{ $category->id }})" title="{{ $category->status === 'active' ? __('Deactivate') : __('Activate') }}" aria-label="{{ $category->status === 'active' ? __('Deactivate') : __('Activate') }}" /></div>@else<span class="text-xs font-medium text-text-muted">{{ __('View only') }}</span>@endcan</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        <div data-guide="categories-pagination">{{ $categories->links() }}</div>
    @endif

    <flux:modal wire:model="showCategoryModal" class="max-w-xl">
        <div class="catalog-modal-section space-y-1"><flux:heading size="lg">{{ $editingCategoryId ? __('Edit category') : __('Create category') }}</flux:heading><flux:subheading>{{ __('Choose no parent for a root category. Server validation rejects self-parenting and descendant cycles.') }}</flux:subheading></div>
        <form wire:submit="saveCategory" novalidate class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="categoryForm.code" :label="__('Category code')" required /><flux:input wire:model="categoryForm.sort_order" type="number" min="0" :label="__('Display order')" required /></div>
            <div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="categoryForm.name_ar" :label="__('Arabic name')" required /><flux:input wire:model="categoryForm.name_en" :label="__('English name')" required /></div>
            <flux:select wire:model="categoryForm.parent_id" :label="__('Parent category')"><flux:select.option value="">{{ __('No parent (root)') }}</flux:select.option>@foreach ($parentOptions as $parent)<flux:select.option :value="$parent->id">{{ $parent->code }} · {{ app()->getLocale() === 'ar' ? $parent->name_ar : $parent->name_en }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model="categoryForm.status" :label="__('Status')" required><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option></flux:select>
            <div class="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end"><flux:button type="button" variant="subtle" wire:click="$set('showCategoryModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCategory">{{ __('Save category') }}</flux:button></div>
        </form>
    </flux:modal>
</x-app.page>
