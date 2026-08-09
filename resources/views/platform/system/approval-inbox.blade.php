<?php

use App\Models\User;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Approval Inbox')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $state = 'pending';

    public string $sourceType = '';

    public string $branchId = '';

    public string $storeId = '';

    public ?int $selectedApprovalId = null;

    public string $decisionReason = '';

    public mixed $evidence = null;

    public string $revokeReason = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ($user->is_super_admin
            || $user->hasPermission('audit_logs.view')
            || collect([
                'pricing_labels.submit', 'pricing_labels.approve',
                'purchase_orders.edit', 'purchase_orders.approve',
                'purchase_invoices_supplier_returns.edit', 'purchase_invoices_supplier_returns.approve',
                'purchase_returns.edit', 'purchase_returns.approve',
                'inventory_stock_card.submit', 'inventory_stock_card.approve',
                'stock_counts.submit', 'stock_counts.reconcile',
                'transfers.submit', 'transfers.approve',
                'shifts_cash_movements.submit', 'shifts_cash_movements.approve', 'shifts_cash_movements.reject',
            ])->contains(fn (string $permission): bool => $user->hasPermission($permission))), 403);
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
        $this->decisionReason = '';
        $this->resetValidation();
    }

    public function closeApproval(): void
    {
        $this->selectedApprovalId = null;
        $this->decisionReason = '';
        $this->evidence = null;
        $this->revokeReason = '';
        $this->resetValidation();
    }

    public function approve(): void
    {
        $approval = $this->selectedPendingApproval();
        Gate::authorize('decide', $approval);
        app(DecideApprovalSource::class)->approve($approval);
        session()->flash('approval-success', __('The source was approved and its audit trail was recorded.'));
        $this->closeApproval();
    }

    public function reject(): void
    {
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
    }

    public function uploadEvidence(): void
    {
        $approval = $this->selectedPendingApproval();
        Gate::authorize('view', $approval);
        $this->validate(['evidence' => ['required', 'file', 'max:12288']]);
        app(StoreAttachment::class)->execute(
            $this->evidence,
            'approval_evidence',
            new AttachmentSourceReference(
                ApprovalRecord::class,
                (string) $approval->id,
                $approval->branch_id,
                $approval->store_id,
                'private',
            ),
            fn (User $user, AttachmentSourceReference $source): bool => Gate::forUser($user)->allows('view', $approval)
                && $source->sourceType === ApprovalRecord::class
                && $source->sourceId === (string) $approval->id,
        );
        $this->evidence = null;
        session()->flash('approval-evidence-success', __('Approval evidence uploaded securely.'));
    }

    public function revokeEvidence(string $attachmentId): void
    {
        $approval = $this->selectedPendingApproval();
        Gate::authorize('decide', $approval);
        $validated = $this->validate(['revokeReason' => ['required', 'string', 'min:3', 'max:500']]);
        $attachment = Attachment::query()
            ->where('source_type', ApprovalRecord::class)
            ->where('source_id', (string) $approval->id)
            ->findOrFail($attachmentId);
        app(RevokeAttachment::class)->execute(
            $attachment,
            $validated['revokeReason'],
            fn (User $user, Attachment $candidate): bool => Gate::forUser($user)->allows('decide', $approval)
                && $candidate->source_type === ApprovalRecord::class
                && $candidate->source_id === (string) $approval->id,
        );
        $this->revokeReason = '';
    }

    public function render()
    {
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

        return view('platform.system.approval-inbox', [
            'approvals' => $approvals,
            'selectedApproval' => $selectedApproval,
            'sourceTypes' => (clone $base)->distinct()->orderBy('source_type')->pluck('source_type'),
            'branches' => Branch::query()->visibleTo($user)->orderBy('code')->get(),
            'stores' => Store::query()->visibleTo($user)->orderBy('code')->get(),
            'attachments' => $selectedApproval === null ? collect() : Attachment::query()
                ->where('source_type', ApprovalRecord::class)
                ->where('source_id', (string) $selectedApproval->id)
                ->latest()->get(),
            'canDecide' => $selectedApproval !== null && Gate::allows('decide', $selectedApproval),
            'canReject' => $selectedApproval !== null && app(DecideApprovalSource::class)->canReject($selectedApproval),
            'sourceUrl' => $selectedApproval === null ? null : app(DecideApprovalSource::class)->sourceRoute($selectedApproval),
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
                        @foreach (ApprovalState::cases() as $approvalState)<option value="{{ $approvalState->value }}">{{ str($approvalState->value)->headline() }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model.live="sourceType" :label="__('Source type')"><option value="">{{ __('All source types') }}</option>@foreach($sourceTypes as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="branchId" :label="__('Branch')"><option value="">{{ __('All visible branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }}</option>@endforeach</flux:select>
                    <flux:select wire:model.live="storeId" :label="__('Store')"><option value="">{{ __('All visible stores') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }}</option>@endforeach</flux:select>
                </div>
            </x-tables.filter-bar>
        </x-slot:toolbar>

        @if($approvals->isEmpty())
            <x-state.empty :title="__('No approval requests found')" :description="__('No requests match your permissions, scope, and filters.')" icon="check-circle" />
        @else
            <div class="hidden md:block">
                <flux:table aria-label="{{ __('Approval requests') }}">
                    <flux:table.columns><flux:table.column>{{ __('Requested') }}</flux:table.column><flux:table.column>{{ __('Source') }}</flux:table.column><flux:table.column>{{ __('Requester') }}</flux:table.column><flux:table.column>{{ __('Scope') }}</flux:table.column><flux:table.column>{{ __('State') }}</flux:table.column><flux:table.column>{{ __('Actions') }}</flux:table.column></flux:table.columns>
                    <flux:table.rows>@foreach($approvals as $approval)<flux:table.row key="approval-{{ $approval->id }}"><flux:table.cell class="whitespace-nowrap font-mono text-xs">{{ $approval->requested_at->format('Y-m-d H:i') }}</flux:table.cell><flux:table.cell><div class="font-medium">{{ str($approval->source_type)->headline() }} #{{ $approval->source_id }}</div><div class="font-mono text-xs text-text-muted">{{ $approval->requested_action }}</div></flux:table.cell><flux:table.cell>{{ $approval->requester?->name }}</flux:table.cell><flux:table.cell class="font-mono text-xs">{{ $approval->branch_id ? __('Branch').' #'.$approval->branch_id : '' }} {{ $approval->store_id ? __('Store').' #'.$approval->store_id : '' }}</flux:table.cell><flux:table.cell><x-status.badge :status="$approval->approval_state->value" /></flux:table.cell><flux:table.cell><flux:button size="xs" variant="subtle" icon="eye" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</flux:button></flux:table.cell></flux:table.row>@endforeach</flux:table.rows>
                </flux:table>
            </div>
            <div class="space-y-3 md:hidden">@foreach($approvals as $approval)<x-cards.section-card :title="str($approval->source_type)->headline().' #'.$approval->source_id"><div class="space-y-3 text-sm"><div class="flex items-center justify-between gap-3"><x-status.badge :status="$approval->approval_state->value" /><span class="font-mono text-xs text-text-muted">{{ $approval->requested_at->format('Y-m-d H:i') }}</span></div><p>{{ __('Requester') }}: {{ $approval->requester?->name }}</p><flux:button size="sm" variant="subtle" icon="eye" wire:click="showApproval({{ $approval->id }})">{{ __('Review') }}</flux:button></div></x-cards.section-card>@endforeach</div>
        @endif
        <x-slot:footer>{{ $approvals->links() }}</x-slot:footer>
    </x-tables.data-panel>

    <flux:modal wire:model="selectedApprovalId" class="max-w-3xl">
        @if($selectedApproval)
            <div class="space-y-5">
                <div><flux:heading size="lg">{{ __('Approval request') }}</flux:heading><flux:text class="mt-1 font-mono text-xs">{{ $selectedApproval->uuid }}</flux:text></div>
                @error('approval')<x-state.error :title="$message" />@enderror
                <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-text-muted">{{ __('Source') }}</dt><dd>{{ str($selectedApproval->source_type)->headline() }} #{{ $selectedApproval->source_id }}</dd></div><div><dt class="text-text-muted">{{ __('State') }}</dt><dd><x-status.badge :status="$selectedApproval->approval_state->value" /></dd></div><div><dt class="text-text-muted">{{ __('Requester') }}</dt><dd>{{ $selectedApproval->requester?->name }}</dd></div><div><dt class="text-text-muted">{{ __('Requested action') }}</dt><dd>{{ $selectedApproval->requested_action }}</dd></div>@if($selectedApproval->reason_text)<div class="sm:col-span-2"><dt class="text-text-muted">{{ __('Reason') }}</dt><dd>{{ $selectedApproval->reason_text }}</dd></div>@endif</dl>
                <flux:button :href="$sourceUrl" variant="subtle" icon="arrow-top-right-on-square">{{ __('Open source') }}</flux:button>

                <section class="space-y-3" aria-labelledby="approval-evidence-heading"><div><h3 id="approval-evidence-heading" class="font-semibold">{{ __('Approval evidence') }}</h3><p class="text-sm text-text-muted">{{ __('Private files, maximum five per request. Every download is reauthorized and audited.') }}</p></div>@if(session('approval-evidence-success'))<flux:callout variant="info" icon="check-circle">{{ session('approval-evidence-success') }}</flux:callout>@endif<div class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input wire:model="evidence" type="file" :label="__('Evidence file')" accept="image/jpeg,image/png,image/webp,application/pdf" /><flux:button wire:click="uploadEvidence" wire:loading.attr="disabled" wire:target="evidence,uploadEvidence" icon="arrow-up-tray">{{ __('Upload securely') }}</flux:button></div>@error('evidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror@if($canDecide && $attachments->contains(fn ($attachment) => $attachment->status->isDeliverable()))<flux:input wire:model="revokeReason" :label="__('Evidence revocation reason')" :description="__('Required before revoking active evidence; the reason is written to the audit trail.')" />@error('revokeReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror@endif<div class="space-y-2">@forelse($attachments as $attachment)<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border-subtle p-3"><div><p class="text-sm font-medium">{{ $attachment->original_filename }}</p><p class="text-xs text-text-muted">{{ Number::fileSize($attachment->size_bytes) }} · {{ str($attachment->status->value)->headline() }}</p></div><div class="flex gap-2">@if($attachment->status->isDeliverable())<flux:button size="xs" variant="subtle" icon="arrow-down-tray" :href="route('admin.approvals.attachments.download', [$selectedApproval, $attachment])">{{ __('Download') }}</flux:button>@if($canDecide)<flux:button size="xs" variant="danger" wire:click="revokeEvidence('{{ $attachment->id }}')" wire:confirm="{{ __('Revoke this evidence file?') }}">{{ __('Revoke') }}</flux:button>@endif@endif</div></div>@empty<p class="text-sm text-text-muted">{{ __('No evidence attached.') }}</p>@endforelse</div></section>

                @if($selectedApproval->approval_state === ApprovalState::Pending && $canDecide)
                    <div class="space-y-3 border-t border-border-subtle pt-4"><flux:textarea wire:model="decisionReason" :label="$selectedApproval->source_type === 'pos_shifts' ? __('Recount reason') : __('Rejection reason')" :description="$selectedApproval->source_type === 'pos_shifts' ? __('Returning a shift for recount rejects this exact approval request and preserves its history.') : __('Required only when rejecting. The source keeps this reason in its immutable history.')" />@error('decisionReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<div class="flex flex-wrap justify-end gap-2"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button>@if($canReject)<flux:button variant="danger" wire:click="reject" wire:confirm="{{ $selectedApproval->source_type === 'pos_shifts' ? __('Return this shift for recount?') : __('Reject this source record?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Request recount') : __('Reject') }}</flux:button>@endif<flux:button variant="primary" wire:click="approve" wire:confirm="{{ __('Approve this source record and execute its domain effects?') }}">{{ $selectedApproval->source_type === 'pos_shifts' ? __('Approve and close') : __('Approve') }}</flux:button></div></div>
                @else<div class="flex justify-end"><flux:button variant="subtle" wire:click="closeApproval">{{ __('Close') }}</flux:button></div>@endif
            </div>
        @endif
    </flux:modal>
</x-app.page>
