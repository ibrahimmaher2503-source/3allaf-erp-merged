<x-layouts::app :title="__('TSK-027 Customer Loyalty Readiness')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">TSK-027 · DM 4.1</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Customer and loyalty readiness') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('This is a read-only Local/Dev boundary. No customer, consent, loyalty, wallet, or gift records are loaded or changed.') }}</p>
            </div>
            <a href="{{ route('pos') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-800">{{ __('Back to POS') }}</a>
        </div>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ __('Read-only boundary') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ __('Customer and loyalty records are not enabled in this slice') }}</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-amber-900">{{ __('The existing POS view permission is reused only to protect this empty readiness page. It does not grant customer, sensitive, loyalty, wallet, export, or adjustment capabilities.') }}</p>
                </div>
                <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-bold text-amber-800">PENDING</span>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-950">{{ __('Customer identity and consent') }}</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">PENDING</span>
                </div>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                    <li><strong>{{ __('Unique phone and duplicate review') }}:</strong> {{ __('No customer profile or merge action exists.') }}</li>
                    <li><strong>{{ __('Consent and retention') }}:</strong> {{ __('Legal wording, purpose, version, and retention owner input are pending.') }}</li>
                    <li><strong>{{ __('Children and sensitive fields') }}:</strong> {{ __('Purpose-scoped storage and server authorization are not enabled.') }}</li>
                    <li><strong>{{ __('Unified history') }}:</strong> {{ __('No customer, sale, party, payment, return, or gift history is exposed here.') }}</li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-950">{{ __('Shared loyalty contract') }}</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">PENDING</span>
                </div>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                    <li><strong>{{ __('Activity-specific rules') }}:</strong> {{ __('Retail and party rules require approved configurable values.') }}</li>
                    <li><strong>{{ __('Earn and redeem') }}:</strong> {{ __('No preview, approval, earn, redeem, expiry, or adjustment action exists.') }}</li>
                    <li><strong>{{ __('Ledger integrity') }}:</strong> {{ __('The append-only ledger, source idempotency, locking, and audit contract are not implemented.') }}</li>
                    <li><strong>{{ __('Balance protection') }}:</strong> {{ __('Insufficient, expired, duplicate, concurrent, and direct-edit paths remain blocked by absence.') }}</li>
                </ul>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">{{ __('Deferred separate instruments') }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="font-semibold text-slate-900">{{ __('Product and Party Wallets') }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Deferred to TSK-028. The ledgers must remain separately named, scoped, append-only, and non-transferable.') }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="font-semibold text-slate-900">{{ __('Gift Cards and Gift Receipts') }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Deferred to TSK-029. No issue, balance, redemption, void, expiry, or print behavior is enabled.') }}</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
