<?php

use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CancelPurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\RejectPurchaseReturnAction;
use App\Modules\Purchasing\Actions\ReversePurchaseReturnAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Actions\UpdatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Supplier Returns')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showFormModal = false;

    public ?int $selectedInvoiceId = null;

    public ?int $selectedReturnId = null;

    public ?int $transitionReturnId = null;

    public bool $showTransitionModal = false;

    public string $transitionAction = '';

    public string $transitionReason = '';

    public ?int $selectedReasonId = null;

    /** @var array<int, array<string, string>> */
    public array $returnLines = [];

    public function mount(): void
    {
        Gate::authorize('purchase_returns.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('purchase_returns.create');
        if (! SupplierReturnReason::query()->active()->exists()) {
            Flux::toast(__('No active supplier return reasons are configured yet.'), variant: 'warning');

            return;
        }

        $this->resetValidation();
        $this->selectedInvoiceId = null;
        $this->selectedReturnId = null;
        $this->selectedReasonId = null;
        $this->returnLines = [];
        $this->showFormModal = true;
    }

    public function editDraft(int $id): void
    {
        Gate::authorize('purchase_returns.edit');
        $return = PurchaseReturn::query()->with('lines')->where('status', 'draft')->findOrFail($id);
        $this->selectedReturnId = $return->id;
        $this->selectedInvoiceId = $return->purchase_invoice_id;
        $this->selectedReasonId = $return->reason_id;
        $this->returnLines = $return->lines->map(fn ($line): array => ['purchase_invoice_line_id' => (string) $line->purchase_invoice_line_id, 'quantity' => (string) $line->quantity, 'unit_cost' => (string) $line->unit_cost, 'available' => '', 'product' => app()->getLocale() === 'ar' ? ($line->product?->name_ar ?: $line->product?->name_en ?: '#'.$line->product_id) : ($line->product?->name_en ?: $line->product?->name_ar ?: '#'.$line->product_id)])->values()->all();
        $this->showFormModal = true;
    }

    public function updatedSelectedInvoiceId(?int $invoiceId): void
    {
        $this->returnLines = [];
        if ($invoiceId === null) {
            return;
        }

        $invoice = PurchaseInvoice::query()->with('lines.product')->where('status', 'approved')->findOrFail($invoiceId);
        $this->returnLines = $invoice->lines
            ->map(function ($line) use ($invoice): ?array {
                $returned = PurchaseReturnLine::query()
                    ->where('purchase_invoice_line_id', $line->id)
                    ->when($this->selectedReturnId !== null, fn ($query) => $query->where('purchase_return_id', '!=', $this->selectedReturnId))
                    ->whereHas('purchaseReturn', fn ($query) => $query->whereNotIn('status', ['cancelled', 'rejected', 'reversed']))
                    ->sum('quantity');
                $remaining = bcsub((string) $line->quantity_received, (string) $returned, 6);
                $onHand = (string) (StockBalance::query()->where('store_id', $invoice->store_id)->where('product_id', $line->product_id)->value('on_hand') ?? '0');
                $available = bccomp($remaining, $onHand, 6) <= 0 ? $remaining : $onHand;
                if (bccomp($available, '0', 6) <= 0) {
                    return null;
                }

                return [
                    'purchase_invoice_line_id' => (string) $line->id,
                    'quantity' => bccomp($available, '1', 6) < 0 ? $available : '1',
                    'unit_cost' => (string) $line->unit_cost,
                    'available' => $available,
                    'product' => app()->getLocale() === 'ar' ? ($line->product?->name_ar ?: $line->product?->name_en ?: '#'.$line->product_id) : ($line->product?->name_en ?: $line->product?->name_ar ?: '#'.$line->product_id),
                ];
            })->filter()->values()->all();
    }

    public function saveDraft(CreatePurchaseReturnDraftAction $create, UpdatePurchaseReturnDraftAction $update): void
    {
        Gate::authorize($this->selectedReturnId === null ? 'purchase_returns.create' : 'purchase_returns.edit');
        $this->validate([
            'selectedInvoiceId' => 'required|integer|exists:purchase_invoices,id',
            'selectedReasonId' => 'required|integer|exists:supplier_return_reasons,id',
            'returnLines' => 'required|array|min:1',
            'returnLines.*.purchase_invoice_line_id' => 'required|integer',
            'returnLines.*.quantity' => 'required|numeric|gt:0',
        ]);

        try {
            $return = $this->selectedReturnId === null
                ? $create->execute($this->selectedInvoiceId, $this->selectedReasonId, $this->returnLines)
                : $update->execute($this->selectedReturnId, $this->selectedReasonId, $this->returnLines, PurchaseReturn::query()->findOrFail($this->selectedReturnId)->lock_version);
            $this->showFormModal = false;
            Flux::toast(__('Supplier return saved as draft.'), variant: 'success');
            $this->dispatch('supplier-return-created', id: $return->id);
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function submitReturn(int $id, SubmitPurchaseReturnAction $action): void
    {
        Gate::authorize('purchase_returns.edit');
        try {
            $return = PurchaseReturn::query()->findOrFail($id);
            $action->execute($return->id, $return->lock_version);
            Flux::toast(__('Supplier return submitted for approval.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function approveReturn(int $id, ApprovePurchaseReturnAction $action): void
    {
        Gate::authorize('purchase_returns.approve');
        try {
            $return = PurchaseReturn::query()->findOrFail($id);
            $action->execute($return->id, $return->lock_version);
            Flux::toast(__('Supplier return approved and stock cost reversed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function openTransitionModal(int $id, string $action): void
    {
        Gate::authorize(match ($action) {
            'cancel' => 'purchase_returns.cancel',
            'reject' => 'purchase_returns.reject',
            'reverse' => 'purchase_returns.reverse',
            default => throw new InvalidArgumentException(__('Unknown supplier return transition.')),
        });
        $this->resetValidation();
        $this->transitionReturnId = $id;
        $this->transitionAction = $action;
        $this->transitionReason = '';
        $this->showTransitionModal = true;
    }

    public function executeTransition(CancelPurchaseReturnAction $cancel, RejectPurchaseReturnAction $reject, ReversePurchaseReturnAction $reverse): void
    {
        $ability = match ($this->transitionAction) {
            'cancel' => 'purchase_returns.cancel',
            'reject' => 'purchase_returns.reject',
            'reverse' => 'purchase_returns.reverse',
            default => throw new InvalidArgumentException(__('Unknown supplier return transition.')),
        };
        Gate::authorize($ability);
        $this->validate(['transitionReason' => 'required|string|min:3|max:500']);

        try {
            $return = PurchaseReturn::query()->findOrFail($this->transitionReturnId);
            $action = match ($this->transitionAction) {
                'cancel' => $cancel,
                'reject' => $reject,
                'reverse' => $reverse,
            };
            $action->execute($return->id, $this->transitionReason, $return->lock_version);
            $this->transitionReturnId = null;
            $this->transitionAction = '';
            $this->transitionReason = '';
            $this->showTransitionModal = false;
            Flux::toast(__('Supplier return transition completed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $visibleStoreIds = $user?->is_super_admin ? null : Store::query()->visibleTo($user)->pluck('id');
        $returns = PurchaseReturn::query()
            ->with(['supplier', 'store', 'reason', 'purchaseInvoice'])
            ->when($visibleStoreIds !== null, fn ($query) => $query->whereIn('store_id', $visibleStoreIds))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('return_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('purchaseInvoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        $sourceInvoices = PurchaseInvoice::query()
            ->where('status', 'approved')
            ->when($visibleStoreIds !== null, fn ($query) => $query->whereIn('store_id', $visibleStoreIds))
            ->with(['supplier', 'lines.product'])
            ->latest()
            ->limit(100)
            ->get();
        $lineIds = $sourceInvoices->flatMap(fn ($invoice) => $invoice->lines->pluck('id'))->values();
        $returnedQuantities = PurchaseReturnLine::query()
            ->whereIn('purchase_invoice_line_id', $lineIds)
            ->whereHas('purchaseReturn', fn ($query) => $query->whereNotIn('status', ['cancelled', 'rejected', 'reversed']))
            ->selectRaw('purchase_invoice_line_id, SUM(quantity) as quantity')
            ->groupBy('purchase_invoice_line_id')
            ->pluck('quantity', 'purchase_invoice_line_id');
        $balances = StockBalance::query()
            ->whereIn('product_id', $sourceInvoices->flatMap(fn ($invoice) => $invoice->lines->pluck('product_id'))->unique())
            ->when($visibleStoreIds !== null, fn ($query) => $query->whereIn('store_id', $visibleStoreIds))
            ->get()
            ->keyBy(fn ($balance) => $balance->store_id.':'.$balance->product_id);
        $sourceInvoices = $sourceInvoices->filter(fn ($invoice): bool => $invoice->lines->contains(function ($line) use ($invoice, $returnedQuantities, $balances): bool {
            $remaining = bcsub((string) $line->quantity_received, (string) ($returnedQuantities[$line->id] ?? '0'), 6);
            $onHand = (string) ($balances[$invoice->store_id.':'.$line->product_id]?->on_hand ?? '0');

            $available = bccomp($remaining, $onHand, 6) <= 0 ? $remaining : $onHand;

            return bccomp($available, '0', 6) > 0;
        }))->values();

        return view('purchasing.returns', [
            'returns' => $returns,
            'sourceInvoices' => $sourceInvoices,
            'reasons' => SupplierReturnReason::query()->active()->orderBy('code')->get(),
            'hasReasonCatalog' => SupplierReturnReason::query()->active()->exists(),
        ]);
    }
};
?>

<x-app.page
    :title="__('Supplier Returns')"
    :description="__('Return stock only against an approved purchase invoice line. Cost is always copied from the original invoice line.')"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="supplier-returns-filters">
        <flux:button href="{{ route('purchasing.invoices') }}" variant="subtle" icon="arrow-left">{{ __('Purchase invoices') }}</flux:button>
        @can('company_settings.view')
            <flux:button href="{{ route('purchasing.returns.settings') }}" variant="subtle" icon="adjustments-horizontal">{{ __('Return settings') }}</flux:button>
        @endcan
        @can('purchase_returns.create')
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus" :disabled="!$hasReasonCatalog">{{ __('New supplier return') }}</flux:button>
        @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:callout variant="info" icon="information-circle">
        {{ __('Phase 1 rule: every return line must reference an approved purchase invoice line. No WAC or fallback cost is accepted.') }}
    </flux:callout>

    <flux:card id="supplier-returns-filters" class="scroll-mt-24">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Return number or invoice number') }}" />
            </div>
            <div class="w-48">
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    <flux:select.option value="reversed">{{ __('Reversed') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:table aria-label="{{ __('Supplier returns') }}">
            <flux:table.columns>
                <flux:table.column>{{ __('Return') }}</flux:table.column>
                <flux:table.column>{{ __('Original invoice') }}</flux:table.column>
                <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Total cost') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($returns as $return)
                    <flux:table.row :key="$return->id">
                        <flux:table.cell><a class="font-medium underline" href="{{ route('purchasing.returns.show', $return) }}">{{ $return->return_number ?: '#'.$return->id }}</a></flux:table.cell>
                        <flux:table.cell>{{ $return->purchaseInvoice?->invoice_number ?: '#'.$return->purchase_invoice_id }}</flux:table.cell>
                        <flux:table.cell>{{ app()->getLocale() === 'ar' ? ($return->supplier?->name_ar ?: $return->supplier?->name_en) : ($return->supplier?->name_en ?: $return->supplier?->name_ar) }}</flux:table.cell>
                        <flux:table.cell>{{ $return->reason?->code ?: __('Unavailable') }}</flux:table.cell>
                        <flux:table.cell>{{ $return->total_amount }}</flux:table.cell>
                        <flux:table.cell><x-status.badge :status="$return->status" /></flux:table.cell>
                        <flux:table.cell>
                            @if ($return->status === 'draft')
                                @can('purchase_returns.edit')
                                    <flux:button size="sm" variant="subtle" wire:click="editDraft({{ $return->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="subtle" wire:click="submitReturn({{ $return->id }})">{{ __('Submit') }}</flux:button>
                                @endcan
                                @can('purchase_returns.cancel')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'cancel')">{{ __('Cancel') }}</flux:button>
                                @endcan
                            @elseif ($return->status === 'submitted')
                                @can('purchase_returns.approve')
                                    <flux:button size="sm" variant="primary" wire:click="approveReturn({{ $return->id }})">{{ __('Approve & post') }}</flux:button>
                                @endcan
                                @can('purchase_returns.reject')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'reject')">{{ __('Reject') }}</flux:button>
                                @endcan
                                @can('purchase_returns.cancel')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'cancel')">{{ __('Cancel') }}</flux:button>
                                @endcan
                            @elseif ($return->status === 'approved')
                                @can('purchase_returns.reverse')
                                    <flux:button size="sm" variant="subtle" wire:click="openTransitionModal({{ $return->id }}, 'reverse')">{{ __('Reverse') }}</flux:button>
                                @endcan
                            @else
                                <flux:text>{{ __('No further actions') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="flex flex-col items-center gap-2 py-10 text-center">
                                <flux:icon name="arrow-uturn-left" class="size-8 text-text-muted" />
                                <flux:heading size="sm">{{ __('No supplier returns yet.') }}</flux:heading>
                                <flux:text>{{ __('Create a supplier return from an approved purchase invoice.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $returns->links() }}</div>
    </flux:card>

    @if ($showTransitionModal)
        <flux:modal wire:model.self="showTransitionModal" class="md:w-[min(96vw,600px)]">
            <form wire:submit="executeTransition" class="space-y-5">
                <flux:heading size="lg">{{ match ($transitionAction) { 'cancel' => __('Cancel supplier return'), 'reject' => __('Reject supplier return'), 'reverse' => __('Reverse supplier return') } }}</flux:heading>
                <flux:callout variant="warning">{{ __('This transition is audited and cannot be undone from this screen. A reason is required.') }}</flux:callout>
                <flux:textarea wire:model="transitionReason" :label="__('Reason')" required rows="4" />
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showTransitionModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Confirm transition') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($showFormModal)
        <flux:modal wire:model.self="showFormModal" class="md:w-[min(96vw,900px)]">
            <form wire:submit="saveDraft" class="space-y-5">
                <flux:heading size="lg">{{ __('New supplier return draft') }}</flux:heading>
                <flux:callout variant="warning" icon="shield-check">
                    {{ __('The source invoice and line cost are server-authoritative. The cost field below is read-only and cannot be replaced with current WAC.') }}
                </flux:callout>
                <flux:select wire:model.live="selectedInvoiceId" :label="__('Approved purchase invoice')">
                    <flux:select.option value="">{{ __('Select approved invoice') }}</flux:select.option>
                    @foreach ($sourceInvoices as $invoice)
                        <flux:select.option value="{{ $invoice->id }}">{{ $invoice->invoice_number ?: '#'.$invoice->id }} — {{ app()->getLocale() === 'ar' ? ($invoice->supplier?->name_ar ?: $invoice->supplier?->name_en) : ($invoice->supplier?->name_en ?: $invoice->supplier?->name_ar) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="selectedReasonId" :label="__('Return reason')">
                    <flux:select.option value="">{{ __('Select required reason') }}</flux:select.option>
                    @foreach ($reasons as $reason)
                        <flux:select.option value="{{ $reason->id }}">{{ $reason->code }} — {{ app()->getLocale() === 'ar' ? ($reason->label_ar ?: $reason->label_en) : ($reason->label_en ?: $reason->label_ar) }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($returnLines !== [])
                    <div class="space-y-3">
                        <flux:heading size="base">{{ __('Invoice lines') }}</flux:heading>
                        @foreach ($returnLines as $index => $line)
                            <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-4" wire:key="return-line-{{ $line['purchase_invoice_line_id'] }}">
                                <div class="md:col-span-2">
                                    <flux:text class="font-medium">{{ $line['product'] }}</flux:text>
                                    <flux:text size="sm">{{ __('Eligible quantity') }}: {{ $line['available'] }}</flux:text>
                                </div>
                                <flux:input wire:model="returnLines.{{ $index }}.quantity" type="number" min="0.000001" step="0.000001" :label="__('Return quantity')" />
                                <flux:input wire:model="returnLines.{{ $index }}.unit_cost" type="text" readonly :label="__('Original unit cost')" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="warning">{{ __('Select an approved invoice with received lines.') }}</flux:callout>
                @endif
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" :disabled="$returnLines === []">{{ __('Save draft') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</x-app.page>
