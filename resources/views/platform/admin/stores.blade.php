<?php

use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Store;
use App\Support\Bulk\WithBulkSelection;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Store & Inventory Mapping Masters')] class extends Component
{
    use WithBulkSelection, WithPagination;

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

        $store = Store::findOrFail($id);
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
            'storeForm.branch_id' => ['nullable', 'exists:branches,id'],
            'storeForm.type' => ['required', 'in:'.implode(',', SaveStoreAction::ALLOWED_TYPES)],
            'storeForm.name_ar' => ['required', 'string', 'max:255'],
            'storeForm.name_en' => ['required', 'string', 'max:255'],
            'storeForm.status' => ['required', 'in:active,inactive'],
            'storeForm.allows_negative_stock' => ['boolean'],
            'storeForm.policy_notes' => ['nullable', 'string'],
        ])['storeForm'];

        try {
            $action->execute($validated, $this->editingStoreId);
            Flux::toast(variant: 'success', text: $this->editingStoreId ? __('Store updated successfully.') : __('Store created successfully.'));
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

    public function bulkToggleStoreStatus(SaveStoreAction $action): void
    {
        Gate::authorize('branches_stores.edit');

        try {
            $count = $this->forEachBulkSelected(function (int $id) use ($action): void {
                $action->toggleStatus($id);
            });
            $this->clearBulkSelection();
            Flux::toast(variant: 'success', text: __('Store status updated for :count records.', ['count' => $count]));
        } catch (Exception $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function deleteStore(int $id, PlatformSettingsApprovalAction $approvalAction): void
    {
        Gate::authorize('branches_stores.logical_delete');

        try {
            $store = Store::query()->findOrFail($id);
            $approvalAction->request('store_delete', $store->id, ['deleted' => true], $store->getAttributes(), $store->branch_id, $store->id);
            Flux::toast(variant: 'success', text: __('Store deletion submitted for independent approval.'));
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openStoreMappingModal(int $storeId): void
    {
        Gate::authorize('branches_stores.edit');

        $store = Store::findOrFail($storeId);
        if ($store->type !== 'selling') {
            Flux::toast(variant: 'danger', text: __('Only stores of type Selling Store can be mapped to branches for POS operations.'));

            return;
        }

        if ($store->status !== 'active') {
            Flux::toast(variant: 'danger', text: __('Selling store must be active to map to a branch.'));

            return;
        }

        $activeMapping = BranchSellingStore::where('store_id', $store->id)->where('status', 'active')->first();

        $this->mappingStoreId = $store->id;
        $this->mappingStoreName = app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en;
        $this->selectedBranchId = $activeMapping?->branch_id ?? $store->branch_id;
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
            'stores' => $query->orderBy('code')->paginate(10),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Store Masters & Branch Mapping')"
    :description="__('Manage physical/logical locations (point of sale, main warehouse, service center, damaged & defective stock, stock in transit) and POS branch assignments.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="stores-header"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="stores-filters">
            @can('branches_stores.create')
                <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal" data-guide="stores-add-action">{{ __('Add Store') }}</flux:button>
            @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <!-- Filters & Search -->
    <flux:card id="stores-filters" class="scroll-mt-24 space-y-4" data-guide="stores-filters">
        <div class="grid gap-4 sm:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search code or name...') }}"
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

            <flux:select wire:model.live="typeFilter" size="sm" :label="__('Location Type')">
                <flux:select.option value="all">{{ __('All Location Types') }}</flux:select.option>
                <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                <flux:select.option value="warehouse">{{ __('Main Warehouse') }}</flux:select.option>
                <flux:select.option value="party">{{ __('Service Center') }}</flux:select.option>
                <flux:select.option value="damaged">{{ __('Damaged & Defective Stock') }}</flux:select.option>
                <flux:select.option value="transit">{{ __('Stock in Transit') }}</flux:select.option>
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
            <flux:heading level="3" size="lg">{{ __('No Stores Configured') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-md mx-auto">
                {{ __('Add a store to make it available for sales, inventory, or service operations.') }}
            </flux:text>
            <div class="pt-2">
                @can('branches_stores.create')
                    <flux:button icon="plus" variant="primary" size="sm" wire:click="openCreateStoreModal">{{ __('Add store') }}</flux:button>
                @endcan
            </div>
        </flux:card>
    @else
        <x-tables.bulk-actions :page-ids="$stores->pluck('id')->all()" :selected-ids="$selectedIds" :selected-count="count($selectedIds)" :page-count="$stores->count()">
            <x-slot:actions>
                @can('branches_stores.edit')
                    <flux:button type="button" size="sm" variant="subtle" wire:click="bulkToggleStoreStatus" wire:confirm="{{ __('Toggle status for the selected stores?') }}">{{ __('Toggle status') }}</flux:button>
                @endcan
            </x-slot:actions>
        </x-tables.bulk-actions>
        <flux:table data-guide="stores-table">
            <flux:table.columns>
                <flux:table.column><span class="sr-only">{{ __('Select') }}</span></flux:table.column>
                <flux:table.column sortable>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Store Name (AR / EN)') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Branch Context') }}</flux:table.column>
                <flux:table.column>{{ __('Mapped POS Branch') }}</flux:table.column>
                <flux:table.column>{{ __('Negative Stock') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="text-end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($stores as $st)
                    <?php
                $activeBranchMapping = $st->sellingStoreMappings->firstWhere('status', 'active');
?>
                    <flux:table.row :key="$st->id">
                        <flux:table.cell><input type="checkbox" value="{{ $st->id }}" wire:model.live="selectedIds" aria-label="{{ __('Select store :code', ['code' => $st->code]) }}" class="size-4 rounded border-border text-primary focus:ring-primary" /></flux:table.cell>
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
                                    <flux:badge size="sm" variant="subtle" color="zinc">{{ __('Point of Sale (POS)') }}</flux:badge>
                                    @break
                                @case('warehouse')
                                    <flux:badge size="sm" variant="subtle" color="zinc">{{ __('Main Warehouse') }}</flux:badge>
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

                        <flux:table.cell class="text-xs">
                            @if ($st->branch)
                                <span class="font-mono font-medium text-zinc-700 dark:text-zinc-300">{{ $st->branch->code }}</span>
                                <span class="text-zinc-500">({{ app()->getLocale() === 'ar' ? $st->branch->name_ar : $st->branch->name_en }})</span>
                            @else
                                <span class="text-zinc-400 font-italic">{{ __('Central / Unassigned') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($st->type === 'selling')
                                @if ($activeBranchMapping && $activeBranchMapping->branch)
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="sm" variant="solid" color="emerald">
                                            {{ $activeBranchMapping->branch->code }}
                                        </flux:badge>
                                        <span class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-[120px]">
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
                            @if ($st->status === 'active')
                                <flux:badge size="sm" color="emerald" inset="top" class="font-medium">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-end space-x-1 rtl:space-x-reverse">
                            @can('branches_stores.edit')
                                <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditStoreModal({{ $st->id }})" title="{{ __('Edit') }}" />
                                @if ($st->type === 'selling' && $st->status === 'active')
                                    <flux:button size="xs" variant="subtle" icon="arrows-right-left" wire:click="openStoreMappingModal({{ $st->id }})" title="{{ __('Map to Branch') }}" />
                                @endif
                                @if ($st->status === 'active')
                                    <flux:button size="xs" variant="subtle" icon="pause" wire:click="toggleStoreStatus({{ $st->id }})" title="{{ __('Deactivate') }}" />
                                @else
                                    <flux:button size="xs" variant="subtle" icon="play" wire:click="toggleStoreStatus({{ $st->id }})" title="{{ __('Activate') }}" />
                                @endif
                            @endcan
                            @can('branches_stores.logical_delete')
                                <flux:button size="xs" variant="subtle" color="red" icon="trash" wire:click="deleteStore({{ $st->id }})" wire:confirm="{{ __('Are you sure you want to delete this store?') }}" title="{{ __('Delete') }}" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="pt-4" data-guide="stores-pagination">
            {{ $stores->links() }}
        </div>
    @endif

    <!-- Create / Edit Store Modal -->
    <flux:modal wire:model="showStoreModal" class="md:w-160 space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingStoreId ? __('Edit Store Master') : __('Create Store Master') }}</flux:heading>
            <flux:subheading>{{ __('Define location code, location type, bilingual names, branch association, and negative stock policy.') }}</flux:subheading>
        </div>

        <form wire:submit="saveStore" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input
                    wire:model="storeForm.code"
                    :label="__('Store Code')"
                    placeholder="STR-01"
                    required
                />

                <flux:select wire:model="storeForm.type" :label="__('Location Type')" required>
                    <flux:select.option value="selling">{{ __('Point of Sale (POS)') }}</flux:select.option>
                    <flux:select.option value="warehouse">{{ __('Main Warehouse') }}</flux:select.option>
                    <flux:select.option value="party">{{ __('Service Center') }}</flux:select.option>
                    <flux:select.option value="damaged">{{ __('Damaged & Defective Stock') }}</flux:select.option>
                    <flux:select.option value="transit">{{ __('Stock in Transit') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="storeForm.status" :label="__('Status')" required>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model="storeForm.branch_id" :label="__('Branch Association (Optional)')">
                    <flux:select.option value="">{{ __('Central / No Direct Branch') }}</flux:select.option>
                    @foreach ($branchesList as $b)
                        <flux:select.option :value="$b->id">
                            {{ $b->code }} - {{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex items-center pt-6">
                    <flux:checkbox
                        wire:model="storeForm.allows_negative_stock"
                        :label="__('Allow negative stock')"
                    />
                </div>
            </div>

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

            <flux:textarea
                wire:model="storeForm.policy_notes"
                :label="__('Notes')"
                rows="2"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Store') }}</flux:button>
            </div>
        </form>
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
                :label="__('Approval / Reason Notes')"
                placeholder="{{ __('Enter context or manager approval reason for store mapping change...') }}"
                rows="3"
            />

            <div class="flex justify-end gap-3 pt-4">
                <flux:button variant="subtle" wire:click="$set('showStoreMappingModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Update Mapping') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
