<x-layouts::app :title="__('TSK-025 Shift Readiness')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('TSK-025 Shift Readiness') }}</flux:heading>
                <flux:text class="mt-1 max-w-3xl">{{ __('Read-only shift and cash-control boundary. No shift, cash movement, payment, expected total, or variance record is changed here.') }}</flux:text>
            </div>
            <flux:button href="{{ route('pos') }}" variant="primary">{{ __('Back to POS') }}</flux:button>
        </div>

        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>{{ __('Blind close is preserved') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Expected amounts are not rendered, preloaded, or exposed before an owner-approved actual submission workflow.') }}</flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([
                ['title' => __('Shift opening'), 'detail' => __('Cashier, drawer, store, currency, opening float, and idempotency rules require an approved operating policy.'), 'status' => 'POLICY / PENDING'],
                ['title' => __('Cash movement linkage'), 'detail' => __('Cash in/out, petty disbursement, safe deposit, and correction events require types, reasons, actor, source, and approval rules.'), 'status' => 'POLICY / PENDING'],
                ['title' => __('Actual submission'), 'detail' => __('Cashier actuals must be complete and stored immutably; duplicate submission and concurrency behavior remain pending.'), 'status' => 'CSH-02 / PENDING'],
                ['title' => __('Manager variance review'), 'detail' => __('Expected versus actual detail, recount, approval separation, and variance limits are not enabled.'), 'status' => 'BLK-008 / PENDING'],
                ['title' => __('Closed shift immutability'), 'detail' => __('Close transition, correction references, post-close transaction denial, and audit events require the approved lifecycle.'), 'status' => 'CSH-03 / PENDING'],
                ['title' => __('Thermal/A4 close output'), 'detail' => __('Close reports must respect viewer permissions and approved print/numbering/device configuration.'), 'status' => 'BLK-008 / PENDING'],
            ] as $item)
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $item['title'] }}</flux:heading>
                        <flux:badge color="amber">{{ $item['status'] }}</flux:badge>
                    </div>
                    <flux:text class="mt-3 leading-6 text-zinc-600 dark:text-zinc-300">{{ $item['detail'] }}</flux:text>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950">
            <flux:heading size="lg">{{ __('Observed scoped readiness, read-only') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Counts only; no monetary fields or expected values are loaded into this page.') }}</flux:text>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:text class="text-sm text-zinc-500">{{ __('Visible active drawers') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $activeDrawerCount }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:text class="text-sm text-zinc-500">{{ __('Your open local shifts') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $openShiftCount }}</flux:heading>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
