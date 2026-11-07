{{--
    Cashier shift screen (docs/32 §17).

    CSH-02 / docs/32 §10: this view must never render an expected total, a
    variance, or any figure the cashier could work one out from. The controller
    deliberately passes no expected data at all, so there is nothing here to
    leak through HTML, a hidden field, or a preloaded response.
--}}
<x-layouts::pos :title="__('Cash Shift')" :store="$shift?->store" :shift="$shift">
    <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
            <div class="flex items-center gap-2"><flux:heading size="xl">{{ __('Shifts & collections') }}</flux:heading><x-context-help :title="__('Shifts & collections')" :label="__('Open contextual help')"><p>{{ __('Manage your active drawer and review recent immutable shift closes.') }}</p></x-context-help></div>
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Sales navigation') }}">
                <flux:button href="{{ route('sales.index') }}" variant="subtle" icon="presentation-chart-line" wire:navigate>{{ __('Sales overview') }}</flux:button>
                @can('pos_sales.create')<flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart" wire:navigate>{{ __('Open POS') }}</flux:button>@endcan
            </nav>
        </header>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @foreach ($blockingShifts as $blockingShift)
            <section class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-700 dark:bg-amber-950/30" aria-label="{{ __('Blocking active shift') }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Blocking active shift') }} #{{ $blockingShift->id }}</flux:heading>
                    <x-status.badge :status="$blockingShift->status->value" />
                </div>
                <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('Drawer :drawer is in use by :cashier on shift #:shift since :time.', ['drawer' => $blockingShift->cashDrawer?->code ?: $blockingShift->cash_drawer_code_snapshot, 'cashier' => $blockingShift->cashier?->name ?? __('Another cashier'), 'shift' => $blockingShift->id, 'time' => $blockingShift->opened_at?->format('H:i') ?? '—']) }}</flux:callout>
                <flux:text class="mt-3">{{ __('Drawer') }}: {{ $blockingShift->cashDrawer?->code ?: $blockingShift->cash_drawer_code_snapshot }}</flux:text>
                @can('pos_sales.view')<a class="mt-2 inline-block text-sm font-semibold text-cyan-700 underline" href="{{ route('pos.shift.receipts', $blockingShift) }}">{{ __('View shift receipts') }}</a>@endcan
                @if (auth()->user()->hasGlobalApplicationAccess() && auth()->user()->can('shifts_cash_movements.approve'))
                    <dl class="mt-4 grid gap-2 text-sm">
                        <div class="flex justify-between gap-3"><dt>{{ __('Branch / store') }}</dt><dd>{{ $blockingShift->branch?->code ?: $blockingShift->branch_code_snapshot }} / {{ $blockingShift->store?->code ?: $blockingShift->store_code_snapshot }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Opened at') }}</dt><dd>{{ $blockingShift->opened_at?->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Cashier') }}</dt><dd>{{ $blockingShift->cashier?->name ?? $blockingShift->cashier?->username ?? '—' }}</dd></div>
                    </dl>
                    @if ($blockingShift->status->acceptsActivity())
                        <form method="POST" action="{{ route('pos.shift.blind-close', $blockingShift) }}" class="mt-5 grid gap-3 border-t border-amber-300 pt-4 dark:border-amber-700">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <flux:heading size="sm">{{ __('Administrator blind close') }}</flux:heading>
                            <flux:text>{{ __('Count the physical cash only. Electronic totals are calculated from recorded shift transactions, then reviewed in the canonical approval inbox.') }}</flux:text>
                            <flux:input type="number" step="0.01" min="0" name="actual_cash" :label="__('Counted cash')" required />
                            <flux:textarea name="notes" :label="__('Resolution reason')" rows="2" required />
                            @error('notes')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                            @error('actual_cash')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                            <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Submit count for approval') }}</flux:button>
                        </form>
                    @else
                        <flux:button class="mt-4" :href="route('admin.approvals')" variant="primary" icon="inbox-arrow-down">{{ __('Open canonical approval request') }}</flux:button>
                    @endif
                @endif
            </section>
        @endforeach
        @if ($blockingShifts->hasPages())
            <div class="mt-4">{{ $blockingShifts->links() }}</div>
        @endif

        @if ($pendingShifts->isNotEmpty())
            <section class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-700 dark:bg-amber-950/30" aria-labelledby="pending-shifts-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading id="pending-shifts-heading" size="lg">{{ __('Pending close review') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Submitted shifts are operationally closed. You may open the next shift while management reviews them.') }}</flux:text>
                    </div>
                    <flux:badge color="amber">{{ $pendingCloseCount }}/2</flux:badge>
                </div>
                <div class="mt-4 grid gap-3">
                    @foreach ($pendingShifts as $pendingShift)
                        @php($pendingSubmission = $pendingShift->closingSubmissions->sortByDesc('attempt')->first())
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-white p-3 dark:border-amber-800 dark:bg-zinc-900">
                            <div>
                                <div class="font-semibold">#{{ $pendingShift->id }} · {{ $pendingShift->cashDrawer?->code ?: $pendingShift->cash_drawer_code_snapshot }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Submitted') }}: {{ $pendingSubmission?->submitted_at?->toDayDateTimeString() }} · {{ $pendingShift->status->value === 'variance_review' ? __('In review') : __('Pending approval') }}</div>
                            </div>
                            @can('shifts_cash_movements.print')
                                <div class="flex flex-wrap gap-2">
                                    <x-actions.button semantic="print" :label="__('Print Shift Close Sheet')" :href="route('pos.shift.print.thermal', $pendingShift)" target="_blank" rel="noopener">{{ __('Print Shift Close Sheet') }}</x-actions.button>
                                    <x-actions.button semantic="print" :label="__('Print A4')" :href="route('pos.shift.print.a4', $pendingShift)" target="_blank" rel="noopener">{{ __('Print A4') }}</x-actions.button>
                                </div>
                            @endcan
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! $shift)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Open a shift') }}</flux:heading>
                <flux:text class="mt-1">{{ __('A shift must be open before any sale can be recorded.') }}</flux:text>

                @if ($drawers->isEmpty())
                    <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ $blockingShifts->total() > 0 ? __('Every available cash drawer is occupied by an active shift. See the blocking shift above.') : __('No active cash drawer is assigned to you.') }}</flux:callout>
                @elseif ($pendingCloseCount >= 2)
                    <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('You have two previous shifts awaiting approval. Resolve at least one pending close before opening another shift.') }}</flux:callout>
                @else
                    <form method="POST" action="{{ route('pos.shift.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $openToken }}">
                        <flux:select name="cash_drawer_id" :label="__('Sales outlet and cash drawer')" required>
                            @foreach ($drawers as $drawer)
                                <flux:select.option value="{{ $drawer->id }}" :selected="(int) old('cash_drawer_id', $preselectedDrawerId) === $drawer->id">{{ \App\Support\HumanName::name($drawer->store) }} — {{ \App\Support\HumanName::code($drawer->store) }} · {{ \App\Support\HumanName::name($drawer) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if($drawers->count() === 1)
                            <flux:callout variant="info" icon="building-storefront">{{ __('Your authorized sales outlet is :outlet.', ['outlet'=>\App\Support\HumanName::name($drawers->first()->store)]) }}</flux:callout>
                        @else
                            <flux:text>{{ __('Select the exact authorized sales outlet before opening the shift.') }}</flux:text>
                        @endif
                        <flux:checkbox name="confirm_outlet" value="1" :label="__('I confirm this sales outlet and its stock source for the shift.')" required />
                        @error('confirm_outlet')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:input type="number" step="0.01" min="0" name="opening_float" :label="__('Opening float')" value="0.00" required />
                        @error('cash_drawer_id')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="primary" icon="lock-open">{{ __('Open shift') }}</flux:button>
                    </form>
                @endif
            </div>

            @if ($closedShifts->isNotEmpty())
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="closed-shifts-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <flux:heading id="closed-shifts-heading" size="lg">{{ __('Closed shifts') }}</flux:heading>
                            <x-context-help :title="__('Closed shifts')" :label="__('Open contextual help')"><p>{{ __('Print a historical close without changing its immutable record.') }}</p></x-context-help>
                        </div>
                        <flux:badge color="zinc">{{ $closedShifts->count() }}</flux:badge>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="data-table responsive-resource-table w-full text-sm">
                            <thead><tr><th class="text-start">{{ __('Document') }}</th><th class="text-start">{{ __('Drawer') }}</th><th class="text-start">{{ __('Closed at') }}</th><th class="text-end">{{ __('Outputs') }}</th></tr></thead>
                            <tbody>
                                @foreach ($closedShifts as $closedShift)
                                    <tr>
                                        <td data-primary class="font-mono font-semibold">{{ $closedShift->closing_document_number }}</td>
                                        <td data-label="{{ __('Drawer') }}">{{ $closedShift->cashDrawer?->code ?: $closedShift->cash_drawer_code_snapshot }}</td>
                                        <td data-label="{{ __('Closed at') }}">{{ $closedShift->closed_at?->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</td>
                                        <td data-label="{{ __('Outputs') }}" class="text-end">
                                            @can('shifts_cash_movements.print')
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <x-actions.button semantic="print" :label="__('Thermal')" :href="route('pos.shift.print.thermal', $closedShift)" target="_blank" rel="noopener">{{ __('Thermal') }}</x-actions.button>
                                                    <x-actions.button semantic="print" :label="__('Print A4')" :href="route('pos.shift.print.a4', $closedShift)" target="_blank" rel="noopener">{{ __('Print A4') }}</x-actions.button>
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
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Shift ID') }}</dt><dd class="font-mono font-semibold">#{{ $shift->id }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Drawer') }}</dt><dd class="text-end font-semibold">{{ $shift->cashDrawer?->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $shift->cashDrawer?->name_ar : $shift->cashDrawer?->name_en }}<span class="block text-xs font-normal text-zinc-500">{{ $shift->branch?->code }} → {{ $shift->store?->code }}</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Cashier') }}</dt><dd>{{ $shift->cashier?->name ?? $shift->cashier?->username ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opened at') }}</dt><dd>{{ $shift->opened_at?->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</dd></div>
                    @can('pos_sales.view')<div class="flex justify-end"><a class="font-semibold text-cyan-700 underline" href="{{ route('pos.shift.receipts', $shift) }}">{{ __('View shift receipts') }}</a></div>@endcan
                    @if ($shift->recount_count > 0)
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Recounts requested') }}</dt><dd>{{ $shift->recount_count }}</dd></div>
                    @endif
                </dl>
                @if ($canManageOwnShift && $shift->status->acceptsActivity())
                    <nav class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('Shift actions') }}">
                        @can('pos_sales.create')<flux:button :href="route('pos')" variant="primary" icon="shopping-cart">{{ __('Open POS') }}</flux:button>@endcan
                        @can('shifts_cash_movements.create')<flux:button :href="route('pos.shift.cash-movement.form', $shift)" variant="subtle" icon="banknotes">{{ __('Cash movement') }}</flux:button>@endcan
                    </nav>
                @endif
            </div>

            @if (! $canManageOwnShift)
                <flux:callout class="mt-6" variant="warning" icon="exclamation-triangle">{{ __('Your active shift is outside your current location access. Ask a System Administrator to resolve it.') }}</flux:callout>
            @elseif ($shift->status->acceptsActivity())
                <div id="shift-close-{{ $shift->id }}" class="mt-6 scroll-mt-24 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Close shift') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Count the physical cash in the drawer before submitting your blind close.') }}</flux:text>
                    <flux:button type="button" class="mt-4" variant="primary" icon="lock-closed" onclick="document.getElementById('blind-close-dialog-{{ $shift->id }}').showModal()">{{ __('Close shift') }}</flux:button>
                </div>
                <dialog id="blind-close-dialog-{{ $shift->id }}" class="m-auto w-[min(94vw,34rem)] rounded-xl border border-zinc-200 bg-white p-0 shadow-2xl backdrop:bg-zinc-950/70 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 p-5 dark:border-zinc-700"><flux:heading size="lg">{{ __('Count physical cash') }}</flux:heading><flux:text class="mt-1">{{ __('Enter only the cash physically counted. Authorized staff review the close after submission.') }}</flux:text></div>
                    <form method="POST" action="{{ route('pos.shift.blind-close', $shift) }}" class="grid gap-4 p-5" onsubmit="return window.confirm({{ \Illuminate\Support\Js::from(__('Submit this physical cash count and close the shift?')) }})">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $closeToken }}">
                        <flux:input type="number" step="0.01" min="0" name="actual_cash" :label="__('Counted cash')" required autofocus />
                        <flux:textarea name="notes" :label="__('Notes (optional)')" rows="2" />
                        @error('actual_cash')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" onclick="this.closest('dialog').close()">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Confirm cash count') }}</flux:button></div>
                    </form>
                </dialog>
            @else
                <flux:callout class="mt-6" variant="secondary" icon="clock">{{ __('Your current shift is awaiting closing approval. A new shift cannot be opened until it is closed.') }} {{ __('Your count has been submitted and is awaiting review. No further sales or cash movements can be recorded on this shift.') }}</flux:callout>
            @endif
        @endif
    </div>
</x-layouts::pos>
