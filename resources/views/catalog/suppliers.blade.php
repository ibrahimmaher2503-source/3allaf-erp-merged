<?php

use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierCommunicationDestinationAction;
use App\Modules\Catalog\Actions\SaveSupplierContactAction;
use App\Modules\Catalog\Actions\SaveSupplierGroupAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Actions\ToggleSupplierStatusAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierCommunicationDestination;
use App\Modules\Catalog\Models\SupplierContact;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Models\Company;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Supplier Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierGroupFilter = 'all';

    #[Url(as: 'section', except: 'supplier-masters')]
    public string $section = 'supplier-masters';

    public ?int $activeCompanyId = null;

    public bool $showSupplierGroupModal = false;

    public ?int $editingSupplierGroupId = null;

    public array $supplierGroupForm = [
        'name_ar' => '',
        'name_en' => '',
        'parent_id' => '',
        'status' => 'active',
    ];

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
        'supplier_group_id' => '',
        'status' => 'active',
        'lock_version' => 0,
    ];

    public bool $showDetailModal = false;

    public ?int $viewingSupplierId = null;

    public string $detailTab = 'profile';

    public bool $showSupplierContactModal = false;

    public ?int $editingSupplierContactId = null;

    public array $supplierContactForm = [
        'role' => 'representative',
        'name' => '',
        'email' => '',
        'phone' => '',
        'whatsapp' => '',
        'is_primary' => false,
        'status' => 'active',
    ];

    public bool $showSupplierDestinationModal = false;

    public ?int $editingSupplierDestinationId = null;

    public array $supplierDestinationForm = [
        'purpose' => 'purchase_order',
        'channel' => 'email',
        'destination' => '',
        'label' => '',
        'is_primary' => false,
        'status' => 'active',
    ];

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
        $section = (string) request()->query('section', 'supplier-masters');
        if (in_array($section, ['supplier-masters', 'supplier-groups'], true)) {
            $this->section = $section;
        }
        $this->activeCompanyId = Company::query()->where('status', 'active')->value('id');
    }

    public function rendering(): void
    {
        if (! in_array($this->section, ['supplier-masters', 'supplier-groups'], true)) {
            $this->section = 'supplier-masters';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierGroupFilter(): void
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
            'supplier_group_id' => '',
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
            'supplier_group_id' => $supplier->supplier_group_id ?? '',
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
            'supplierForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierForm.tax_number' => ['nullable', 'string', 'max:50'],
            'supplierForm.payment_terms' => ['nullable', 'string', 'max:1000'],
            'supplierForm.address' => ['nullable', 'string', 'max:1000'],
            'supplierForm.supplier_group_id' => ['nullable', 'integer'],
            'supplierForm.status' => ['required', 'in:active,inactive'],
        ], [], [
            'supplierForm.code' => app()->getLocale() === 'ar' ? 'كود المورد' : __('Supplier code'),
            'supplierForm.name_ar' => app()->getLocale() === 'ar' ? 'اسم المورد بالعربية' : __('Arabic name'),
            'supplierForm.name_en' => app()->getLocale() === 'ar' ? 'اسم المورد بالإنجليزية' : __('English name'),
            'supplierForm.status' => app()->getLocale() === 'ar' ? 'حالة التشغيل' : __('Operational status'),
        ])['supplierForm'];

        try {
            $action->execute(
                $validated,
                $this->editingSupplierId,
                $this->editingSupplierId ? (int) $this->supplierForm['lock_version'] : null,
                $this->activeCompanyId,
            );
            Flux::toast(
                variant: 'success',
                text: $this->editingSupplierId ? __('Supplier master updated successfully.') : __('Supplier master created successfully.')
            );
            $this->showSupplierModal = false;
        } catch (Throwable $exception) {
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
        } catch (Throwable $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function bulkToggleSupplierStatus(ToggleSupplierStatusAction $action): void
    {
        Gate::authorize('suppliers.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $action->execute($id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Supplier status updated for :count records.', ['count' => $count]));
        } catch (Throwable $exception) {
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
        } catch (Throwable $exception) {
            $this->addError('productLinkForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function openCreateSupplierGroupModal(): void
    {
        Gate::authorize('suppliers.create');
        $this->editingSupplierGroupId = null;
        $this->supplierGroupForm = ['name_ar' => '', 'name_en' => '', 'parent_id' => '', 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierGroupModal = true;
    }

    public function openEditSupplierGroupModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        abort_unless($this->activeCompanyId !== null, 404);
        $group = SupplierGroup::query()->forCompany($this->activeCompanyId)->findOrFail($id);
        $this->editingSupplierGroupId = $group->id;
        $this->supplierGroupForm = [
            'name_ar' => $group->name_ar,
            'name_en' => $group->name_en ?? '',
            'parent_id' => $group->parent_id ?? '',
            'status' => $group->status,
        ];
        $this->resetValidation();
        $this->showSupplierGroupModal = true;
    }

    public function saveSupplierGroup(SaveSupplierGroupAction $action): void
    {
        Gate::authorize($this->editingSupplierGroupId ? 'suppliers.edit' : 'suppliers.create');
        if ($this->activeCompanyId === null) {
            $this->addError('supplierGroupForm', __('Complete active company setup before creating supplier groups.'));
            return;
        }
        $validated = $this->validate([
            'supplierGroupForm.name_ar' => ['required', 'string', 'max:190'],
            'supplierGroupForm.name_en' => ['nullable', 'string', 'max:190'],
            'supplierGroupForm.parent_id' => ['nullable', 'integer'],
            'supplierGroupForm.status' => ['required', 'in:active,inactive'],
        ])['supplierGroupForm'];
        try {
            $action->execute($validated, $this->activeCompanyId, $this->editingSupplierGroupId);
            $this->showSupplierGroupModal = false;
            Flux::toast(variant: 'success', text: __('Supplier group saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierGroupForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function openCreateSupplierContactModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->editingSupplierContactId = null;
        $this->supplierContactForm = ['role' => 'representative', 'name' => '', 'email' => '', 'phone' => '', 'whatsapp' => '', 'is_primary' => false, 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierContactModal = true;
    }

    public function openEditSupplierContactModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $contact = SupplierContact::query()->where('supplier_id', $this->viewingSupplierId)->findOrFail($id);
        $this->editingSupplierContactId = $contact->id;
        $this->supplierContactForm = $contact->only(['role', 'name', 'email', 'phone', 'whatsapp', 'is_primary', 'status']);
        $this->resetValidation();
        $this->showSupplierContactModal = true;
    }

    public function saveSupplierContact(SaveSupplierContactAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'supplierContactForm.role' => ['required', Rule::in(SupplierContact::ROLES)],
            'supplierContactForm.name' => ['required', 'string', 'max:255'],
            'supplierContactForm.email' => ['nullable', 'email', 'max:255'],
            'supplierContactForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierContactForm.whatsapp' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'supplierContactForm.is_primary' => ['boolean'],
            'supplierContactForm.status' => ['required', 'in:active,inactive'],
        ])['supplierContactForm'];
        try {
            $action->execute((int) $this->viewingSupplierId, $validated, $this->editingSupplierContactId);
            $this->showSupplierContactModal = false;
            Flux::toast(variant: 'success', text: __('Supplier contact saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierContactForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function openCreateSupplierDestinationModal(): void
    {
        Gate::authorize('suppliers.edit');
        $this->editingSupplierDestinationId = null;
        $this->supplierDestinationForm = ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => '', 'label' => '', 'is_primary' => false, 'status' => 'active'];
        $this->resetValidation();
        $this->showSupplierDestinationModal = true;
    }

    public function openEditSupplierDestinationModal(int $id): void
    {
        Gate::authorize('suppliers.edit');
        $destination = SupplierCommunicationDestination::query()->where('supplier_id', $this->viewingSupplierId)->findOrFail($id);
        $this->editingSupplierDestinationId = $destination->id;
        $this->supplierDestinationForm = $destination->only(['purpose', 'channel', 'destination', 'label', 'is_primary', 'status']);
        $this->resetValidation();
        $this->showSupplierDestinationModal = true;
    }

    public function saveSupplierDestination(SaveSupplierCommunicationDestinationAction $action): void
    {
        Gate::authorize('suppliers.edit');
        $validated = $this->validate([
            'supplierDestinationForm.purpose' => ['required', Rule::in(SupplierCommunicationDestination::PURPOSES)],
            'supplierDestinationForm.channel' => ['required', Rule::in(SupplierCommunicationDestination::CHANNELS)],
            'supplierDestinationForm.destination' => ['required', 'string', 'max:255'],
            'supplierDestinationForm.label' => ['nullable', 'string', 'max:190'],
            'supplierDestinationForm.is_primary' => ['boolean'],
            'supplierDestinationForm.status' => ['required', 'in:active,inactive'],
        ])['supplierDestinationForm'];
        try {
            $action->execute((int) $this->viewingSupplierId, $validated, $this->editingSupplierDestinationId);
            $this->showSupplierDestinationModal = false;
            Flux::toast(variant: 'success', text: __('Supplier communication destination saved successfully.'));
        } catch (Throwable $exception) {
            $this->addError('supplierDestinationForm', $exception->getMessage());
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function render()
    {
        $supplierGroups = $this->activeCompanyId === null ? collect() : SupplierGroup::query()->forCompany($this->activeCompanyId)->withCount('suppliers')->orderBy('parent_id')->orderBy('name_ar')->limit(200)->get();
        $supplierGroupParents = $this->activeCompanyId === null ? collect() : SupplierGroup::query()->forCompany($this->activeCompanyId)->active()->orderBy('parent_id')->orderBy('name_ar')->limit(200)->get(['id', 'parent_id', 'name_ar', 'name_en']);
        $isSupplierMasters = $this->section === 'supplier-masters';
        $suppliers = null;
        $viewingSupplier = null;
        $availableProducts = collect();

        if ($isSupplierMasters) {
            $query = Supplier::query()->with(['supplierGroup'])->withCount('productSuppliers');
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

            if ($this->supplierGroupFilter !== 'all' && $this->activeCompanyId !== null) {
                $query->whereHas('supplierGroup', fn ($group) => $group->forCompany($this->activeCompanyId)->whereKey((int) $this->supplierGroupFilter));
            }

            $suppliers = $query->orderBy('code')->paginate(15);
            $viewingSupplier = $this->viewingSupplierId
                ? Supplier::query()->with(['productSuppliers.product', 'supplierGroup', 'contacts', 'communicationDestinations'])->find($this->viewingSupplierId)
                : null;
            $availableProducts = Product::query()->where('status', 'active')->orderBy('item_code')->get(['id', 'item_code', 'name_ar', 'name_en']);
        }

        return view('catalog.suppliers', [
            'suppliers' => $suppliers,
            'viewingSupplier' => $viewingSupplier,
            'availableProducts' => $availableProducts,
            'supplierGroups' => $supplierGroups,
            'supplierGroupParents' => $supplierGroupParents,
            'canCreate' => Gate::allows('suppliers.create'),
            'canEdit' => Gate::allows('suppliers.edit'),
        ]);
    }
}; ?>

<x-app.page
    :title="$section === 'supplier-groups' ? __('Supplier group setup') : __('Supplier Masters & Product-Supplier History')"
    :description="$section === 'supplier-groups' ? __('Create and maintain the supplier hierarchy before assigning supplier masters to a group.') : __('Maintain supplier master contacts, commercial payment terms, preferred product relations, and purchase history.')"
    max-width="7xl"
    class="catalog-screen"
    data-guide="suppliers-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="suppliers-filters">
            @if ($canCreate && $section === 'supplier-masters')
                <flux:button
                    icon="plus"
                    variant="primary"
                    wire:click="openCreateSupplierModal"
                    data-guide="suppliers-add-action"
                >
                    {{ __('Add supplier') }}
                </flux:button>
            @elseif ($canCreate && $section === 'supplier-groups')
                <flux:button icon="folder-plus" variant="primary" wire:click="openCreateSupplierGroupModal" data-guide="supplier-groups-add-action">
                    {{ __('Add supplier group') }}
                </flux:button>
            @endif
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <div class="space-y-5" data-supplier-workspace="{{ $section }}">
        @if ($section === 'supplier-groups')
            <section class="space-y-5" data-guide="supplier-groups-workspace">
                <flux:callout variant="info" icon="information-circle" title="{{ __('Supplier group setup') }}">
                    {{ __('Create and maintain the supplier hierarchy here. Supplier master records are assigned to a group from the Supplier masters workspace.') }}
                </flux:callout>

                <flux:card class="space-y-4 p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('Supplier groups') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-text-muted">{{ __('Use parent groups to keep related suppliers organized. Group status does not alter historical supplier records.') }}</flux:text>
                        </div>
                        @if ($canCreate)
                            <flux:button icon="folder-plus" variant="primary" wire:click="openCreateSupplierGroupModal">
                                {{ __('Add supplier group') }}
                            </flux:button>
                        @endif
                    </div>

                    @php($groupsById = $supplierGroups->keyBy('id'))
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse ($supplierGroups as $group)
                            <article class="rounded-xl border border-border p-4 {{ $group->parent_id ? 'ms-5 border-s-4 border-s-primary/30' : '' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ app()->getLocale() === 'ar' || blank($group->name_en) ? $group->name_ar : $group->name_en }}</div>
                                        @if (filled($group->name_en) && app()->getLocale() === 'ar')
                                            <div class="mt-1 text-xs text-text-muted" dir="ltr">{{ $group->name_en }}</div>
                                        @elseif (filled($group->name_en))
                                            <div class="mt-1 text-xs text-text-muted">{{ $group->name_ar }}</div>
                                        @endif
                                    </div>
                                    <flux:badge size="sm" color="{{ $group->status === 'active' ? 'emerald' : 'zinc' }}">{{ __($group->status === 'active' ? 'Active' : 'Inactive') }}</flux:badge>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                                    <span>{{ __('Suppliers') }}: {{ $group->suppliers_count }}</span>
                                    @if ($group->parent_id && $groupsById->has($group->parent_id))
                                        <span>{{ __('Parent group') }}: {{ $groupsById->get($group->parent_id)->name_ar }}</span>
                                    @endif
                                    @if ($canEdit)
                                        <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditSupplierGroupModal({{ $group->id }})">{{ __('Edit') }}</flux:button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="lg:col-span-2">
                                <x-state.empty :title="__('No supplier groups yet')" :message="__('Create a root group first, then add optional child groups as the supplier hierarchy grows.')" icon="folder-plus" />
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            </section>
        @else
            <section class="space-y-5" data-guide="supplier-masters-workspace">
                <flux:callout variant="info" icon="information-circle" title="{{ __('Supplier masters') }}">
                    {{ __('Maintain supplier identities, contacts, terms, and product links here. Use Supplier group setup to change the hierarchy.') }}
                </flux:callout>
        <flux:card id="suppliers-filters" class="scroll-mt-24 space-y-4 p-5 sm:p-6" data-guide="suppliers-filters">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                <flux:select wire:model.live="supplierGroupFilter" :label="__('Supplier group')">
                    <option value="all">{{ __('All supplier groups') }}</option>
                    @foreach ($supplierGroups as $group)
                        <option value="{{ $group->id }}">
                            {{ $group->name_ar }}{{ $group->name_en ? ' — '.$group->name_en : '' }} ({{ $group->suppliers_count }})
                        </option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        <flux:card class="overflow-hidden p-0" data-guide="suppliers-table">
            <div class="border-b border-border p-4">
                <x-tables.bulk-actions
                    :page-ids="$suppliers->pluck('id')->all()"
                    :selected-ids="$selectedIds"
                    :selected-count="count($selectedIds)"
                    :page-count="$suppliers->count()"
                >
                    <x-slot:actions>
                        @if ($canEdit)
                            <flux:button type="button" size="sm" variant="subtle" wire:click="bulkToggleSupplierStatus" wire:confirm="{{ __('Toggle status for the selected suppliers?') }}">{{ __('Toggle status') }}</flux:button>
                        @endif
                    </x-slot:actions>
                </x-tables.bulk-actions>
            </div>
            <div class="app-table-frame">
                <table class="data-table min-w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-text-muted uppercase tracking-wider"><span class="sr-only">{{ __('Select') }}</span></th>
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
                                <td class="px-4 py-3 text-sm"><input type="checkbox" value="{{ $supplier->id }}" wire:model.live="selectedIds" aria-label="{{ __('Select supplier :code', ['code' => $supplier->code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" /></td>
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
                                    <flux:badge size="sm" color="zinc">{{ $supplier->product_suppliers_count }}</flux:badge>
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
                                            onclick='if (! window.confirm(@js(__('Change supplier :name to :status? Its historical records are preserved.', ['name' => app()->getLocale() === 'ar' || blank($supplier->name_en) ? $supplier->name_ar : $supplier->name_en, 'status' => $supplier->status === 'active' ? __('Inactive') : __('Active')])))) { event.preventDefault(); event.stopImmediatePropagation(); event.stopPropagation(); return false; }'
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
            </section>
        @endif
    </div>

    @if ($section === 'supplier-masters')
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

            <form wire:submit.prevent="saveSupplier" novalidate class="space-y-4">
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
                        :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                        :description="__('Egyptian numbers accept local, +20, 0020, spaces, and Arabic numerals.')"
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

                <flux:select wire:model="supplierForm.supplier_group_id" :label="__('Supplier group')" :description="__('Optional hierarchy used to filter suppliers and keep purchasing contacts organized.')">
                    <option value="">{{ __('No supplier group') }}</option>
                    @foreach ($supplierGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name_ar }}{{ $group->name_en ? ' — '.$group->name_en : '' }}</option>
                    @endforeach
                </flux:select>

                @error('supplierForm')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="text-sm">
                        {{ $message }}
                    </flux:callout>
                @enderror

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
                    <flux:button variant="subtle" wire:click="$set('showSupplierModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplier">
                        <span wire:loading.remove wire:target="saveSupplier">{{ $editingSupplierId ? __('Update Supplier') : __('Create Supplier') }}</span><span wire:loading wire:target="saveSupplier">{{ __('Saving...') }}</span>
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
                            variant="{{ $detailTab === 'contacts' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'contacts')"
                        >
                            {{ __('Contacts') }} ({{ $viewingSupplier->contacts->count() }})
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="{{ $detailTab === 'communication' ? 'primary' : 'subtle' }}"
                            wire:click="$set('detailTab', 'communication')"
                        >
                            {{ __('Communication') }} ({{ $viewingSupplier->communicationDestinations->count() }})
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
                    {{-- `<dt>`/`<dd>` require a `<dl>` ancestor (WCAG 1.3.1 / axe-core `dlitem`); this
                         was a plain `<div>` with no list semantics at all. --}}
                    <dl class="grid gap-4 sm:grid-cols-2">
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
                            <dt class="catalog-detail-label">{{ __('Supplier group') }}</dt>
                            <dd class="mt-1 text-sm font-medium">{{ $viewingSupplier->supplierGroup?->name_ar ?: __('No supplier group') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Configurable payment terms') }}</dt>
                            <dd class="mt-1 text-sm font-medium whitespace-pre-line">{{ $viewingSupplier->payment_terms ?: __('Not configured') }}</dd>
                        </div>
                        <div class="catalog-detail-field sm:col-span-2">
                            <dt class="catalog-detail-label">{{ __('Address') }}</dt>
                            <dd class="mt-1 text-sm font-medium whitespace-pre-line">{{ $viewingSupplier->address ?: __('Not provided') }}</dd>
                        </div>
                    </dl>
                @elseif ($detailTab === 'contacts')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Structured supplier contacts') }}</flux:heading>
                            @if ($canEdit)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openCreateSupplierContactModal">{{ __('Add contact') }}</flux:button>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @forelse ($viewingSupplier->contacts as $contact)
                                <div class="rounded-lg border border-border p-3 space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium">{{ $contact->name }}</span>
                                        <flux:badge size="sm" color="zinc">{{ __(ucwords(str_replace('_', ' ', $contact->role))) }}</flux:badge>
                                    </div>
                                    <div class="text-xs text-text-muted">{{ $contact->email ?: __('No email') }} · {{ $contact->phone ?: ($contact->whatsapp ?: __('No phone')) }}</div>
                                    @if ($contact->is_primary)<flux:badge size="sm" color="emerald">{{ __('Primary') }}</flux:badge>@endif
                                    @if ($canEdit)<flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditSupplierContactModal({{ $contact->id }})">{{ __('Edit') }}</flux:button>@endif
                                </div>
                            @empty
                                <div class="sm:col-span-2"><x-state.empty :title="__('No structured contacts yet')" :message="__('Add owner, representative, order, or accounting contacts without changing the legacy supplier fields.')" icon="users" /></div>
                            @endforelse
                        </div>
                    </div>
                @elseif ($detailTab === 'communication')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Communication destinations') }}</flux:heading>
                            @if ($canEdit)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openCreateSupplierDestinationModal">{{ __('Add destination') }}</flux:button>
                            @endif
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="min-w-full divide-y divide-border">
                                <thead><tr><th class="px-3 py-2 text-start text-xs">{{ __('Purpose') }}</th><th class="px-3 py-2 text-start text-xs">{{ __('Channel') }}</th><th class="px-3 py-2 text-start text-xs">{{ __('Destination') }}</th><th class="px-3 py-2 text-end text-xs">{{ __('Actions') }}</th></tr></thead>
                                <tbody class="divide-y divide-border">
                                    @forelse ($viewingSupplier->communicationDestinations as $destination)
                                        <tr><td class="px-3 py-2 text-xs">{{ __(ucwords(str_replace('_', ' ', $destination->purpose))) }}</td><td class="px-3 py-2 text-xs">{{ __(ucwords($destination->channel)) }}</td><td class="px-3 py-2 text-xs" dir="ltr">{{ $destination->destination }} @if($destination->is_primary)<flux:badge size="sm" color="emerald">{{ __('Primary') }}</flux:badge>@endif</td><td class="px-3 py-2 text-end">@if ($canEdit)<flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditSupplierDestinationModal({{ $destination->id }})">{{ __('Edit') }}</flux:button>@endif</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-6 text-center text-xs text-text-muted">{{ __('No communication destinations configured yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
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
                                                {{ $ps->last_purchase_price ? number_format($ps->last_purchase_price, 2) : '—' }}
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
                            :message="__('Purchase orders, invoices, weighted-average cost, and last purchase price history appear after related documents are recorded.')"
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
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveProductLink"><span wire:loading.remove wire:target="saveProductLink">{{ __('Save Product Link') }}</span><span wire:loading wire:target="saveProductLink">{{ __('Saving...') }}</span></flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    @endif

    @if ($section === 'supplier-groups')
    <flux:modal wire:model="showSupplierGroupModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingSupplierGroupId ? __('Edit supplier group') : __('Create supplier group') }}</flux:heading>
            <form wire:submit.prevent="saveSupplierGroup" class="space-y-4">
                <flux:input wire:model="supplierGroupForm.name_ar" :label="__('Arabic group name')" required dir="rtl" />
                <flux:input wire:model="supplierGroupForm.name_en" :label="__('English group name (optional)')" dir="ltr" />
                <flux:select wire:model="supplierGroupForm.parent_id" :label="__('Parent group (optional)')" :description="__('Leave empty for a root group; child groups stay beneath their selected parent.')">
                    <option value="">{{ __('No parent group') }}</option>
                    @foreach ($supplierGroupParents as $parent)
                        @if ($parent->id !== $editingSupplierGroupId)
                            <option value="{{ $parent->id }}">{{ $parent->name_ar }}{{ $parent->name_en ? ' — '.$parent->name_en : '' }}</option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:select wire:model="supplierGroupForm.status" :label="__('Status')"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></flux:select>
                @error('supplierGroupForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierGroupModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierGroup"><span wire:loading.remove wire:target="saveSupplierGroup">{{ __('Save group') }}</span><span wire:loading wire:target="saveSupplierGroup">{{ __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>
    @endif

    @if ($section === 'supplier-masters')
    <flux:modal wire:model="showSupplierContactModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingSupplierContactId ? __('Edit supplier contact') : __('Add supplier contact') }}</flux:heading>
            <form wire:submit.prevent="saveSupplierContact" class="space-y-4">
                <flux:select wire:model="supplierContactForm.role" :label="__('Contact role')"><option value="owner">{{ __('Company / owner') }}</option><option value="representative">{{ __('Sales / account representative') }}</option><option value="order">{{ __('Order contact') }}</option><option value="accounting">{{ __('Accounting contact') }}</option><option value="general">{{ __('General contact') }}</option></flux:select>
                <flux:input wire:model="supplierContactForm.name" :label="__('Contact name')" required />
                <div class="grid gap-4 sm:grid-cols-3"><flux:input wire:model="supplierContactForm.email" :label="__('Email')" type="email" dir="ltr" /><flux:input wire:model="supplierContactForm.phone" :label="__('Phone')" dir="ltr" /><flux:input wire:model="supplierContactForm.whatsapp" :label="__('WhatsApp')" dir="ltr" /></div>
                <flux:checkbox wire:model="supplierContactForm.is_primary" :label="__('Primary contact for this role')" />
                @error('supplierContactForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                 <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierContactModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierContact"><span wire:loading.remove wire:target="saveSupplierContact">{{ __('Save contact') }}</span><span wire:loading wire:target="saveSupplierContact">{{ __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showSupplierDestinationModal" class="md:max-w-lg">
        <div class="space-y-5">
            <flux:heading size="lg">{{ $editingSupplierDestinationId ? __('Edit communication destination') : __('Add communication destination') }}</flux:heading>
            <form wire:submit.prevent="saveSupplierDestination" class="space-y-4">
                <flux:text class="text-sm text-text-muted">{{ __('Mark one active primary destination for each purpose. Purchase Orders use only the designated order recipient; no personal fallback is selected automatically.') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="supplierDestinationForm.purpose" :label="__('Purpose')"><option value="purchase_order">{{ __('Purchase Orders') }}</option><option value="accounting">{{ __('Accounting correspondence') }}</option><option value="general">{{ __('General communication') }}</option></flux:select><flux:select wire:model="supplierDestinationForm.channel" :label="__('Preferred channel')"><option value="email">{{ __('Email') }}</option><option value="whatsapp">{{ __('WhatsApp') }}</option><option value="phone">{{ __('Phone') }}</option></flux:select></div>
                <flux:input wire:model="supplierDestinationForm.destination" :label="__('Destination')" required dir="ltr" />
                <flux:input wire:model="supplierDestinationForm.label" :label="__('Label (optional)')" />
                <flux:checkbox wire:model="supplierDestinationForm.is_primary" :label="__('Primary destination for this purpose')" />
                @error('supplierDestinationForm')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror
                 <div class="flex justify-end gap-3 border-t border-border pt-3"><flux:button variant="subtle" wire:click="$set('showSupplierDestinationModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSupplierDestination"><span wire:loading.remove wire:target="saveSupplierDestination">{{ __('Save destination') }}</span><span wire:loading wire:target="saveSupplierDestination">{{ __('Saving...') }}</span></flux:button></div>
            </form>
        </div>
    </flux:modal>
    @endif
</x-app.page>
