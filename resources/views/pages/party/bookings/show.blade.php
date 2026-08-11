<x-layouts::app :title="$booking->booking_number">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="$booking->booking_number" :description="__('Party booking and working invoice control center.')">
            <x-slot:actions>
                <flux:button href="{{ route('parties.bookings.index') }}" variant="subtle">{{ __('All bookings') }}</flux:button>
                <flux:button href="{{ route('parties.invoices.print', $booking->invoice->id) }}" target="_blank" variant="subtle" icon="printer">{{ __('Print invoice') }}</flux:button>
            </x-slot:actions>
        </x-page-header>
        @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <div class="grid gap-4 lg:grid-cols-3">
            <flux:card class="lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading size="lg">{{ __('Schedule and scope') }}</flux:heading><flux:text class="mt-1">{{ app()->getLocale()==='ar' ? $booking->store->name_ar : $booking->store->name_en }} · {{ $booking->location }}</flux:text></div><x-status.badge :status="$booking->status" /></div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-text-muted">{{ __('Customer') }}</dt><dd class="font-semibold">{{ app()->getLocale()==='ar' ? $booking->customer->name_ar : $booking->customer->name_en }}</dd></div><div><dt class="text-text-muted">{{ __('Party time') }}</dt><dd class="font-semibold">{{ $booking->party_date?->format('Y-m-d') }} · {{ $booking->starts_at?->format('H:i') }}–{{ $booking->ends_at?->format('H:i') }}</dd><dd class="text-xs text-text-muted">{{ $booking->timezone }}</dd></div><div><dt class="text-text-muted">{{ __('Contact') }}</dt><dd class="font-semibold" dir="ltr">{{ $booking->primary_contact }}</dd></div></dl>
                <p class="mt-5 whitespace-pre-wrap text-sm text-text-secondary">{{ $booking->notes ?: __('No additional notes.') }}</p>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('Next safe action') }}</flux:heading>
                <div class="mt-4 space-y-3">
                    @if($booking->status === 'draft')
                        @can('party_bookings_invoices.approve')<form method="POST" action="{{ route('parties.bookings.confirm', $booking->id) }}">@csrf<flux:button type="submit" variant="primary" class="w-full">{{ __('Confirm booking') }}</flux:button></form>@endcan
                        <flux:text class="text-sm">{{ __('Confirmation rechecks overlapping Party schedules and resource keys.') }}</flux:text>
                    @elseif(in_array($booking->status, ['confirmed','rescheduled'], true))
                        @can('party_operating_orders_consumables.create')<form method="POST" action="{{ route('parties.orders.store', $booking->id) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:button type="submit" variant="primary" class="w-full">{{ __('Create operating order') }}</flux:button></form>@endcan
                    @elseif($booking->status === 'completed_pending_settlement')
                        <flux:callout variant="warning">{{ __('Operations are complete. Reconcile the Party invoice before final close.') }}</flux:callout>
                    @elseif($booking->status === 'closed')
                        <flux:callout variant="success">{{ __('Closed and immutable. Corrections require a referenced document.') }}</flux:callout>
                    @endif
                </div>
            </flux:card>
        </div>

        <flux:card>
            <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading size="lg">{{ __('Working invoice') }}</flux:heading><flux:text class="mt-1">{{ $booking->invoice->invoice_number }} · {{ ucfirst(str_replace('_',' ',$booking->invoice->state)) }}</flux:text></div><div class="flex flex-wrap gap-2"><flux:button href="{{ route('parties.invoices.show', $booking->invoice->id) }}" variant="subtle">{{ __('Open invoice') }}</flux:button><flux:button href="{{ route('parties.invoices.payments', $booking->invoice->id) }}" variant="subtle">{{ __('Payments') }}</flux:button></div></div>
            <div class="mt-4 overflow-x-auto"><table class="data-table min-w-[650px] w-full text-sm"><thead><tr><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Line total') }}</th></tr></thead><tbody>@foreach($booking->invoice->lines as $line)<tr><td><flux:badge color="cyan">{{ ucfirst($line->line_type) }}</flux:badge></td><td>{{ app()->getLocale()==='ar' ? $line->description_ar : $line->description_en }}</td><td class="text-end tabular-nums">{{ $line->quantity }}</td><td class="text-end tabular-nums">{{ number_format((float)$line->line_total, 2) }} {{ $booking->invoice->currency_code }}</td></tr>@endforeach</tbody></table></div>
            <div class="mt-4 flex justify-end border-t border-border pt-4 text-lg font-bold">{{ __('Total') }}: {{ number_format((float)$booking->invoice->total_amount, 2) }} {{ $booking->invoice->currency_code }}</div>
        </flux:card>
    </div>
</x-layouts::app>
