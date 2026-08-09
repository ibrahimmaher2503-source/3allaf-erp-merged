<x-layouts::app :title="__('Payments')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div><flux:heading size="xl">{{ __('Payments') }}</flux:heading><flux:text class="mt-1">{{ __('Immutable payment records linked to approved scoped sales.') }}</flux:text></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Invoice')" />
            <flux:select name="method_id" :label="__('Method')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($methods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) request('method_id') === (string) $method->id">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</flux:select.option>@endforeach</flux:select>
            <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
            <div class="flex items-end gap-2"><flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" /><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" tabindex="0" role="region" aria-label="{{ __('Payments') }}">
            <table class="w-full min-w-[920px] text-sm"><thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800"><tr><th class="p-4 text-start">{{ __('Invoice') }}</th><th class="p-4 text-start">{{ __('Method') }}</th><th class="p-4 text-start">{{ __('Store') }}</th><th class="p-4 text-start">{{ __('Recorded by') }}</th><th class="p-4 text-end">{{ __('Applied') }}</th><th class="p-4 text-end">{{ __('Tendered') }}</th><th class="p-4 text-end">{{ __('Change') }}</th><th class="p-4 text-start">{{ __('Evidence') }}</th></tr></thead><tbody>
                @forelse ($payments as $payment)<tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800"><td class="p-4"><a class="font-semibold text-blue-600 hover:underline" href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->document_number }}</a><div class="text-xs text-zinc-500">{{ $payment->created_at?->format('Y-m-d H:i') }}</div></td><td class="p-4">{{ app()->getLocale() === 'ar' ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</td><td class="p-4">{{ $payment->sale->store->code }}</td><td class="p-4">{{ $payment->creator->name }}</td><td class="p-4 text-end font-semibold">{{ $payment->amount }}</td><td class="p-4 text-end">{{ $payment->tendered_amount ?? '—' }}</td><td class="p-4 text-end">{{ $payment->change_amount }}</td><td class="p-4">@if ($payment->evidenceAttachment && auth()->user()->can('pos_sales.payment_evidence_view'))<a class="text-blue-600 hover:underline" href="{{ route('payments.evidence.show', $payment->evidenceAttachment) }}" target="_blank">{{ __('View protected file') }}</a>@else<span class="text-zinc-500">{{ __('None') }}</span>@endif</td></tr>
                @empty<tr><td colspan="8" class="p-10 text-center text-zinc-500">{{ __('No payments match the selected filters.') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $payments->links() }}
    </div>
</x-layouts::app>
