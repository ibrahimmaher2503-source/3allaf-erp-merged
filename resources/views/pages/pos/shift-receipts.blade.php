<x-layouts::app :title="__('Shift receipts')">
    <x-app.page :title="__('Shift receipts').' #'.$shift->id" :description="($shift->branch?->code ?? '—').' / '.($shift->store?->code ?? '—').' / '.($shift->cashDrawer?->code ?? '—')" max-width="7xl">
        <div class="mb-4 flex flex-wrap gap-2">
            <flux:button :href="route('pos.shift')" variant="subtle" icon="arrow-left">{{ __('Sessions') }}</flux:button>
            @can('pos_sales.create')
                @if($shift->status->acceptsActivity())<flux:button :href="route('pos')" icon="shopping-cart">{{ __('Open POS') }}</flux:button>@endif
            @endcan
        </div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="data-table responsive-resource-table w-full text-sm">
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Completed') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Cashier') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse($receipts as $sale)
                        <tr>
                            <td data-primary class="font-mono font-semibold">{{ $sale->document_number }}</td>
                            <td data-label="{{ __('Completed') }}">{{ $sale->approved_at?->format('Y-m-d H:i') }}</td>
                            <td data-label="{{ __('Customer') }}">{{ $sale->customer?->name_en ?? $sale->customer?->name_ar ?? '—' }}</td>
                            <td data-label="{{ __('Cashier') }}">{{ $sale->cashier?->name ?? '—' }}</td>
                            <td data-label="{{ __('Total') }}" class="text-end font-mono">{{ number_format((float) $sale->total, 2) }}</td>
                            <td data-label="{{ __('Actions') }}" class="whitespace-nowrap">
                                <a class="text-cyan-700 underline" href="{{ route('sales.show', $sale) }}">{{ __('View') }}</a>
                                @can('pos_sales.print') <a class="ms-3 text-cyan-700 underline" href="{{ route('sales.print', $sale) }}" target="_blank" rel="noopener">{{ __('Print / PDF') }}</a> @endcan
                                @if(auth()->user()->can('returns.view') || auth()->user()->can('returns_exchanges_gift_instruments.view')) <a class="ms-3 text-cyan-700 underline" href="{{ route('returns.index', ['source_sale_id' => $sale->id]) }}">{{ __('Return / Exchange') }}</a> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-zinc-500">{{ __('No completed receipts in this shift.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $receipts->links() }}</div>
    </x-app.page>
</x-layouts::app>
