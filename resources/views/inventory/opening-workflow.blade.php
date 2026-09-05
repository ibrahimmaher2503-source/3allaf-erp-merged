<?php

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\SaveOpeningInventoryDraftAction;
use App\Modules\Inventory\Actions\SaveProductLocationAssignmentsAction;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Queries\SearchAssignableProducts;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $documentId = null;
    public string $companyId = '';
    public string $branchId = '';
    public array $locationIds = [];
    public string $primaryStoreId = '';
    public string $copySourceStoreId = '';
    public string $search = '';
    public string $categoryId = '';
    public string $brandId = '';
    public string $productType = '';
    public string $supplierId = '';
    public string $status = 'active';
    public bool $notAssigned = false;
    public array $selectedIds = [];
    public bool $allFiltered = false;
    public array $excludedIds = [];
    public array $quantities = [];
    public string $pasteRows = '';
    public string $notes = '';

    public function mount(?int $documentId = null): void
    {
        Gate::authorize($documentId === null ? 'inventory_stock_card.create' : 'inventory_stock_card.edit');
        $actor = $this->actor();
        $company = app(AuthorizedCompanyContext::class)->resolve($actor);
        $this->companyId = (string) $company->id;
        $this->documentId = $documentId;

        if ($documentId !== null) {
            $document = OpeningInventoryDocument::query()->where('company_id', $company->id)->findOrFail($documentId);
            abort_unless($document->status === 'draft', 403);
            abort_unless($document->lines()->count() <= 500 && $document->lines()->distinct()->count('store_id') <= 1, 403);
            $document->load('lines');
            $this->notes = (string) $document->notes;
            $this->selectedIds = $document->lines->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $first = $document->lines->first();
            if ($first !== null) {
                $store = Store::query()->visibleTo($actor)->findOrFail($first->store_id);
                $this->branchId = (string) $store->branch_id;
                $this->primaryStoreId = (string) $store->id;
                $this->locationIds = [(string) $store->id];
            }
            foreach ($document->lines as $line) {
                $this->quantities[(string) $line->product_id] = (string) $line->quantity;
            }
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'brandId', 'productType', 'supplierId', 'status', 'notAssigned', 'primaryStoreId'], true)) {
            $this->resetPage('products_page');
            $this->allFiltered = false;
            $this->excludedIds = [];
        }
        if ($property === 'companyId') {
            $this->branchId = '';
            $this->locationIds = [];
            $this->primaryStoreId = '';
        }
        if ($property === 'branchId') {
            $this->locationIds = [];
            $this->primaryStoreId = '';
        }
    }

    public function toggleProduct(int $productId): void
    {
        if ($this->allFiltered) {
            $this->excludedIds = in_array($productId, $this->excludedIds, true)
                ? array_values(array_diff($this->excludedIds, [$productId]))
                : [...$this->excludedIds, $productId];
            return;
        }

        $this->selectedIds = in_array($productId, $this->selectedIds, true)
            ? array_values(array_diff($this->selectedIds, [$productId]))
            : [...$this->selectedIds, $productId];
    }

    public function selectAllFiltered(): void
    {
        $this->allFiltered = true;
        $this->selectedIds = [];
        $this->excludedIds = [];
    }

    public function clearSelection(): void
    {
        $this->allFiltered = false;
        $this->selectedIds = [];
        $this->excludedIds = [];
    }

    public function assign(): void
    {
        $this->validate([
            'companyId' => ['required', 'integer'],
            'branchId' => ['required', 'integer'],
            'locationIds' => ['required', 'array', 'min:1'],
            'locationIds.*' => ['integer'],
        ]);

        $count = app(SaveProductLocationAssignmentsAction::class)->execute(
            (int) $this->companyId,
            (int) $this->branchId,
            array_map('intval', $this->locationIds),
            $this->allFiltered
                ? ['mode' => 'filtered', 'filters' => $this->filters(), 'excluded_ids' => $this->excludedIds]
                : ['mode' => 'explicit', 'ids' => $this->selectedIds],
        );
        session()->flash('opening_workflow_status', __(':count product/location assignments saved.', ['count' => $count]));
    }

    public function copyAssortment(): void
    {
        $this->validate([
            'companyId' => ['required', 'integer'],
            'branchId' => ['required', 'integer'],
            'locationIds' => ['required', 'array', 'min:1'],
            'locationIds.*' => ['integer'],
            'copySourceStoreId' => ['required', 'integer'],
        ]);

        $count = app(SaveProductLocationAssignmentsAction::class)->execute(
            (int) $this->companyId,
            (int) $this->branchId,
            array_map('intval', $this->locationIds),
            ['mode' => 'explicit', 'ids' => []],
            (int) $this->copySourceStoreId,
        );
        session()->flash('opening_workflow_status', __(':count product/location assignments copied.', ['count' => $count]));
    }

    public function addSelectedToOpening(): void
    {
        if ($this->allFiltered) {
            $this->addError('selectedIds', __('For manual quantity entry, select up to 500 individual products. Use XLSX for the full filtered result.'));
            return;
        }
        if ($this->selectedIds === [] || count($this->selectedIds) > 500) {
            $this->addError('selectedIds', __('Select between 1 and 500 products for manual quantity entry.'));
            return;
        }
        foreach ($this->selectedIds as $id) {
            $this->quantities[(string) $id] ??= '';
        }
    }

    public function parsePastedRows(): void
    {
        $lines = preg_split('/\R/u', trim($this->pasteRows)) ?: [];
        if (count($lines) > 500) {
            $this->addError('pasteRows', __('Paste at most 500 product codes or barcodes for manual entry.'));
            return;
        }

        $wanted = [];
        foreach ($lines as $line) {
            $parts = preg_split('/[\t,; ]+/', trim($line), 2) ?: [];
            if (($parts[0] ?? '') !== '') {
                $wanted[$parts[0]] = trim($parts[1] ?? '');
            }
        }
        $products = Product::query()->sellable()->where('product_type', '!=', 'service')
            ->where(fn ($query) => $query->whereIn('item_code', array_keys($wanted))->orWhereHas('barcodes', fn ($barcodes) => $barcodes->whereIn('barcode', array_keys($wanted))->where('status', 'active')))
            ->with(['barcodes' => fn ($query) => $query->where('status', 'active')])->get();

        $matched = [];
        foreach ($products as $product) {
            $identifier = array_key_exists($product->item_code, $wanted)
                ? $product->item_code
                : $product->barcodes->pluck('barcode')->first(fn ($barcode) => array_key_exists($barcode, $wanted));
            if ($identifier === null) continue;
            $matched[] = (int) $product->id;
            $this->quantities[(string) $product->id] = $wanted[$identifier];
        }
        $this->selectedIds = array_values(array_unique([...$this->selectedIds, ...$matched]));
        if (count($matched) !== count($wanted)) {
            $this->addError('pasteRows', __('Some pasted codes or barcodes were not eligible products.'));
        }
    }

    public function saveDraft(): void
    {
        $this->validate([
            'companyId' => ['required', 'integer'],
            'primaryStoreId' => ['required', 'integer'],
            'quantities' => ['required', 'array', 'min:1', 'max:500'],
            'quantities.*' => ['required', 'regex:/^\d+$/', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $rows = collect($this->quantities)->map(fn ($quantity, $productId): array => [
            'product_id' => (int) $productId,
            'store_id' => (int) $this->primaryStoreId,
            'quantity' => $quantity,
        ])->values()->all();

        $document = app(SaveOpeningInventoryDraftAction::class)->execute($rows, $this->documentId, $this->notes ?: null, (int) $this->companyId);
        $this->redirectRoute('inventory.opening.show', ['document' => $document->id], navigate: true);
    }

    public function removeQuantity(int $productId): void
    {
        unset($this->quantities[(string) $productId]);
    }

    public function render()
    {
        $actor = $this->actor();
        $companies = app(AuthorizedCompanyContext::class)->companies($actor);
        $branches = $this->companyId === '' ? collect() : Branch::query()->visibleTo($actor)->where('company_id', (int) $this->companyId)->where('status', 'active')->orderBy('code')->get();
        $locations = $this->branchId === '' ? collect() : Store::query()->visibleTo($actor)->where('company_id', (int) $this->companyId)->where('branch_id', (int) $this->branchId)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->orderBy('code')->get();
        $productQuery = app(SearchAssignableProducts::class)->query($this->filters());
        $filteredCount = (clone $productQuery)->count();
        $products = $productQuery->paginate(25, pageName: 'products_page');
        $quantityProducts = $this->quantities === [] ? collect() : Product::query()->whereIn('id', array_map('intval', array_keys($this->quantities)))->orderBy('item_code')->get();
        $categories = Category::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
        $brands = Brand::query()->active()->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);
        $suppliers = Supplier::query()->active()->when($this->companyId !== '', fn ($query) => $query->where(fn ($scope) => $scope->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn ($groups) => $groups->where('company_id', (int) $this->companyId))))->orderBy('code')->get(['id', 'code', 'name_ar', 'name_en']);

        return view('inventory.opening-workflow', compact('companies', 'branches', 'locations', 'products', 'filteredCount', 'quantityProducts', 'categories', 'brands', 'suppliers'));
    }

    private function filters(): array
    {
        return [
            'q' => Str::limit(trim($this->search), 100, ''),
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'product_type' => $this->productType,
            'supplier_id' => $this->supplierId,
            'status' => $this->status,
            'not_assigned' => $this->notAssigned,
            'store_id' => $this->primaryStoreId,
        ];
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        return $actor;
    }
}; ?>

<div class="space-y-4">
    @if(session('opening_workflow_status'))<flux:callout variant="success" icon="check-circle">{{ session('opening_workflow_status') }}</flux:callout>@endif

    <flux:card class="space-y-4">
        <div><flux:heading>{{ __('Product/location assignment') }}</flux:heading><p class="text-sm text-text-muted">{{ __('Choose company, branch, and inventory locations once; assignments never create inventory quantities.') }}</p></div>
        <div class="grid gap-3 md:grid-cols-3">
            <flux:select wire:model.live="companyId" :label="__('Company')" required>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->code }} · {{ app()->isLocale('ar') ? $company->name_ar : $company->name_en }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="branchId" :label="__('Branch')" required><option value="">{{ __('Select branch') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ app()->isLocale('ar') ? $branch->name_ar : $branch->name_en }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="primaryStoreId" :label="__('Opening inventory location')" required><option value="">{{ __('Select location') }}</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ app()->isLocale('ar') ? $location->name_ar : $location->name_en }}</option>@endforeach</flux:select>
        </div>
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2"><p class="mb-2 text-sm font-medium">{{ __('Bulk assignment locations') }}</p><div class="flex flex-wrap gap-3">@foreach($locations as $location)<flux:checkbox wire:model="locationIds" value="{{ $location->id }}" :label="$location->code" />@endforeach</div></div>
            <div><flux:select wire:model="copySourceStoreId" :label="__('Copy assortment from')"><option value="">{{ __('Select source location') }}</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }}</option>@endforeach</flux:select><flux:button class="mt-2" size="sm" wire:click="copyAssortment">{{ __('Copy assortment') }}</flux:button></div>
        </div>
    </flux:card>

    <flux:card class="space-y-4">
        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <flux:input wire:model.live.debounce.350ms="search" :label="__('Search / scan')" :placeholder="__('Name, item code, SKU, or barcode')" />
            <flux:select wire:model.live="categoryId" :label="__('Category')"><option value="">{{ __('All') }}</option>@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->code }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="brandId" :label="__('Brand')"><option value="">{{ __('All') }}</option>@foreach($brands as $item)<option value="{{ $item->id }}">{{ $item->code }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="productType" :label="__('Product type')"><option value="">{{ __('All') }}</option>@foreach(['standard','composite','digital'] as $type)<option value="{{ $type }}">{{ __(Str::headline($type)) }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="supplierId" :label="__('Supplier')"><option value="">{{ __('All') }}</option>@foreach($suppliers as $item)<option value="{{ $item->id }}">{{ $item->code }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:checkbox wire:model.live="notAssigned" :label="__('Not assigned to opening location')" />
            <div class="flex flex-wrap gap-2"><flux:button size="sm" wire:click="selectAllFiltered">{{ __('Select all :count filtered', ['count' => number_format($filteredCount)]) }}</flux:button><flux:button size="sm" variant="subtle" wire:click="clearSelection">{{ __('Clear selection') }}</flux:button></div>
        </div>
        @if($allFiltered)<flux:callout icon="check-circle">{{ __('All :count filtered products are selected except :excluded exclusions.', ['count' => number_format($filteredCount), 'excluded' => count($excludedIds)]) }}</flux:callout>@endif
        <div class="overflow-x-auto"><table class="app-data-table min-w-[52rem]"><thead><tr><th>{{ __('Select') }}</th><th>{{ __('Item code') }}</th><th>{{ __('Product') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Category') }}</th><th>{{ __('Brand') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
            @forelse($products as $product)<tr wire:key="assign-product-{{ $product->id }}"><td><input type="checkbox" aria-label="{{ __('Select') }}" @checked($allFiltered ? !in_array($product->id,$excludedIds,true) : in_array($product->id,$selectedIds,true)) wire:click="toggleProduct({{ $product->id }})"></td><td class="font-mono">{{ $product->item_code }}</td><td>{{ app()->isLocale('ar') ? $product->name_ar : ($product->name_en ?: $product->name_ar) }}</td><td class="font-mono">{{ $product->barcodes->first()?->barcode ?: '—' }}</td><td>{{ $product->category?->code ?: '—' }}</td><td>{{ $product->brand?->code ?: '—' }}</td><td><x-status.badge :status="$product->status" /></td></tr>@empty<tr><td colspan="7"><x-state.empty :title="__('No matching products')" :description="__('Change the search or filters and try again.')" /></td></tr>@endforelse
        </tbody></table></div>
        {{ $products->links() }}
        @error('selectedIds')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="flex flex-wrap justify-end gap-2"><flux:button wire:click="assign">{{ __('Assign selected products') }}</flux:button><flux:button variant="primary" wire:click="addSelectedToOpening">{{ __('Add selected to quantity grid') }}</flux:button></div>
    </flux:card>

    <flux:card class="space-y-4">
        <div><flux:heading>{{ __('Manual opening quantities') }}</flux:heading><p class="text-sm text-text-muted">{{ __('Paste one code or barcode per line, optionally followed by a quantity. Manual entry is limited to 500 rows; use the current-data XLSX workbook for larger work.') }}</p></div>
        <div class="grid gap-3 md:grid-cols-[1fr_auto]"><flux:textarea wire:model="pasteRows" :label="__('Paste codes / barcodes and quantities')" rows="4" /><flux:button class="self-end" wire:click="parsePastedRows">{{ __('Add pasted rows') }}</flux:button></div>
        @error('pasteRows')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @if($quantityProducts->isNotEmpty())<div class="overflow-x-auto"><table class="app-data-table min-w-[44rem]"><thead><tr><th>{{ __('Item code') }}</th><th>{{ __('Product') }}</th><th>{{ __('Opening quantity') }}</th><th>{{ __('Product Card cost') }}</th><th></th></tr></thead><tbody>@foreach($quantityProducts as $product)<tr wire:key="opening-quantity-{{ $product->id }}"><td class="font-mono">{{ $product->item_code }}</td><td>{{ app()->isLocale('ar') ? $product->name_ar : ($product->name_en ?: $product->name_ar) }}</td><td><flux:input wire:model="quantities.{{ $product->id }}" type="number" min="1" step="1" /></td><td>{{ __('Automatic') }}</td><td><flux:button size="xs" variant="danger" wire:click="removeQuantity({{ $product->id }})">{{ __('Remove') }}</flux:button></td></tr>@endforeach</tbody></table></div>@endif
        <flux:textarea wire:model="notes" :label="__('Notes')" />
        <div class="flex justify-end"><flux:button variant="primary" wire:click="saveDraft">{{ __('Save opening inventory draft') }}</flux:button></div>
    </flux:card>
</div>
