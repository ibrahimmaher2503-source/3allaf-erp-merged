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
        @else
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Active shift') }}</flux:heading>
                    <flux:badge>{{ __($shift->status->value) }}</flux:badge>
                </div>
                <dl class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Drawer') }}</dt><dd class="font-semibold">{{ $shift->cashDrawer?->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opened at') }}</dt><dd>{{ $shift->opened_at?->toDayDateTimeString() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opening float') }}</dt><dd>{{ number_format((float) $shift->opening_cash, 2) }}</dd></div>
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
                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="text-start text-zinc-500"><th class="py-2 text-start">{{ __('Type') }}</th><th class="py-2 text-start">{{ __('Reason') }}</th><th class="py-2 text-end">{{ __('Amount') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($shift->cashMovements as $movement)
                                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                            <td class="py-2">{{ __(ucwords(str_replace('_', ' ', $movement->movement_type))) }}</td>
                                            <td class="py-2">{{ $movement->reason }}</td>
                                            <td class="py-2 text-end font-semibold">{{ number_format((float) $movement->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
