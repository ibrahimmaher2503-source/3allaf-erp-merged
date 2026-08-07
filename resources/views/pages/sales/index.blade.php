<x-layouts::app :title="__('Sales')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3" data-guide="sales-header"><div><flux:heading size="xl">{{ __('Sales') }}</flux:heading><flux:text class="mt-1">{{ __('Approved Local/Dev retail sales.') }}</flux:text></div><flux:button href="{{ route('pos') }}" variant="primary" data-guide="sales-open-pos">{{ __('Open POS') }}</flux:button></div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full min-w-[720px] text-sm"><thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800" data-guide="sales-table-heading"><tr><th class="p-4 text-start">{{ __('Invoice') }}</th><th class="p-4 text-start">{{ __('Store') }}</th><th class="p-4 text-start">{{ __('Cashier') }}</th><th class="p-4 text-start">{{ __('Date') }}</th><th class="p-4 text-end">{{ __('Total') }}</th></tr></thead><tbody>
                @forelse ($sales as $sale)<tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800"><td class="p-4"><a class="font-semibold text-blue-600 hover:underline" href="{{ route('sales.show', $sale) }}">{{ $sale->document_number }}</a></td><td class="p-4">{{ app()->getLocale() === 'ar' ? $sale->store->name_ar : $sale->store->name_en }}</td><td class="p-4">{{ $sale->cashier->name }}</td><td class="p-4">{{ $sale->approved_at?->format('Y-m-d H:i') }}</td><td class="p-4 text-end font-semibold">{{ number_format((float) $sale->total, 2) }} {{ $sale->currency_code }}</td></tr>@empty<tr><td colspan="5" class="p-10 text-center text-zinc-500">{{ __('No sales recorded.') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $sales->links() }}
    </div>
</x-layouts::app>
