<x-layouts::app :title="__('TSK-026 Offline Readiness')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="offline-readiness-header">
            <div class="max-w-3xl space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-teal-700">{{ __('TSK-026') }}</p>
                <h1 class="text-3xl font-semibold tracking-tight text-zinc-950">{{ __('TSK-026 Offline Readiness') }}</h1>
                <p class="text-sm leading-6 text-zinc-600">{{ __('Read-only restricted offline boundary. No offline queue, sync, replay, conflict, or transaction is enabled here.') }}</p>
            </div>
            <a href="{{ route('pos') }}" class="inline-flex items-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-teal-300 hover:text-teal-700">{{ __('Back to POS') }}</a>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 shadow-sm" data-guide="offline-readiness-boundary">
            <p class="text-sm font-semibold">{{ __('Transactional offline POS is disabled by default') }}</p>
            <p class="mt-1 text-sm leading-6 text-amber-900">{{ __('Branch/device enablement, limits, price age, expiry, retry, and conflict ownership require explicit owner configuration before any offline sale can be accepted.') }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['title' => __('Enabled branch and device scope'), 'code' => 'OFF-01 / PENDING', 'body' => __('Opt-in branch and device registration is not configured. No offline device is enabled by this page.')],
                ['title' => __('Duration, queue, and amount limits'), 'code' => 'OFF-02 / PENDING', 'body' => __('Maximum duration, queued transactions, and transaction value remain owner-configurable. No defaults are exposed or applied.')],
                ['title' => __('Permitted price age'), 'code' => 'OFF-03 / PENDING', 'body' => __('The permitted cached-price age is unresolved. Stale-price behavior remains disabled rather than silently accepting a sale.')],
                ['title' => __('Queue expiry and retry'), 'code' => 'OFF-04 / PENDING', 'body' => __('Expiry, retry, and idempotent replay policy is unresolved. No local queue or replay action exists.')],
                ['title' => __('Conflict review ownership'), 'code' => 'OFF-05 / PENDING', 'body' => __('Conflict disposition and review ownership are unresolved. Conflicts cannot be auto-resolved.')],
                ['title' => __('Protected local data boundary'), 'code' => 'NFR-04 / PENDING', 'body' => __('No customer, payment, wallet, loyalty, audit, expected-cash, cost, or margin data is cached by this slice.')],
            ] as $card)
                <article @if ($loop->first) data-guide="offline-readiness-first-card" @endif class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-base font-semibold text-zinc-900">{{ $card['title'] }}</h2>
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-800">{{ $card['code'] }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-zinc-600">{{ $card['body'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="text-base font-semibold text-emerald-950">{{ __('PRD permitted classes — not enabled') }}</h2>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-emerald-900">
                    <li>• {{ __('Cash sale') }}</li>
                    <li>• {{ __('Manually recorded electronic payment with evidence') }}</li>
                    <li>• {{ __('Standard approved price') }}</li>
                    <li>• {{ __('Product search and suspended sale held locally') }}</li>
                </ul>
            </section>
            <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                <h2 class="text-base font-semibold text-rose-950">{{ __('PRD blocked classes — enforced as pending') }}</h2>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-rose-900">
                    <li>• {{ __('Credit sale') }}</li>
                    <li>• {{ __('Product/Party Wallet use or loyalty redemption') }}</li>
                    <li>• {{ __('Special discount, open price, return, exchange, or party operation') }}</li>
                    <li>• {{ __('Negative-stock override or any balance/price conflict operation') }}</li>
                </ul>
            </section>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5" data-guide="offline-readiness-summary">
            <p class="text-sm font-semibold text-zinc-900">{{ __('Readiness status — no offline mutation surface') }}</p>
            <p class="mt-1 text-sm leading-6 text-zinc-600">{{ __('This page records the boundary only. Server truth, secure synchronization, conflict review, provisional numbering, and device security require approved policy and a later implementation slice.') }}</p>
        </div>
    </div>
</x-layouts::app>
