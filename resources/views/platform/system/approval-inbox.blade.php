<?php

use App\Models\User;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ApprovalPresentation;
use App\Modules\Platform\Support\UiLabel;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Approval Inbox')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $state = 'pending';

    public string $sourceType = '';

    public string $branchId = '';

    public string $storeId = '';

    #[Locked]
    public ?int $selectedApprovalId = null;

    public bool $approvalModalOpen = false;

    public string $decisionReason = '';

    public function mount(): void
    {
        Gate::authorize('view-approval-inbox');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state', 'sourceType', 'branchId', 'storeId'], true)) {
            $this->resetPage();
        }
    }

    public function showApproval(int $approvalId): void
    {
        $approval = $this->baseQuery()->findOrFail($approvalId);
        Gate::authorize('view', $approval);
        $this->selectedApprovalId = $approval->id;
        $this->approvalModalOpen = true;
        $this->decisionReason = '';
        $this->resetValidation();
    }

    public function closeApproval(): void
    {
        $this->approvalModalOpen = false;
        $this->selectedApprovalId = null;
        $this->decisionReason = '';
        $this->resetValidation();
    }

    public function updatedApprovalModalOpen(bool $isOpen): void
    {
        if (! $isOpen && $this->selectedApprovalId !== null) {
            $this->closeApproval();
        }
    }

    public function approve(): void
    {
        try {
            $approval = $this->selectedPendingApproval();
            Gate::authorize('decide', $approval);
            app(DecideApprovalSource::class)->approve($approval);
            session()->flash('approval-success', __('The source was approved and its audit trail was recorded.'));
            $this->closeApproval();
        } catch (AuthorizationException) {
            $this->addError('approval', __('Permission denied'));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('Unable to complete the request. Please review the data and try again.');
            $this->addError('approval', (string) $message);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('approval', __('Unable to complete the request. Please review the data and try again.'));
        }
    }

    public function reject(): void
    {
        try {
            $approval = $this->selectedPendingApproval();
            Gate::authorize('decide', $approval);
            if (! app(DecideApprovalSource::class)->canReject($approval)) {
                $this->addError('decisionReason', __('This source does not expose a rejection transition.'));

                return;
            }
            $validated = $this->validate(['decisionReason' => ['required', 'string', 'min:3', 'max:1000']]);
            app(DecideApprovalSource::class)->reject($approval, $validated['decisionReason']);
            session()->flash('approval-success', __('The source was rejected and its audit trail was recorded.'));
            $this->closeApproval();
        } catch (AuthorizationException) {
            $this->addError('approval', __('Permission denied'));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first()
                ?? __('Unable to complete the request. Please review the data and try again.');
            $this->addError(isset($errors['decisionReason']) ? 'decisionReason' : 'approval', (string) $message);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('approval', __('Unable to complete the request. Please review the data and try again.'));
        }
    }

    public function render()
    {
        Gate::authorize('view-approval-inbox');
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $base = $this->baseQuery();
        $approvals = (clone $base)
            ->with(['requester:id,name', 'approver:id,name'])
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('uuid', 'like', $search)
                        ->orWhere('source_type', 'like', $search)
                        ->orWhere('source_id', 'like', $search)
                        ->orWhere('requested_action', 'like', $search)
                        ->orWhere('reason_text', 'like', $search);
                });
            })
            ->when($this->state !== '', fn (Builder $query) => $query->where('approval_state', $this->state))
            ->when($this->sourceType !== '', fn (Builder $query) => $query->where('source_type', $this->sourceType))
            ->when($this->branchId !== '', fn (Builder $query) => $query->where('branch_id', (int) $this->branchId))
            ->when($this->storeId !== '', fn (Builder $query) => $query->where('store_id', (int) $this->storeId))
            ->latest('requested_at')
            ->paginate(20);

        $selectedApproval = $this->selectedApprovalId === null
            ? null
            : (clone $base)->with(['requester:id,name', 'approver:id,name'])->find($this->selectedApprovalId);
        if ($selectedApproval !== null) {
            Gate::authorize('view', $selectedApproval);
        }

        $selectedShift = $selectedApproval?->source_type === 'pos_shifts' && Gate::allows('shifts_cash_movements.approve')
            ? PosShift::query()->visibleTo($user)
                ->where('variance_approval_record_id', $selectedApproval->id)
                ->find($selectedApproval->source_id)
            : null;
        $shiftSubmission = $selectedShift?->closingSubmissions()->orderByDesc('attempt')->first();

        return view('platform.system.approval-inbox', [
            'approvals' => $approvals,
            'selectedApproval' => $selectedApproval,
            'selectedShift' => $selectedShift,
            'shiftSubmission' => $shiftSubmission,
            'sourceTypes' => (clone $base)->distinct()->orderBy('source_type')->pluck('source_type'),
            'branches' => Branch::query()->visibleTo($user)->orderBy('code')->get(),
            'stores' => Store::query()->visibleTo($user)->orderBy('code')->get(),
            'canDecide' => $selectedApproval !== null && Gate::allows('decide', $selectedApproval),
            'canReject' => $selectedApproval !== null && app(DecideApprovalSource::class)->canReject($selectedApproval),
            'sourceUrl' => $selectedApproval === null ? null : app(DecideApprovalSource::class)->sourceRoute($selectedApproval),
            'reviewDetails' => $selectedApproval === null ? null : ApprovalPresentation::reviewDetails($selectedApproval, $user),
        ]);
    }

    private function selectedPendingApproval(): ApprovalRecord
    {
        $approval = $this->baseQuery()->findOrFail($this->selectedApprovalId);
        if ($approval->approval_state !== ApprovalState::Pending) {
            throw ValidationException::withMessages(['approval' => __('This approval request was already decided. Reload the inbox.')]);
        }

        return $approval;
    }

    private function baseQuery(): Builder
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $query = ApprovalRecord::query()->visibleTo($user);
        if ($user->is_super_admin || $user->hasPermission('audit_logs.view')) {
            return $query;
        }

        $permissionCodes = $user->roles()
            ->where('roles.status', 'active')
            ->with('permissions:id,code,status')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->where('status', 'active')->pluck('code'))
            ->unique()->values()->all();

        return $query->where(function (Builder $visible) use ($user, $permissionCodes): void {
            $visible->where('requester_id', $user->id)
                ->orWhereIn('request_permission', $permissionCodes)
                ->orWhereIn('decision_permission', $permissionCodes);
        });
    }
}; ?>

<x-app.page :title="__('Approval inbox')" :description="__('Review source-linked requests within your permissions and branch or store scope.')" max-width="7xl" class="space-y-5">
    @if (session('approval-success'))
        <flux:callout variant="info" icon="check-circle">{{ session('approval-success') }}</flux:callout>
    @endif

    <x-tables.data-panel :title="__('Approval requests')" :description="__('Decisions call the source domain action, so posting and audit remain atomic.')">
        <x-slot:toolbar>
            <x-tables.filter-bar>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <flux:input wire:model.live.debounce.350ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('UUID, source, action, or reason')" />
                    <flux:select wire:model.live="state" :label="__('State')">
                        <option value="">{{ __('All states') }}</option>
                        @foreach (ApprovalState::cases() as $approvalState)<option value="{{ $approvalState->value }}">{{ UiLabel::status($approvalState->value) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model.live="sourceType" :label="__('Source type')"><option value="">{{ __('All source types') }}</option>@foreach($sourceTypes as $type)<option value="{{ $type }}">{{ ApprovalPresentation::source($type) }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="branchId" :label="__('Branch')"><option value="">{{ __('All visible branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="storeId" :label="__('Store')"><option value="">{{ __('All visible stores') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }}</option>@endforeach</flux:select>
                </div>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if($approvals->isEmpty())
            <x-state.empty :title="__('No approval requests found')" :description="__('No requests match your permissions, scope, and filters.')" icon="check-circle" />
        @else
            <div class="hidden overflow-x-auto md:block">
                <flux:table class="min-w-[980px] table-fixed" aria-label="{{ __('Approval requests') }}">
                    <flux:table.columns><flux:table.column class="w-[130px] whitespace-nowrap">{{ __('Requested') }}</flux:table.column><flux:table.column class="w-[280px]">{{ __('Source') }}</flux:table.column><flux:table.column class="w-[190px]">{{ __('Requester') }}</flux:table.column><flux:table.column class="w-[170px]">{{ __('Scope') }}</flux:table.column><flux:table.column class="w-[120px] whitespace-nowrap">{{ __('State') }}</flux:table.column><flux:table.column class="w-[110px] whitespace-nowrap">{{ __('Actions') }}</flux:table.column></flux:table.columns>
                    <flux:table.rows>@foreach($approvals as $approval)<flux:table.row key="approval-{{ $approval->id }}"><flux:table.cell class="w-[130px] whitespace-nowrap align-top font-mono text-xs">{{ $approval->requested_at->format('Y-m-d H:i') }}</flux:table.cell><flux:table.cell class="w-[280px] max-w-[280px] min-w-0 align-top"><div class="min-w-0 break-words whitespace-normal font-medium leading-5">{{ ApprovalPresentation::source($approval->source_type, $approval->source_id) }}</div><div class="min-w-0 break-all whitespace-normal font-mono text-xs leading-5 text-text-muted">{{ ApprovalPresentation::action($approval->requested_action) }}</div></flux:table.cell><flux:table.cell class="w-[190px] max-w-[190px] min-w-0 align-top"><div class="min-w-0 break-words whitespace-normal leading-5">{{ $approval->requester?->name }}</div></flux:table.cell><flux:table.cell class="w-[170px] max-w-[170px] min-w-0 align-top"><div class="min-w-0 break-words whitespace-normal font-mono text-xs leading-5">{{ $approval->branch_id ? __('Branch').' #'.$approval->branch_id : '' }} {{ $approval->store_id ? __('Store').' #'.$approval->store_id : '' }}</div></flux:table.cell><flux:table.cell class="w-[120px] align-top"><x-status.badge :status="$approval->approval_state->value" /></flux:table.cell><flux:table.cell class="w-[110px] align-top whitespace-nowrap"><x-actions.button semantic="view" :label="__('Review')" size="xs" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</x-actions.button></flux:table.cell></flux:table.row>@endforeach</flux:table.rows>
                </flux:table>
            </div>
            <div class="space-y-3 md:hidden">@foreach($approvals as $approval)<x-cards.section-card :title="ApprovalPresentation::source($approval->source_type, $approval->source_id)"><div class="space-y-3 text-sm"><div class="flex items-center justify-between gap-3"><x-status.badge :status="$approval->approval_state->value" /><span class="font-mono text-xs text-text-muted">{{ $approval->requested_at->format('Y-m-d H:i') }}</span></div><p>{{ __('Requester') }}: {{ $approval->requester?->name }}</p><x-actions.button semantic="view" :label="__('Review')" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</x-actions.button></div></x-cards.section-card>@endforeach</div>
        @endif
        <x-slot:footer>{{ $approvals->links() }}</x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="approvalModalOpen" class="max-w-3xl">
        @if($selectedApproval)
            <div class="space-y-5">
                <div><flux:heading size="lg">{{ __('Approval request') }}</flux:heading><flux:text class="mt-1 font-mono text-xs">{{ $selectedApproval->uuid }}</flux:text></div>
                @error('approval')<x-state.error :title="$message" />@enderror
                <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-text-muted">{{ __('Source') }}</dt><dd>{{ ApprovalPresentation::source($selectedApproval->source_type, $selectedApproval->source_id) }}</dd></div><div><dt class="text-text-muted">{{ __('State') }}</dt><dd><x-status.badge :status="$selectedApproval->approval_state->value" /></dd></div><div><dt class="text-text-muted">{{ __('Requester') }}</dt><dd>{{ $selectedApproval->requester?->name }}</dd></div><div><dt class="text-text-muted">{{ __('Requested action') }}</dt><dd>{{ ApprovalPresentation::action($selectedApproval->requested_action) }}</dd></div>@if($selectedApproval->reason_text)<div class="sm:col-span-2"><dt class="text-text-muted">{{ __('Reason') }}</dt><dd>{{ $selectedApproval->reason_text }}</dd></div>@endif</dl>

                @if($reviewDetails && $reviewDetails['type'] === 'tax_setting')
                    <x-cards.section-card :title="__('Tax settings')">
                        <div class="overflow-x-auto">
                            <flux:table class="min-w-[620px]" aria-label="{{ __('Tax settings') }}">
                                <flux:table.columns><flux:table.column>{{ __('Field') }}</flux:table.column><flux:table.column>{{ __('Before') }}</flux:table.column><flux:table.column>{{ __('After') }}</flux:table.column></flux:table.columns>
                                <flux:table.rows>
                                    @foreach($reviewDetails['rows'] as $change)
                                        <flux:table.row key="tax-setting-change-{{ $loop->index }}">
                                            <flux:table.cell class="font-medium">{{ $change['label'] }}</flux:table.cell>
                                            <flux:table.cell>{{ $change['before'] }}</flux:table.cell>
                                            <flux:table.cell>{{ $change['after'] }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </x-cards.section-card>
                @elseif($reviewDetails && $reviewDetails['type'] === 'branch_delete')
                    <x-cards.section-card :title="__('Request-time dependency context')">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            @foreach($reviewDetails['counts'] as $count)
                                <div><dt class="text-text-muted">{{ $count['label'] }}</dt><dd class="mt-1 font-semibold tabular-nums">{{ $count['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    </x-cards.section-card>
                @endif

                <flux:button :href="$sourceUrl" variant="subtle" icon="arrow-top-right-on-square">{{ __('Open source') }}</flux:button>

                @if($shiftSubmission)
                    <x-tables.table-shell :label="__('Shift Variance Review')">
                        <table class="data-table w-full text-sm">
                            <thead><tr><th class="text-start">{{ __('Method') }}</th><th class="text-end">{{ __('Expected') }}</th><th class="text-end">{{ __('Actual') }}</th><th class="text-end">{{ __('Variance') }}</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Cash') }}</td>
                                    <td class="text-end"><x-money :amount="$shiftSubmission->expected_cash" :currency="$selectedShift->currency_code" /></td>
                                    <td class="text-end"><x-money :amount="$shiftSubmission->actual_cash" :currency="$selectedShift->currency_code" /></td>
                                    <td class="text-end"><x-money :amount="$shiftSubmission->cash_variance" :currency="$selectedShift->currency_code" /></td>
                                </tr>
                                @foreach(($shiftSubmission->method_variance ?? []) as $code => $delta)
                                    <tr>
                                        <td>{{ $code }}</td>
                                        <td class="text-end"><x-money :amount="(($shiftSubmission->expected_by_method ?? [])[$code] ?? 0)" :currency="$selectedShift->currency_code" /></td>
                                        <td class="text-end"><x-money :amount="(($shiftSubmission->actual_by_method ?? [])[$code] ?? 0)" :currency="$selectedShift->currency_code" /></td>
                                        <td class="text-end"><x-money :amount="$delta" :currency="$selectedShift->currency_code" /></td>
                                    </tr>
                                @endforeach
                                <tr><td colspan="3" class="font-semibold">{{ __('Total variance') }}</td><td class="text-end"><x-money :amount="$shiftSubmission->total_variance" :currency="$selectedShift->currency_code" /></td></tr>
                            </tbody>
                        </table>
                    </x-tables.table-shell>
                @endif

                @if($selectedApproval->approval_state === ApprovalState::Pending && $canDecide)
                    <div class="space-y-3 border-t border-border-subtle pt-4"><div><flux:textarea wire:model="decisionReason" :label="$selectedApproval->source_type === 'pos_shifts' ? __('Recount reason') : __('Rejection reason')" /><x-context-help :title="$selectedApproval->source_type === 'pos_shifts' ? __('Recount reason') : __('Rejection reason')" :label="__('Open contextual help')"><p>{{ $selectedApproval->source_type === 'pos_shifts' ? __('Returning a shift for recount rejects this exact approval request and preserves its history.') : __('Required only when rejecting. The source keeps this reason in its immutable history.') }}</p></x-context-help></div>@error('decisionReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<div class="flex flex-wrap justify-end gap-2"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button>@if($canReject)<flux:button variant="danger" wire:click="reject" wire:confirm="{{ $selectedApproval->source_type === 'pos_shifts' ? __('Return this shift for recount?') : __('Reject this source record?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Request recount') : __('Reject') }}</flux:button>@endif<flux:button variant="primary" wire:click="approve" wire:confirm="{{ __('Approve this source record and execute its domain effects?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Approve and close') : __('Approve') }}</flux:button></div></div>
                @else<div class="flex justify-end"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button></div>@endif
            </div>
        @endif
    </flux:modal>
</x-app.page>
