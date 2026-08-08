<?php

use App\Models\User;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Cash Drawer Masters')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $branchFilter = 'all';
    public string $statusFilter = 'all';

    // Drawer Modal State
    public bool $showDrawerModal = false;
    public ?int $editingDrawerId = null;
    public array $drawerForm = [
        'branch_id' => '',
        'store_id' => '',
        'assigned_user_id' => '',
        'code' => '',
        'name_ar' => '',
        'name_en' => '',
        'status' => 'active',
        'policy_notes' => '',
    ];

    public function mount(): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateDrawerModal(): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.create');

        $this->editingDrawerId = null;
        $defaultBranchId = Branch::where('status', 'active')->first()?->id ?? '';

        $this->drawerForm = [
            'branch_id' => (string) $defaultBranchId,
            'store_id' => '',
            'assigned_user_id' => '',
            'code' => '',
            'name_ar' => '',
            'name_en' => '',
            'status' => 'active',
            'policy_notes' => 'TBD: Production cash drawer baseline pending shift rules and owner approval (BLK-006).',
        ];
        $this->resetValidation();
        $this->showDrawerModal = true;
    }

    public function openEditDrawerModal(int $id): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');

        $drawer = CashDrawer::findOrFail($id);
        $this->editingDrawerId = $drawer->id;
        $this->drawerForm = [
            'branch_id' => (string) $drawer->branch_id,
            'store_id' => (string) ($drawer->store_id ?? ''),
            'assigned_user_id' => (string) ($drawer->assigned_user_id ?? ''),
            'code' => $drawer->code,
            'name_ar' => $drawer->name_ar,
            'name_en' => $drawer->name_en,
            'status' => $drawer->status,
            'policy_notes' => $drawer->policy_notes ?? '',
        ];
        $this->resetValidation();
        $this->showDrawerModal = true;
    }

    public function saveDrawer(SaveCashDrawerAction $action): void
    {
        Gate::authorize($this->editingDrawerId ? 'drawers_payments_tax_numbering_printers.edit' : 'drawers_payments_tax_numbering_printers.create');

        $validated = $this->validate([
            'drawerForm.branch_id' => ['required', 'exists:branches,id'],
            'drawerForm.store_id' => ['nullable', 'exists:stores,id'],
            'drawerForm.assigned_user_id' => ['nullable', 'exists:users,id'],
            'drawerForm.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('cash_drawers', 'code')
                    ->where('branch_id', $this->drawerForm['branch_id'])
                    ->ignore($this->editingDrawerId),
            ],
            'drawerForm.name_ar' => ['required', 'string', 'max:255'],
            'drawerForm.name_en' => ['required', 'string', 'max:255'],
            'drawerForm.status' => ['required', 'in:active,inactive,maintenance'],
            'drawerForm.policy_notes' => ['nullable', 'string'],
        ])['drawerForm'];

        try {
            $action->execute($validated, $this->editingDrawerId);
            Flux::toast(variant: 'success', text: $this->editingDrawerId ? __('Cash drawer updated successfully.') : __('Cash drawer created successfully.'));
            $this->showDrawerModal = false;
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function toggleDrawerStatus(int $id, string $status, SaveCashDrawerAction $action): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');

        try {
            $action->toggleStatus($id, $status);
            Flux::toast(variant: 'success', text: __('Cash drawer status updated successfully.'));
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function deleteDrawer(int $id, SaveCashDrawerAction $action): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.logical_delete');

        try {
            $action->delete($id);
            Flux::toast(variant: 'success', text: __('Cash drawer deleted successfully.'));
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function rendering(): void
    {
        // Compute available stores filtered by selected branch in modal form if chosen
    }
}; ?>

<x-app.page
    :title="__('Cash Drawer Masters')"
    :description="__('Configure branch-scoped cash drawers and default assignments for POS operations. Shifts and opening balances remain deferred (BLK-006).')"
    max-width="7xl"
    class="space-y-6"
    data-guide="drawers-header"
>
    <x-slot:actions>
        @can('drawers_payments_tax_numbering_printers.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateDrawerModal" data-guide="drawers-add-action">{{ __('Add Cash Drawer') }}</flux:button>
        @endcan
    </x-slot:actions>

    <!-- BLK-006 & Local Development Notice Banner -->
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30 text-xs text-amber-800 dark:text-amber-300">
        <div class="flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
            <div class="space-y-1">
                <p class="font-semibold">
                    {{ __('Local Baseline — Reversible Cash Drawer Masters (TSK-007 / BLK-006)') }}
                </p>
                <p>
                    {{ __('Cash drawers are registered per branch and optional store/cashier link. Opening balances, shift sessions, cash movements, and blind close reconciliation are explicitly deferred to DM 3.3 (TSK-025). Shift dependency guards remain set to TBD.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" data-guide="drawers-filters">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            <div class="w-full sm:w-72">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Search drawers by code or name...')"
                />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="branchFilter" :label="null">
                    <option value="all">{{ __('All Branches') }}</option>
                    @foreach(Branch::visibleTo(auth()->user())->orderBy('name_en')->get() as $branchOption)
                        <option value="{{ $branchOption->id }}">{{ app()->getLocale() === 'ar' ? $branchOption->name_ar : $branchOption->name_en }} ({{ $branchOption->code }})</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-full sm:w-44">
                <flux:select wire:model.live="statusFilter" :label="null">
                    <option value="all">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="maintenance">{{ __('Maintenance') }}</option>
                </flux:select>
            </div>
        </div>
    </div>

    @php
        $query = CashDrawer::visibleTo(auth()->user())->with(['branch', 'store', 'assignedUser']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('name_ar', 'like', '%' . $search . '%')
                  ->orWhere('name_en', 'like', '%' . $search . '%');
            });
        }

        if ($branchFilter !== 'all') {
            $query->where('branch_id', $branchFilter);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $drawers = $query->orderBy('branch_id')->orderBy('code')->paginate(15);
        $branches = Branch::visibleTo(auth()->user())->where('status', 'active')->orderBy('name_en')->get();
        $allStores = Store::visibleTo(auth()->user())->where('status', 'active')->orderBy('name_en')->get();
        $users = User::orderBy('name')->get();
    @endphp

    <!-- Cash Drawers Data Table -->
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800 overflow-hidden shadow-xs" data-guide="drawers-table">
        @if($drawers->count() > 0)
            <div class="overflow-x-auto">
                <table class="data-table w-full text-start text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Code') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Drawer Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Branch') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Linked Store') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Assigned Cashier / User') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($drawers as $drawer)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-primary">
                                    {{ $drawer->code }}
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ app()->getLocale() === 'ar' ? $drawer->name_ar : $drawer->name_en }}
                                    <div class="text-xs text-zinc-400 font-normal">
                                        {{ app()->getLocale() === 'ar' ? $drawer->name_en : $drawer->name_ar }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if($drawer->branch)
                                        <span class="inline-flex items-center gap-1 font-medium">
                                            <flux:icon name="building-office-2" class="size-3.5 text-zinc-400" />
                                            {{ app()->getLocale() === 'ar' ? $drawer->branch->name_ar : $drawer->branch->name_en }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 font-italic">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if($drawer->store)
                                        <span class="inline-flex items-center gap-1 text-xs">
                                            <flux:icon name="building-storefront" class="size-3.5 text-zinc-400" />
                                            {{ app()->getLocale() === 'ar' ? $drawer->store->name_ar : $drawer->store->name_en }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if($drawer->assignedUser)
                                        <span class="inline-flex items-center gap-1 text-xs font-medium">
                                            <flux:icon name="user" class="size-3.5 text-zinc-400" />
                                            {{ $drawer->assignedUser->name }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400 text-xs font-italic">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($drawer->status === 'active')
                                        <x-status.badge status="active" :label="__('Active')" />
                                    @elseif($drawer->status === 'maintenance')
                                        <x-status.badge status="warning" :label="__('Maintenance')" />
                                    @else
                                        <x-status.badge status="inactive" :label="__('Inactive')" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('drawers_payments_tax_numbering_printers.edit')
                                            <flux:button size="xs" variant="subtle" icon="pencil" wire:click="openEditDrawerModal({{ $drawer->id }})">{{ __('Edit') }}</flux:button>
                                            @if($drawer->status === 'active')
                                                <flux:button size="xs" variant="subtle" class="text-amber-600 dark:text-amber-400" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'maintenance')">{{ __('Maintenance') }}</flux:button>
                                                <flux:button size="xs" variant="subtle" class="text-red-600 dark:text-red-400" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'inactive')">{{ __('Deactivate') }}</flux:button>
                                            @else
                                                <flux:button size="xs" variant="subtle" class="text-emerald-600 dark:text-emerald-400" wire:click="toggleDrawerStatus({{ $drawer->id }}, 'active')">{{ __('Activate') }}</flux:button>
                                            @endif
                                        @endcan
                                        @can('drawers_payments_tax_numbering_printers.logical_delete')
                                            <flux:button size="xs" variant="subtle" class="text-red-700 dark:text-red-300" wire:click="deleteDrawer({{ $drawer->id }})" wire:confirm="{{ __('Are you sure you want to delete this cash drawer?') }}">{{ __('Delete') }}</flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700" data-guide="drawers-pagination">
                {{ $drawers->links() }}
            </div>
        @else
            <x-state.empty
                :title="__('No Cash Drawers Found')"
                :description="__('No cash drawers match your search or active filters.')"
                icon="inbox-stack"
            >
                <x-slot:actions>
                    <flux:button variant="primary" icon="plus" wire:click="openCreateDrawerModal">
                        {{ __('Create Cash Drawer') }}
                    </flux:button>
                </x-slot:actions>
            </x-state.empty>
        @endif
    </div>

    <!-- Modal: Create / Edit Cash Drawer -->
    <flux:modal wire:model="showDrawerModal" class="max-w-xl space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $editingDrawerId ? __('Edit Cash Drawer') : __('Add Cash Drawer') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Configure cash drawer identifier, branch assignment, store, and default cashier.') }}
            </flux:subheading>
        </div>

        <form wire:submit="saveDrawer" novalidate class="space-y-4">
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle" title="{{ __('Validation Errors') }}">
                    <ul class="list-disc space-y-1 ps-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:model="drawerForm.branch_id"
                    :label="__('Branch')"
                    required
                >
                    <option value="">{{ __('Select Branch') }}</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }} ({{ $b->code }})</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="drawerForm.code"
                    :label="__('Drawer Code')"
                    placeholder="CDR-001"
                    required
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="drawerForm.name_ar"
                    :label="__('Arabic Name')"
                    placeholder="درج النقدية الأول"
                    required
                    dir="rtl"
                />

                <flux:input
                    wire:model="drawerForm.name_en"
                    :label="__('English Name')"
                    placeholder="Main Cash Drawer 1"
                    required
                    dir="ltr"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:model="drawerForm.store_id"
                    :label="__('Linked Store (Optional)')"
                >
                    <option value="">{{ __('None / Branch Level') }}</option>
                    @foreach($allStores as $st)
                        @if(empty($drawerForm['branch_id']) || (int) $st->branch_id === (int) $drawerForm['branch_id'])
                            <option value="{{ $st->id }}">{{ app()->getLocale() === 'ar' ? $st->name_ar : $st->name_en }} ({{ $st->code }})</option>
                        @endif
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="drawerForm.assigned_user_id"
                    :label="__('Default Assigned Cashier (Optional)')"
                >
                    <option value="">{{ __('Unassigned / Any Cashier') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username ?? $u->email }})</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select
                    wire:model="drawerForm.status"
                    :label="__('Status')"
                    required
                >
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="maintenance">{{ __('Maintenance') }}</option>
                </flux:select>
            </div>

            <flux:textarea
                wire:model="drawerForm.policy_notes"
                :label="__('Policy / Operational Notes (BLK-006 / Local TBD)')"
                rows="2"
            />

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="subtle" wire:click="$set('showDrawerModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingDrawerId ? __('Save Changes') : __('Create Cash Drawer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
