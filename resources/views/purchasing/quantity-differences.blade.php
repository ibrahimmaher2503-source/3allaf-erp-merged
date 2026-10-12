<x-layouts::app :title="__('Quantity differences')">
    <x-app.page :title="__('Quantity differences')" :description="__('Approved purchase shortages — information only.')">
        <flux:card>
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input name="search" :value="$filters['search'] ?? ''" :label="__('Search')" :placeholder="__('Invoice, supplier, product or barcode')" />
                <flux:input name="from" type="date" :value="$filters['from'] ?? ''" :label="__('From')" />
                <flux:input name="to" type="date" :value="$filters['to'] ?? ''" :label="__('To')" />
                <div class="flex items-end gap-2"><flux:button type="submit" variant="primary">{{ __('Filter') }}</flux:button><flux:button :href="route('purchasing.quantity-differences')">{{ __('Reset') }}</flux:button></div>
            </form>
            @foreach($errors->all() as $message)<p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p>@endforeach
        </flux:card>
        @can('purchase_invoices_supplier_returns.export')
            <div class="flex justify-end"><flux:button :href="route('purchasing.quantity-differences', [...$filters, 'export' => 'csv'])">{{ __('Export') }}</flux:button></div>
        @endcan
        <flux:card class="overflow-hidden">
            <div class="overflow-x-auto"><table class="data-table w-full min-w-[900px]">
                <thead><tr><th>{{ __('Purchase Invoice') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Branch') }} / {{ __('Receiving store') }}</th><th>{{ __('Product') }}</th><th>{{ __('Original quantity') }}</th><th>{{ __('Accepted quantity') }}</th><th>{{ __('Quantity difference') }}</th><th>{{ __('Status') }}</th><th>{{ __('Approved by') }}</th></tr></thead>
                <tbody>
                    @forelse($lines as $line)
                        @php($invoice = $line->invoice)
                        <tr><td><span dir="ltr">{{ $invoice->invoice_number }}</span><small class="block" dir="ltr">{{ $invoice->invoice_date?->format('Y-m-d') }}</small></td><td>{{ \App\Support\HumanName::name($invoice->supplier) }}</td><td>{{ \App\Support\HumanName::name($invoice->store?->branch) }}<small class="block">{{ \App\Support\HumanName::name($invoice->store) }}</small></td><td>{{ \App\Support\HumanName::name($line->product) }}<small class="block" dir="ltr">{{ $line->product?->item_code }}</small></td><td><x-product-quantity :value="$line->original_quantity" /></td><td><x-product-quantity :value="$line->quantity_received" /></td><td><x-product-quantity :value="$line->shortageQuantity()" /></td><td><flux:badge color="amber">{{ __('Not fully received') }}</flux:badge></td><td>{{ \App\Support\HumanName::name($invoice->approver) }}<small class="block" dir="ltr">{{ $invoice->approved_at?->format('Y-m-d H:i') }}</small></td></tr>
                    @empty
                        <tr><td colspan="9"><x-state.empty :title="__('No quantity differences found.')" /></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="mt-4">{{ $lines->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
