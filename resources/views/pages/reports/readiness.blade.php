@php $isArabic = app()->getLocale() === 'ar'; @endphp
<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-guide="reports-readiness-header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary" data-guide="reports-readiness-summary">{{ __('TSK-038 · Local/Dev readiness') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $title }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review source lineage, scope, formulas, reconciliation, alerts, pagination, export, precision, and freshness before any dashboard number is trusted.') }}</p>
            </div>
            <span class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ __('PENDING') }}</span>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100" data-guide="reports-readiness-boundary">{{ __('No KPI, report, export artifact, alert, or financial figure is calculated in this readiness slice.') }}</div>
        <div class="grid gap-4 md:grid-cols-2" data-guide="reports-readiness-cards">
            @foreach ($cards as $index => $card)
                <article @class(['rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900', 'md:col-span-2' => $index === count($cards) - 1]) data-guide="{{ $index === 0 ? 'reports-readiness-first-card' : 'reports-readiness-card-'.($index + 1) }}">
                    <div data-guide="reports-readiness-card-{{ $index + 1 }}">
                        <h2 class="text-base font-semibold">{{ $card['title'] }}</h2>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $card['body'] }}</p>
                    </div>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('PENDING — owner/source approval required') }}</p>
                </article>
            @endforeach
        </div>
        <div class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-guide="reports-readiness-empty">{{ __('No report rows, KPI values, alerts, exports, or drilldown records are available in this Local/Dev readiness boundary.') }}</div>
    </div>
</x-layouts::app>
