<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-guide="master-data-migration-readiness-header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary" data-guide="master-data-migration-readiness-summary">{{ __('TSK-041 · Local/Dev readiness') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $title }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review approved sources, dependency order, staged validation, reconciliation, maker/checker approval, backup, and cutover prerequisites before any production master data is trusted.') }}</p>
            </div>
            <span class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ __('PENDING') }}</span>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100" data-guide="master-data-migration-readiness-boundary">{{ __('No file upload, parsing, batch persistence, destructive replacement, opening-stock posting, cutover, or production data action is enabled in this readiness slice.') }}</div>
        <div class="grid gap-4 md:grid-cols-2" data-guide="master-data-migration-readiness-cards">
            @foreach ($cards as $index => $card)
                <article @class(['rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900', 'md:col-span-2' => $index === count($cards) - 1]) data-guide="{{ $index === 0 ? 'master-data-migration-readiness-first-card' : 'master-data-migration-readiness-card-'.($index + 1) }}">
                    <h2 class="text-base font-semibold">{{ $card['title'] }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $card['body'] }}</p>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('PENDING — owner/source approval required') }}</p>
                </article>
            @endforeach
        </div>
        <div class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-guide="master-data-migration-readiness-empty">{{ __('No approved production source file, import batch, reconciliation report, cutover record, or production master data is available in this Local/Dev readiness boundary.') }}</div>
    </div>
</x-layouts::app>
