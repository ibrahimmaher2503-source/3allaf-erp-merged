<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\CancelPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\RejectPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ReversePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Invoices')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showFormModal = false;

    public bool $showTransitionModal = false;

    public ?int $transitionInvoiceId = null;

    public string $transitionType = '';

    public string $transitionReason = '';

    public ?int $editingInvoiceId = null;

    public array $invoiceForm = [
        'supplier_id' => '',
        'store_id' => '',
        'purchase_order_id' => '',
        'supplier_reference' => '',
        'invoice_date' => '',
        'currency_code' => '',
        'notes' => '',
        'lock_version' => 0,
    ];

    public array $lineItems = [];

    public function mount(): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.view');
        $this->invoiceForm['invoice_date'] = now()->toDateString();
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
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $this->resetValidation();
        $this->editingInvoiceId = null;
        $this->invoiceForm = [
            'supplier_id' => '',
            'store_id' => '',
            'purchase_order_id' => '',
            'supplier_reference' => '',
            'invoice_date' => now()->toDateString(),
            'currency_code' => '',
            'notes' => '',
            'lock_version' => 0,
        ];
        $this->lineItems = [$this->emptyLine()];
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        $invoice = PurchaseInvoice::query()->with('lines')->findOrFail($id);
        abort_if($invoice->status !== 'draft', 422, __('Only draft purchase invoices can be edited.'));

        $this->resetValidation();
        $this->editingInvoiceId = $invoice->id;
        $this->invoiceForm = [
            'supplier_id' => (string) $invoice->supplier_id,
            'store_id' => (string) $invoice->store_id,
            'purchase_order_id' => $invoice->purchase_order_id ? (string) $invoice->purchase_order_id : '',
            'supplier_reference' => $invoice->supplier_reference ?: '',
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d') ?: now()->toDateString(),
            'currency_code' => $invoice->currency_code ?: '',
            'notes' => $invoice->notes ?: '',
            'lock_version' => $invoice->lock_version,
        ];
        $this->lineItems = $invoice->lines->map(fn ($line): array => [
            'product_id' => (string) $line->product_id,
            'purchase_order_line_id' => $line->purchase_order_line_id ? (string) $line->purchase_order_line_id : '',
            'quantity' => (string) $line->quantity,
            'unit_cost' => (string) $line->unit_cost,
            'discount_type' => $line->discount_type ?: '',
            'discount_value' => (string) $line->discount_value,
            'tax_rate' => (string) $line->tax_rate,
            'tax_code' => $line->tax_code ?: '',
        ])->all();
        $this->lineItems = $this->lineItems ?: [$this->emptyLine()];
        $this->showFormModal = true;
    }

    public function addLine(): void
    {
        $this->lineItems[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function saveInvoice(SavePurchaseInvoiceAction $action): void
    {
        Gate::authorize($this->editingInvoiceId ? 'purchase_invoices_supplier_returns.edit' : 'purchase_invoices_supplier_returns.create');
        $this->validate([
            'invoiceForm.supplier_id' => 'required|exists:suppliers,id',
            'invoiceForm.store_id' => 'required|exists:stores,id',
            'invoiceForm.purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'invoiceForm.supplier_reference' => 'nullable|string|max:100',
            'invoiceForm.invoice_date' => 'required|date',
            'invoiceForm.currency_code' => 'nullable|string|size:3',
            'invoiceForm.notes' => 'nullable|string|max:5000',
            'lineItems' => 'required|array|min:1',
            'lineItems.*.product_id' => 'required|exists:products,id',
            'lineItems.*.quantity' => 'required|numeric|gt:0',
            'lineItems.*.unit_cost' => 'required|numeric|gte:0',
            'lineItems.*.discount_type' => 'nullable|in:percentage,amount',
            'lineItems.*.discount_value' => 'nullable|numeric|gte:0',
            'lineItems.*.tax_rate' => 'nullable|numeric|between:0,100',
        ]);

        try {
            $invoice = $action->execute(
                data: $this->invoiceForm,
                lines: $this->lineItems,
                id: $this->editingInvoiceId,
                expectedVersion: $this->editingInvoiceId ? (int) $this->invoiceForm['lock_version'] : null,
            );
            $this->showFormModal = false;
            Flux::toast($this->editingInvoiceId ? __('Purchase invoice updated.') : __('Purchase invoice saved as draft.'), variant: 'success');
            $this->editingInvoiceId = $invoice->id;
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function submitInvoice(int $id, SubmitPurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            Flux::toast(__('Purchase invoice submitted for approval.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function approveInvoice(int $id, ApprovePurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.approve');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            Flux::toast(__('Purchase invoice approved and stock/WAC posted.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function openTransitionModal(string $type, int $id): void
    {
        Gate::authorize(match ($type) {
            'reject' => 'purchase_invoices_supplier_returns.approve',
            'cancel' => 'purchase_invoices_supplier_returns.cancel',
            'reverse' => 'purchase_invoices_supplier_returns.reverse',
            default => throw new InvalidArgumentException(__('Unsupported invoice transition.')),
        });
        $this->transitionType = $type;
        $this->transitionInvoiceId = $id;
        $this->transitionReason = '';
        $this->resetValidation();
        $this->showTransitionModal = true;
    }

    public function executeTransition(RejectPurchaseInvoiceAction $reject, CancelPurchaseInvoiceAction $cancel, ReversePurchaseInvoiceAction $reverse): void
    {
        $this->validate(['transitionReason' => 'required|string|min:3|max:500']);
        $invoice = PurchaseInvoice::query()->findOrFail($this->transitionInvoiceId);
        try {
            match ($this->transitionType) {
                'reject' => $reject->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                'cancel' => $cancel->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                'reverse' => $reverse->execute($invoice->id, $this->transitionReason, $invoice->lock_version),
                default => throw new InvalidArgumentException(__('Unsupported invoice transition.')),
            };
            $this->showTransitionModal = false;
            Flux::toast(__('Invoice transition completed.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function render(): View
    {
        $invoices = PurchaseInvoice::query()
            ->with(['supplier', 'store'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('supplier_reference', 'like', '%'.$this->search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        return view('purchasing.invoices', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name_en')->get(),
            'stores' => Store::query()->where('status', 'active')->orderBy('name_en')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('item_code')->limit(1000)->get(),
        ]);
    }

    /** @return array<string, string> */
    private function emptyLine(): array
    {
        return [
            'product_id' => '',
            'purchase_order_line_id' => '',
            'quantity' => '1',
            'unit_cost' => '0',
            'discount_type' => '',
            'discount_value' => '0',
            'tax_rate' => '0',
            'tax_code' => '',
        ];
    }
};
?>

<x-app.page
    :title="__('Purchase Invoices')"
    :description="__('Create and review draft purchase invoices. Approval and stock posting are separate controlled actions.')"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="purchase-invoices-filters">
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="shield-check">{{ __('Readiness') }}</flux:button>
        @can('purchase_invoices_supplier_returns.create')
            <flux:button href="{{ route('purchasing.invoices.import') }}" variant="subtle" icon="arrow-up-tray">{{ __('Import') }}</flux:button>
        @endcan
        @can('purchase_returns.view')
            <flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-uturn-left">{{ __('Supplier returns') }}</flux:button>
        @endcan
        @can('purchase_invoices_supplier_returns.export')
            <flux:button href="{{ route('purchasing.invoices.export') }}" variant="subtle" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
        @endcan
        @can('purchase_invoices_supplier_returns.create')
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">{{ __('New draft invoice') }}</flux:button>
        @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:callout variant="warning" icon="exclamation-triangle">
        {{ __('Drafts calculate totals and create audit records only. No stock, WAC, receipt, or sale-price mutation occurs until a separate approved posting action is completed.') }}
    </flux:callout>

    <flux:card id="purchase-invoices-filters" class="scroll-mt-24">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Invoice number or supplier reference') }}" />
            </div>
            <div class="w-48">
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:table aria-label="{{ __('Purchase invoices') }}">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                <flux:table.column>{{ __('Store') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>{{ $invoice->invoice_number ?: $invoice->supplier_reference ?: '#'.$invoice->id }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->supplier?->name_en ?: $invoice->supplier?->name_ar }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->store?->name_en ?: $invoice->store?->name_ar }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->invoice_date?->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->total_amount }}</flux:table.cell>
                        <flux:table.cell><x-status.badge :status="$invoice->status" /></flux:table.cell>
                        <flux:table.cell>
                            @can('purchase_invoices_supplier_returns.print')
                                <flux:button size="sm" variant="subtle" href="{{ route('purchasing.invoices.print', $invoice) }}" target="_blank">{{ __('Print') }}</flux:button>
                            @endcan
                            @if ($invoice->status === 'draft')
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" variant="subtle" wire:click="openEditModal({{ $invoice->id }})">{{ __('Edit') }}</flux:button>
                                    @can('purchase_invoices_supplier_returns.edit')
                                        <flux:button size="sm" variant="subtle" wire:click="submitInvoice({{ $invoice->id }})">{{ __('Submit') }}</flux:button>
                                    @endcan
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif ($invoice->status === 'submitted')
                                <div class="flex flex-wrap gap-2">
                                    @can('purchase_invoices_supplier_returns.approve')
                                        <flux:button size="sm" variant="primary" wire:click="approveInvoice({{ $invoice->id }})">{{ __('Approve & post') }}</flux:button>
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('reject', {{ $invoice->id }})">{{ __('Reject') }}</flux:button>
                                    @endcan
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif ($invoice->status === 'approved')
                                @can('purchase_invoices_supplier_returns.reverse')
                                    <flux:button size="sm" variant="danger" wire:click="openTransitionModal('reverse', {{ $invoice->id }})">{{ __('Reverse') }}</flux:button>
                                @endcan
                            @else
                                <flux:text>{{ __('Locked') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7">{{ __('No purchase invoices yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </flux:card>

    @if ($showFormModal)
        <flux:modal wire:model.self="showFormModal" class="md:w-[min(96vw,1100px)]">
            <form wire:submit="saveInvoice" class="space-y-5">
                <flux:heading size="lg">{{ $editingInvoiceId ? __('Edit draft invoice') : __('New draft invoice') }}</flux:heading>
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model="invoiceForm.supplier_id" :label="__('Supplier')">
                        <flux:select.option value="">{{ __('Select supplier') }}</flux:select.option>
                        @foreach ($suppliers as $supplier)
                            <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name_en ?: $supplier->name_ar }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="invoiceForm.store_id" :label="__('Receiving store')">
                        <flux:select.option value="">{{ __('Select store') }}</flux:select.option>
                        @foreach ($stores as $store)
                            <flux:select.option value="{{ $store->id }}">{{ $store->name_en ?: $store->name_ar }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="invoiceForm.invoice_date" type="date" :label="__('Invoice date')" />
                    <flux:input wire:model="invoiceForm.supplier_reference" :label="__('Supplier invoice reference')" />
                    <flux:input wire:model="invoiceForm.currency_code" maxlength="3" :label="__('Currency code')" placeholder="{{ __('Optional') }}" />
                    <flux:input wire:model="invoiceForm.notes" :label="__('Notes')" />
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="base">{{ __('Invoice lines') }}</flux:heading>
                        <flux:button type="button" size="sm" variant="subtle" wire:click="addLine" icon="plus">{{ __('Add line') }}</flux:button>
                    </div>
                    @foreach ($lineItems as $index => $line)
                        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-8" wire:key="invoice-line-{{ $index }}">
                            <flux:select wire:model="lineItems.{{ $index }}.product_id" class="md:col-span-2" :label="__('Product')">
                                <flux:select.option value="">{{ __('Select product') }}</flux:select.option>
                                @foreach ($products as $product)
                                    <flux:select.option value="{{ $product->id }}">{{ $product->item_code }} — {{ $product->name_en ?: $product->name_ar }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="lineItems.{{ $index }}.quantity" type="number" min="0.000001" step="0.000001" :label="__('Quantity')" />
                            <flux:input wire:model="lineItems.{{ $index }}.unit_cost" type="number" min="0" step="0.0001" :label="__('Unit cost')" />
                            <flux:select wire:model.live="lineItems.{{ $index }}.discount_type" :label="__('Discount type')">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                <flux:select.option value="percentage">{{ __('Percentage') }}</flux:select.option>
                                <flux:select.option value="amount">{{ __('Amount') }}</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="lineItems.{{ $index }}.discount_value" type="number" min="0" step="0.0001" :label="__('Discount')" />
                            <flux:input wire:model="lineItems.{{ $index }}.tax_rate" type="number" min="0" max="100" step="0.0001" :label="__('Tax %')" />
                            <div class="flex items-end justify-end">
                                <flux:button type="button" variant="danger" size="sm" wire:click="removeLine({{ $index }})" :disabled="count($lineItems) <= 1">{{ __('Remove') }}</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save draft') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($showTransitionModal)
        <flux:modal wire:model.self="showTransitionModal" class="md:max-w-lg">
            <form wire:submit="executeTransition" class="space-y-5">
                <flux:heading size="lg">{{ ucfirst($transitionType) }} {{ __('purchase invoice') }}</flux:heading>
                <flux:text>{{ __('A reason is required and will be written to the audit trail.') }}</flux:text>
                <flux:textarea wire:model="transitionReason" :label="__('Reason')" rows="4" required />
                @error('transitionReason') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" wire:click="$set('showTransitionModal', false)">{{ __('Close') }}</flux:button>
                    <flux:button type="submit" variant="danger">{{ __('Confirm') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

</x-app.page>
