<x-layouts::app :title="$mode === 'supplier' ? __('Supplier invoice history') : __('Purchase cost history')">
    <x-app.page
        :title="$mode === 'supplier' ? __('Supplier invoice history') : __('Purchase cost history')"
        :description="$mode === 'supplier' ? __('Review approved supplier invoices in your authorized store scope.') : __('Trace approved source invoice costs by product and supplier.')"
        max-width="7xl"
    >
        <flux:card class="space-y-4">
            <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <flux:select name="supplier_id" :label="__('Supplier')"><option value="">{{ __('All suppliers') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->code }} · {{ app()->getLocale() === 'ar' ? $supplier->name_ar : $supplier->name_en }}</option>@endforeach</flux:select>
                <flux:select name="product_id" :label="__('Product')"><option value="">{{ __('All products') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($productId === $product->id)>{{ $product->item_code }} · {{ app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en }}</option>@endforeach</flux:select>
                <flux:input name="date_from" type="date" :value="$dateFrom" :label="__('From date')" />
                <flux:input name="date_to" type="date" :value="$dateTo" :label="__('To date')" />
                <flux:button type="submit" variant="primary" icon="funnel" class="w-full min-h-11 whitespace-nowrap font-semibold shadow-sm sm:w-auto" aria-label="{{ __('Apply filters') }}">{{ __('Apply filters') }}</flux:button>
            </form>
        </flux:card>

        <x-tables.data-panel :title="$mode === 'supplier' ? __('Approved supplier invoices') : __('Approved source-line costs')" :description="__('Only approved, source-linked purchasing history is included.')">
            <div class="overflow-x-auto">
                @if($mode === 'supplier')
                    <table class="data-table min-w-[760px] w-full"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Store') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reference') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead><tbody>
                    @forelse($invoices as $invoice)<tr><td class="font-mono">{{ $invoice->invoice_number }}</td><td>{{ $invoice->supplier?->code }}</td><td>{{ $invoice->store?->code }}</td><td>{{ $invoice->invoice_date?->format('Y-m-d') }}</td><td>{{ $invoice->supplier_reference }}</td><td class="text-end tabular-nums">{{ number_format((float) $invoice->total_amount, 2) }} {{ $invoice->currency_code }}</td></tr>@empty<tr><td colspan="6" class="p-0"><div class="rounded-lg border border-dashed border-zinc-300/70 bg-zinc-50/5 px-4 py-2"><x-state.empty :title="__('No approved supplier invoices found.')" :description="__('Adjust the filters or approve an invoice first.')" /></div></td></tr>@endforelse
                    </tbody></table>
                @else
                    <table class="data-table min-w-[880px] w-full"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Product') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Store') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Line total') }}</th></tr></thead><tbody>
                    @forelse($costLines as $line)<tr><td class="font-mono">{{ $line->invoice?->invoice_number }}</td><td><span class="font-mono">{{ $line->product?->item_code }}</span> · {{ app()->getLocale() === 'ar' ? $line->product?->name_ar : $line->product?->name_en }}</td><td>{{ $line->invoice?->supplier?->code }}</td><td>{{ $line->invoice?->store?->code }}</td><td>{{ $line->invoice?->invoice_date?->format('Y-m-d') }}</td><td class="text-end tabular-nums">{{ $line->quantity }}</td><td class="text-end tabular-nums">{{ number_format((float) $line->unit_cost, 4) }}</td><td class="text-end tabular-nums">{{ number_format((float) $line->line_total, 4) }}</td></tr>@empty<tr><td colspan="8" class="p-0"><div class="rounded-lg border border-dashed border-zinc-300/70 bg-zinc-50/5 px-4 py-2"><x-state.empty :title="__('No approved purchase costs found.')" :description="__('Adjust the filters or approve a source invoice first.')" /></div></td></tr>@endforelse
                    </tbody></table>
                @endif
            </div>
            <x-slot:footer>{{ $mode === 'supplier' ? $invoices->links() : $costLines->links() }}</x-slot:footer>
        </x-tables.data-panel>
        @if($mode === 'supplier')
            <div class="grid gap-5 xl:grid-cols-2">
                <x-tables.data-panel :title="__('Approved supplier returns')" :description="__('Latest referenced returns in the selected scope.')">
                    <div class="overflow-x-auto"><table class="data-table min-w-[560px] w-full"><thead><tr><th>{{ __('Return') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Source invoice') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead><tbody>@forelse($supplierReturns as $return)<tr><td class="font-mono">{{ $return->return_number }}</td><td>{{ $return->supplier?->code }}</td><td class="font-mono">{{ $return->purchaseInvoice?->invoice_number }}</td><td>{{ $return->return_date?->format('Y-m-d') }}</td><td class="text-end tabular-nums">{{ number_format((float) $return->total_amount, 2) }}</td></tr>@empty<tr><td colspan="5"><x-state.empty :title="__('No approved supplier returns found.')" /></td></tr>@endforelse</tbody></table></div>
                </x-tables.data-panel>
                <x-tables.data-panel :title="__('Supplier last prices')" :description="__('Current supplier-product references derived from purchasing history.')">
                    <div class="overflow-x-auto"><table class="data-table min-w-[520px] w-full"><thead><tr><th>{{ __('Product') }}</th><th>{{ __('Last purchase date') }}</th><th class="text-end">{{ __('Last price') }}</th></tr></thead><tbody>@forelse($lastPrices as $link)<tr><td><span class="font-mono">{{ $link->product?->item_code }}</span> · {{ app()->getLocale() === 'ar' ? $link->product?->name_ar : $link->product?->name_en }}</td><td>{{ $link->last_purchase_date?->format('Y-m-d') }}</td><td class="text-end tabular-nums">{{ number_format((float) $link->last_purchase_price, 4) }}</td></tr>@empty<tr><td colspan="3"><x-state.empty :title="__('Select a supplier with recorded last prices.')" /></td></tr>@endforelse</tbody></table></div>
                </x-tables.data-panel>
            </div>
        @endif
    </x-app.page>
</x-layouts::app>
