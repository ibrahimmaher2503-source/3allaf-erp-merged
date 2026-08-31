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
    public string $itemEntry = '';
    public string $productSearch = '';
    public int $productSearchRequest = 0;
    public bool $showQuickProduct = false;
    public array $quickProduct = ['barcode'=>'','name_ar'=>'','name_en'=>'','model_number'=>'','category_id'=>''];
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

    public function addItemEntry(): void
    {
        $lookup = trim($this->itemEntry);
        if ($lookup === '') { $this->dispatch('invoice-scanner-focus'); return; }
        $product = Product::query()->sellable()->where(function ($query) use ($lookup): void {
            $query->where('item_code', $lookup)->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $lookup)->where('status', 'active'));
        })->first();
        if (! $product) { $this->openQuickProduct($lookup); return; }
        $this->insertProduct($product);
    }

    public function selectSearchProduct(int $productId): void
    {
        $product = Product::query()->sellable()->findOrFail($productId);
        $this->insertProduct($product);
        $this->productSearch = '';
    }

    public function updatedProductSearch(): void
    {
        $this->productSearchRequest++;
    }

    public function searchProductsNow(string $term): void
    {
        $this->productSearch = trim($term);
        $this->productSearchRequest++;
    }

    public function openQuickProduct(string $barcode = ''): void
    {
        Gate::authorize('purchase_invoices_supplier_returns.create');
        $this->quickProduct = ['barcode'=>trim($barcode),'name_ar'=>'','name_en'=>'','model_number'=>'','category_id'=>''];
        $this->showQuickProduct = true;
    }

    public function closeQuickProduct(): void
    {
        $this->showQuickProduct = false;
        $this->dispatch('invoice-scanner-focus');
    }

    private function insertProduct(Product $product): void
    {
        foreach ($this->lineItems as $index => $line) {
            if ((int) ($line['product_id'] ?? 0) === $product->id) {
                $this->lineItems[$index]['quantity'] = bcadd((string) $line['quantity'], '1', 6);
                $this->itemEntry='';
                $this->dispatch('invoice-line-focus', index: $index);
                $this->dispatch('invoice-scanner-focus');
                return;
            }
        }
        if (count($this->lineItems) === 1 && empty($this->lineItems[0]['product_id'])) $this->lineItems=[];
        $this->lineItems[] = [...$this->emptyLine(), 'product_id'=>(string)$product->id, 'unit_cost'=>(string)($product->average_cost ?? 0), 'base_consumer_price'=>(string)($product->sale_price ?? 0)];
        $this->itemEntry='';
        $this->dispatch('invoice-scanner-focus');
    }

    public function createQuickProduct(SaveProductAction $save, ReserveProductIdentityAction $identity): void
    {
        $this->validate(['quickProduct.barcode'=>'nullable|string|max:64','quickProduct.name_ar'=>'required|string|max:255','quickProduct.name_en'=>'nullable|string|max:255','quickProduct.model_number'=>'nullable|string|max:100','quickProduct.category_id'=>'required|exists:categories,id']);
        $lookup=trim((string)$this->quickProduct['barcode']);
        $product=$save->execute([...$this->quickProduct,'item_code'=>preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,49}$/i',$lookup)?$lookup:'','product_type'=>'standard','status'=>'active','preferred_supplier_id'=>$this->invoiceForm['supplier_id'] ?: null,'average_cost'=>0,'sale_price'=>0]);
        if (preg_match('/^\d{8}$|^\d{12,14}$/',$lookup) && ! Barcode::query()->where('barcode',$lookup)->exists()) $identity->createPrimaryBarcode($product,app(\App\Modules\Catalog\Support\ProductBarcodePolicy::class)->international($lookup),'international');
        $this->showQuickProduct=false; $this->insertProduct($product);
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
            ->with(['supplier', 'store', 'lines.distributions', 'distributions.destination'])
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
            'productResults' => $this->searchProducts($actor),
            'lineProducts' => Product::query()->whereIn('id', collect($this->lineItems)->pluck('product_id')->filter()->map(fn($id)=>(int)$id))->get()->keyBy('id'),
            'categories' => Category::query()->where('status','active')->orderBy('name_en')->get(),
            'distributionInvoice' => $this->showDistribution && $this->editingInvoiceId ? PurchaseInvoice::query()->with(['lines.product','distributions.destination'])->find($this->editingInvoiceId) : null,
        ]);
    }

    public function formatQuantity(string|int|float|null $quantity): string
    {
        $formatted = number_format((float) ($quantity ?? 0), 6, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function searchProducts(User $actor): \Illuminate\Support\Collection
    {
        $term = trim($this->productSearch);
        if (mb_strlen($term) < 3) return collect();

        $companyId = Store::query()->visibleTo($actor)
            ->when(filled($this->invoiceForm['store_id']), fn ($query) => $query->whereKey((int) $this->invoiceForm['store_id']))
            ->where('status', 'active')->value('company_id');
        if (! $companyId) return collect();

        $escaped = addcslashes($term, '\\%_');
        $prefix = $escaped.'%';
        $contained = '%'.$escaped.'%';
        $tokens = collect(preg_split('/\s+/u', $term) ?: [])->filter()->take(4);

        return Product::query()->sellable()
            ->with(['barcodes' => fn ($query) => $query->where('status', 'active')->orderByDesc('is_primary')])
            ->where(function ($query) use ($term, $prefix, $contained, $tokens): void {
                $query->where('item_code', $term)->orWhere('model_number', $term)
                    ->orWhere('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)
                    ->orWhere('item_code', 'like', $contained)->orWhere('model_number', 'like', $contained)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('status', 'active')->where(function ($query) use ($term, $prefix, $contained): void {
                        $query->where('barcode', $term)->orWhere('barcode', 'like', $prefix)->orWhere('barcode', 'like', $contained);
                    }))
                    ->orWhere(function ($names) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $escapedToken = addcslashes((string) $token, '\\%_');
                            $names->orWhere('name_ar', 'like', $escapedToken.'%')->orWhere('name_ar', 'like', '% '.$escapedToken.'%')
                                ->orWhere('name_en', 'like', $escapedToken.'%')->orWhere('name_en', 'like', '% '.$escapedToken.'%');
                        }
                    });
            })
            ->orderByRaw('CASE WHEN item_code = ? OR model_number = ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode = ?) THEN 0 WHEN item_code LIKE ? OR model_number LIKE ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode LIKE ?) THEN 1 ELSE 2 END', [$term,$term,'active',$term,$prefix,$prefix,'active',$prefix])
            ->orderBy('item_code')->limit(20)->get();
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
                    <flux:select.option value="reversed">{{ __('Reversed') }}</flux:select.option>
                    <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                </flux:select>
            </div>
            <flux:select wire:model.live="supplierFilter" :label="__('Supplier')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($suppliers as $supplier)<flux:select.option value="{{ $supplier->id }}">{{ app()->getLocale() === 'ar' ? ($supplier->name_ar ?: '—') : ($supplier->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model.live="storeFilter" :label="__('Store')"><flux:select.option value="all">{{ __('All') }}</flux:select.option>@foreach($stores as $store)<flux:select.option value="{{ $store->id }}">{{ app()->getLocale() === 'ar' ? ($store->name_ar ?: '—') : ($store->name_en ?: '—') }}</flux:select.option>@endforeach</flux:select>
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
        </div>
    </flux:card>

    <div class="space-y-4" aria-label="{{ __('Purchase invoices') }}">
        @forelse ($invoices as $invoice)
            @php($remaining=$invoice->lines->reduce(fn($total,$line)=>bcadd($total,bcsub((string)$line->quantity,$line->distributions->reduce(fn($allocated,$distribution)=>bcadd($allocated,(string)$distribution->quantity,6),'0'),6),6),'0'))
            @php($fullyDistributed=$invoice->lines->isNotEmpty()&&bccomp($remaining,'0',6)===0)
            @php($stageOneComplete=$invoice->lines->isNotEmpty())
            @php($stage=$invoice->status==='draft' ? __('Stage 1') : __('Stage 2'))
            @php($nextAction=match($invoice->status){'draft'=>$stageOneComplete?__('Review and distribute quantities'):__('Complete invoice'),'submitted','awaiting_distribution'=>$fullyDistributed?__('Approve and post invoice'):__('Review and distribute quantities'),default=>__('No further action')})
            <flux:card class="overflow-hidden" wire:key="invoice-card-{{ $invoice->id }}">
                <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(11rem,1.25fr)_minmax(10rem,1fr)_minmax(9rem,.8fr)_minmax(13rem,1.35fr)] lg:items-start">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="truncate" dir="ltr">{{ $invoice->invoice_number ?: $invoice->supplier_reference ?: '#'.$invoice->id }}</strong>
                            <x-status.badge :status="$invoice->status" />
                        </div>
                        @if(filled($invoice->supplier_reference))<div class="truncate text-xs text-text-muted" dir="ltr">{{ $invoice->supplier_reference }}</div>@endif
                        <div class="text-sm">{{ app()->getLocale() === 'ar' ? ($invoice->supplier?->name_ar ?: '—') : ($invoice->supplier?->name_en ?: '—') }}</div>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm lg:grid-cols-1">
                        <div><dt class="text-xs text-text-muted">{{ __('Store') }}</dt><dd>{{ app()->getLocale() === 'ar' ? ($invoice->store?->name_ar ?: '—') : ($invoice->store?->name_en ?: '—') }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Date') }}</dt><dd dir="ltr">{{ $invoice->invoice_date?->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Total') }}</dt><dd><x-money :amount="$invoice->total_amount" :currency="$invoice->currency_code" /></dd></div>
                    </dl>
                    <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-1">
                        <div><dt class="text-xs text-text-muted">{{ __('Current stage') }}</dt><dd class="font-medium">{{ $stage }}</dd></div>
                        <div><dt class="text-xs text-text-muted">{{ __('Remaining quantity') }}</dt><dd class="tabular-nums" dir="ltr">{{ $this->formatQuantity($remaining) }}</dd></div>
                    </dl>
                    <div class="min-w-0 space-y-3 lg:border-s lg:ps-5">
                        <div><div class="text-xs text-text-muted">{{ __('Next required action') }}</div><div class="font-medium">{{ $nextAction }}</div></div>
                        <div class="flex flex-wrap gap-2">
                            @can('purchase_invoices_supplier_returns.print')
                                <x-actions.button semantic="print" :label="__('Print')" :href="route('purchasing.invoices.print', $invoice)" target="_blank">{{ __('Print') }}</x-actions.button>
                            @endcan
                            @if ($invoice->status === 'draft')
                                <div class="flex flex-wrap gap-2">
                                    @if($stageOneComplete)
                                        @can('purchase_invoices_supplier_returns.edit')
                                            <flux:button size="sm" variant="primary" wire:click="submitInvoice({{ $invoice->id }})">{{ __('Review and distribute quantities') }}</flux:button>
                                        @else
                                            <flux:button size="sm" disabled title="{{__('You do not have permission to complete Stage 1.')}}">{{ __('Review and distribute quantities') }}</flux:button>
                                        @endcan
                                        <flux:button size="sm" variant="subtle" wire:click="openEditModal({{ $invoice->id }})">{{ __('Edit') }}</flux:button>
                                    @else
                                        <flux:button variant="primary" wire:click="openEditModal({{ $invoice->id }})">{{ __('Complete invoice') }}</flux:button>
                                    @endif
                                    @can('purchase_invoices_supplier_returns.cancel')
                                        <flux:button size="sm" variant="subtle" wire:click="openTransitionModal('cancel', {{ $invoice->id }})">{{ __('Cancel') }}</flux:button>
                                    @endcan
                                </div>
                            @elseif (in_array($invoice->status, ['submitted','awaiting_distribution'], true))
                                <div class="flex flex-wrap gap-2">
                                    @if($fullyDistributed)
                                        @can('purchase_invoices.approve')<flux:button size="sm" variant="primary" wire:click="approveInvoice({{ $invoice->id }})">{{ __('Approve and post invoice') }}</flux:button>@else<flux:button size="sm" disabled title="{{__('You do not have permission to approve and post this invoice.')}}">{{ __('Approve and post invoice') }}</flux:button>@endcan
                                    @else
                                        @can('purchase_invoices.distribute')<flux:button size="sm" variant="primary" wire:click="openDistribution({{ $invoice->id }})">{{ __('Review and distribute quantities') }}</flux:button>@else<flux:button size="sm" disabled title="{{__('You do not have permission to distribute this invoice.')}}">{{ __('Review and distribute quantities') }}</flux:button>@endcan
                                    @endif
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
                                <flux:text title="{{ __('This invoice is locked because its state is final.') }}">{{ __('Locked') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:card><div class="flex flex-col items-center gap-2 py-10 text-center"><flux:icon name="document-text" class="size-8 text-text-muted" /><flux:heading size="sm">{{ __('No purchase invoices yet.') }}</flux:heading><flux:text>{{ __('Create a draft invoice to begin reviewing purchases.') }}</flux:text></div></flux:card>
        @endforelse
        <x-tables.pagination :paginator="$invoices" />
    </div>
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
                            <flux:select.option value="{{ $supplier->id }}">{{ app()->getLocale()==='ar' ? ($supplier->name_ar ?: '—') : ($supplier->name_en ?: '—') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="invoiceForm.store_id" :label="__('Receiving store')">
                        <flux:select.option value="">{{ __('Select store') }}</flux:select.option>
                        @foreach ($stores as $store)
                            <flux:select.option value="{{ $store->id }}">{{ app()->getLocale()==='ar' ? ($store->name_ar ?: '—') : ($store->name_en ?: '—') }}</flux:select.option>
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
                    </div>
                    <div x-data="{camera:false,stream:null,async start(){if(!('BarcodeDetector' in window)){alert(@js(__('Camera barcode scanning is not supported by this browser.')));return} this.camera=true; this.stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}); this.$nextTick(()=>{this.$refs.video.srcObject=this.stream; this.detect()})},async detect(){if(!this.camera)return; const hits=await (new BarcodeDetector()).detect(this.$refs.video); if(hits[0]){this.stop(); await $wire.set('itemEntry',hits[0].rawValue); await $wire.addItemEntry();return} requestAnimationFrame(()=>this.detect())},stop(){this.camera=false;this.stream?.getTracks().forEach(track=>track.stop());this.stream=null} }" class="space-y-3">
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                            <div class="min-w-0"><flux:input id="purchase-item-entry" wire:model="itemEntry" wire:keydown.enter.prevent="addItemEntry" :label="__('Barcode or item code')" autocomplete="off" autofocus x-on:invoice-scanner-focus.window="$nextTick(()=>$el.querySelector('input')?.focus())" /></div>
                            <flux:button type="button" wire:click="openQuickProduct" variant="primary" icon="plus" class="w-full sm:w-auto">{{ __('Add') }}</flux:button>
                            <flux:button type="button" x-on:click="start" variant="subtle" icon="camera" class="w-full sm:w-auto">{{ __('Scan with camera') }}</flux:button>
                        </div>
                        <div x-show="camera" x-cloak class="rounded-xl border p-3"><video x-ref="video" autoplay playsinline class="mx-auto max-h-64 rounded-lg"></video><flux:button type="button" class="mt-2" x-on:click="stop">{{ __('Close camera') }}</flux:button></div>
                    </div>
                    <div class="relative" x-data="{timer:null,queue(value){clearTimeout(this.timer);this.timer=setTimeout(()=>$wire.searchProductsNow(value),175)},now(value){clearTimeout(this.timer);$wire.searchProductsNow(value)}}">
                        <flux:input wire:model="productSearch" x-on:input="queue($event.target.value)" x-on:keydown.enter.prevent="now($event.target.value)" :label="__('Search products')" :description="__('Enter at least 3 characters or digits. Search covers name, model, item code, and barcode.')" autocomplete="off" />
                        <div wire:loading.flex wire:target="searchProductsNow" class="mt-2 items-center gap-2 rounded-xl border p-3 text-sm text-text-muted"><flux:icon.loading class="size-4" />{{ __('Searching products…') }}</div>
                        @if(mb_strlen(trim($productSearch)) >= 3)<div wire:key="product-search-{{$productSearchRequest}}" wire:loading.remove wire:target="searchProductsNow" class="mt-2 divide-y rounded-xl border bg-surface shadow-sm">@forelse($productResults as $result)<button type="button" wire:click="selectSearchProduct({{$result->id}})" class="grid w-full gap-2 p-3 text-start hover:bg-surface-muted sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)] sm:items-center"><strong class="truncate">{{ app()->getLocale()==='ar'?($result->name_ar?:'—'):($result->name_en?:'—') }}</strong><span class="grid gap-1 text-xs text-text-muted sm:grid-cols-3" dir="ltr"><span>{{ __('Model') }}: {{ $result->model_number ?: '—' }}</span><span>{{ __('Item code') }}: {{ $result->item_code }}</span><span>{{ __('Barcode') }}: {{ $result->barcodes->first()?->barcode ?: '—' }}</span></span></button>@empty<div class="p-3 text-sm text-text-muted">{{__('No matching products.')}}</div>@endforelse</div>@endif
                    </div>
                    @foreach ($lineItems as $index => $line)
                        @continue(empty($line['product_id']))
                        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-8" wire:key="invoice-line-{{ $index }}">
                            <div class="md:col-span-2" tabindex="-1" x-on:invoice-line-focus.window="if($event.detail.index==={{$index}})$el.focus()"><flux:label>{{__('Product')}}</flux:label><div class="mt-2 rounded-lg border p-3">@php($lineProduct=$lineProducts->get((int)$line['product_id']))<strong>{{ app()->getLocale()==='ar' ? ($lineProduct?->name_ar ?: '—') : ($lineProduct?->name_en ?: '—') }}</strong><div class="text-xs text-text-muted" dir="ltr">{{ $lineProduct?->item_code }}</div></div></div>
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
                    <flux:checkbox wire:model.live="selectedDestinationIds" value="{{ $store->id }}" :label="app()->getLocale()==='ar' ? ($store->name_ar ?: '—') : ($store->name_en ?: '—')" />
                @endforeach
            </div>
            <form wire:submit="saveDistribution" class="space-y-4 overflow-x-auto">
                <table class="min-w-full text-sm"><thead><tr><th class="p-2 text-start">{{__('Product')}}</th><th class="p-2">{{__('Purchased')}}</th>@foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<th class="p-2">{{ app()->getLocale()==='ar'?$store->name_ar:($store->name_en?:$store->name_ar) }}@if($store->type==='selling')<div class="text-xs text-text-muted">{{__('Effective destination price')}}</div>@endif</th>@endforeach<th class="p-2">{{__('Distributed')}}</th><th class="p-2">{{__('Remaining')}}</th></tr></thead>
                <tbody>@foreach($distributionInvoice->lines as $line) @php($distributed=collect($distributionMatrix[$line->id]??[])->reduce(fn($t,$q)=>bcadd($t,is_numeric($q)?(string)$q:'0',6),'0'))
                    <tr class="border-t"><td class="p-2"><strong>{{ app()->getLocale()==='ar'?($line->product->name_ar?:'—'):($line->product->name_en?:'—') }}</strong><div dir="ltr">{{ $line->product->model_number }} · {{ $line->product->item_code }}</div></td><td class="p-2 text-center" dir="ltr">{{ $this->formatQuantity($line->quantity) }}</td>
                    @foreach($stores->whereIn('id',array_map('intval',$selectedDestinationIds)) as $store)<td class="p-2"><flux:input wire:model.live="distributionMatrix.{{ $line->id }}.{{ $store->id }}" type="number" min="0" step="0.000001" aria-label="{{__('Distribution quantity')}}" />@php($saved=$distributionInvoice->distributions->first(fn($d)=>$d->purchase_invoice_line_id===$line->id&&$d->destination_store_id===$store->id))@if($saved?->effective_selling_price)<div class="mt-1 text-center"><x-money :amount="$saved->effective_selling_price" :currency="$distributionInvoice->currency_code" /></div>@endif</td>@endforeach
                    <td class="p-2 text-center" dir="ltr">{{ $this->formatQuantity($distributed) }}</td><td class="p-2 text-center {{ bccomp(bcsub((string)$line->quantity,$distributed,6),'0',6)!==0?'text-red-600':'text-green-700' }}" dir="ltr">{{ $this->formatQuantity(bcsub((string)$line->quantity,$distributed,6)) }}</td></tr>
                @endforeach</tbody></table>
                <div class="flex flex-wrap justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="$set('showDistribution', false)">{{__('Back to invoices')}}</flux:button><flux:button type="submit" variant="primary">{{__('Save distribution')}}</flux:button>@can('purchase_invoices.approve')<flux:button type="button" wire:click="approveInvoice({{$distributionInvoice->id}})" variant="primary">{{__('Approve & post')}}</flux:button>@endcan</div>
            </form>
        </flux:card>
    @endif

    @if($showQuickProduct)
        <flux:modal wire:model.self="showQuickProduct" class="md:max-w-2xl"><form wire:submit="createQuickProduct" class="space-y-4"><flux:heading size="lg">{{__('Unknown barcode — create Product Card')}}</flux:heading><flux:text>{{__('The invoice remains open. Create the product and it will be inserted immediately.')}}</flux:text><div class="grid gap-3 sm:grid-cols-2"><flux:input wire:model="quickProduct.barcode" :label="__('Barcode or item code')"/><flux:input wire:model="quickProduct.name_ar" :label="__('Arabic name')" required/><flux:input wire:model="quickProduct.name_en" :label="__('English name')"/><flux:input wire:model="quickProduct.model_number" :label="__('Model number')"/><flux:select wire:model="quickProduct.category_id" :label="__('Category')"><flux:select.option value="">{{__('Select category')}}</flux:select.option>@foreach($categories as $category)<flux:select.option value="{{$category->id}}">{{app()->getLocale()==='ar'?($category->name_ar?:'—'):($category->name_en?:'—')}}</flux:select.option>@endforeach</flux:select></div><div class="flex justify-end gap-2"><flux:button type="button" variant="subtle" wire:click="closeQuickProduct">{{__('Cancel')}}</flux:button><flux:button type="submit" variant="primary">{{__('Create and add')}}</flux:button></div></form></flux:modal>
    @endif

    @if ($showTransitionModal)
        <flux:modal wire:model.self="showTransitionModal" class="md:max-w-lg">
            <form wire:submit="executeTransition" class="space-y-5">
                <flux:heading size="lg">{{ __(ucfirst($transitionType)) }} {{ __('purchase invoice') }}</flux:heading>
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
