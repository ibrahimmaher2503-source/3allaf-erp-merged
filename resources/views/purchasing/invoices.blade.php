<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\CancelPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\RejectPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ReversePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Invoices')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierFilter = 'all';

    public string $storeFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

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
    public string $productLookup = '';
    public bool $showQuickProduct = false;
    public array $quickProduct = ['name_ar'=>'','name_en'=>'','model_number'=>'','category_id'=>''];
    public bool $showDistribution = false;
    public array $selectedDestinationIds = [];
    public array $distributionMatrix = [];

    public function mount(): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.view');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $this->invoiceForm['invoice_date'] = now()->toDateString();

        if (($purchaseOrderId = request()->integer('purchase_order')) > 0) {
            $this->openReceivingModal($purchaseOrderId);
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

    public function updatedSupplierFilter(): void { $this->resetPage(); }

    public function updatedStoreFilter(): void { $this->resetPage(); }

    public function updatedDateFrom(): void { $this->resetPage(); }

    public function updatedDateTo(): void { $this->resetPage(); }

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

    public function openReceivingModal(int $purchaseOrderId): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $order = PurchaseOrder::query()
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with('lines')
            ->findOrFail($purchaseOrderId);
        abort_unless(in_array($order->status, ['approved', 'partially_received'], true), 422, __('Only approved purchase orders can be received.'));

        $lineItems = $order->lines
            ->map(function ($line): ?array {
                $remainingQuantity = bcsub((string) $line->quantity_ordered, (string) $line->quantity_received, 6);

                if (bccomp($remainingQuantity, '0', 6) <= 0) {
                    return null;
                }

                return [
                    'product_id' => (string) $line->product_id,
                    'purchase_order_line_id' => (string) $line->id,
                    'quantity' => $remainingQuantity,
                    'unit_cost' => (string) $line->unit_cost,
                    'discount_type' => '',
                    'discount_value' => '0',
                    'tax_rate' => '0',
                    'tax_code' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();

        abort_unless($lineItems !== [], 422, __('This purchase order has no remaining quantity to receive.'));

        $this->resetValidation();
        $this->editingInvoiceId = null;
        $this->invoiceForm = [
            'supplier_id' => (string) $order->supplier_id,
            'store_id' => (string) $order->store_id,
            'purchase_order_id' => (string) $order->id,
            'supplier_reference' => '',
            'invoice_date' => now()->toDateString(),
            'currency_code' => '',
            'notes' => __('Receiving for :number', ['number' => $order->po_number]),
            'lock_version' => 0,
        ];
        $this->lineItems = $lineItems;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
        $invoice = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with('lines')
            ->findOrFail($id);
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
            'base_consumer_price' => (string) ($line->base_consumer_price ?? $line->product?->sale_price ?? 0),
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

    public function addScannedProduct(): void
    {
        $lookup = trim($this->productLookup);
        if ($lookup === '') return;
        $product = Product::query()->sellable()->where(function ($query) use ($lookup): void {
            $query->where('item_code', $lookup)->orWhere('model_number', $lookup)->orWhere('name_ar', 'like', '%'.$lookup.'%')->orWhere('name_en', 'like', '%'.$lookup.'%')->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $lookup)->where('status', 'active'));
        })->first();
        if (! $product) { $this->showQuickProduct = true; return; }
        foreach ($this->lineItems as $index => $line) {
            if ((int) ($line['product_id'] ?? 0) === $product->id) { $this->lineItems[$index]['quantity'] = bcadd((string) $line['quantity'], '1', 6); $this->productLookup=''; return; }
        }
        if (count($this->lineItems) === 1 && empty($this->lineItems[0]['product_id'])) $this->lineItems=[];
        $this->lineItems[] = [...$this->emptyLine(), 'product_id'=>(string)$product->id, 'unit_cost'=>(string)($product->average_cost ?? 0), 'base_consumer_price'=>(string)($product->sale_price ?? 0)];
        $this->productLookup='';
    }

    public function createQuickProduct(SaveProductAction $save, ReserveProductIdentityAction $identity): void
    {
        $this->validate(['quickProduct.name_ar'=>'required|string|max:255','quickProduct.name_en'=>'nullable|string|max:255','quickProduct.model_number'=>'nullable|string|max:100','quickProduct.category_id'=>'required|exists:categories,id']);
        $lookup=trim($this->productLookup);
        $product=$save->execute([...$this->quickProduct,'item_code'=>preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,49}$/i',$lookup)?$lookup:'','product_type'=>'standard','status'=>'active','preferred_supplier_id'=>$this->invoiceForm['supplier_id'] ?: null,'average_cost'=>0,'sale_price'=>0]);
        if (preg_match('/^\d{8}$|^\d{12,14}$/',$lookup) && ! Barcode::query()->where('barcode',$lookup)->exists()) $identity->createPrimaryBarcode($product,app(\App\Modules\Catalog\Support\ProductBarcodePolicy::class)->international($lookup),'international');
        $this->showQuickProduct=false; $this->addScannedProduct();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function saveInvoice(SavePurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices.draft');
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
            'lineItems.*.base_consumer_price' => 'required|numeric|gte:0',
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
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function submitInvoice(int $id, SubmitPurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            $this->openDistribution($invoice->id);
            Flux::toast(__('Purchase invoice submitted for approval.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function openDistribution(int $id): void
    {
        Gate::authorize('purchase_invoices.distribute');
        $invoice=PurchaseInvoice::query()->with(['lines.product','distributions'])->findOrFail($id);
        $this->editingInvoiceId=$invoice->id; $this->invoiceForm['lock_version']=$invoice->lock_version;
        $this->selectedDestinationIds=$invoice->distributions->pluck('destination_store_id')->unique()->map(fn($id)=>(string)$id)->values()->all();
        $this->distributionMatrix=[];
        foreach($invoice->distributions as $allocation) $this->distributionMatrix[$allocation->purchase_invoice_line_id][$allocation->destination_store_id]=(string)$allocation->quantity;
        $this->showDistribution=true;
    }

    public function saveDistribution(SavePurchaseDistributionAction $action): void
    {
        try { $invoice=$action->execute((int)$this->editingInvoiceId,$this->distributionMatrix,(int)$this->invoiceForm['lock_version']); $this->invoiceForm['lock_version']=$invoice->lock_version; Flux::toast(__('Distribution saved. Every line must balance before approval.'),variant:'success'); }
        catch(Throwable $e){ Flux::toast(\App\Support\UserSafeError::message($e),variant:'danger'); }
    }

    public function approveInvoice(int $id, ApprovePurchaseInvoiceAction $action): void
    {
        Gate::authorize('purchase_invoices.approve');
        try {
            $invoice = PurchaseInvoice::query()->findOrFail($id);
            $action->execute($invoice->id, $invoice->lock_version);
            Flux::toast(__('Purchase invoice approved and stock/WAC posted.'), variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function openTransitionModal(string $type, int $id): void
    {
        Gate::authorize(match ($type) {
            'reject' => 'purchase_invoices.approve',
            'cancel' => 'purchase_invoices_supplier_returns.cancel',
            'reverse' => 'purchase_invoices.reverse',
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
            Flux::toast(\App\Support\UserSafeError::message($exception), variant: 'danger');
        }
    }

    public function render(): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');

        $invoices = PurchaseInvoice::query()
            ->whereIn('store_id', $visibleStoreIds)
            ->with(['supplier', 'store', 'distributions.destination'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('supplier_reference', 'like', '%'.$this->search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->supplierFilter !== 'all', fn ($query) => $query->where('supplier_id', (int) $this->supplierFilter))
            ->when($this->storeFilter !== 'all', fn ($query) => $query->where('store_id', (int) $this->storeFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $this->dateTo))
            ->latest()
            ->paginate(20);

        return view('purchasing.invoices', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name_en')->get(),
            'stores' => Store::query()->visibleTo($actor)->where('status', 'active')->orderBy('name_en')->get(),
            'products' => Product::query()->sellable()->orderBy('item_code')->limit(1000)->get(),
            'categories' => Category::query()->where('status','active')->orderBy('name_en')->get(),
            'distributionInvoice' => $this->showDistribution && $this->editingInvoiceId ? PurchaseInvoice::query()->with(['lines.product','distributions.destination'])->find($this->editingInvoiceId) : null,
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
            'base_consumer_price' => '0',
            'discount_type' => '',
            'discount_value' => '0',
            'tax_rate' => '0',
            'tax_code' => '',
        ];
    }
};
?>

<x-app.page
    :title="app()->getLocale() === 'ar' ? 'فواتير المشتريات' : __('Purchase invoices')"
    :description="app()->getLocale() === 'ar' ? 'راجع فواتير الموردين والاستلام والاعتماد والترحيل ضمن نطاقك.' : __('Review supplier invoices, receiving, approvals, and posting within your scope.')"
    :breadcrumbs="app()->getLocale() === 'ar' ? 'المشتريات والموردون' : 'Purchasing & suppliers'"
    max-width="7xl"
    class="space-y-6"
>
    <x-slot:actions>
        <x-tables.resource-toolbar filter-target="purchase-invoices-filters">
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="shield-check">{{ __('Invoice availability') }}</flux:button>
        @can('purchase_invoices_supplier_returns.create')
            <flux:button href="{{ route('purchasing.invoices.import') }}" variant="subtle" icon="arrow-up-tray">{{ __('Import') }}</flux:button>
        @endcan
        @can('purchase_returns.view')
            <flux:button href="{{ route('purchasing.returns') }}" variant="subtle" icon="arrow-uturn-left">{{ __('Supplier returns') }}</flux:button>
        @endcan
        @can('purchase_invoices_supplier_returns.export')
            <x-actions.button semantic="export" :label="__('Export')" href="{{ route('purchasing.invoices.export') }}" variant="subtle" icon="arrow-down-tray">{{ __('Export') }}</x-actions.button>
        @endcan
        @can('purchase_invoices_supplier_returns.create')
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">{{ __('New draft invoice') }}</flux:button>
        @endcan
        </x-tables.resource-toolbar>
    </x-slot:actions>

    <flux:callout variant="warning" icon="exclamation-triangle">
        {{ __('Drafts calculate totals and create audit records only. No stock, WAC, receipt, or sale-price mutation occurs until a separate approved posting action is completed.') }}
    </flux:callout>

    @unless($showFormModal || $showDistribution)
    <flux:card id="purchase-invoices-filters" class="scroll-mt-24">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="min-w-64 flex-1">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Invoice number or supplier reference') }}" />
            </div>
            <div>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                    <flux:select.option value="awaiting_distribution">{{ __('Awaiting Distribution') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                </flux:select>
            </div>
            <flux:select wire:model.live="supplierFilter" :label="app()->getLocale() === 'ar' ? 'المورد' : __('Supplier')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ app()->getLocale() === 'ar' ? $supplier->name_ar : ($supplier->name_en ?: $supplier->name_ar) }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model.live="storeFilter" :label="app()->getLocale() === 'ar' ? 'المخزن' : __('Store')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($stores as $store)<flux:select.option value="{{ $store->id }}">{{ app()->getLocale() === 'ar' ? $store->name_ar : ($store->name_en ?: $store->name_ar) }}</flux:select.option>@endforeach</flux:select>
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
        </div>
    </flux:card>

    <flux:card class="overflow-hidden p-0">
        <flux:table class="responsive-resource-table" aria-label="{{ __('Purchase invoices') }}">
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
                        <flux:table.cell data-primary>{{ $invoice->invoice_number ?: $invoice->supplier_reference ?: '#'.$invoice->id }}<div class="text-xs text-text-muted">{{ $invoice->supplier_reference }}</div></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Supplier') }}">{{ app()->getLocale() === 'ar' ? ($invoice->supplier?->name_ar ?: $invoice->supplier?->name_en) : ($invoice->supplier?->name_en ?: $invoice->supplier?->name_ar) }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Store') }}">{{ app()->getLocale() === 'ar' ? ($invoice->store?->name_ar ?: $invoice->store?->name_en) : ($invoice->store?->name_en ?: $invoice->store?->name_ar) }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Date') }}">{{ $invoice->invoice_date?->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell data-label="{{ __('Total') }}"><x-money :amount="$invoice->total_amount" :currency="$invoice->currency_code" /></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Status') }}"><x-status.badge :status="$invoice->status" /></flux:table.cell>
                        <flux:table.cell data-label="{{ __('Actions') }}">
                            @can('purchase_invoices_supplier_returns.print')
                                <x-actions.button semantic="print" :label="__('Print')" :href="route('purchasing.invoices.print', $invoice)" target="_blank">{{ __('Print') }}</x-actions.button>
                            @endcan
                            @if ($invoice->status === 'draft')
                                <div class="flex flex-wrap gap-2">
                                    <x-actions.button semantic="edit" :label="__('Edit')" wire:click="openEditModal({{ $invoice->id }})">{{ __('Edit') }}</x-actions.button>
                                    @can('purchase_invoices_supplier_returns.edit')
                                        <flux:button size="sm" variant="subtle" wire:click="submitInvoice({{ $invoice->id }})">{{ __('Submit') }}</flux:button>
                                    @endcan
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif (in_array($invoice->status, ['submitted','awaiting_distribution'], true))
                                <div class="flex flex-wrap gap-2">
                                    @can('purchase_invoices.distribute')
                                        <flux:button size="sm" variant="primary" wire:click="openDistribution({{ $invoice->id }})">{{ __('Distribute') }}</flux:button>
                                    @endcan
                                    @can('purchase_invoices.approve')
                                        <x-actions.button semantic="reject" :label="__('Reject')" wire:click="openTransitionModal('reject', {{ $invoice->id }})">{{ __('Reject') }}</x-actions.button>
                                    @endcan
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif ($invoice->status === 'approved')
                                @can('purchase_invoices.print_labels')
                                    @foreach($invoice->distributions->whereNotNull('effective_selling_price')->unique('destination_store_id') as $allocation)
                                        <x-actions.button semantic="print" :label="__('Print labels')" :href="route('purchasing.invoices.destination-labels',[$invoice,$allocation->destination_store_id])" target="_blank">{{ __('Labels') }} · {{ app()->getLocale()==='ar'?$allocation->destination?->name_ar:($allocation->destination?->name_en?:$allocation->destination?->name_ar) }}</x-actions.button>
                                    @endforeach
                                @endcan
                                @can('purchase_invoices.reverse')
                                    <flux:button size="sm" variant="danger" wire:click="openTransitionModal('reverse', {{ $invoice->id }})">{{ __('Reverse') }}</flux:button>
                                @endcan
                            @else
                                <flux:text>{{ __('Locked') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="flex flex-col items-center gap-2 py-10 text-center">
                                <flux:icon name="document-text" class="size-8 text-text-muted" />
                                <flux:heading size="sm">{{ __('No purchase invoices yet.') }}</flux:heading>
                                <flux:text>{{ __('Create a draft invoice to begin reviewing purchases.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <x-tables.pagination :paginator="$invoices" />
    </flux:card>
    @endunless

    @if ($showFormModal)
        <flux:card class="space-y-5" aria-label="{{ __('Stage 1 — Purchase invoice') }}">
            <form wire:submit="saveInvoice" class="space-y-5">
                <div><flux:heading size="xl">{{ __('Stage 1 — Purchase invoice') }}</flux:heading><flux:text>{{ __('Scan or search products, then save the invoice safely as a draft.') }}</flux:text></div>
                @if (filled($invoiceForm['purchase_order_id']))
                    <input type="hidden" wire:model="invoiceForm.purchase_order_id" />
                    <flux:callout icon="inbox-arrow-down" variant="info">
                        {{ __('Receiving purchase order :id', ['id' => $invoiceForm['purchase_order_id']]) }}
                    </flux:callout>
                @endif
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
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:input wire:model="productLookup" class="flex-1" :label="__('Barcode, item code, product name, or model number')" autofocus />
                        <flux:button type="button" wire:click="addScannedProduct" variant="primary" icon="barcode">{{ __('Add / scan') }}</flux:button>
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
                            <flux:input wire:model="lineItems.{{ $index }}.unit_cost" type="number" min="0" step="0.0001" :label="__('Unit cost')" :disabled="!Gate::allows('purchase_invoices.change_cost')" />
                            <flux:input wire:model="lineItems.{{ $index }}.base_consumer_price" type="number" min="0" step="0.001" :label="__('Base Consumer Price List 0')" :disabled="!Gate::allows('purchase_invoices.change_list0_price')" />
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
        </flux:card>
    @endif

    @if($showDistribution && $distributionInvoice)
        <flux:card class="space-y-5" aria-label="{{ __('Stage 2 — Mandatory distribution') }}">
            <div><flux:heading size="xl">{{ __('Stage 2 — Mandatory distribution') }}</flux:heading><flux:text>{{ __('Select destinations and allocate every purchased unit. The receiving warehouse is temporary and must finish at zero.') }}</flux:text></div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($stores->where('id','!=',$distributionInvoice->store_id) as $store)
                    <flux:checkbox wire:model.live="selectedDestinationIds" value="{{ $store->id }}" :label="app()->getLocale()==='ar' ? $store->name_ar : ($store->name_en ?: $store->name_ar)" />
                @endforeach
            </div>
            <form wire:submit="saveDistribution" class="space-y-4 overflow-x-auto">
                <table class="min-w-full text-sm"><thead><tr><th class="p-2 text-start">{{__('Product')}}</th><th class="p-2">{{__('Purchased')}}</th>@foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<th class="p-2">{{ app()->getLocale()==='ar'?$store->name_ar:($store->name_en?:$store->name_ar) }}@if($store->type==='selling')<div class="text-xs text-text-muted">{{__('Effective destination price')}}</div>@endif</th>@endforeach<th class="p-2">{{__('Distributed')}}</th><th class="p-2">{{__('Remaining')}}</th></tr></thead>
                <tbody>@foreach($distributionInvoice->lines as $line) @php($distributed=collect($distributionMatrix[$line->id]??[])->reduce(fn($t,$q)=>bcadd($t,is_numeric($q)?(string)$q:'0',6),'0'))
                    <tr class="border-t"><td class="p-2"><strong>{{ app()->getLocale()==='ar'?$line->product->name_ar:($line->product->name_en?:$line->product->name_ar) }}</strong><div>{{ $line->product->model_number }} · {{ $line->product->item_code }}</div></td><td class="p-2 text-center">{{ $line->quantity }}</td>
                    @foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<td class="p-2"><flux:input wire:model.live="distributionMatrix.{{ $line->id }}.{{ $store->id }}" type="number" min="0" step="0.000001" aria-label="{{__('Distribution quantity')}}" />@php($saved=$distributionInvoice->distributions->first(fn($d)=>$d->purchase_invoice_line_id===$line->id&&$d->destination_store_id===$store->id))@if($saved?->effective_selling_price)<div class="mt-1 text-center">{{ $saved->effective_selling_price }} {{ $distributionInvoice->currency_code }}</div>@endif</td>@endforeach
                    <td class="p-2 text-center">{{ $distributed }}</td><td class="p-2 text-center {{ bccomp(bcsub((string)$line->quantity,$distributed,6),'0',6)!==0?'text-red-600':'text-green-700' }}">{{ bcsub((string)$line->quantity,$distributed,6) }}</td></tr>
                @endforeach</tbody></table>
                <div class="flex flex-wrap justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('showDistribution', false)">{{__('Back to invoices')}}</flux:button><flux:button type="submit" variant="primary">{{__('Save distribution')}}</flux:button>@can('purchase_invoices.approve')<flux:button type="button" wire:click="approveInvoice({{$distributionInvoice->id}})" variant="primary">{{__('Approve & post')}}</flux:button>@endcan</div>
            </form>
        </flux:card>
    @endif

    @if($showQuickProduct)
        <flux:modal wire:model.self="showQuickProduct" class="md:max-w-2xl"><form wire:submit="createQuickProduct" class="space-y-4"><flux:heading size="lg">{{__('Unknown barcode — create Product Card')}}</flux:heading><flux:text>{{__('The invoice remains open. Create the product and it will be inserted immediately.')}}</flux:text><div class="grid gap-3 sm:grid-cols-2"><flux:input wire:model="quickProduct.name_ar" :label="__('Arabic name')" required/><flux:input wire:model="quickProduct.name_en" :label="__('English name')"/><flux:input wire:model="quickProduct.model_number" :label="__('Model number')"/><flux:select wire:model="quickProduct.category_id" :label="__('Category')"><flux:select.option value="">{{__('Select category')}}</flux:select.option>@foreach($categories as $category)<flux:select.option value="{{$category->id}}">{{$category->name_en?:$category->name_ar}}</flux:select.option>@endforeach</flux:select></div><div class="flex justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('showQuickProduct',false)">{{__('Cancel')}}</flux:button><flux:button type="submit" variant="primary">{{__('Create and add')}}</flux:button></div></form></flux:modal>
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
