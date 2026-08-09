<x-layouts::app :title="__('Sales')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">{{ __('Sales') }}</flux:heading><flux:text class="mt-1">{{ __('All scoped retail sale records, including draft and suspended operational states.') }}</flux:text></div>
            <flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart">{{ __('Open POS') }}</flux:button>
        </div>

        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Search')" :placeholder="__('Invoice or checkout reference')" />
            <flux:select name="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (['draft', 'suspended', 'approved', 'cancelled'] as $status)<flux:select.option value="{{ $status }}" :selected="request('status') === $status">{{ __(ucfirst($status)) }}</flux:select.option>@endforeach</flux:select>
            <flux:select name="store_id" :label="__('Store')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($stores as $store)<flux:select.option value="{{ $store->id }}" :selected="(string) request('store_id') === (string) $store->id">{{ $store->code }}</flux:select.option>@endforeach</flux:select>
            <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
            <div class="flex items-end gap-2"><flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" /><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900" tabindex="0" role="region" aria-label="{{ __('Sales') }}">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800"><tr><th class="p-4 text-start">{{ __('Reference') }}</th><th class="p-4 text-start">{{ __('Status') }}</th><th class="p-4 text-start">{{ __('Store') }}</th><th class="p-4 text-start">{{ __('Cashier') }}</th><th class="p-4 text-end">{{ __('Lines') }}</th><th class="p-4 text-end">{{ __('Payments') }}</th><th class="p-4 text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                            <td class="p-4"><a class="font-semibold text-blue-600 hover:underline" href="{{ route('sales.show', $sale) }}">{{ $sale->document_number ?: __('Sale #:id', ['id' => $sale->id]) }}</a><div class="text-xs text-zinc-500">{{ $sale->created_at?->format('Y-m-d H:i') }}</div></td>
                            <td class="p-4"><flux:badge :color="$sale->status === 'approved' ? 'green' : ($sale->status === 'suspended' ? 'amber' : 'zinc')">{{ __(ucfirst($sale->status)) }}</flux:badge></td>
                            <td class="p-4">{{ app()->getLocale() === 'ar' ? $sale->store->name_ar : $sale->store->name_en }}</td>
                            <td class="p-4">{{ $sale->cashier->name }}</td>
                            <td class="p-4 text-end">{{ $sale->lines_count }}</td><td class="p-4 text-end">{{ $sale->payments_count }}</td>
                            <td class="p-4 text-end font-semibold">{{ $sale->total }} {{ $sale->currency_code }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-zinc-500">{{ __('No sales match the selected filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sales->links() }}
    </div>
</x-layouts::app>
