<x-layouts::pos :title="__('Cash movement')" :store="$shift->store" :shift="$shift">
    <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
            <flux:heading size="xl">{{ __('Cash movement') }}</flux:heading>
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Sales navigation') }}">
                @can('pos_sales.create')<flux:button :href="route('pos')" variant="subtle" icon="shopping-cart">{{ __('Open POS') }}</flux:button>@endcan
                <flux:button :href="route('pos.shift')" variant="subtle">{{ __('Back to shift') }}</flux:button>
            </nav>
        </header>
        @if (session('success'))<flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        <flux:text class="mt-4">{{ __('Active shift') }} #{{ $shift->id }} · {{ __('Drawer') }}: {{ $shift->cashDrawer?->code }}</flux:text>
                <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Cash movement') }}</flux:heading>
                    <form method="POST" action="{{ route('pos.shift.cash-movement', $shift) }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $movementToken }}">
                        <flux:select name="movement_type" :label="__('Type')" required>
                            @foreach ($movementTypes as $type)
                                <flux:select.option value="{{ $type }}">{{ __(ucwords(str_replace('_', ' ', $type))) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input type="number" step="0.01" min="0.01" name="amount" :label="__('Amount')" required />
                        <flux:input name="reason" :label="__('Reason')" required />
                        <flux:input name="reference" :label="__('Reference (optional)')" />
                        @error('amount')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="subtle" icon="banknotes">{{ __('Record movement') }}</flux:button>
                    </form>

                    @if ($shift->cashMovements->isNotEmpty())
                        <x-tables.table-shell :label="__('Cash movement')" class="mt-5">
                            <table class="data-table w-full text-sm">
                                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Reference') }}</th>@can('shifts_cash_movements.approve')<th class="text-end">{{ __('Amount') }}</th>@endcan</tr></thead>
                                <tbody>
                                    @foreach ($shift->cashMovements as $movement)
                                        <tr>
                                            <td>{{ __(ucwords(str_replace('_', ' ', $movement->movement_type))) }}</td>
                                            <td>{{ $movement->reason }}</td>
                                            <td>{{ $movement->creator?->name ?? '—' }}</td>
                                            <td>{{ $movement->reference ?? '—' }}</td>
                                            @can('shifts_cash_movements.approve')<td class="text-end"><x-money :amount="$movement->amount" :currency="$shift->currency_code" class="font-semibold" /></td>@endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-tables.table-shell>
                    @endif
                </div>

    </div>
</x-layouts::pos>
