<x-layouts::app :title="__('Party bookings')">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Party bookings')" :description="__('Schedule Party services and keep the working invoice separate from retail.')">
            <x-slot:actions><flux:button href="{{ route('parties.bookings.create') }}" variant="primary" icon="plus">{{ __('New Party booking') }}</flux:button><flux:button href="{{ route('parties.orders.index') }}" variant="subtle">{{ __('Operating orders') }}</flux:button></x-slot:actions>
        </x-page-header>
        @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
        <flux:card class="space-y-4">
            <form method="GET" class="grid gap-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
                <flux:input name="q" :label="__('Booking or customer')" :value="request('q')" placeholder="PB- or name" />
                <flux:select name="status" :label="__('Status')"><flux:select.option value="">{{ __('All statuses') }}</flux:select.option>@foreach(['draft','confirmed','rescheduled','in_operation','completed_pending_settlement','closed','cancelled'] as $status)<flux:select.option value="{{ $status }}">{{ str_replace('_', ' ', ucfirst($status)) }}</flux:select.option>@endforeach</flux:select>
                <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Filter') }}</flux:button>
            </form>
        </flux:card>
        <x-tables.data-panel :title="__('Scheduled Party work')" :description="__('Only records in your authorized Party branch/store scope are shown.')">
            <div class="overflow-x-auto"><table class="data-table data-table--mobile-summary min-w-[920px] w-full text-sm"><thead><tr><th>{{ __('Booking') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Store') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Action') }}</th></tr></thead><tbody>
                @forelse($bookings as $booking)<tr><td class="font-mono font-semibold">{{ $booking->booking_number }}</td><td><div class="font-semibold">{{ app()->getLocale()==='ar' ? $booking->customer->name_ar : $booking->customer->name_en }}</div><div class="text-xs text-text-muted" dir="ltr">{{ $booking->primary_contact }}</div></td><td><div>{{ $booking->party_date?->format('Y-m-d') }}</div><div class="text-xs text-text-muted">{{ $booking->starts_at?->format('H:i') }}–{{ $booking->ends_at?->format('H:i') }}</div></td><td>{{ app()->getLocale()==='ar' ? $booking->store->name_ar : $booking->store->name_en }}</td><td class="font-mono">{{ $booking->invoice?->invoice_number }}</td><td><x-status.badge :status="$booking->status" /></td><td class="text-end"><flux:button size="sm" variant="subtle" href="{{ route('parties.bookings.show', $booking->id) }}">{{ __('Open') }}</flux:button></td></tr>@empty<tr><td colspan="7"><x-state.empty :title="__('No Party bookings found.')" :description="__('Create a booking to begin the Party workflow.')" /></td></tr>@endforelse
            </tbody></table></div><x-slot:footer>@if($bookings->hasPages()){{ $bookings->links() }}@endif</x-slot:footer>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
