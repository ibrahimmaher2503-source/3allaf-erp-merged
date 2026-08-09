<x-layouts::app :title="__('Payment Evidence')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div><flux:heading size="xl">{{ __('Payment Evidence') }}</flux:heading><flux:text class="mt-1">{{ __('Private evidence files linked to immutable electronic payment records. Every access is reauthorized and audited.') }}</flux:text></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto] dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Invoice')" />
            <flux:select name="method_id" :label="__('Method')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($methods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) request('method_id') === (string) $method->id">{{ app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en }}</flux:select.option>@endforeach</flux:select>
            <div class="flex items-end"><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" tabindex="0" role="region" aria-label="{{ __('Payment Evidence') }}">
            <table class="w-full min-w-[780px] text-sm"><thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800"><tr><th class="p-4 text-start">{{ __('Invoice') }}</th><th class="p-4 text-start">{{ __('Method') }}</th><th class="p-4 text-start">{{ __('Store') }}</th><th class="p-4 text-start">{{ __('File') }}</th><th class="p-4 text-start">{{ __('Status') }}</th><th class="p-4 text-start">{{ __('Uploaded by') }}</th></tr></thead><tbody>
                @forelse ($evidencePayments as $payment)@php($attachment = $payment->evidenceAttachment)<tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800"><td class="p-4"><a class="font-semibold text-blue-600 hover:underline" href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->document_number }}</a></td><td class="p-4">{{ app()->getLocale() === 'ar' ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</td><td class="p-4">{{ $payment->sale->store->code }}</td><td class="p-4">@if ($attachment)<a class="text-blue-600 hover:underline" href="{{ route('payments.evidence.show', $attachment) }}" target="_blank">{{ $attachment->original_filename }}</a><div class="text-xs text-zinc-500">{{ $attachment->detected_mime_type }} · {{ number_format($attachment->size_bytes / 1024, 1) }} KB</div>@endif</td><td class="p-4"><flux:badge :color="$attachment?->status->value === 'active' ? 'green' : 'amber'">{{ __(ucfirst($attachment?->status->value ?? 'missing')) }}</flux:badge></td><td class="p-4">{{ $payment->creator->name }}</td></tr>
                @empty<tr><td colspan="6" class="p-10 text-center text-zinc-500">{{ __('No protected payment evidence matches the selected filters.') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $evidencePayments->links() }}
    </div>
</x-layouts::app>
