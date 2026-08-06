<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Modules\Purchasing\Actions\ClosePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Orders')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $supplierFilter = 'all';

    public bool $showFormModal = false;

    public ?int $editingOrderId = null;

    public array $orderForm = [
        'supplier_id' => '',
        'store_id' => '',
        'order_date' => '',
        'expected_delivery_date' => '',
        'payment_terms' => '',
        'notes' => '',
        'lock_version' => 0,
    ];

    public array $lineItems = [];

    public bool $showDetailModal = false;

    public ?int $viewingOrderId = null;

    public string $detailTab = 'items';

    public bool $showCancelModal = false;

    public ?int $cancellingOrderId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        Gate::authorize('purchase_orders.view');
        $this->orderForm['order_date'] = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('purchase_orders.create');

        $this->resetValidation();
        $this->editingOrderId = null;
        $this->orderForm = [
            'supplier_id' => '',
            'store_id' => '',
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => '',
            'payment_terms' => '',
            'notes' => '',
            'lock_version' => 0,
        ];
        $this->lineItems = [
            ['product_id' => '', 'quantity_ordered' => 1, 'unit_cost' => 0, 'notes' => ''],
        ];
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        $order = PurchaseOrder::query()->with('lines')->findOrFail($id);
        if ($order->status !== 'draft') {
            Flux::toast(__('Only draft purchase orders can be edited.'), variant: 'danger');

            return;
        }

        $this->resetValidation();
        $this->editingOrderId = $order->id;
        $this->orderForm = [
            'supplier_id' => (string) $order->supplier_id,
            'store_id' => $order->store_id ? (string) $order->store_id : '',
            'order_date' => $order->order_date?->format('Y-m-d') ?: now()->toDateString(),
            'expected_delivery_date' => $order->expected_delivery_date?->format('Y-m-d') ?: '',
            'payment_terms' => $order->payment_terms ?: '',
            'notes' => $order->notes ?: '',
            'lock_version' => $order->lock_version,
        ];

        $this->lineItems = [];
        foreach ($order->lines as $line) {
            $this->lineItems[] = [
                'product_id' => (string) $line->product_id,
                'quantity_ordered' => (float) $line->quantity_ordered,
                'unit_cost' => (float) $line->unit_cost,
                'notes' => $line->notes ?: '',
            ];
        }

        if (empty($this->lineItems)) {
            $this->lineItems[] = ['product_id' => '', 'quantity_ordered' => 1, 'unit_cost' => 0, 'notes' => ''];
        }

        $this->showFormModal = true;
    }

    public function addLine(): void
    {
        $this->lineItems[] = ['product_id' => '', 'quantity_ordered' => 1, 'unit_cost' => 0, 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lineItems) > 1) {
            array_splice($this->lineItems, $index, 1);
        }
    }

    public function onProductSelected(int $index, string $productId): void
    {
        $this->lineItems[$index]['product_id'] = $productId;
        if (! empty($productId)) {
            $product = Product::query()->find((int) $productId);
            if ($product && ! empty($product->cost_price)) {
                $this->lineItems[$index]['unit_cost'] = (float) $product->cost_price;
            }
        }
    }

    public function saveOrder(): void
    {
        Gate::authorize($this->editingOrderId ? 'purchase_orders.edit' : 'purchase_orders.create');

        $this->validate([
            'orderForm.supplier_id' => 'required|exists:suppliers,id',
            'orderForm.store_id' => 'nullable|exists:stores,id',
            'orderForm.order_date' => 'required|date',
            'orderForm.expected_delivery_date' => 'nullable|date|after_or_equal:orderForm.order_date',
            'lineItems' => 'required|array|min:1',
            'lineItems.*.product_id' => 'required|exists:products,id',
            'lineItems.*.quantity_ordered' => 'required|numeric|gt:0',
            'lineItems.*.unit_cost' => 'required|numeric|gte:0',
        ]);

        try {
            $action = app(SavePurchaseOrderAction::class);
            $order = $action->execute(
                data: $this->orderForm,
                lines: $this->lineItems,
                id: $this->editingOrderId,
                expectedVersion: $this->editingOrderId ? (int) $this->orderForm['lock_version'] : null,
            );

            $this->showFormModal = false;
            Flux::toast($this->editingOrderId ? __('Purchase Order updated successfully.') : __('Purchase Order :number created as draft.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function submitOrder(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Flux::toast(__('Purchase Order :number submitted successfully.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function approveOrder(int $id): void
    {
        Gate::authorize('purchase_orders.approve');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Flux::toast(__('Purchase Order :number approved successfully. No stock or invoice posting occurred.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function openCancelModal(int $id): void
    {
        Gate::authorize('purchase_orders.cancel');

        $this->cancellingOrderId = $id;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function cancelOrder(): void
    {
        Gate::authorize('purchase_orders.cancel');

        $this->validate([
            'cancelReason' => 'required|string|min:3|max:500',
        ]);

        try {
            $order = PurchaseOrder::findOrFail($this->cancellingOrderId);
            app(CancelPurchaseOrderAction::class)->execute($order->id, $this->cancelReason, $order->lock_version);

            $this->showCancelModal = false;
            Flux::toast(__('Purchase Order :number cancelled.', ['number' => $order->po_number]), variant: 'warning');
        } catch (Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function closeOrder(int $id): void
    {
        Gate::authorize('purchase_orders.edit');

        try {
            $order = PurchaseOrder::findOrFail($id);
            app(ClosePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            Flux::toast(__('Purchase Order :number closed.', ['number' => $order->po_number]), variant: 'success');
        } catch (Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    public function openDetailModal(int $id): void
    {
        Gate::authorize('purchase_orders.view');

        $this->viewingOrderId = $id;
        $this->detailTab = 'items';
        $this->showDetailModal = true;
    }

    public function render(): View
    {
        $user = auth()->user();
        $query = PurchaseOrder::query()->with(['supplier', 'store', 'creator', 'lines']);

        if ($user && ! $user->is_super_admin) {
            $query->where(function ($scope) use ($user) {
                $scope->whereIn('store_id', Store::visibleTo($user)->select('id'))
                    ->orWhereIn('branch_id', Branch::visibleTo($user)->select('id'));
            });
        }

        if (! empty($this->search)) {
            $search = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', $search)
                    ->orWhere('notes', 'like', $search)
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name_ar', 'like', $search)
                            ->orWhere('name_en', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->supplierFilter !== 'all') {
            $query->where('supplier_id', (int) $this->supplierFilter);
        }

        $orders = $query->latest('id')->paginate(15);
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('name_ar')->get();
        $stores = Store::visibleTo($user)->where('status', 'active')->orderBy('name_ar')->get();
        $products = Product::query()->where('status', 'active')->orderBy('name_ar')->get();

        $viewingOrderQuery = PurchaseOrder::query()->with(['supplier', 'store', 'creator', 'submitter', 'approver', 'canceller', 'closer', 'lines.product']);
        if ($user && ! $user->is_super_admin) {
            $viewingOrderQuery->where(function ($scope) use ($user) {
                $scope->whereIn('store_id', Store::visibleTo($user)->select('id'))
                    ->orWhereIn('branch_id', Branch::visibleTo($user)->select('id'));
            });
        }
        $viewingOrder = $this->viewingOrderId ? $viewingOrderQuery->find($this->viewingOrderId) : null;

        $auditLogs = $viewingOrder ? AuditLog::query()
            ->where('source_type', PurchaseOrder::class)
            ->where('source_id', (string) $viewingOrder->id)
            ->latest('id')
            ->get() : collect();

        // Calculate preview totals for current form lines
        $formSubtotal = 0;
        foreach ($this->lineItems as $item) {
            $qty = (float) ($item['quantity_ordered'] ?? 0);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $formSubtotal += ($qty * $cost);
        }

        return view('purchasing.orders', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'stores' => $stores,
            'products' => $products,
            'viewingOrder' => $viewingOrder,
            'auditLogs' => $auditLogs,
            'formSubtotal' => $formSubtotal,
            'canCreate' => Gate::allows('purchase_orders.create'),
            'canEdit' => Gate::allows('purchase_orders.edit'),
            'canCancel' => Gate::allows('purchase_orders.cancel'),
            'canPrint' => Gate::allows('purchase_orders.print'),
            'canApprove' => Gate::allows('purchase_orders.approve'),
        ]);
    }
}; ?>

<x-app.page
    :title="__('Purchase Orders')"
    :description="__('Manage procurement lifecycle, draft order lines, and order state actions.')"
    max-width="7xl"
    class="purchasing-screen"
    data-guide="po-header"
>
    <x-slot:actions>
        <flux:button href="{{ route('purchasing.invoices.readiness') }}" variant="subtle" icon="clipboard-document-list" data-guide="tsk-015-readiness-link">
            {{ app()->getLocale() === 'ar' ? 'جاهزية الفواتير' : 'Invoice readiness' }}
        </flux:button>
        @if ($canCreate)
            <div>
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal" data-guide="po-create-action">
                    {{ __('New Purchase Order') }}
                </flux:button>
            </div>
        @endif
    </x-slot:actions>

    <!-- Filters Bar -->
    <flux:card class="space-y-4 p-5 sm:p-6" data-guide="po-filters">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by PO #, supplier or notes...')" />

            <flux:select wire:model.live="statusFilter" :label="null">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="submitted">{{ __('Submitted') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="partially_received">{{ __('Partially Received') }}</option>
                <option value="received">{{ __('Received') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
                <option value="closed">{{ __('Closed') }}</option>
            </flux:select>

            <flux:select wire:model.live="supplierFilter" :label="null">
                <option value="all">{{ __('All Suppliers') }}</option>
                @foreach ($suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ app()->getLocale() === 'ar' ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <!-- Orders Table -->
    <flux:card class="overflow-hidden p-0" data-guide="po-table">
        <div class="app-table-frame">
            <table class="w-full text-start text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('PO Number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Store') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Order Date') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Total') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3 font-mono font-bold text-primary">
                                {{ $order->po_number }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ app()->getLocale() === 'ar' ? $order->supplier->name_ar : ($order->supplier->name_en ?: $order->supplier->name_ar) }}
                                </div>
                                <div class="text-xs font-mono text-zinc-500">{{ $order->supplier->code }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400 text-xs">
                                {{ $order->store ? (app()->getLocale() === 'ar' ? $order->store->name_ar : $order->store->name_en) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium uppercase tracking-wide
                                    @if($order->status === 'draft') bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300
                                    @elseif($order->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300
                                    @elseif($order->status === 'approved') bg-violet-100 text-violet-800 dark:bg-violet-950/80 dark:text-violet-300
                                    @elseif($order->status === 'partially_received') bg-sky-100 text-sky-800 dark:bg-sky-950/80 dark:text-sky-300
                                    @elseif($order->status === 'received') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300
                                    @elseif($order->status === 'cancelled') bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300
                                    @else bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 @endif">
                                    {{ __(ucfirst(str_replace('_', ' ', $order->status))) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400 font-mono">
                                {{ $order->order_date?->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-3 text-end font-mono font-semibold text-zinc-900 dark:text-white">
                                {{ number_format((float) $order->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button size="xs" variant="ghost" icon="eye" wire:click="openDetailModal({{ $order->id }})" title="{{ __('View Details') }}" />

                                    @if ($order->isDraft() && $canEdit)
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditModal({{ $order->id }})" title="{{ __('Edit Draft') }}" />
                                        <flux:button size="xs" variant="subtle" icon="paper-airplane" wire:click="submitOrder({{ $order->id }})" title="{{ __('Submit Order') }}" />
                                    @endif

                                    @if ($order->status === 'submitted' && $canApprove && $order->submitted_by !== auth()->id())
                                        <flux:button size="xs" variant="primary" icon="check-badge" wire:click="approveOrder({{ $order->id }})" title="{{ __('Approve Order') }}" />
                                    @endif

                                    @if ($order->isCancellable() && $canCancel)
                                        <flux:button size="xs" variant="ghost" icon="x-mark" class="text-rose-600 dark:text-rose-400" wire:click="openCancelModal({{ $order->id }})" title="{{ __('Cancel Order') }}" />
                                    @endif

                                    @if ($order->isClosable() && $canEdit)
                                        <flux:button size="xs" variant="ghost" icon="check-circle" class="text-emerald-600 dark:text-emerald-400" wire:click="closeOrder({{ $order->id }})" title="{{ __('Close Order') }}" />
                                    @endif

                                    @if ($canPrint)
                                        <a href="{{ route('purchasing.orders.print', $order->id) }}" target="_blank" class="inline-flex items-center justify-center p-1 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition" title="{{ __('Print A4') }}">
                                            <flux:icon name="printer" size="sm" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center" data-guide="po-empty">
                                <div class="max-w-xs mx-auto space-y-3">
                                    <flux:icon name="document-text" class="size-10 mx-auto text-zinc-400" />
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('No purchase orders found') }}</h3>
                                    <p class="text-xs text-zinc-500">{{ __('Try adjusting search query or filters, or create a new draft purchase order.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="border-t border-border p-4" data-guide="po-pagination">
                {{ $orders->links() }}
            </div>
        @endif
    </flux:card>

    <!-- Create / Edit Form Modal -->
    <flux:modal wire:model="showFormModal" class="md:max-w-4xl space-y-6">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                {{ $editingOrderId ? __('Edit Draft Purchase Order') : __('New Purchase Order') }}
            </h2>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Enter supplier, destination store, order dates, and item lines.') }}</p>
        </div>

        <form wire:submit.prevent="saveOrder" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:select wire:model="orderForm.supplier_id" :label="__('Supplier') . ' *'">
                    <option value="">{{ __('Select Supplier') }}</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ app()->getLocale() === 'ar' ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="orderForm.store_id" :label="__('Receiving Store / Warehouse')">
                    <option value="">{{ __('Select Store (Optional)') }}</option>
                    @foreach ($stores as $st)
                        <option value="{{ $st->id }}">{{ app()->getLocale() === 'ar' ? $st->name_ar : $st->name_en }} ({{ $st->code }})</option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="orderForm.order_date" :label="__('Order Date') . ' *'" />
                <flux:input type="date" wire:model="orderForm.expected_delivery_date" :label="__('Expected Delivery Date')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="orderForm.payment_terms" :label="__('Payment Terms')" :placeholder="__('e.g. Net 30 days')" />
                <flux:input wire:model="orderForm.notes" :label="__('Order Notes')" :placeholder="__('Internal procurement reference notes...')" />
            </div>

            <!-- Line Items Editor -->
            <div class="space-y-3 border-t border-border pt-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Order Line Items') }}</h3>
                    <flux:button size="xs" variant="subtle" icon="plus" wire:click="addLine">
                        {{ __('Add Item Line') }}
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @foreach ($lineItems as $index => $item)
                        <div class="grid grid-cols-12 gap-2 items-center bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-border">
                            <div class="col-span-12 sm:col-span-5">
                                <label class="text-xs text-zinc-500 sm:hidden">{{ __('Product') }}</label>
                                <select wire:change="onProductSelected({{ $index }}, $event.target.value)" class="w-full text-xs rounded-md border-border bg-white dark:bg-zinc-800 p-2">
                                    <option value="">{{ __('Select Product') }}</option>
                                    @foreach ($products as $prod)
                                        <option value="{{ $prod->id }}" @if(($item['product_id'] ?? '') == $prod->id) selected @endif>
                                            {{ app()->getLocale() === 'ar' ? $prod->name_ar : ($prod->name_en ?: $prod->name_ar) }} ({{ $prod->sku ?: $prod->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-5 sm:col-span-3">
                                <label class="text-xs text-zinc-500 sm:hidden">{{ __('Qty') }}</label>
                                <flux:input type="number" step="0.0001" min="0.0001" wire:model.live="lineItems.{{ $index }}.quantity_ordered" :placeholder="__('Qty')" size="sm" />
                            </div>

                            <div class="col-span-5 sm:col-span-3">
                                <label class="text-xs text-zinc-500 sm:hidden">{{ __('Unit Cost') }}</label>
                                <flux:input type="number" step="0.0001" min="0" wire:model.live="lineItems.{{ $index }}.unit_cost" :placeholder="__('Cost')" size="sm" />
                            </div>

                            <div class="col-span-2 sm:col-span-1 text-end">
                                @if (count($lineItems) > 1)
                                    <flux:button size="xs" variant="ghost" icon="trash" class="text-rose-600" wire:click="removeLine({{ $index }})" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2">
                    <div class="bg-zinc-100 dark:bg-zinc-800 px-4 py-2 rounded-lg text-end">
                        <span class="text-xs text-zinc-500">{{ __('Estimated Subtotal') }}: </span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($formSubtotal, 2) }}</span>
                        <span class="text-xs text-zinc-400 block mt-0.5">{{ __('Tax (Explicit local zero / TBD)') }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Draft') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Detail Drawer / Modal -->
    @if ($viewingOrder)
        <flux:modal wire:model="showDetailModal" class="md:max-w-3xl space-y-6">
            <div class="flex items-start justify-between border-b border-border pb-4">
                <div>
                    <h2 class="text-xl font-bold font-mono text-zinc-900 dark:text-white">{{ $viewingOrder->po_number }}</h2>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ __('Supplier') }}: <strong>{{ app()->getLocale() === 'ar' ? $viewingOrder->supplier->name_ar : ($viewingOrder->supplier->name_en ?: $viewingOrder->supplier->name_ar) }}</strong>
                    </p>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                        @if($viewingOrder->status === 'draft') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                        @elseif($viewingOrder->status === 'submitted') bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300
                        @elseif($viewingOrder->status === 'partially_received') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300
                        @elseif($viewingOrder->status === 'received') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                        @elseif($viewingOrder->status === 'cancelled') bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300
                        @else bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 @endif">
                        {{ __(ucfirst(str_replace('_', ' ', $viewingOrder->status))) }}
                    </span>
                </div>
            </div>

            <!-- Detail Tabs -->
            <div class="flex border-b border-border gap-4 text-sm font-medium">
                <button wire:click="$set('detailTab', 'items')" class="pb-2 border-b-2 transition @if($detailTab === 'items') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Items & Totals') }}
                </button>
                <button wire:click="$set('detailTab', 'receipts')" class="pb-2 border-b-2 transition @if($detailTab === 'receipts') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Goods Receipts & Invoices') }}
                </button>
                <button wire:click="$set('detailTab', 'audit')" class="pb-2 border-b-2 transition @if($detailTab === 'audit') border-primary text-primary font-bold @else border-transparent text-zinc-500 hover:text-zinc-700 @endif">
                    {{ __('Audit History') }}
                </button>
            </div>

            @if ($detailTab === 'items')
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-lg">
                        <div>
                            <span class="text-zinc-500">{{ __('Order Date') }}:</span>
                            <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->order_date?->format('Y-m-d') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500">{{ __('Expected Delivery') }}:</span>
                            <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->expected_delivery_date?->format('Y-m-d') ?: __('Not specified') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500">{{ __('Store') }}:</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->store ? (app()->getLocale() === 'ar' ? $viewingOrder->store->name_ar : $viewingOrder->store->name_en) : __('Unassigned') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500">{{ __('Payment Terms') }}:</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200 block">{{ $viewingOrder->payment_terms ?: __('Standard') }}</span>
                        </div>
                    </div>

                    <div class="border border-border rounded-lg overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 font-semibold uppercase text-zinc-600 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 text-start">#</th>
                                    <th class="px-3 py-2 text-start">{{ __('Product') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Qty') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Unit Cost') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('Subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($viewingOrder->lines as $line)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-zinc-400">{{ $line->line_number }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ app()->getLocale() === 'ar' ? $line->product->name_ar : ($line->product->name_en ?: $line->product->name_ar) }}</div>
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $line->product->sku ?: $line->product->code }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format((float) $line->quantity_ordered, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ number_format((float) $line->unit_cost, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-semibold">{{ number_format((float) $line->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <div class="w-60 space-y-1 text-xs bg-zinc-50 dark:bg-zinc-800/60 p-3 rounded-lg border border-border">
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Subtotal') }}:</span>
                                <span class="font-mono font-semibold">{{ number_format((float)$viewingOrder->subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Tax (TBD)') }}:</span>
                                <span class="font-mono">{{ number_format((float)$viewingOrder->tax_amount, 2) }}</span>
                            </div>
                            <div class="border-t border-border pt-1 flex justify-between font-bold text-sm text-zinc-900 dark:text-white">
                                <span>{{ __('Total') }}:</span>
                                <span class="font-mono">{{ number_format((float)$viewingOrder->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($viewingOrder->cancel_reason)
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
                            <strong>{{ __('Cancellation Reason') }}:</strong> {{ $viewingOrder->cancel_reason }}
                        </div>
                    @endif
                </div>
            @elseif ($detailTab === 'receipts')
                <div class="py-8 text-center space-y-3">
                    <flux:icon name="archive-box" class="size-10 mx-auto text-zinc-400" />
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('No Goods Receipts or Purchase Invoices') }}</h3>
                    <p class="text-xs text-zinc-500 max-w-sm mx-auto">
                        {{ __('Goods receipts and purchase invoice processing are handled in downstream milestone TSK-015. No receiving stock records or invoices exist for this order yet.') }}
                    </p>
                </div>
            @elseif ($detailTab === 'audit')
                <div class="space-y-3">
                    @forelse ($auditLogs as $log)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/40 border border-border text-xs space-y-1">
                            <div class="flex items-center justify-between font-semibold">
                                <span class="font-mono text-primary">{{ $log->event }}</span>
                                <span class="text-zinc-400 font-mono">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                            <p class="text-zinc-600 dark:text-zinc-300">
                                {{ __('Actor') }}: {{ $log->actor_name ?: __('System') }}
                            </p>
                            @if ($log->reason_text)
                                <p class="text-rose-600 dark:text-rose-400 italic">{{ __('Reason') }}: {{ $log->reason_text }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500 text-center py-6">{{ __('No audit log entries recorded for this document.') }}</p>
                    @endforelse
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">{{ __('Close') }}</flux:button>
                @if ($canPrint)
                    <a href="{{ route('purchasing.orders.print', $viewingOrder->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-800 dark:text-zinc-100 rounded-lg text-xs font-medium transition">
                        🖨️ {{ __('Print A4 Document') }}
                    </a>
                @endif
            </div>
        </flux:modal>
    @endif

    <!-- Cancel Confirmation Modal -->
    <flux:modal wire:model="showCancelModal" class="md:max-w-md space-y-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Cancel Purchase Order') }}</h2>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Please provide a reason for cancelling this purchase order.') }}</p>
        </div>

        <form wire:submit.prevent="cancelOrder" class="space-y-4">
            <flux:input wire:model="cancelReason" :label="__('Cancellation Reason') . ' *'" :placeholder="__('e.g. Supplier item out of stock...')" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Back') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Confirm Cancel') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-app.page>
