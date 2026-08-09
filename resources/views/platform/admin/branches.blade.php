<?php

use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Store;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Branch Management')] class extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

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
        'timezone' => 'UTC',
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
            'timezone' => 'UTC',
            'status' => 'active',
            'policy_notes' => 'TBD: Production branch details pending owner approval (BLK-006).',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function openEditBranchModal(int $id): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::findOrFail($id);
        $this->editingBranchId = $branch->id;
        $this->branchForm = [
            'code' => $branch->code,
            'name_ar' => $branch->name_ar,
            'name_en' => $branch->name_en,
            'phone' => $branch->phone ?? '',
            'email' => $branch->email ?? '',
            'address' => $branch->address ?? '',
            'timezone' => $branch->timezone ?? 'UTC',
            'status' => $branch->status,
            'policy_notes' => $branch->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showBranchModal = true;
    }

    public function saveBranch(SaveBranchAction $action): void
    {
        Gate::authorize($this->editingBranchId ? 'branches_stores.edit' : 'branches_stores.create');

        $validated = $this->validate([
            'branchForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('branches', 'code')->ignore($this->editingBranchId),
            ],
            'branchForm.name_ar' => ['required', 'string', 'max:255'],
            'branchForm.name_en' => ['required', 'string', 'max:255'],
            'branchForm.phone' => ['nullable', 'string', 'max:50'],
            'branchForm.email' => ['nullable', 'email', 'max:255'],
            'branchForm.address' => ['nullable', 'string'],
            'branchForm.timezone' => ['required', 'string', 'max:100'],
            'branchForm.status' => ['required', 'in:active,inactive'],
            'branchForm.policy_notes' => ['nullable', 'string'],
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

    public function deleteBranch(int $id, SaveBranchAction $action): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $action->delete($id);
            Flux::toast(variant: 'success', text: __('Branch deleted successfully.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openMappingModal(int $branchId): void
    {
        Gate::authorize('branches_stores.edit');

        $branch = Branch::findOrFail($branchId);
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

        $branch = Branch::with(['sellingStoreMappings.store', 'sellingStoreMappings.creator'])->findOrFail($branchId);
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
        $query = Branch::visibleTo(auth()->user())->with(['stores', 'activeSellingStoreMapping.store']);

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
        ]);
    }
}; ?>

<x-app.page
    :title="__('Branch Masters')"
    :description="__('Manage commercial branch locations and their authorized POS selling store assignments.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="branches-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="branches-filters">
            @can('branches_stores.create')
                <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal" data-guide="branches-add-action">{{ __('Add Branch') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <!-- TBD / BLK-006 Tracking Banner -->
    <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Development Baseline Only (BLK-006 Unresolved)') }}">
        {{ __('This is a local schema and UI slice (TSK-006). Official branch codes, locations, and operational policies are TBD pending owner input.') }}
    </flux:callout>

    <!-- Filters & Controls -->
    <flux:card id="branches-filters" class="scroll-mt-24 space-y-4" data-guide="branches-filters">
        <div class="grid gap-4 sm:grid-cols-3">
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
                {{ __('No local branch records found. Click below to add a local branch for testing, or wait for official master data (BLK-006).') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateBranchModal">{{ __('Create Local Branch') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <x-tables.bulk-actions :page-ids="$branches->pluck('id')->all()" :selected-ids="$selectedIds" :selected-count="count($selectedIds)" :page-count="$branches->count()">
            <x-slot:actions>
                @can('branches_stores.edit')
                    <flux:button type="button" size="sm" variant="subtle" wire:click="bulkToggleBranchStatus" wire:confirm="{{ __('Toggle status for the selected branches?') }}">{{ __('Toggle status') }}</flux:button>
                @endcan
            </x-slot:actions>
        </x-tables.bulk-actions>
        <flux:table data-guide="branches-table">
            <flux:table.columns>
                <flux:table.column><span class="sr-only">{{ __('Select') }}</span></flux:table.column>
                <flux:table.column sortable>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Branch Name (AR / EN)') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Timezone') }}</flux:table.column>
                <flux:table.column>{{ __('Stores') }}</flux:table.column>
                <flux:table.column>{{ __('POS Selling Store') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($branches as $branch)
                    <flux:table.row :key="$branch->id">
                        <flux:table.cell><input type="checkbox" value="{{ $branch->id }}" wire:model.live="selectedIds" aria-label="{{ __('Select branch :code', ['code' => $branch->code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" /></flux:table.cell>
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

                        <flux:table.cell class="text-xs font-mono">
                            {{ $branch->timezone }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" variant="subtle">
                                {{ $branch->stores->count() }} {{ __('stores') }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($branch->activeSellingStoreMapping && $branch->activeSellingStoreMapping->store)
                                <div class="flex items-center gap-1.5">
                                    <flux:badge size="sm" variant="solid" color="emerald">
                                        {{ $branch->activeSellingStoreMapping->store->code }}
                                    </flux:badge>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-[120px]">
                                        {{ app()->getLocale() === 'ar' ? $branch->activeSellingStoreMapping->store->name_ar : $branch->activeSellingStoreMapping->store->name_en }}
                                    </span>
                                </div>
                            @else
                                <flux:badge size="sm" variant="outline" color="zinc">
                                    {{ __('Unmapped') }}
                                </flux:badge>
                            @endif
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
                                <flux:button size="xs" variant="subtle" icon="arrows-right-left" wire:click="openMappingModal({{ $branch->id }})" title="{{ __('Map POS Store') }}" />
                            @endcan
                            <flux:button size="xs" variant="subtle" icon="clock" wire:click="openHistoryModal({{ $branch->id }})" title="{{ __('Mapping History') }}" />

                            @can('branches_stores.edit')
                                @if ($branch->status === 'active')
                                    <flux:button size="xs" variant="subtle" icon="pause" wire:click="toggleBranchStatus({{ $branch->id }})" title="{{ __('Deactivate') }}" />
                                @else
                                    <flux:button size="xs" variant="subtle" icon="play" wire:click="toggleBranchStatus({{ $branch->id }})" title="{{ __('Activate') }}" />
                                @endif
                            @endcan
                            @can('branches_stores.logical_delete')
                                <flux:button size="xs" variant="subtle" color="red" icon="trash" wire:click="deleteBranch({{ $branch->id }})" wire:confirm="{{ __('Are you sure you want to delete this branch?') }}" title="{{ __('Delete') }}" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="pt-4" data-guide="branches-pagination">
            {{ $branches->links() }}
        </div>
    @endif

    <!-- Create / Edit Branch Modal -->
    <flux:modal wire:model="showBranchModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingBranchId ? __('Edit Branch Master') : __('Create Branch Master') }}</flux:heading>
            <flux:subheading>{{ __('Define branch code, bilingual name, contact information, and local policies.') }}</flux:subheading>
        </div>

        <form wire:submit="saveBranch" class="space-y-4">
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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input
                    wire:model="branchForm.phone"
                    :label="__('Phone')"
                    placeholder="+966500000000"
                />

                <flux:input
                    wire:model="branchForm.email"
                    :label="__('Email')"
                    type="email"
                    placeholder="branch@toyjoy.com"
                />

                <flux:input
                    wire:model="branchForm.timezone"
                    :label="__('Timezone')"
                    placeholder="Asia/Riyadh"
                    required
                />
            </div>

            <flux:textarea
                wire:model="branchForm.address"
                :label="__('Address')"
                placeholder="{{ __('Physical branch address details...') }}"
                rows="2"
            />

            <flux:textarea
                wire:model="branchForm.policy_notes"
                :label="__('Policy / TBD Notes')"
                rows="2"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showBranchModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Branch') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Map Selling Store Modal -->
    <flux:modal wire:model="showMappingModal" class="md:w-140 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Map POS Selling Store') }}</flux:heading>
            <flux:subheading>{{ __('Assign an active POS selling store to') }} <strong class="text-zinc-900 dark:text-zinc-100">{{ $mappingBranchName }}</strong>.</flux:subheading>
        </div>

        <?php
    $availableSellingStores = Store::visibleTo(auth()->user())
        ->where('type', 'selling')
        ->where('status', 'active')
        ->when($mappingBranchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
        ->orderBy('code')
        ->get();
?>

        <form wire:submit="saveSellingStoreMapping" class="space-y-4">
            <flux:select wire:model="selectedStoreId" :label="__('Selling Store')" required>
                <flux:select.option value="">{{ __('Select an active selling store...') }}</flux:select.option>
                @foreach ($availableSellingStores as $st)
                    <flux:select.option :value="$st->id">
                        {{ $st->code }} - {{ app()->getLocale() === 'ar' ? $st->name_ar : $st->name_en }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="mappingApprovalNotes"
                :label="__('Approval / Reason Notes')"
                placeholder="{{ __('Enter context or manager approval reason for store mapping change...') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showMappingModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Update Mapping') }}</flux:button>
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
</x-app.page>
