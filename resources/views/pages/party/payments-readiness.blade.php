@php
    $isArabic = app()->getLocale() === 'ar';
@endphp

<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="party-payments-readiness-header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600">{{ __('TSK-032') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $description }}</p>
            </div>
            <div class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200" data-guide="party-payments-readiness-boundary">
                {{ __('Local/Dev readiness only') }}
            </div>
        </div>

        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-700 dark:bg-amber-950/20 dark:text-amber-100" data-guide="party-payments-readiness-summary">
            <strong>{{ __('Party payment boundary:') }}</strong>
            {{ __('No payment, receipt, balance, reversal, financial settlement, or Party Wallet entry is created. Product Wallet and retail payment flows remain separate.') }}
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-guide="party-payments-readiness-cards">
            @foreach ($items as $index => $item)
                <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-guide="party-payments-readiness-card-{{ $index + 1 }}">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $item['title'] }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $item['body'] }}</p>
                    <span class="mt-4 inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ __('PENDING') }}</span>
                </article>
            @endforeach
        </div>

        <div class="rounded-2xl border border-dashed border-zinc-300 p-5 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-guide="party-payments-readiness-empty">
            {{ __('No party payments, receipts, balances, or Party Wallet entries exist in this Local/Dev readiness slice. Configure approved values first; keep owner inputs PENDING/TBD until reviewed.') }}
        </div>
    </div>
</x-layouts::app>
