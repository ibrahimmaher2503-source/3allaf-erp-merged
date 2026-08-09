<x-layouts::app :title="__('Sale :number', ['number' => $sale->document_number ?: $sale->id])">
    <div class="mx-auto w-full max-w-6xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">{{ $sale->document_number ?: __('Sale #:id', ['id' => $sale->id]) }}</flux:heading><flux:text class="mt-1">{{ __('Retail sale source, financial snapshots, payments, and stock-linked lines.') }}</flux:text></div>
            <div class="flex flex-wrap gap-2">
                @if ($sale->status === 'approved')
                    @can('pos_sales.print')
                        <flux:button href="{{ route('sales.receipt.thermal', $sale) }}" target="_blank" variant="subtle" icon="printer">{{ __('Thermal receipt') }}</flux:button>
                        <flux:button href="{{ route('sales.print', $sale) }}" target="_blank" variant="subtle" icon="document-text">{{ __('A4 invoice') }}</flux:button>
                    @endcan
                @endif
                <flux:button href="{{ route('sales.index') }}" variant="subtle">{{ __('Back') }}</flux:button>
            </div>
        </div>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Store') }}</div><div class="mt-1 font-semibold">{{ app()->getLocale() === 'ar' ? $sale->store->name_ar : $sale->store->name_en }}</div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Cashier') }}</div><div class="mt-1 font-semibold">{{ $sale->cashier->name }}</div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Status') }}</div><div class="mt-1"><flux:badge :color="$sale->status === 'approved' ? 'green' : ($sale->status === 'suspended' ? 'amber' : 'zinc')">{{ __(ucfirst($sale->status)) }}</flux:badge></div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Approved') }}</div><div class="mt-1 font-semibold">{{ $sale->approved_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" tabindex="0" role="region" aria-label="{{ __('Sale lines') }}">
            <table class="w-full min-w-[980px] text-sm"><thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800"><tr><th class="p-4 text-start">{{ __('Product') }}</th><th class="p-4 text-end">{{ __('Quantity') }}</th><th class="p-4 text-end">{{ __('Original price') }}</th><th class="p-4 text-end">{{ __('Selling price') }}</th><th class="p-4 text-end">{{ __('Gross') }}</th><th class="p-4 text-end">{{ __('Discount') }}</th><th class="p-4 text-end">{{ __('Line net') }}</th><th class="p-4 text-start">{{ __('Pricing context') }}</th></tr></thead><tbody>
                @foreach ($sale->lines as $line)<tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800"><td class="p-4"><div class="font-medium">{{ app()->getLocale() === 'ar' ? $line->name_ar : $line->name_en }}</div><div class="text-xs text-zinc-500">{{ $line->item_code }}</div></td><td class="p-4 text-end">{{ $line->quantity }}</td><td class="p-4 text-end">{{ \App\Modules\Retail\Support\DecimalMoney::round((string) ($line->reference_price ?? $line->unit_price)) }}</td><td class="p-4 text-end">{{ \App\Modules\Retail\Support\DecimalMoney::round((string) $line->unit_price) }}</td><td class="p-4 text-end">{{ $line->gross_amount }}</td><td class="p-4 text-end">{{ $line->discount_amount }}@if ($line->discount_type)<div class="text-xs text-zinc-500">{{ $line->discount_type }}</div>@endif</td><td class="p-4 text-end font-semibold">{{ $line->net_amount }}</td><td class="p-4">@if ($line->is_open_price)<flux:badge color="amber">{{ __('Open price') }}</flux:badge><div class="mt-1 max-w-48 text-xs text-zinc-500">{{ $line->open_price_reason }}</div>@else<span class="text-zinc-500">{{ __('Approved price') }}</span>@endif</td></tr>@endforeach
            </tbody></table>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Financial reconciliation') }}</flux:heading>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Gross subtotal') }}</dt><dd>{{ $sale->subtotal }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Discount total') }}</dt><dd>{{ $sale->discount_total }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Post-discount net') }}</dt><dd>{{ bcsub((string) $sale->subtotal, (string) $sale->discount_total, 2) }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Tax') }}@if ($sale->tax_applicable) · {{ $sale->tax_rate_snapshot }}%@endif</dt><dd>{{ $sale->tax_total }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 font-semibold dark:border-zinc-700"><dt>{{ __('Final total') }}</dt><dd>{{ $sale->total }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Cash rounding') }}</dt><dd>{{ $sale->cash_rounding_amount }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>{{ __('Payable / payment total') }}</dt><dd>{{ $sale->payable_total }} / {{ $sale->paid_total }} {{ $sale->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Change') }}</dt><dd>{{ $sale->change_total }} {{ $sale->currency_code }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                <div class="mt-4 space-y-3">
                    @forelse ($sale->payments as $payment)
                        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                            <div class="flex justify-between gap-3"><span class="font-semibold">{{ app()->getLocale() === 'ar' ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</span><span class="font-semibold">{{ $payment->amount }} {{ $sale->currency_code }}</span></div>
                            @if ($payment->tendered_amount !== null)<div class="mt-1 text-xs text-zinc-500">{{ __('Tendered') }} {{ $payment->tendered_amount }} · {{ __('Change') }} {{ $payment->change_amount }}</div>@endif
                            @if ($payment->evidenceAttachment && auth()->user()->can('pos_sales.payment_evidence_view'))<a class="mt-2 inline-block text-xs text-blue-600 hover:underline" href="{{ route('payments.evidence.show', $payment->evidenceAttachment) }}" target="_blank">{{ __('View protected payment evidence') }}</a>@endif
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">{{ __('No payments have been posted.') }}</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
