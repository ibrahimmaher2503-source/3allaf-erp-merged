{{--
    Cashier shift screen (docs/32 §17).

    CSH-02 / docs/32 §10: this view must never render an expected total, a
    variance, or any figure the cashier could work one out from. The controller
    deliberately passes no expected data at all, so there is nothing here to
    leak through HTML, a hidden field, or a preloaded response.
--}}
<x-layouts::pos :title="__('Cash Shift')">
    <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
        <flux:heading size="xl">{{ __('Cash Shift') }}</flux:heading>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (! $shift)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Open a shift') }}</flux:heading>
                <flux:text class="mt-1">{{ __('A shift must be open before any sale can be recorded.') }}</flux:text>

                @if ($drawers->isEmpty())
                    <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('No active cash drawer is assigned to you.') }}</flux:callout>
                @else
                    <form method="POST" action="{{ route('pos.shift.open') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $openToken }}">
                        <flux:select name="cash_drawer_id" :label="__('Cash drawer')" required>
                            @foreach ($drawers as $drawer)
                                <flux:select.option value="{{ $drawer->id }}">{{ $drawer->code }} — {{ app()->getLocale() === 'ar' ? $drawer->name_ar : $drawer->name_en }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input type="number" step="0.01" min="0" name="opening_float" :label="__('Opening float')" value="0.00" required />
                        @error('cash_drawer_id')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="primary" icon="lock-open">{{ __('Open shift') }}</flux:button>
                    </form>
                @endif
            </div>

            @if ($closedShifts->isNotEmpty())
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="closed-shifts-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading id="closed-shifts-heading" size="lg">{{ __('Closed shifts') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Print a historical close without changing its immutable record.') }}</flux:text>
                        </div>
                        <flux:badge color="zinc">{{ $closedShifts->count() }}</flux:badge>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="data-table w-full min-w-[36rem] text-sm">
                            <thead><tr><th class="text-start">{{ __('Document') }}</th><th class="text-start">{{ __('Drawer') }}</th><th class="text-start">{{ __('Closed at') }}</th><th class="text-end">{{ __('Outputs') }}</th></tr></thead>
                            <tbody>
                                @foreach ($closedShifts as $closedShift)
                                    <tr>
                                        <td class="font-mono font-semibold">{{ $closedShift->closing_document_number }}</td>
                                        <td>{{ $closedShift->cashDrawer?->code ?: $closedShift->cash_drawer_code_snapshot }}</td>
                                        <td>{{ $closedShift->closed_at?->toDayDateTimeString() }}</td>
                                        <td class="text-end">
                                            @can('shifts_cash_movements.print')
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <a class="text-sm font-medium text-zinc-700 underline hover:text-zinc-950" href="{{ route('pos.shift.print.thermal', $closedShift) }}" target="_blank" rel="noopener">{{ __('Thermal') }}</a>
                                                    <a class="text-sm font-medium text-zinc-700 underline hover:text-zinc-950" href="{{ route('pos.shift.print.a4', $closedShift) }}" target="_blank" rel="noopener">{{ __('Print A4') }}</a>
                                                </div>
                                            @else
                                                <span class="text-zinc-500">{{ __('Not permitted') }}</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @else
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Active shift') }}</flux:heading>
                    <x-status.badge :status="$shift->status->value" />
                </div>
                <dl class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Drawer') }}</dt><dd class="font-semibold">{{ $shift->cashDrawer?->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opened at') }}</dt><dd>{{ $shift->opened_at?->toDayDateTimeString() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opening float') }}</dt><dd><x-money :amount="$shift->opening_cash" :currency="$shift->currency_code" /></dd></div>
                    @if ($shift->recount_count > 0)
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Recounts requested') }}</dt><dd>{{ $shift->recount_count }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($shift->status->acceptsActivity())
                <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
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
                                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($shift->cashMovements as $movement)
                                        <tr>
                                            <td>{{ __(ucwords(str_replace('_', ' ', $movement->movement_type))) }}</td>
                                            <td>{{ $movement->reason }}</td>
                                            <td class="text-end"><x-money :amount="$movement->amount" :currency="$shift->currency_code" class="font-semibold" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-tables.table-shell>
                    @endif
                </div>

                <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Blind close') }}</flux:heading>
                    {{-- CSH-02: the cashier counts first and is shown nothing to count towards. --}}
                    <flux:text class="mt-1">{{ __('Count the drawer and enter the actual amounts. Expected totals are not shown before you submit.') }}</flux:text>
                    <form method="POST" action="{{ route('pos.shift.blind-close', $shift) }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $closeToken }}">
                        <flux:input type="number" step="0.01" min="0" name="actual_cash" :label="__('Counted cash')" required />
                        @foreach ($methods as $method)
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                name="actual_by_method[{{ $method->code }}]"
                                :label="(app()->getLocale() === 'ar' ? $method->name_ar : $method->name_en).' — '.__('counted total')" />
                        @endforeach
                        <flux:textarea name="notes" :label="__('Notes (optional)')" rows="2" />
                        @error('actual_cash')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Submit count') }}</flux:button>
                    </form>
                </div>
            @else
                <flux:callout class="mt-6" variant="secondary" icon="clock">{{ __('Your count has been submitted and is awaiting review. No further sales or cash movements can be recorded on this shift.') }}</flux:callout>
            @endif
        @endif
    </div>
</x-layouts::pos>
