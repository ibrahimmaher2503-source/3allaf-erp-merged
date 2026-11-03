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
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ str_starts_with(app()->getLocale(), 'ar') ? 'النوع' : 'Type' }}</th><th>{{ __('Completed') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Cashier') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse($receipts as $transaction)
                        <tr>
                            <td data-primary class="font-mono font-semibold">{{ $transaction->reference }} @if($transaction->original_reference)<span class="block text-xs font-normal">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الإيصال الأصلي' : 'Original receipt' }}: {{ $transaction->original_reference }}</span>@endif</td>
                            <td>{{ $transaction->kind === 'return' ? ($transaction->status === 'exchange' ? (str_starts_with(app()->getLocale(), 'ar') ? 'استبدال' : 'Exchange') : (str_starts_with(app()->getLocale(), 'ar') ? 'مرتجع' : 'Return')) : ($transaction->status === 'approved' ? (str_starts_with(app()->getLocale(), 'ar') ? 'بيع' : 'Sale') : __($transaction->status)) }}</td>
                            <td data-label="{{ __('Completed') }}">{{ \Illuminate\Support\Carbon::parse($transaction->occurred_at)->locale(app()->getLocale())->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</td>
                            <td data-label="{{ __('Customer') }}">{{ (str_starts_with(app()->getLocale(), 'ar') ? ($transaction->customer_ar ?: $transaction->customer_en) : ($transaction->customer_en ?: $transaction->customer_ar)) ?: '—' }}</td>
                            <td data-label="{{ __('Cashier') }}">{{ $transaction->cashier_name ?: '—' }}</td>
                            <td data-label="{{ __('Total') }}" class="text-end font-mono">{{ number_format((float) $transaction->amount, 2) }}</td>
                            <td data-label="{{ __('Actions') }}" class="whitespace-nowrap">
                                @if($transaction->kind === 'return')
                                    @if(auth()->user()->can('returns.view') || auth()->user()->can('returns_exchanges_gift_instruments.view'))<a class="text-cyan-700 underline" href="{{ route('returns.show', $transaction->id) }}">{{ __('View') }}</a>@endif
                                @else
                                    <a class="text-cyan-700 underline" href="{{ route('sales.show', $transaction->id) }}">{{ __('View') }}</a>
                                    @can('pos_sales.print') <a class="ms-3 text-cyan-700 underline" href="{{ route('sales.print', $transaction->id) }}" target="_blank" rel="noopener">{{ __('Print / PDF') }}</a> @endcan
                                    @if(auth()->user()->can('returns.view') || auth()->user()->can('returns_exchanges_gift_instruments.view')) <a class="ms-3 text-cyan-700 underline" href="{{ route('returns.index', ['source_sale_id' => $transaction->id]) }}">{{ __('Return / Exchange') }}</a> @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-zinc-500">{{ __('No completed receipts in this shift.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $receipts->links() }}</div>
    </x-app.page>
</x-layouts::app>
