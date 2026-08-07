<x-layouts::app :title="__('POS Financial Readiness')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="financial-readiness-header">
            <div>
                <flux:heading size="xl">{{ __('POS Financial Readiness') }}</flux:heading>
                <flux:text class="mt-1 max-w-3xl">{{ __('Read-only readiness boundary for TSK-024. No discount, tax, payment, evidence, or open-price records are changed here.') }}</flux:text>
            </div>
            <flux:button href="{{ route('pos') }}" variant="primary">{{ __('Back to POS') }}</flux:button>
        </div>

        <flux:callout variant="warning" icon="information-circle" data-guide="financial-readiness-boundary">
            <flux:callout.heading>{{ __('Owner/configuration approval required') }}</flux:callout.heading>
            <flux:callout.text>{{ __('POSF-01 through POSF-04 and BLK-008 remain PENDING. This page is Local/Dev evidence only and does not enable Production financial behavior.') }}</flux:callout.text>
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([
                ['title' => __('Discount replacement'), 'detail' => __('One discount may apply to an amount; replacement and limits require the pending POSF-04 decision.'), 'status' => 'POSF-04 / PENDING'],
                ['title' => __('Invoice tax'), 'detail' => __('Tax is optional per invoice, but the approved rate, mode, rounding, and snapshot contract remain pending.'), 'status' => 'BLK-008 / PENDING'],
                ['title' => __('Cash/manual electronic payment'), 'detail' => __('Payment methods, residual ordering, underpayment, overpayment, and evidence requirements are not enabled.'), 'status' => 'BLK-008 / PENDING'],
                ['title' => __('Rounding and split residual'), 'detail' => __('Line/receipt rounding, cash denomination rounding, and split-payment residual rules are unresolved.'), 'status' => 'POSF-01..03 / PENDING'],
                ['title' => __('Open price'), 'detail' => __('Reference price, min/max bounds, reason, audit, and online eligibility require an approved policy.'), 'status' => 'OWNER CONFIG / PENDING'],
                ['title' => __('Exact print totals'), 'detail' => __('Thermal/A4 totals must share the approved calculator and immutable snapshots; final output policy is pending.'), 'status' => 'BLK-008 / PENDING'],
            ] as $item)
                <div @if ($loop->first) data-guide="financial-readiness-first-card" @endif class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $item['title'] }}</flux:heading>
                        <flux:badge color="amber">{{ $item['status'] }}</flux:badge>
                    </div>
                    <flux:text class="mt-3 leading-6 text-zinc-600 dark:text-zinc-300">{{ $item['detail'] }}</flux:text>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950" data-guide="financial-readiness-summary">
            <flux:heading size="lg">{{ __('Observed local configuration, read-only') }}</flux:heading>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:text class="text-sm text-zinc-500">{{ __('Active payment-method rows') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $activePaymentMethods }}</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Presence is not Production approval or payment enablement.') }}</flux:text>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:text class="text-sm text-zinc-500">{{ __('Active tax-setting rows') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $activeTaxSettings }}</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Presence is not an approved POS tax snapshot policy.') }}</flux:text>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
