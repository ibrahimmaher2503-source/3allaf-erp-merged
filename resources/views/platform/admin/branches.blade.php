<?php

use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Branch Management')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    #[Url(as: 'section', except: 'branch-masters')]
    public string $section = 'branch-masters';

    // Branch Modal State
    public bool $showBranchModal = false;

    public ?int $editingBranchId = null;

    public array $branchForm = [
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'timezone' => 'Africa/Cairo',
        'status' => 'active',
        'policy_notes' => '',
    ];

    // Mapping Modal State
    public bool $showMappingModal = false;

    public ?int $mappingBranchId = null;

    public ?string $mappingBranchName = null;

    public ?int $selectedStoreId = null;

    public string $mappingApprovalNotes = '';

    // History Modal State
    public bool $showHistoryModal = false;

    public ?int $historyBranchId = null;

    public ?string $historyBranchName = null;

    public array $historyRecords = [];

    public function mount(): void
    {
        Gate::authorize('branches_stores.view');
        $section = (string) request()->query('section', 'branch-masters');
        if (in_array($section, ['branch-masters', 'selling-store-mapping'], true)) {
            $this->section = $section;
        }
    }

    public function rendering(): void
    {
        if (! in_array($this->section, ['branch-masters', 'selling-store-mapping'], true)) {
            $this->section = 'branch-masters';
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

    public function openCreateBranchModal(): void
    {
        Gate::authorize('branches_stores.create');

        $this->editingBranchId = null;
        $this->branchForm = [
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'timezone' => Company::query()->where('status', 'active')->value('timezone') ?? 'Africa/Cairo',
            'status' => 'active',
            'policy_notes' => '',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function openEditBranchModal(int $id): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::visibleTo(auth()->user())->findOrFail($id);
        $this->editingBranchId = $branch->id;
        $this->branchForm = [
            'code' => $branch->code,
            'name_ar' => $branch->name_ar,
            'name_en' => $branch->name_en,
            'phone' => $branch->phone ?? '',
            'email' => $branch->email ?? '',
            'address' => $branch->address ?? '',
            'timezone' => $branch->timezone ?? 'Africa/Cairo',
            'status' => $branch->status,
            'policy_notes' => $branch->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function saveBranch(SaveBranchAction $action): void
    {
        Gate::authorize($this->editingBranchId ? 'branches_stores.edit' : 'branches_stores.create');

        $this->branchForm['code'] = strtoupper(trim($this->branchForm['code']));

        $validated = $this->validate([
            'branchForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('branches', 'code')->ignore($this->editingBranchId),
            ],
            'branchForm.name_ar' => ['required', 'string', 'max:255'],
            'branchForm.name_en' => ['required', 'string', 'max:255'],
            'branchForm.phone' => ['nullable', 'string', 'max:50', PhoneNormalizer::validationRule()],
            'branchForm.email' => ['nullable', 'email', 'max:255'],
            'branchForm.address' => ['nullable', 'string'],
            'branchForm.timezone' => ['required', 'string', 'max:100'],
            'branchForm.status' => ['required', 'in:active,inactive'],
            'branchForm.policy_notes' => ['nullable', 'string'],
        ], [], [
            'branchForm.code' => app()->getLocale() === 'ar' ? 'رمز الفرع' : __('Branch Code'),
            'branchForm.name_ar' => app()->getLocale() === 'ar' ? 'اسم الفرع بالعربية' : __('Arabic Name'),
            'branchForm.name_en' => app()->getLocale() === 'ar' ? 'اسم الفرع بالإنجليزية' : __('English Name'),
            'branchForm.timezone' => app()->getLocale() === 'ar' ? 'المنطقة الزمنية' : __('Timezone'),
            'branchForm.status' => app()->getLocale() === 'ar' ? 'حالة الفرع' : __('Status'),
        ])['branchForm'];

        try {
            $action->execute($validated, $this->editingBranchId);
            Flux::toast(variant: 'success', text: $this->editingBranchId ? __('Branch updated successfully.') : __('Branch created successfully.'));
            $this->showBranchModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function toggleBranchStatus(int $id, SaveBranchAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $action->toggleStatus($id);
            Flux::toast(variant: 'success', text: __('Branch status updated successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function bulkToggleBranchStatus(SaveBranchAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $action->toggleStatus($id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Branch status updated for :count records.', ['count' => $count]));
        } catch (Exception $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function deleteBranch(int $id, PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $branch = Branch::visibleTo(auth()->user())->findOrFail($id);
            $approvalAction->request('branch_delete', $branch->id, ['deleted' => true], $branch->getAttributes(), $branch->id);
            Flux::toast(variant: 'success', text: auth()->user()?->canBypassApproval() ? __('Super Admin action completed without separate approval.') : __('Branch deletion submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openMappingModal(int $branchId): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::visibleTo(auth()->user())->findOrFail($branchId);
        $this->mappingBranchId = $branch->id;
        $this->mappingBranchName = app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en;
        $this->selectedStoreId = $branch->activeSellingStoreMapping?->store_id;
        $this->mappingApprovalNotes = '';
        $this->resetValidation();
        $this->showMappingModal = true;
    }

    public function saveSellingStoreMapping(SaveBranchSellingStoreMappingAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        $validated = $this->validate([
            'selectedStoreId' => ['required', 'exists:stores,id'],
            'mappingApprovalNotes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute(
                branchId: $this->mappingBranchId,
                storeId: (int) $validated['selectedStoreId'],
                approvalNotes: $validated['mappingApprovalNotes']
            );
            Flux::toast(variant: 'success', text: __('Branch selling store mapped successfully.'));
            $this->showMappingModal = false;
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openHistoryModal(int $branchId): void
    {
        Gate::authorize('branches_stores.view');

        $branch = Branch::visibleTo(auth()->user())->with(['sellingStoreMappings.store', 'sellingStoreMappings.creator'])->findOrFail($branchId);
        $this->historyBranchId = $branch->id;
        $this->historyBranchName = app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en;
        $this->historyRecords = $branch->sellingStoreMappings
            // MySQL's default `timestamp` precision is whole seconds, so two
            // mappings created within the same second tie on `created_at` and previously fell
            // back to insertion order — showing history oldest-first instead
            // of newest-first. `id` is a monotonically increasing, unambiguous
            // proxy for creation order regardless of timestamp precision.
            ->sortByDesc('id')
            ->map(fn ($item) => [
                'id' => $item->id,
                'store_code' => $item->store?->code,
                'store_name' => app()->getLocale() === 'ar' ? $item->store?->name_ar : $item->store?->name_en,
                'effective_from' => $item->effective_from?->format('Y-m-d H:i:s'),
                'effective_to' => $item->effective_to?->format('Y-m-d H:i:s') ?? __('Current Active'),
                'status' => $item->status,
                'approval_notes' => $item->approval_notes,
                'creator_name' => $item->creator?->name ?? __('System'),
            ])
            ->values()
            ->toArray();

        $this->showHistoryModal = true;
    }

    public function render()
    {
        $query = Branch::visibleTo(auth()->user())
            ->with('activeSellingStoreMapping.store')
            ->withCount([
                'warehouses as active_warehouse_count' => fn ($warehouseQuery) => $warehouseQuery
                    ->whereColumn('stores.company_id', 'branches.company_id'),
            ]);

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($scope) => $scope
                ->where('code', 'like', $term)
                ->orWhere('name_ar', 'like', $term)
                ->orWhere('name_en', 'like', $term));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return view('platform.admin.branches', [
            'branches' => $query->orderBy('code')->paginate(10),
            'companyTimezone' => Company::query()->where('status', 'active')->value('timezone'),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Branch Masters')"
    :description="__('Manage branch locations, active warehouses, and authorized POS selling store assignments.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="branches-header"
>
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2" data-guide="branches-workspace-navigation">
            <flux:button size="sm" icon="arrows-right-left" variant="primary" href="{{ route('admin.branches', ['section' => 'selling-store-mapping']) }}">{{ __('Link selling stores to branches') }}</flux:button>
            @if ($section === 'branch-masters')
                <div data-guide="branch-masters-actions">
                    <x-tables.resource-toolbar>
                        @can('branches_stores.create')
                            <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal" data-guide="branches-add-action">{{ __('Add Branch') }}</flux:button>
                        @endcan
                    </x-tables.resource-toolbar>
                </div>
            @endif
        </div>
    </x-slot:actions>

    @if ($section === 'selling-store-mapping')
        <section data-branch-section="selling-store-mapping" data-guide="selling-store-mapping-workspace" class="space-y-4">
            <flux:callout variant="info" icon="arrows-right-left" title="{{ __('POS selling-location linkage') }}">
                {{ __('Map each retail branch to its active selling store here. This setup step does not open a cashier shift or start a transaction.') }}
            </flux:callout>

            <flux:card class="space-y-4 p-4 sm:p-5">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('POS selling-location linkage') }}</flux:heading>
                    <flux:subheading>{{ __('Each applicable retail branch needs exactly one current, valid selling-store mapping.') }}</flux:subheading>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table data-guide="selling-store-mapping-table" class="min-w-[58rem]">
                    <flux:table.columns>
                        <flux:table.column class="min-w-56">{{ __('Branch') }}</flux:table.column>
                        <flux:table.column class="min-w-64">{{ __('POS selling location / stock source') }}</flux:table.column>
                        <flux:table.column class="min-w-32">{{ __('Mapping status') }}</flux:table.column>
                        <flux:table.column class="min-w-56 text-end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($branches as $branch)
                            <flux:table.row :key="$branch->id">
                                <flux:table.cell class="whitespace-normal align-top">
                                    <div class="space-y-1">
                                        <div class="font-medium">{{ app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en }}</div>
                                        <div class="font-mono text-xs text-zinc-500">{{ $branch->code }}</div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-normal align-top">
                                    @if ($branch->activeSellingStoreMapping && $branch->activeSellingStoreMapping->store)
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ app()->getLocale() === 'ar' ? $branch->activeSellingStoreMapping->store->name_ar : $branch->activeSellingStoreMapping->store->name_en }}</div>
                                            <div class="font-mono text-xs text-zinc-500">{{ $branch->activeSellingStoreMapping->store->code }}</div>
                                        </div>
                                    @else
                                        <span class="text-zinc-500">{{ __('No active selling-store mapping') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="align-top">
                                    <flux:badge size="sm" :color="$branch->activeSellingStoreMapping ? 'green' : 'amber'">{{ $branch->activeSellingStoreMapping ? __('Mapped') : __('Needs assignment') }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="align-top text-end">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                    @can('branches_stores.edit')
                                        <flux:button size="sm" variant="subtle" icon="arrows-right-left" wire:click="openMappingModal({{ $branch->id }})">{{ __('Assign POS selling & stock location') }}</flux:button>
                                    @endcan
                                    <flux:button size="xs" variant="subtle" icon="clock" wire:click="openHistoryModal({{ $branch->id }})" title="{{ __('Mapping History') }}" aria-label="{{ __('Mapping History') }}" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center py-4">{{ __('No branches are available for POS selling-location linkage.') }}</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                </div>
                {{ $branches->links() }}
            </flux:card>
        </section>
    @else
    <section data-branch-section="branch-masters" data-guide="branch-masters-workspace" class="space-y-4">

    <!-- Filters & Controls -->
    <flux:card id="branches-filters" class="scroll-mt-24 space-y-4" data-guide="branches-filters">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search code or name...') }}"
                size="sm"
            />

            <flux:select wire:model.live="statusFilter" size="sm" :label="__('Status Filter')">
                <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <!-- Data Table -->

    @if ($branches->isEmpty())
        <flux:card class="p-8 text-center space-y-3" data-guide="branches-empty">
            <div class="flex justify-center">
                <flux:icon icon="building-office-2" class="size-12 text-zinc-400" />
            </div>
            <flux:heading level="3" size="lg">{{ __('No Branches Configured') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-md mx-auto">
                {{ __('Add a branch to organize stores and daily operations.') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal">{{ __('Add Branch') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700" aria-label="{{ __('Branch directory table') }}">
            <flux:table data-guide="branch-masters-table" class="min-w-[80rem]">
            <flux:table.columns>
                <flux:table.column sortable class="min-w-28 whitespace-nowrap">{{ __('Code') }}</flux:table.column>
                <flux:table.column class="min-w-56"><span class="block whitespace-normal leading-tight">{{ __('Branch Name (AR / EN)') }}</span></flux:table.column>
                <flux:table.column class="min-w-32 whitespace-nowrap">{{ __('Phone') }}</flux:table.column>
                <flux:table.column class="min-w-32 whitespace-nowrap">{{ __('Timezone') }}</flux:table.column>
                <flux:table.column class="min-w-32 whitespace-nowrap">{{ __('Warehouses') }}</flux:table.column>
                <flux:table.column class="min-w-24 whitespace-nowrap">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="min-w-44 whitespace-nowrap text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($branches as $branch)
                    <flux:table.row :key="$branch->id">
                        <flux:table.cell class="font-mono font-medium">
                            {{ $branch->code }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $branch->name_ar }}</div>
                            <div class="text-xs text-zinc-500">{{ $branch->name_en }}</div>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs">
                            {{ $branch->phone ?: '—' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs">
                            <div class="font-mono">{{ $branch->timezone }}</div>
                            @if (filled($companyTimezone) && $branch->timezone !== $companyTimezone)
                                <flux:badge size="sm" variant="outline" color="amber" class="mt-1">{{ __('company.branch_override') }}</flux:badge>
                                <div class="mt-1 text-[11px] text-zinc-500">{{ __('company.override_help', ['branch_timezone' => $branch->timezone, 'company_timezone' => $companyTimezone]) }}</div>
                            @elseif (filled($companyTimezone) && $branch->timezone === $companyTimezone)
                                <flux:badge size="sm" variant="outline" color="blue" class="mt-1">{{ __('company.matches_company_default') }}</flux:badge>
                                <div class="mt-1 text-[11px] text-zinc-500">{{ __('company.match_help') }}</div>
                            @else
                                <div class="mt-1 text-[11px] text-zinc-500">{{ __('company.scope_not_recorded') }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" variant="subtle">
                                <span class="inline-flex items-center gap-1">
                                    <span data-testid="branch-warehouse-count-{{ $branch->id }}">{{ $branch->active_warehouse_count }}</span>
                                    <span data-testid="branch-warehouse-label-{{ $branch->id }}">{{ (int) $branch->active_warehouse_count === 1 ? __('Warehouse') : __('Warehouses') }}</span>
                                </span>
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($branch->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="font-medium">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-end space-x-1 rtl:space-x-reverse">
                            @can('branches_stores.edit')
                                <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditBranchModal({{ $branch->id }})" title="{{ __('Edit') }}" />
                            @endcan

                            @can('branches_stores.edit')
                                @if ($branch->status === 'active')
                                <flux:button size="xs" variant="subtle" icon="pause" wire:click="toggleBranchStatus({{ $branch->id }})" onclick="if (! window.confirm(@js(__('Deactivate branch :name? Its historical records are preserved.', ['name' => app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en])))) { event.preventDefault(); event.stopImmediatePropagation(); event.stopPropagation(); return false; }" title="{{ __('Deactivate') }}" />
                                @else
                                    <flux:button size="xs" variant="subtle" icon="play" wire:click="toggleBranchStatus({{ $branch->id }})" title="{{ __('Activate') }}" />
                                @endif
                            @endcan
                            @can('branches_stores.logical_delete')
                                <flux:button size="xs" variant="subtle" color="red" icon="trash" wire:click="deleteBranch({{ $branch->id }})" onclick="if (! window.confirm(@js(__('Submit deletion request for branch :name? It remains in history and becomes pending independent approval.', ['name' => app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en])))) { event.preventDefault(); event.stopImmediatePropagation(); event.stopPropagation(); return false; }" title="{{ __('Delete') }}" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
            </flux:table>
        </div>

        <div class="pt-4" data-guide="branches-pagination">
            {{ $branches->links() }}
        </div>
    @endif

    <!-- Create / Edit Branch Modal -->
    <flux:modal wire:model="showBranchModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingBranchId ? __('Edit Branch Master') : __('Create Branch Master') }}</flux:heading>
            <flux:subheading>{{ __('Define the branch code, bilingual name, contact information, and operating policies.') }}</flux:subheading>
        </div>

        <form wire:submit="saveBranch" novalidate class="space-y-4" data-guide="branch-master-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.code"
                    :label="__('Branch Code')"
                    placeholder="BR-01"
                    required
                />

                <flux:select wire:model="branchForm.status" :label="__('Status')" required>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="فرع الرياض الرئيسي"
                    required
                />

                <flux:input
                    wire:model="branchForm.name_en"
                    :label="__('English Name')"
                    placeholder="Riyadh Main Branch"
                    required
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branchForm.phone"
                    :label="__('Phone')"
                    :placeholder="__('e.g. 01012345678 or +20 1012345678')"
                    dir="ltr"
                />

                <flux:input
                    wire:model="branchForm.email"
                    :label="__('Email')"
                    type="email"
                    placeholder="branch@toyjoy.com"
                />

            </div>

            <flux:textarea
                wire:model="branchForm.address"
                :label="__('Address')"
                placeholder="{{ __('Physical branch address details...') }}"
                rows="2"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showBranchModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBranch"><span wire:loading.remove wire:target="saveBranch">{{ __('Save Branch') }}</span><span wire:loading wire:target="saveBranch">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>
    </section>
    @endif

    <!-- Assign POS selling & stock location Modal -->
    @if ($section === 'selling-store-mapping')
    <flux:modal wire:model="showMappingModal" class="md:w-[42rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Assign POS selling & stock location') }}</flux:heading>
            <flux:subheading>{{ __('Branch source') }} → {{ __('POS selling location and stock source') }} · <strong class="text-zinc-900 dark:text-zinc-100">{{ $mappingBranchName }}</strong></flux:subheading>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('The selected POS selling location is also the stock source for sales. The cashier shift must use this exact location.') }}</p>
        </div>

        <?php
    $availableSellingStores = Store::visibleTo(auth()->user())
        ->where('type', 'selling')
        ->where('status', 'active')
        ->when($mappingBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
        ->orderBy('code')
        ->get();
?>

        @if ($availableSellingStores->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p class="font-semibold">{{ __('No active POS selling locations are available for this branch.') }}</p>
                    <p class="text-sm">{{ __('Create an active selling location for this branch first; it will also be the stock source for POS sales.') }}</p>
                    @can('branches_stores.create')
                        <flux:button href="{{ route('admin.stores') }}" size="sm" variant="primary" wire:navigate>{{ __('Create selling location') }}</flux:button>
                    @endcan
                </div>
            </flux:callout>
        @endif
        <form wire:submit="saveSellingStoreMapping" class="space-y-4">
            <flux:select wire:model="selectedStoreId" :label="__('POS selling location / stock source')" :disabled="$availableSellingStores->isEmpty()" required>
                <flux:select.option value="">{{ __('Select an active POS selling location...') }}</flux:select.option>
                @foreach ($availableSellingStores as $st)
                    <flux:select.option :value="$st->id">
                        {{ $st->code }} · {{ app()->getLocale() === 'ar' ? $st->name_ar : $st->name_en }} · {{ __('Stock source') }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            @if ($availableSellingStores->isNotEmpty())
                <p class="text-xs text-zinc-500">{{ __('Only active selling locations belonging to this branch are shown. The shift, selling location, and stock source must match.') }}</p>
            @endif

            <flux:textarea
                wire:model="mappingApprovalNotes"
                :label="__('Change notes (optional)')"
                placeholder="{{ __('Add context for this direct selling-store mapping change.') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showMappingModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveSellingStoreMapping"><span wire:loading.remove wire:target="saveSellingStoreMapping">{{ __('Update Mapping') }}</span><span wire:loading wire:target="saveSellingStoreMapping">{{ __('Saving...') }}</span></flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- History Drawer / Modal -->
    <flux:modal wire:model="showHistoryModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Selling Store Mapping History') }}</flux:heading>
            <flux:subheading>{{ __('Historical POS selling store assignments for') }} <strong>{{ $historyBranchName }}</strong>.</flux:subheading>
        </div>

        @if (empty($historyRecords))
            <flux:text class="text-center py-4 text-zinc-500">{{ __('No mapping history records found for this branch.') }}</flux:text>
        @else
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                @foreach ($historyRecords as $record)
                    <div class="p-3 border rounded-lg border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200">{{ $record['store_code'] }} - {{ $record['store_name'] }}</span>
                            @if ($record['status'] === 'active')
                                <flux:badge size="sm" color="emerald">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Superceded') }}</flux:badge>
                            @endif
                        </div>

                        <div class="text-xs text-zinc-500 flex justify-between">
                            <span>{{ __('From:') }} {{ $record['effective_from'] }}</span>
                            <span>{{ __('To:') }} {{ $record['effective_to'] }}</span>
                        </div>

                        @if ($record['approval_notes'])
                            <div class="text-xs italic text-zinc-600 dark:text-zinc-400 pt-1">
                                "{{ $record['approval_notes'] }}"
                            </div>
                        @endif

                        <div class="text-[11px] text-zinc-400 text-end">
                            {{ __('Updated by:') }} {{ $record['creator_name'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end pt-2">
            <flux:button variant="subtle" wire:click="$set('showHistoryModal', false)">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>
    @endif
</x-app.page>
