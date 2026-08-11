<x-layouts::app :title="__('Rental assets')">
    @php
        $pageTitle = match($mode) {
            'reservations' => __('Asset reservations & checkout'),
            'returns' => __('Return, condition & damages'),
            'history' => __('Depreciation & asset history'),
            default => __('Rental assets & calendar'),
        };
    @endphp
    <x-app.page :title="$pageTitle" :description="__('Track availability, reservations, condition, and controlled asset transitions.')" max-width="7xl">
        @if (session('success')) <flux:callout variant="success">{{ session('success') }}</flux:callout> @endif
        @if ($errors->any()) <flux:callout variant="danger"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout> @endif
        <flux:callout variant="info" icon="information-circle">{{ __('Rental assets are separate from consumable stock. Reservations change availability only, and event costs are operational history, not a general-ledger posting.') }}</flux:callout>
        @can('rental_assets.create')
            @if($mode === 'workspace')
            <flux:card class="space-y-4">
                <div><flux:heading size="lg">{{ __('Add rental asset') }}</flux:heading><flux:text>{{ __('Use a stable code. Historical assets are never physically deleted.') }}</flux:text></div>
                <form method="POST" action="{{ route('party.assets.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <flux:input name="code" label="{{ __('Asset code') }}" required />
                    <flux:input name="name_en" label="{{ __('Name (English)') }}" required />
                    <flux:input name="name_ar" label="{{ __('Name (Arabic)') }}" required />
                    <flux:input name="category" label="{{ __('Category') }}" />
                    <flux:select name="branch_id" label="{{ __('Branch') }}" required><option value="">{{ __('Select') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ $branch->name_en }}</option>@endforeach</flux:select>
                    <flux:select name="store_id" label="{{ __('Store') }}" required><option value="">{{ __('Select') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->code }} · {{ $store->name_en }}</option>@endforeach</flux:select>
                    <flux:input name="location" label="{{ __('Current location') }}" />
                    <flux:select name="condition" label="{{ __('Condition') }}" required><option value="good">{{ __('Good') }}</option><option value="fair">{{ __('Fair') }}</option><option value="poor">{{ __('Poor') }}</option></flux:select>
                    @can('rental_assets.cost_edit')<flux:input name="cost_value" type="number" step="0.01" min="0" label="{{ __('Cost value') }}" />@endcan
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><flux:button type="submit" variant="primary">{{ __('Create asset') }}</flux:button></div>
                </form>
            </flux:card>
            @endif
        @endcan
        @if(in_array($mode, ['workspace', 'reservations'], true))
        <flux:card class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><flux:heading size="lg">{{ __('Reservation calendar') }}</flux:heading><flux:text>{{ __('Upcoming reservations for the next 30 days, limited to your authorized scope.') }}</flux:text></div>
                <flux:badge color="zinc">{{ $calendarReservations->count() }} / 100 {{ __('shown') }}</flux:badge>
            </div>
            @if ($calendarReservations->isEmpty())
                <x-state.empty :title="__('No upcoming reservations.')" :description="__('New reservations will appear here after they pass the conflict check.')" />
            @else
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-[720px] w-full text-sm"><thead><tr class="bg-zinc-50 text-start dark:bg-zinc-800/60"><th class="p-3">{{ __('Asset') }}</th><th class="p-3">{{ __('Starts') }}</th><th class="p-3">{{ __('Ends') }}</th><th class="p-3">{{ __('Location') }}</th><th class="p-3">{{ __('Reference') }}</th></tr></thead><tbody>
                        @foreach ($calendarReservations as $reservation)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800"><td class="p-3 font-medium">{{ $reservation->asset->code }} · {{ app()->getLocale() === 'ar' ? $reservation->asset->name_ar : $reservation->asset->name_en }}</td><td class="p-3">{{ $reservation->starts_at?->format('Y-m-d H:i') }}</td><td class="p-3">{{ $reservation->ends_at?->format('Y-m-d H:i') }}</td><td class="p-3">{{ $reservation->store?->code ?: __('Not recorded') }}</td><td class="p-3">{{ $reservation->source_reference ?: __('Not recorded') }}</td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @endif
        </flux:card>
        @endif
        @if($mode === 'history')
            <x-tables.data-panel :title="__('Immutable asset events')" :description="__('Depreciation, damage, inspection, loss, and maintenance events in your authorized scope.')">
                <div class="overflow-x-auto"><table class="data-table min-w-[820px] w-full"><thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Event') }}</th><th>{{ __('Status') }}</th><th>{{ __('Assessment') }}</th><th>{{ __('Responsible user') }}</th><th class="text-end">{{ __('Operational cost') }}</th></tr></thead><tbody>
                    @forelse($historyEvents as $event)<tr><td><span class="font-mono">{{ $event->asset?->code }}</span></td><td>{{ str($event->event_type)->headline() }}</td><td><x-status.badge :status="$event->status" /></td><td>{{ $event->assessment }}</td><td>{{ $event->responsibleUser?->name ?: __('System') }}</td><td class="text-end tabular-nums">@can('rental_assets.cost_view'){{ $event->cost_value === null ? '—' : number_format((float) $event->cost_value, 2).' '.$event->cost_currency }}@else{{ __('Restricted') }}@endcan</td></tr>@empty<tr><td colspan="6"><x-state.empty :title="__('No asset history found.')" :description="__('Approved and pending asset events will appear here.')" /></td></tr>@endforelse
                </tbody></table></div><x-slot:footer>@if($historyEvents->hasPages()){{ $historyEvents->links() }}@endif</x-slot:footer>
            </x-tables.data-panel>
        @endif
        <flux:card class="overflow-hidden p-0">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-zinc-200 p-4 dark:border-zinc-700">
                <div><flux:heading size="lg">{{ __('Asset register') }}</flux:heading><flux:text>{{ __('Filter by status or search the stable identity.') }}</flux:text></div>
                <form method="GET" class="flex flex-wrap gap-2"><input type="hidden" name="mode" value="{{ $mode }}"><flux:input name="q" value="{{ request('q') }}" placeholder="{{ __('Code or name') }}" aria-label="{{ __('Search assets') }}" /><flux:select name="status" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach(['available','reserved','checked_out','under_inspection','damaged','under_maintenance','retired','lost'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</flux:select><flux:button type="submit" variant="subtle">{{ __('Filter') }}</flux:button></form>
            </div>
            <div class="overflow-x-auto"><table class="data-table min-w-[820px] w-full text-sm"><thead><tr><th>{{ __('Identity') }}</th><th>{{ __('Location') }}</th><th>{{ __('Availability') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Next action') }}</th></tr></thead><tbody>
                @forelse($assets as $asset)
                    @php($reservation = $asset->reservations->firstWhere('status', 'reserved'))
                    @php($checkout = $asset->checkouts->last())
                    @php($assetReturn = $asset->returns->last())
                    <tr class="border-t border-zinc-100 dark:border-zinc-800 align-top">
                        <td><div class="font-semibold">{{ $asset->code }}</div><div class="text-xs text-zinc-500">{{ app()->getLocale() === 'ar' ? $asset->name_ar : $asset->name_en }}</div><div class="mt-1 text-xs text-zinc-500">{{ __('History records') }}: {{ $asset->events_count + $asset->returns_count + $asset->checkouts_count }}</div></td>
                        <td>{{ $asset->store?->code }}<div class="text-xs text-zinc-500">{{ $asset->location ?: __('Not recorded') }}</div></td>
                        <td><x-status.badge :status="$asset->status" /><div class="mt-1 text-xs text-zinc-500">{{ $asset->reservations_count }} {{ __('reservation records') }}</div></td>
                        <td>{{ ucfirst($asset->condition) }}</td>
                        <td class="min-w-[270px] space-y-2">
                            <flux:button size="sm" variant="subtle" href="{{ route('party.assets.print', $asset) }}">{{ __('History / print') }}</flux:button>
                            @can('rental_assets.reserve')
                                @if($asset->status === 'available')
                                    <form method="POST" action="{{ route('party.assets.reserve', $asset) }}" class="grid gap-1 sm:grid-cols-2">@csrf<input type="hidden" name="timezone" value="{{ config('app.timezone') }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="starts_at" type="datetime-local" label="{{ __('Starts') }}" value="{{ now()->addDay()->startOfHour()->format('Y-m-d\TH:i') }}" required /><flux:input name="ends_at" type="datetime-local" label="{{ __('Ends') }}" value="{{ now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i') }}" required /><flux:input name="source_reference" placeholder="{{ __('Party reference') }}" required /><flux:button type="submit" size="sm">{{ __('Reserve interval') }}</flux:button></form>
                                @endif
                            @endcan
                            @can('rental_assets.checkout')
                                @if($asset->status === 'reserved' && $reservation)
                                    <form method="POST" action="{{ route('party.assets.checkout', $asset) }}" class="grid gap-1">@csrf<input type="hidden" name="reservation_id" value="{{ $reservation->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="source_reference" placeholder="{{ __('Booking / order reference') }}" required /><flux:button type="submit" size="sm">{{ __('Check out') }}</flux:button></form>
                                @endif
                            @endcan
                            @can('rental_assets.return')
                                @if($asset->status === 'checked_out' && $checkout)
                                    <form method="POST" action="{{ route('party.assets.return', $asset) }}" class="grid gap-1">@csrf<input type="hidden" name="checkout_id" value="{{ $checkout->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="condition_after" value="{{ $asset->condition }}" label="{{ __('Condition on return') }}" required /><flux:button type="submit" size="sm">{{ __('Return for inspection') }}</flux:button></form>
                                @endif
                            @endcan
                            @can('rental_assets.inspect')
                                @if($asset->status === 'under_inspection' && $assetReturn)
                                    <form method="POST" action="{{ route('party.assets.inspect', $asset) }}" class="grid gap-1">@csrf<input type="hidden" name="return_id" value="{{ $assetReturn->id }}"><flux:select name="resulting_status" label="{{ __('Inspection outcome') }}"><option value="available">{{ __('Available') }}</option><option value="damaged">{{ __('Damaged') }}</option><option value="under_maintenance">{{ __('Under maintenance') }}</option><option value="lost">{{ __('Lost') }}</option></flux:select><flux:textarea name="assessment" label="{{ __('Inspection findings') }}" rows="2" required /><flux:button type="submit" size="sm">{{ __('Complete inspection') }}</flux:button></form>
                                @endif
                            @endcan
                            @can('rental_assets.create')
                                @if(in_array($asset->status, ['available', 'damaged', 'under_maintenance', 'lost'], true))
                                    <details class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700"><summary class="cursor-pointer text-sm font-medium">{{ __('Record damage, loss, maintenance or depreciation') }}</summary><form method="POST" action="{{ route('party.assets.events.store', $asset) }}" class="mt-2 grid gap-2">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:select name="event_type" label="{{ __('Event type') }}"><option value="damage">{{ __('Damage') }}</option><option value="loss">{{ __('Loss') }}</option><option value="maintenance">{{ __('Maintenance') }}</option><option value="depreciation">{{ __('Depreciation history') }}</option></flux:select><flux:textarea name="assessment" label="{{ __('Assessment') }}" rows="2" required /><flux:input name="party_reference" label="{{ __('Source / party reference') }}" /><flux:select name="resulting_status" label="{{ __('Resulting status') }}"><option value="damaged">{{ __('Damaged') }}</option><option value="lost">{{ __('Lost') }}</option><option value="under_maintenance">{{ __('Under maintenance') }}</option><option value="available">{{ __('Available') }}</option><option value="retired">{{ __('Retired') }}</option></flux:select><flux:input name="cost_value" type="number" step="0.01" min="0" label="{{ __('Optional operational cost') }}" /><flux:button type="submit" size="sm">{{ __('Submit for approval') }}</flux:button></form></details>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty <tr><td colspan="5"><x-state.empty :title="__('No rental assets yet.')" :description="__('Create the first asset to start the availability calendar and history.')" /></td></tr>@endforelse
            </tbody></table></div><div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $assets->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
