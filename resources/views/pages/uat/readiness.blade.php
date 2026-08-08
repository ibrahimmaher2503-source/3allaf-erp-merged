<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-guide="uat-readiness-header">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold text-primary" data-guide="uat-readiness-summary">{{ __('TSK-043 · Local/Dev readiness') }}</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $title }}</h1><p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review the manual UAT scenario pack, owners, data/devices, evidence, defects, reconciliation, and acceptance prerequisites without recording a UAT result.') }}</p></div><span class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ __('PENDING') }}</span></div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100" data-guide="uat-readiness-boundary">{{ __('No scenario is marked Pass, no defect is closed, no evidence is accepted, and no UAT sign-off is created in this readiness boundary.') }}</div>
        <div class="grid gap-4 md:grid-cols-2" data-guide="uat-readiness-cards">
            @foreach ($cards as $index => $card)
                <article @class(['rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900', 'md:col-span-2' => $index === count($cards) - 1]) data-guide="{{ $index === 0 ? 'uat-readiness-first-card' : 'uat-readiness-card-'.($index + 1) }}"><h2 class="text-base font-semibold">{{ $card['title'] }}</h2><p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $card['body'] }}</p><p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('PENDING — owner and evidence approval required') }}</p></article>
            @endforeach
        </div>
        <div class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-guide="uat-readiness-empty">{{ __('No UAT run IDs, Pass/Fail/Blocked results, defect closures, retests, or written acceptance are available in this Local/Dev readiness boundary.') }}</div>
    </div>
</x-layouts::app>
