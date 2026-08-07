<x-layouts::app :title="__('TSK-027 Customer Loyalty Readiness')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="customer-readiness-header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">TSK-027 · DM 4.1</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Customer and loyalty readiness') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('This is a Local/Dev policy boundary. Values below come from Settings and remain pending owner approval; no customer, consent, loyalty, wallet, or gift records are loaded or changed.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('company_settings.view')
                    <a href="{{ route('admin.settings.customer-loyalty') }}" class="inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-800 shadow-sm hover:border-cyan-300">{{ __('Open customer policy settings') }}</a>
                @endcan
                <a href="{{ route('pos') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-800">{{ __('Back to POS') }}</a>
            </div>
        </div>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm" data-guide="customer-readiness-boundary">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ __('Local/Dev dynamic policy') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ __('Settings values are visible, but not approved policy') }}</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-amber-900">{{ __('Changing a value creates an append-only local version and audit event. There is no approval bypass and no customer/loyalty transaction consumes these values yet.') }}</p>
                </div>
                <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-bold text-amber-800">{{ __('Owner approval required') }}</span>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="policy-values-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="policy-values-heading" class="text-lg font-semibold text-slate-950">{{ __('Dynamic decision values') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Each key resolves to its latest local version. Empty values remain PENDING.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $decisionSettings->count() }} {{ __('decision keys') }}</span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($decisionSettings as $key => $setting)
                    @php($record = $setting['record'])
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4" data-policy-key="{{ $key }}" @if ($loop->first) data-guide="customer-readiness-first-card" @endif>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900" data-guide="customer-readiness-first-card-heading">{{ __($setting['title']) }}</h3>
                                <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $key }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $record?->value ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-600' }}">{{ $record?->value ? __('Configured locally') : __('PENDING') }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ __($setting['description']) }}</p>
                        <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-white p-3 text-sm" data-policy-value>
                            @if ($record?->value)
                                <p class="font-medium break-words text-slate-900">{{ $record->value }}</p>
                                <p class="mt-1 text-xs text-amber-700">{{ __('Local value — owner approval required') }} · {{ __('Version') }} {{ $record->version }}</p>
                            @else
                                <p class="font-semibold text-slate-500">{{ __('PENDING — configure from Settings') }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">{{ __('Shared loyalty contract') }}</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                    <li><strong>{{ __('Earn and redeem') }}:</strong> {{ __('No preview, approval, earn, redeem, expiry, or adjustment action exists.') }}</li>
                    <li><strong>{{ __('Ledger integrity') }}:</strong> {{ __('The append-only ledger, source idempotency, locking, and audit contract are not implemented.') }}</li>
                    <li><strong>{{ __('Balance protection') }}:</strong> {{ __('Insufficient, expired, duplicate, concurrent, and direct-edit paths remain blocked by absence.') }}</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm" data-guide="customer-readiness-deferred">
                <h2 class="text-lg font-semibold text-slate-950">{{ __('Deferred separate instruments') }}</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-slate-600">
                    <p><strong class="text-slate-900">{{ __('Product and Party Wallets') }}:</strong> {{ __('Deferred to TSK-028. The ledgers must remain separately named, scoped, append-only, and non-transferable.') }}</p>
                    <p><strong class="text-slate-900">{{ __('Gift Cards and Gift Receipts') }}:</strong> {{ __('Deferred to TSK-029. No issue, balance, redemption, void, expiry, or print behavior is enabled.') }}</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
