<?php

use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Actions\ToggleSupplierStatusAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Supplier Masters')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';

    public bool $showSupplierModal = false;
    public ?int $editingSupplierId = null;
    public array $supplierForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'contact_name' => '',
        'email' => '',
        'phone' => '',
        'tax_number' => '',
        'payment_terms' => '',
        'address' => '',
        'status' => 'active',
        'lock_version' => 0,
    ];

    public bool $showDetailModal = false;
    public ?int $viewingSupplierId = null;
    public string $detailTab = 'profile';

    public bool $showLinkProductModal = false;
    public array $productLinkForm = [
        'product_id' => '',
        'supplier_item_code' => '',
        'is_preferred' => false,
        'notes' => '',
    ];

    public function mount(): void
    {
        Gate::authorize('suppliers.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateSupplierModal(): void
    {
        Gate::authorize('suppliers.create');
        $this->editingSupplierId = null;
        $this->supplierForm = [
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'contact_name' => '',
            'email' => '',
            'phone' => '',
            'tax_number' => '',
            'payment_terms' => '',
            'address' => '',
            'status' => 'active',
            'lock_version' => 0,
        ];
        $this->resetValidation();
        $this->showSupplierModal = true;
    }

    public function openEditSupplierModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $supplier = Supplier::query()->findOrFail($id);
        $this->editingSupplierId = $supplier->id;
        $this->supplierForm = [
            'code' => $supplier->code,
            'name_ar' => $supplier->name_ar,
            'name_en' => $supplier->name_en,
            'contact_name' => $supplier->contact_name ?? '',
            'email' => $supplier->email ?? '',
            'phone' => $supplier->phone ?? '',
            'tax_number' => $supplier->tax_number ?? '',
            'payment_terms' => $supplier->payment_terms ?? '',
            'address' => $supplier->address ?? '',
            'status' => $supplier->status,
            'lock_version' => $supplier->lock_version,
        ];
        $this->resetValidation();
        $this->showSupplierModal = true;
    }

    public function saveSupplier(SaveSupplierAction $action): void
    {
        Gate::authorize($this->editingSupplierId ? 'suppliers.edit' : 'suppliers.create');
        $validated = $this->validate([
            'supplierForm.code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
                Rule::unique('suppliers', 'code')->ignore($this->editingSupplierId),
            ],
            'supplierForm.name_ar' => ['required', 'string', 'max:255'],
            'supplierForm.name_en' => ['required', 'string', 'max:255'],
            'supplierForm.contact_name' => ['nullable', 'string', 'max:255'],
            'supplierForm.email' => ['nullable', 'email', 'max:255'],
            'supplierForm.phone' => ['nullable', 'string', 'max:50'],
            'supplierForm.tax_number' => ['nullable', 'string', 'max:50'],
            'supplierForm.payment_terms' => ['nullable', 'string', 'max:1000'],
            'supplierForm.address' => ['nullable', 'string', 'max:1000'],
            'supplierForm.status' => ['required', 'in:active,inactive'],
        ])['supplierForm'];

        try {
            $action->execute(
                $validated,
                $this->editingSupplierId,
                $this->editingSupplierId ? (int) $this->supplierForm['lock_version'] : null
            );
            Flux::toast(
                variant: 'success',
                text: $this->editingSupplierId ? __('Supplier master updated successfully.') : __('Supplier master created successfully.')
            );
            $this->showSupplierModal = false;
        } catch (\Throwable $exception) {
            $this->addError('supplierForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function toggleSupplierStatus(int $id, ToggleSupplierStatusAction $action): void
    {
        Gate::authorize('suppliers.edit');

        try {
            $action->execute($id);
            Flux::toast(variant: 'success', text: __('Supplier status updated successfully.'));
        } catch (\Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function openSupplierDetailModal(int $id): void
    {
        Gate::authorize('suppliers.view');
        $this->viewingSupplierId = $id;
        $this->detailTab = 'profile';
        $this->showDetailModal = true;
    }

    public function openLinkProductModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->productLinkForm = [
            'product_id' => '',
            'supplier_item_code' => '',
            'is_preferred' => false,
            'notes' => '',
        ];
        $this->resetValidation();
        $this->showLinkProductModal = true;
    }

    public function saveProductLink(SaveProductSupplierAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'productLinkForm.product_id' => ['required', 'integer', 'exists:products,id'],
            'productLinkForm.supplier_item_code' => ['nullable', 'string', 'max:100'],
            'productLinkForm.is_preferred' => ['boolean'],
            'productLinkForm.notes' => ['nullable', 'string', 'max:1000'],
        ])['productLinkForm'];

        try {
            $action->execute([
                ...$validated,
                'supplier_id' => $this->viewingSupplierId,
            ]);
            Flux::toast(variant: 'success', text: __('Product linked to supplier successfully.'));
            $this->showLinkProductModal = false;
        } catch (\Throwable $exception) {
            $this->addError('productLinkForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function render()
    {
        $query = Supplier::query()->withCount('productSuppliers');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope->where('code', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('name_en', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('tax_number', 'like', $like)
            );
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $suppliers = $query->orderBy('code')->paginate(15);

        $viewingSupplier = $this->viewingSupplierId
            ? Supplier::query()->with(['productSuppliers.product'])->find($this->viewingSupplierId)
            : null;

        $availableProducts = Product::query()->where('status', 'active')->orderBy('item_code')->get(['id', 'item_code', 'name_ar', 'name_en']);

        return view('catalog.suppliers', [
            'suppliers' => $suppliers,
            'viewingSupplier' => $viewingSupplier,
            'availableProducts' => $availableProducts,
            'canCreate' => Gate::allows('suppliers.create'),
            'canEdit' => Gate::allows('suppliers.edit'),
        ]);
    }
}; ?>

<section class="catalog-screen w-full">
    <x-page-header
        :title="__('Supplier Masters & Product-Supplier History')"
        :description="__('Maintain supplier master contacts, commercial payment terms, preferred product relations, and purchase history.')"
        data-guide="suppliers-header"
    >
        <x-slot:actions>
            @if ($canCreate)
                <flux:button
                    icon="plus"
                    variant="primary"
                    wire:click="openCreateSupplierModal"
                    data-guide="suppliers-add-action"
                >
                    {{ __('Add supplier') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-5">
        <flux:card class="space-y-4 p-5 sm:p-6" data-guide="suppliers-filters">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Search by code, name, contact, phone, or tax number...')"
                    clearable
                />
                <flux:select wire:model.live="statusFilter" :label="__('Status filter')">
                    <option value="all">{{ __('All status') }}</option>
                    <option value="active">{{ __('Active only') }}</option>
                    <option value="inactive">{{ __('Inactive only') }}</option>
                </flux:select>
            </div>
        </flux:card>

        <flux:card class="overflow-hidden p-0" data-guide="suppliers-table">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Supplier code') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Bilingual name') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Contact info') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Tax number') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Payment terms') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Products') }}</th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-text-muted uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-white dark:bg-zinc-900">
                        @forelse ($suppliers as $supplier)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-3 text-sm font-medium whitespace-nowrap">
                                    <span class="catalog-code-chip">{{ $supplier->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ app()->getLocale() === 'ar' ? $supplier->name_ar : $supplier->name_en }}
                                    </div>
                                    <div class="text-xs text-text-muted">
                                        {{ app()->getLocale() === 'ar' ? $supplier->name_en : $supplier->name_ar }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($supplier->contact_name)
                                        <div class="font-medium text-xs">{{ $supplier->contact_name }}</div>
                                    @endif
                                    @if ($supplier->phone)
                                        <div class="text-xs text-text-muted" dir="ltr">{{ $supplier->phone }}</div>
                                    @endif
                                    @if ($supplier->email)
                                        <div class="text-xs text-text-muted truncate max-w-48">{{ $supplier->email }}</div>
                                    @endif
                                    @if (! $supplier->contact_name && ! $supplier->phone && ! $supplier->email)
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap text-text-muted font-mono text-xs">
                                    {{ $supplier->tax_number ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm max-w-48 truncate text-text-muted text-xs">
                                    {{ $supplier->payment_terms ?: __('Not configured') }}
                                </td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $supplier->status === 'active' ? 'emerald' : 'zinc' }}">
                                        {{ __($supplier->status === 'active' ? 'Active' : 'Inactive') }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-center whitespace-nowrap">
                                    <flux:badge size="sm" color="sky">{{ $supplier->product_suppliers_count }}</flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-end whitespace-nowrap space-x-1 rtl:space-x-reverse">
                                    <flux:button
                                        size="xs"
                                        variant="subtle"
                                        icon="eye"
                                        wire:click="openSupplierDetailModal({{ $supplier->id }})"
                                        title="{{ __('View supplier details & history') }}"
                                    />
                                    @if ($canEdit)
                                        <flux:button
                                            size="xs"
                                            variant="subtle"
                                            icon="pencil"
                                            wire:click="openEditSupplierModal({{ $supplier->id }})"
                                            title="{{ __('Edit supplier master') }}"
                                        />
                                        <flux:button
                                            size="xs"
                                            variant="subtle"
                                            icon="arrow-path"
                                            wire:click="toggleSupplierStatus({{ $supplier->id }})"
                                            title="{{ $supplier->status === 'active' ? __('Deactivate supplier') : __('Activate supplier') }}"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center" data-guide="suppliers-empty">
                                    <x-state.empty
                                        :title="__('No suppliers found')"
                                        :message="__('No supplier master records match the active filters or search criteria.')"
                                        icon="truck"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-border p-4" data-guide="suppliers-pagination">
                    {{ $suppliers->links() }}
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Create / Edit Supplier Modal -->
    <flux:modal wire:model="showSupplierModal" class="md:max-w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingSupplierId ? __('Edit Supplier Master') : __('Create Supplier Master') }}
                </flux:heading>
                <flux:text class="text-sm text-text-muted">
                    {{ __('Maintain supplier identity, contact details, tax reference, and configurable payment terms text.') }}
                </flux:text>
            </div>

            <form wire:submit.prevent="saveSupplier" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="supplierForm.code"
                        :label="__('Supplier code')"
                        placeholder="SUP-001"
                        required
                    />
                    <flux:select wire:model="supplierForm.status" :label="__('Operational status')">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </flux:select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="supplierForm.name_ar"
                        :label="__('Arabic name')"
                        placeholder="مورد جديد"
                        required
                        dir="rtl"
                    />
                    <flux:input
                        wire:model="supplierForm.name_en"
                        :label="__('English name')"
                        placeholder="New Supplier Ltd"
                        required
                        dir="ltr"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input
                        wire:model="supplierForm.contact_name"
                        :label="__('Contact name')"
                        placeholder="John Doe"
                    />
                    <flux:input
                        wire:model="supplierForm.phone"
                        :label="__('Phone number')"
                        placeholder="+20 100 000 0000"
                        dir="ltr"
                    />
                    <flux:input
                        wire:model="supplierForm.email"
                        :label="__('Email address')"
                        type="email"
                        placeholder="supplier@example.com"
                        dir="ltr"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="supplierForm.tax_number"
                        :label="__('Tax registration number')"
                        placeholder="123-456-789"
                    />
                    <flux:input
                        wire:model="supplierForm.payment_terms"
                        :label="__('Payment terms text')"
                        placeholder="Net 30 Days / دفع بعد 30 يوم"
                    />
                </div>

                <flux:textarea
                    wire:model="supplierForm.address"
                    :label="__('Address / Location')"
                    rows="2"
                    placeholder="Supplier physical or mailing address..."
                />

                @error('supplierForm')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="text-sm">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
                    <flux:button variant="subtle" wire:click="$set('showSupplierModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingSupplierId ? __('Update Supplier') : __('Create Supplier') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Supplier Detail & History Modal -->
    <flux:modal wire:model="showDetailModal" class="md:max-w-3xl">
        @if ($viewingSupplier)
            <div class="space-y-6">
                <div class="flex items-center justify-between border-b border-border pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="catalog-code-chip">{{ $viewingSupplier->code }}</span>
                            <flux:badge size="sm" color="{{ $viewingSupplier->status === 'active' ? 'emerald' : 'zinc' }}">
                                {{ __($viewingSupplier->status === 'active' ? 'Active' : 'Inactive') }}
                            </flux:badge>
                        </div>
                        <flux:heading size="xl" class="mt-1">
                            {{ app()->getLocale() === 'ar' ? $viewingSupplier->name_ar : $viewingSupplier->name_en }}
                        </flux:heading>
                        <flux:text class="text-sm text-text-muted">
                            {{ app()->getLocale() === 'ar' ? $viewingSupplier->name_en : $viewingSupplier->name_ar }}
                        </flux:text>
                    </div>

                    <div class="flex gap-2">
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'profile' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'profile')"
                        >
                            {{ __('Profile') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'products' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'products')"
                        >
                            {{ __('Linked Products') }} ({{ $viewingSupplier->productSuppliers->count() }})
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'purchases' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'purchases')"
                        >
                            {{ __('Purchase History') }}
                        </flux:button>
                    </div>
                </div>

                @if ($detailTab === 'profile')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Contact person') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ $viewingSupplier->contact_name ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Phone number') }}</dt>
                            <dd class="mt-1 text-sm font-medium" dir="ltr">{{ $viewingSupplier->phone ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Email address') }}</dt>
                            <dd class="mt-1 text-sm font-medium" dir="ltr">{{ $viewingSupplier->email ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field">
                            <dt class="catalog-detail-label">{{ __('Tax registration number') }}</dt>
                            <dd class="mt-1 text-sm font-medium font-mono">{{ $viewingSupplier->tax_number ?: __('Not provided') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Configurable payment terms') }}</dt>
                            <dd class="mt-1 text-sm font-medium whitespace-pre-line">{{ $viewingSupplier->payment_terms ?: __('Not configured') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Address') }}</dt>
                            <dd class="mt-1 text-sm font-medium whitespace-pre-line">{{ $viewingSupplier->address ?: __('Not provided') }}</dd>
                        </div>
                    </div>
                @elseif ($detailTab === 'products')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Products supplied by this supplier') }}</flux:heading>
                            @if ($canEdit)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openLinkProductModal">
                                    {{ __('Link product') }}
                                </flux:button>
                            @endif
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="min-w-full divide-y divide-border">
                                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                    <tr>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Item code') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Product name') }}</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold text-text-muted">{{ __('Supplier item code') }}</th>
                                        <th class="px-3 py-2 text-center text-xs font-semibold text-text-muted">{{ __('Preferred') }}</th>
                                        <th class="px-3 py-2 text-end text-xs font-semibold text-text-muted">{{ __('Last price') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @forelse ($viewingSupplier->productSuppliers as $ps)
                                        <tr>
                                            <td class="px-3 py-2 text-xs font-mono"><span class="catalog-code-chip">{{ $ps->product?->item_code }}</span></td>
                                            <td class="px-3 py-2 text-xs font-medium">{{ app()->getLocale() === 'ar' ? $ps->product?->name_ar : $ps->product?->name_en }}</td>
                                            <td class="px-3 py-2 text-xs text-text-muted font-mono">{{ $ps->supplier_item_code ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-center">
                                                @if ($ps->is_preferred)
                                                    <flux:badge size="sm" color="amber">{{ __('Preferred') }}</flux:badge>
                                                @else
                                                    <span class="text-text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs text-end text-text-muted">
                                                {{ $ps->last_purchase_price ? number_format($ps->last_purchase_price, 2) : '— (TSK-015)' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-xs text-text-muted">
                                                {{ __('No products linked to this supplier yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($detailTab === 'purchases')
                    <div class="py-6 text-center space-y-3">
                        <x-state.empty
                            :title="__('No purchase history yet')"
                            :message="__('Purchase orders, invoices, weighted-average cost, and last purchase price history will be populated starting in TSK-015.')"
                            icon="document-text"
                        />
                    </div>
                @endif

                <div class="flex justify-end border-t border-border pt-4">
                    <flux:button variant="subtle" wire:click="$set('showDetailModal', false)">
                        {{ __('Close') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Link Product Modal -->
    <flux:modal wire:model="showLinkProductModal" class="md:max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Link Product to Supplier') }}</flux:heading>
                <flux:text class="text-sm text-text-muted">{{ __('Associate a catalog product with this supplier master record.') }}</flux:text>
            </div>

            <form wire:submit.prevent="saveProductLink" class="space-y-4">
                <flux:select wire:model="productLinkForm.product_id" :label="__('Select product')" required>
                    <option value="">{{ __('Select a product...') }}</option>
                    @foreach ($availableProducts as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->item_code }} — {{ app()->getLocale() === 'ar' ? $prod->name_ar : $prod->name_en }}</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="productLinkForm.supplier_item_code"
                    :label="__('Supplier item code / reference')"
                    placeholder="SKU-SUP-123"
                />

                <div class="flex items-center gap-2 pt-2">
                    <flux:checkbox wire:model="productLinkForm.is_preferred" :label="__('Set as preferred supplier for this product')" />
                </div>

                <flux:textarea
                    wire:model="productLinkForm.notes"
                    :label="__('Notes')"
                    rows="2"
                    placeholder="Optional supply notes or minimum order quantities..."
                />

                @error('productLinkForm')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="text-sm">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
                    <flux:button variant="subtle" wire:click="$set('showLinkProductModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save Product Link') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
