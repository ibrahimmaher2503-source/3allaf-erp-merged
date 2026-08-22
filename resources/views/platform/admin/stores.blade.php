<?php

use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Store & Inventory Mapping Masters')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $branchFilter = 'all';

    public string $typeFilter = 'all';

    public string $statusFilter = 'all';

    // Store Modal State
    public bool $showStoreModal = false;

    public ?int $editingStoreId = null;

    public array $storeForm = [
        'branch_id' => '',
        'code' => '',
        'type' => 'selling',
        'name_ar' => '',
        'name_en' => '',
        'status' => 'active',
        'allows_negative_stock' => false,
        'policy_notes' => '',
    ];

    // Mapping Modal State
    public bool $showStoreMappingModal = false;

    public bool $showArchiveModal = false;

    public ?int $archiveStoreId = null;

    /** @var array<string, string> */
    public array $archiveStoreContext = [];

    public ?int $mappingStoreId = null;

    public ?string $mappingStoreName = null;

    public ?int $selectedBranchId = null;

    public string $mappingApprovalNotes = '';

    public function mount(): void
    {
        Gate::authorize('branches_stores.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateStoreModal(): void
    {
        Gate::authorize('branches_stores.create');

        $this->editingStoreId = null;
        $this->storeForm = [
            'branch_id' => '',
            'code' => '',
            'type' => 'selling',
            'name_ar' => '',
            'name_en' => '',
            'status' => 'active',
            'allows_negative_stock' => false,
            'policy_notes' => '',
        ];
        $this->resetValidation();
        $this->showStoreModal = true;
    }

    public function openEditStoreModal(int $id): void
    {
        Gate::authorize('branches_stores.edit');

        $store = Store::visibleTo(auth()->user())->findOrFail($id);
        $this->editingStoreId = $store->id;
        $this->storeForm = [
            'branch_id' => (string) ($store->branch_id ?? ''),
            'code' => $store->code,
            'type' => $store->type,
            'name_ar' => $store->name_ar,
            'name_en' => $store->name_en,
            'status' => $store->status,
            'allows_negative_stock' => (bool) $store->allows_negative_stock,
            'policy_notes' => $store->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showStoreModal = true;
    }

    public function saveStore(SaveStoreAction $action): void
    {
        Gate::authorize($this->editingStoreId ? 'branches_stores.edit' : 'branches_stores.create');

        $validated = $this->validate([
            'storeForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('stores', 'code')->ignore($this->editingStoreId),
            ],
            'storeForm.branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'storeForm.type' => ['required', 'in:'.implode(',', SaveStoreAction::ALLOWED_TYPES)],
            'storeForm.name_ar' => ['required', 'string', 'max:255'],
            'storeForm.name_en' => ['required', 'string', 'max:255'],
            'storeForm.status' => ['required', 'in:active,inactive'],
            'storeForm.allows_negative_stock' => ['boolean'],
            'storeForm.policy_notes' => ['nullable', 'string'],
        ], [], [
            'storeForm.code' => app()->getLocale() === 'ar' ? 'رمز المخزن' : __('Location Code'),
            'storeForm.name_ar' => app()->getLocale() === 'ar' ? 'اسم الموقع بالعربية' : __('Arabic Name'),
            'storeForm.name_en' => app()->getLocale() === 'ar' ? 'اسم الموقع بالإنجليزية' : __('English Name'),
            'storeForm.status' => app()->getLocale() === 'ar' ? 'حالة الموقع' : __('Status'),
        ])['storeForm'];

        try {
            $action->execute($validated, $this->editingStoreId);
            Flux::toast(variant: 'success', text: $this->editingStoreId ? __('Location updated successfully.') : __('Location created successfully.'));
            $this->showStoreModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function toggleStoreStatus(int $id, SaveStoreAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Store status updated successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openArchiveModal(int $id, SaveStoreAction $storeAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $store = Store::visibleTo(auth()->user())->with('branch')->findOrFail($id);
            if ($store->status !== 'active') {
                throw new \InvalidArgumentException(__('Only active locations can be submitted for archive approval.'));
            }
            $storeAction->assertStoreDependencyFree($store->id, 'archive', false);
            $this->archiveStoreId = $store->id;
            $this->archiveStoreContext = [
                'code' => $store->code,
                'name' => app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en,
                'type' => match ($store->type) {
                    'selling' => __('Point of Sale (POS)'),
                    'warehouse' => __('Warehouse'),
                    'party' => __('Service Center'),
                    'damaged' => __('Damaged & Defective Stock'),
                    'transit' => __('Stock in Transit'),
                    default => str($store->type)->headline(),
                },
                'branch' => $store->branch === null
                    ? __('Central / Unassigned')
                    : $store->branch->code.' — '.(app()->getLocale() === 'ar' ? $store->branch->name_ar : $store->branch->name_en),
            ];
            $this->resetValidation();
            $this->showArchiveModal = true;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function requestArchive(PlatformSettingsApprovalAction $approvalAction, SaveStoreAction $storeAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            if ($this->archiveStoreId === null) {
                throw new \InvalidArgumentException(__('Select an active location before requesting archive approval.'));
            }
            $store = Store::visibleTo(auth()->user())->findOrFail($this->archiveStoreId);
            if ($store->status !== 'active') {
                throw new \InvalidArgumentException(__('Only active locations can be submitted for archive approval.'));
            }
            $storeAction->assertStoreDependencyFree($store->id, 'archive', false);
            $approvalAction->request('store_archive', $store->id, ['status' => 'inactive'], $store->getAttributes(), $store->branch_id, $store->id);
            $this->showArchiveModal = false;
            Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Archive request submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openStoreMappingModal(int $storeId): void
    {
        Gate::authorize('branches_stores.edit');

        $store = Store::visibleTo(auth()->user())->findOrFail($storeId);
        if ($store->type !== 'selling') {
            Flux::toast(variant: 'danger', text: __('Only stores of type Selling Store can be mapped to branches for POS operations.'));

            return;
        }

        if ($store->status !== 'active') {
            Flux::toast(variant: 'danger', text: __('Selling store must be active to map to a branch.'));

            return;
        }

        $activeMapping = BranchSellingStore::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->whereIn('branch_id', Branch::visibleTo(auth()->user())->select('id'))
            ->first();

        $this->mappingStoreId = $store->id;
        $this->mappingStoreName = app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en;
        $this->selectedBranchId = $activeMapping?->branch_id
            ?? Branch::visibleTo(auth()->user())->whereKey($store->branch_id)->value('id');
        $this->mappingApprovalNotes = '';
        $this->resetValidation();
        $this->showStoreMappingModal = true;
    }

    public function saveStoreMapping(SaveBranchSellingStoreMappingAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        $validated = $this->validate([
            'selectedBranchId' => ['required', 'exists:branches,id'],
            'mappingApprovalNotes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                branchId: (int) $validated['selectedBranchId'],
                storeId: $this->mappingStoreId,
                approvalNotes: $validated['mappingApprovalNotes']
            );
            Flux::toast(variant: 'success', text: __('Selling store mapped to branch successfully.'));
            $this->showStoreMappingModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render()
    {
        $query = Store::visibleTo(auth()->user())->with(['branch', 'sellingStoreMappings.branch']);
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($scope) => $scope
                ->where('code', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('name_en', 'like', $like));
        }

        if ($this->branchFilter !== 'all') {
            $this->branchFilter === 'unassigned'
                ? $query->whereNull('branch_id')
                : $query->where('branch_id', (int) $this->branchFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('platform.admin.stores', [
            'branchesList' => Branch::visibleTo(auth()->user())->orderBy('code')->get(),
            'activeBranchesList' => Branch::visibleTo(auth()->user())
                ->where('company_id', Company::query()->where('status', 'active')->value('id'))
                ->where('status', 'active')
                ->whereHas('company', fn ($query) => $query->where('status', 'active'))
                ->orderBy('code')
                ->get(),
            'stores' => $query->orderBy('code')->paginate(10),
            'pendingArchiveStoreIds' => ApprovalRecord::query()
                ->where('source_type', 'platform_settings')
                ->whereIn('requested_action', ['store_archive', 'store_delete'])
                ->where('approval_state', 'pending')
                ->whereNotNull('store_id')
                ->pluck('store_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Warehouse Masters & Branch Mapping')"
    :description="__('Manage warehouses and selling stores, including physical inventory and POS operations, with their branch context.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="stores-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar>
            @can('branches_stores.create')
                <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal" data-guide="stores-add-action">{{ __('Add warehouse') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <!-- Filters & Search -->
    <flux:card id="stores-filters" class="scroll-mt-24 space-y-4 p-4 sm:p-5" data-guide="stores-filters">
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search warehouse code or name...') }}"
                size="sm"
            />

            <flux:select wire:model.live="branchFilter" size="sm" :label="__('Branch Filter')">
                <flux:select.option value="all">{{ __('All Branches') }}</flux:select.option>
                <flux:select.option value="unassigned">{{ __('Unassigned Branch') }}</flux:select.option>
                @foreach ($branchesList as $b)
                    <flux:select.option :value="$b->id">
                        {{ $b->code }} - {{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="typeFilter" size="sm" :label="__('Warehouse Type')">
                <flux:select.option value="all">{{ __('All Warehouse Types') }}</flux:select.option>
                <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                <flux:select.option value="warehouse">{{ __('Warehouse — physical inventory') }}</flux:select.option>
                <flux:select.option value="party">{{ __('Service Center') }}</flux:select.option>
                <flux:select.option value="damaged">{{ __('Damaged & Defective Stock — inventory routing') }}</flux:select.option>
                <flux:select.option value="transit">{{ __('Stock in Transit — inventory routing') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="statusFilter" size="sm" :label="__('Status Filter')">
                <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <!-- Stores Table -->

    @if ($stores->isEmpty())
        <flux:card class="p-8 text-center space-y-3" data-guide="stores-empty">
            <div class="flex justify-center">
                <flux:icon icon="building-storefront" class="size-12 text-zinc-400" />
            </div>
            <flux:heading level="3" size="lg">{{ __('No Warehouses Configured') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-md mx-auto">
                {{ __('Add a warehouse to make it available for inventory or selling-store operations.') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal">{{ __('Add warehouse') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700" aria-label="{{ __('Warehouse list') }}">
            <flux:table data-guide="stores-table" class="min-w-[90rem]">
            <flux:table.columns>
                <flux:table.column sortable class="min-w-24 whitespace-nowrap">{{ __('Code') }}</flux:table.column>
                <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Warehouse Name (AR / EN)') }}</span></flux:table.column>
                <flux:table.column class="min-w-36 whitespace-nowrap">{{ __('Type') }}</flux:table.column>
                <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Branch Context') }}</span></flux:table.column>
                <flux:table.column class="min-w-48"><span class="block whitespace-normal leading-tight">{{ __('Mapped POS Branch') }}</span></flux:table.column>
                <flux:table.column class="min-w-44"><span class="block whitespace-normal leading-tight">{{ __('Negative Stock') }}</span></flux:table.column>
                <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-64 min-w-64 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($stores as $st)
                    <?php
                $activeBranchMapping = $st->sellingStoreMappings->firstWhere('status', 'active');
                $isPendingArchive = in_array($st->id, $pendingArchiveStoreIds, true);
?>
                    <flux:table.row :key="$st->id">
                        <flux:table.cell class="font-mono font-medium">
                            {{ $st->code }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $st->name_ar }}</div>
                            <div class="text-xs text-zinc-500">{{ $st->name_en }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @switch($st->type)
                                @case('selling')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Point of Sale (POS)') }}</span></flux:badge>
                                    @break
                                @case('warehouse')
                                    <flux:badge size="sm" variant="subtle" color="zinc"><span data-testid="store-type-{{ $st->id }}">{{ __('Warehouse') }}</span></flux:badge>
                                    @break
                                @case('party')
                                    <flux:badge size="sm" variant="subtle" color="zinc">{{ __('Service Center') }}</flux:badge>
                                    @break
                                @case('damaged')
                                    <flux:badge size="sm" variant="subtle" color="rose">{{ __('Damaged & Defective Stock') }}</flux:badge>
                                    @break
                                @case('transit')
                                    <flux:badge size="sm" variant="subtle" color="amber">{{ __('Stock in Transit') }}</flux:badge>
                                    @break
                                @default
                                    <flux:badge size="sm" variant="subtle">{{ $st->type }}</flux:badge>
                            @endswitch
                        </flux:table.cell>

                        <flux:table.cell class="align-top text-xs">
                            @if ($st->branch)
                                <span class="block font-mono font-medium text-zinc-700 dark:text-zinc-300">{{ $st->branch->code }}</span>
                                <span class="block leading-relaxed text-zinc-500">({{ app()->getLocale() === 'ar' ? $st->branch->name_ar : $st->branch->name_en }})</span>
                            @else
                                <span class="text-zinc-400 font-italic">{{ __('Central / Unassigned') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($st->type === 'selling')
                                @if ($activeBranchMapping && $activeBranchMapping->branch)
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <flux:badge size="sm" variant="solid" color="emerald">
                                                {{ $activeBranchMapping->branch->code }}
                                            </flux:badge>
                                        </div>
                                        <span class="block text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                                            {{ app()->getLocale() === 'ar' ? $activeBranchMapping->branch->name_ar : $activeBranchMapping->branch->name_en }}
                                        </span>
                                    </div>
                                @else
                                    <flux:badge size="sm" variant="outline" color="amber">
                                        {{ __('Unmapped') }}
                                    </flux:badge>
                                @endif
                            @else
                                <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($st->allows_negative_stock)
                                <flux:badge size="sm" color="amber" variant="subtle">{{ __('Allowed (Warning)') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" variant="subtle">{{ __('Blocked (Safe)') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($isPendingArchive)
                                <flux:badge size="sm" color="amber" inset="top" class="font-medium">{{ __('Pending archive approval') }}</flux:badge>
                            @elseif ($st->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="font-medium">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive / Archived') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="w-64 min-w-64 align-top text-end">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @can('branches_stores.edit')
                                    <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditStoreModal({{ $st->id }})" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}" />
                                    @if ($st->type === 'selling' && $st->status === 'active')
                                        <flux:button size="xs" variant="subtle" icon="arrows-right-left" wire:click="openStoreMappingModal({{ $st->id }})" aria-label="{{ __('Map to Branch') }}" title="{{ __('Map to Branch') }}" />
                                    @endif
                                    @if ($st->status === 'active' && ! $isPendingArchive)
                                        <flux:button size="xs" variant="subtle" icon="pause" class="whitespace-nowrap" wire:click="toggleStoreStatus({{ $st->id }})" aria-label="{{ __('Deactivate') }}" title="{{ __('Deactivate') }}">{{ __('Deactivate') }}</flux:button>
                                    @else
                                        @if ($st->status !== 'active')
                                            <flux:button size="xs" variant="subtle" icon="play" class="whitespace-nowrap" wire:click="toggleStoreStatus({{ $st->id }})" aria-label="{{ __('Activate') }}" title="{{ __('Activate') }}">{{ __('Activate') }}</flux:button>
                                        @endif
                                    @endif
                                @endcan
                                @can('branches_stores.logical_delete')
                                    @if ($st->status === 'active' && ! $isPendingArchive)
                                        <flux:button size="xs" variant="subtle" icon="archive-box" class="whitespace-nowrap" wire:click="openArchiveModal({{ $st->id }})" aria-label="{{ __('Request archive') }}" title="{{ __('Request archive') }}">{{ __('Request archive') }}</flux:button>
                                    @endif
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
            </flux:table>
        </div>

        <div class="pt-4" data-guide="stores-pagination">
            {{ $stores->links() }}
        </div>
    @endif

    <!-- Create / Edit Store Modal -->
    <flux:modal wire:model="showStoreModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingStoreId ? __('Edit Warehouse') : __('Create Warehouse') }}</flux:heading>
            <flux:subheading>{{ __('Define a location code, location type, bilingual names, branch context, and negative stock policy.') }}</flux:subheading>
        </div>

        <form wire:submit="saveStore" novalidate class="space-y-4">
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    <ul class="list-disc space-y-1 ps-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input
                    wire:model="storeForm.code"
                    :label="__('Warehouse Code')"
                    placeholder="STR-01"
                    required
                />

                <flux:select wire:model="storeForm.type" :label="__('Warehouse Type')" required>
                    <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                    <flux:select.option value="warehouse">{{ __('Warehouse — physical inventory') }}</flux:select.option>
                </flux:select>

                @if (! $editingStoreId)
                    <flux:select wire:model="storeForm.status" :label="__('Status')" required>
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    </flux:select>
                @else
                    <flux:callout icon="information-circle" variant="info">
                        {{ __('Use Deactivate for a reversible status change, or Request archive for the approval-backed Inactive / Archived transition.') }}
                    </flux:callout>
                @endif
            </div>

            <div class="flex items-center">
                <flux:checkbox
                    wire:model="storeForm.allows_negative_stock"
                    :label="__('Allow negative stock')"
                />
            </div>

            <flux:select wire:model="storeForm.branch_id" :label="__('Branch only')" class="w-full" data-testid="store-branch-context-selector">
                <flux:select.option value="">{{ __('Central / No Direct Branch') }}</flux:select.option>
                @foreach ($activeBranchesList as $b)
                    <flux:select.option :value="$b->id">
                        {{ $b->code }} - {{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="storeForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="مستودع المعرض الرئيسي"
                    required
                />

                <flux:input
                    wire:model="storeForm.name_en"
                    :label="__('English Name')"
                    placeholder="Main Showroom Store"
                    required
                />
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveStore"><span wire:loading.remove wire:target="saveStore">{{ __('Save Warehouse') }}</span><span wire:loading wire:target="saveStore">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Archive approval confirmation -->
    <flux:modal wire:model="showArchiveModal" class="md:w-140 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Request archive approval') }}</flux:heading>
            <flux:subheading>{{ __('Review the location context before submitting this approval-backed status change.') }}</flux:subheading>
        </div>

        <dl class="grid gap-3 rounded-xl border border-border-subtle bg-zinc-50 p-4 text-sm dark:bg-zinc-900 sm:grid-cols-2">
            <div><dt class="text-text-muted">{{ __('Warehouse code') }}</dt><dd class="font-mono font-medium">{{ $archiveStoreContext['code'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Warehouse name') }}</dt><dd class="font-medium">{{ $archiveStoreContext['name'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Warehouse type') }}</dt><dd>{{ $archiveStoreContext['type'] ?? '—' }}</dd></div>
            <div><dt class="text-text-muted">{{ __('Branch context') }}</dt><dd>{{ $archiveStoreContext['branch'] ?? '—' }}</dd></div>
        </dl>

        <flux:callout icon="archive-box" variant="warning">
            <strong>{{ __('History is preserved.') }}</strong>
            {{ __('After independent approval, this location will remain in the system and be marked Inactive / Archived. It will not be hard-deleted.') }}
        </flux:callout>
        <flux:callout icon="exclamation-triangle" variant="warning">
            {{ __('Archive and delete are dependency-checked. Inventory, stock movements, transactions, POS links, open counts, transfers, drawers, and historical references can block the action; the exact dependency category is shown in the error.') }}
            {{ __('Deactivate is the reversible option for stopping active use while retaining history.') }}
        </flux:callout>
        <flux:callout icon="shield-check" variant="info">
            {{ __('A second authorized approver is required. Submitting now does not change the location status.') }}
        </flux:callout>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <flux:button variant="subtle" wire:click="$set('showArchiveModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" icon="archive-box" wire:click="requestArchive">{{ __('Request archive') }}</flux:button>
        </div>
    </flux:modal>

    <!-- Map Selling Store to Branch Modal -->
    <flux:modal wire:model="showStoreMappingModal" class="md:w-140 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Map Selling Store to Branch') }}</flux:heading>
            <flux:subheading>{{ __('Assign selling store') }} <strong class="text-zinc-900 dark:text-zinc-100">{{ $mappingStoreName }}</strong> {{ __('to a commercial branch for POS operations.') }}</flux:subheading>
        </div>

        <form wire:submit="saveStoreMapping" class="space-y-4">
            <flux:select wire:model="selectedBranchId" :label="__('Branch Location')" required>
                <flux:select.option value="">{{ __('Select an active branch...') }}</flux:select.option>
                @foreach ($branchesList->where('status', 'active') as $b)
                    <flux:select.option :value="$b->id">
                        {{ $b->code }} - {{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="mappingApprovalNotes"
                :label="__('Change notes (optional)')"
                placeholder="{{ __('Add context for this direct selling-store mapping change.') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreMappingModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveStoreMapping"><span wire:loading.remove wire:target="saveStoreMapping">{{ __('Update Mapping') }}</span><span wire:loading wire:target="saveStoreMapping">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
