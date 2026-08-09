{{--
    Manager variance review (docs/32 §13).

    This is the first screen permitted to show expected versus actual, and it is
    reachable only with `shifts_cash_movements.approve`. Sign convention per
    §12: variance = actual - expected, so a negative figure is a shortage.
--}}
<x-layouts::app :title="__('Shift Variance Review')">
    <div class="mx-auto w-full max-w-5xl p-4 sm:p-6">
        <flux:heading size="xl">{{ __('Shift Variance Review') }}</flux:heading>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if ($shifts->isEmpty())
            <flux:callout class="mt-6" variant="secondary" icon="inbox">{{ __('No submitted shift is awaiting review.') }}</flux:callout>
        @else
            <div class="mt-6 grid gap-5">
                @foreach ($shifts as $shift)
                    @php($submission = $shift->closingSubmissions->sortByDesc('attempt')->first())
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:heading size="lg">{{ $shift->cashDrawer?->code }} — {{ $shift->cashier?->name ?? $shift->cashier?->username }}</flux:heading>
                            <flux:badge :variant="$submission && bccomp((string) $submission->total_variance, '0', 2) === 0 ? 'success' : 'warning'">
                                {{ __($shift->status->value) }}
                            </flux:badge>
                        </div>

                        @if ($submission)
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-zinc-500">
                                            <th class="py-2 text-start">{{ __('Method') }}</th>
                                            <th class="py-2 text-end">{{ __('Expected') }}</th>
                                            <th class="py-2 text-end">{{ __('Actual') }}</th>
                                            <th class="py-2 text-end">{{ __('Variance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                            <td class="py-2 font-medium">{{ __('Cash') }}</td>
                                            <td class="py-2 text-end">{{ number_format((float) $submission->expected_cash, 2) }}</td>
                                            <td class="py-2 text-end">{{ number_format((float) $submission->actual_cash, 2) }}</td>
                                            <td class="py-2 text-end font-semibold">{{ number_format((float) $submission->cash_variance, 2) }}</td>
                                        </tr>
                                        @foreach (($submission->method_variance ?? []) as $code => $delta)
                                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                                <td class="py-2 font-medium">{{ $code }}</td>
                                                <td class="py-2 text-end">{{ number_format((float) (($submission->expected_by_method ?? [])[$code] ?? 0), 2) }}</td>
                                                <td class="py-2 text-end">{{ number_format((float) (($submission->actual_by_method ?? [])[$code] ?? 0), 2) }}</td>
                                                <td class="py-2 text-end font-semibold">{{ number_format((float) $delta, 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="border-t-2 border-zinc-200 dark:border-zinc-700">
                                            <td class="py-2 font-bold" colspan="3">{{ __('Total variance') }}</td>
                                            <td class="py-2 text-end font-bold">{{ number_format((float) $submission->total_variance, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <flux:text class="mt-2 text-xs text-zinc-500">
                                {{ __('Attempt :n · submitted :at', ['n' => $submission->attempt, 'at' => $submission->submitted_at?->toDayDateTimeString()]) }}
                                @if ($submission->notes) · {{ $submission->notes }} @endif
                            </flux:text>
                        @endif

                        <div class="mt-5">
                            {{-- Decisions are intentionally made only in the shared
                                 Platform inbox. This screen is review evidence,
                                 not a second approval workflow. --}}
                            <flux:button :href="route('admin.approvals')" variant="primary" icon="inbox-arrow-down">
                                {{ __('Open canonical approval request') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $shifts->links() }}</div>
        @endif
    </div>
</x-layouts::app>
