<x-layouts::app :title="__('Sales')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">{{ __('Sales') }}</flux:heading><flux:text class="mt-1">{{ __('All scoped retail sale records, including draft and suspended operational states.') }}</flux:text></div>
            <flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart" wire:navigate>{{ __('Open POS') }}</flux:button>
        </div>

        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Search')" :placeholder="__('Invoice or checkout reference')" />
            <flux:select name="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (['draft', 'suspended', 'approved', 'cancelled'] as $status)<flux:select.option value="{{ $status }}" :selected="request('status') === $status">{{ __(ucfirst($status)) }}</flux:select.option>@endforeach</flux:select>
            <flux:select name="store_id" :label="__('Store')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($stores as $store)<flux:select.option value="{{ $store->id }}" :selected="(string) request('store_id') === (string) $store->id">{{ $store->code }}</flux:select.option>@endforeach</flux:select>
            <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
            <div class="flex items-end gap-2"><flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" /><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>

        <x-tables.table-shell :label="__('Sales')">
            <table class="data-table w-full min-w-[900px] text-sm">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th><th>{{ __('Store') }}</th><th>{{ __('Cashier') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td><a class="font-semibold text-primary hover:underline" href="{{ route('sales.show', $sale) }}" wire:navigate>{{ $sale->document_number ?: __('Sale #:id', ['id' => $sale->id]) }}</a><div class="text-xs text-text-muted">{{ $sale->created_at?->format('Y-m-d H:i') }}</div></td>
                            <td><x-status.badge :status="$sale->status" /></td>
                            <td>{{ app()->getLocale() === 'ar' ? $sale->store->name_ar : $sale->store->name_en }}</td>
                            <td>{{ $sale->cashier->name }}</td>
                            <td class="text-end tabular-nums">{{ $sale->lines_count }}</td><td class="text-end tabular-nums">{{ $sale->payments_count }}</td>
                            <td class="text-end"><x-money :amount="$sale->total" :currency="$sale->currency_code" class="font-semibold" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-state.empty :title="__('No sales match the selected filters.')" :description="__('Try another filter or clear the current filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-tables.table-shell>
        {{ $sales->links() }}
    </div>
</x-layouts::app>
