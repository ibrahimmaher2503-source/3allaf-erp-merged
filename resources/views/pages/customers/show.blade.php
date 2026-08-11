@php
    $displayName = app()->getLocale() === 'ar' ? $customer->name_ar : $customer->name_en;
    $storeName = app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en;
@endphp
<x-layouts::app :title="$displayName">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ __('Customer profile') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <flux:heading size="xl">{{ $displayName }}</flux:heading>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ __($customer->status) }}</span>
                </div>
                <flux:text class="mt-1" dir="ltr">{{ $customer->phone_display }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('loyalty.view')
                    <flux:button href="{{ route('customers.loyalty', $customer) }}" variant="primary" icon="star">{{ __('Loyalty ledger') }}</flux:button>
                @endcan
                @can('product_wallet.view')
                    <flux:button href="{{ route('customers.product-wallet', $customer) }}" variant="subtle" icon="wallet">{{ __('Product Wallet') }}</flux:button>
                @endcan
                @can('party_wallet.view')
                    <flux:button href="{{ route('customers.party-wallet', $customer) }}" variant="subtle" icon="briefcase">{{ __('Party Wallet') }}</flux:button>
                @endcan
                <flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                @can('customers.edit')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="customer-identity-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading id="customer-identity-heading" size="lg">{{ __('Identity and contact') }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ __('Changes use a named mutation, increment the version, and write an audit record.') }}</flux:text>
                            </div>
                            <span class="font-mono text-xs text-slate-500" dir="ltr">{{ $customer->public_id }}</span>
                        </div>
                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <flux:input name="phone" :label="__('Primary phone')" :value="$customer->phone_display" required autocomplete="tel" />
                            <flux:input name="secondary_phone" :label="__('Secondary phone')" :value="$customer->secondary_phone" autocomplete="tel" />
                            <flux:input name="name_ar" :label="__('Arabic name')" :value="$customer->name_ar" required dir="rtl" />
                            <flux:input name="name_en" :label="__('English name')" :value="$customer->name_en" required dir="ltr" />
                            @can('customers.sensitive')
                                <flux:input name="email" :label="__('Email')" :value="$customer->email" type="email" autocomplete="email" />
                                <div></div>
                                <flux:textarea name="address_ar" :label="__('Arabic address')" :value="$customer->address_ar" dir="rtl" />
                                <flux:textarea name="address_en" :label="__('English address')" :value="$customer->address_en" dir="ltr" />
                            @endcan
                            <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save customer changes') }}</flux:button></div>
                        </form>
                    </section>
                @else
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('Identity and contact') }}</flux:heading>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Arabic name') }}</dt><dd class="mt-1" dir="rtl">{{ $customer->name_ar }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('English name') }}</dt><dd class="mt-1" dir="ltr">{{ $customer->name_en }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Primary phone') }}</dt><dd class="mt-1 font-mono" dir="ltr">{{ $customer->phone_display }}</dd></div>
                        </dl>
                    </section>
                @endcan

                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="customer-history-heading">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <flux:heading id="customer-history-heading" size="lg">{{ __('Customer history') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Approved retail sales remain linked to this customer profile and are never rewritten by a merge.') }}</flux:text>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-start text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-zinc-950/50"><tr><th class="px-5 py-3">{{ __('Sale') }}</th><th class="px-5 py-3">{{ __('Store') }}</th><th class="px-5 py-3">{{ __('Approved') }}</th><th class="px-5 py-3 text-end">{{ __('Total') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                @forelse ($sales as $sale)
                                    <tr><td class="px-5 py-4"><a class="font-semibold text-cyan-700 hover:underline" href="{{ route('sales.show', $sale) }}">{{ $sale->document_number ?? '#'.$sale->id }}</a></td><td class="px-5 py-4">{{ app()->getLocale() === 'ar' ? $sale->store?->name_ar : $sale->store?->name_en }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ optional($sale->approved_at)->format('Y-m-d H:i') }}</td><td class="px-5 py-4 text-end font-mono" dir="ltr">{{ $sale->total }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-10 text-center"><x-state.empty :title="__('No approved retail history yet.')" :description="__('The customer history will appear here after an approved sale.')" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($sales->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-zinc-800">{{ $sales->links() }}</div>@endif
                </section>

                @can('customers.sensitive')
                    <section class="grid gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                            <flux:heading size="lg">{{ __('Consent history') }}</flux:heading>
                            <div class="mt-4 space-y-3">
                                @forelse ($consents as $consent)
                                    <div class="rounded-xl border border-cyan-100 bg-white/80 p-3 text-sm dark:border-cyan-900 dark:bg-zinc-900/70"><div class="flex justify-between gap-3"><span class="font-semibold">{{ $consent->purpose }}</span><span class="font-bold">{{ __($consent->status) }}</span></div><div class="mt-1 text-xs text-slate-500">{{ $consent->wording_version }} · {{ optional($consent->captured_at)->format('Y-m-d H:i') }} · {{ $consent->source }}</div></div>
                                @empty
                                    <x-state.empty :title="__('No consent history found.')" :description="__('A configured consent event is required for this profile.')" />
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('customers.consents.store', $customer) }}" class="mt-5 grid gap-3 sm:grid-cols-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <flux:input name="purpose" :label="__('Purpose')" required />
                                <flux:select name="status" :label="__('Status')" required><flux:select.option value="granted">{{ __('Granted') }}</flux:select.option><flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option><flux:select.option value="denied">{{ __('Denied') }}</flux:select.option></flux:select>
                                <div class="flex items-end"><flux:button type="submit" variant="subtle">{{ __('Record consent') }}</flux:button></div>
                            </form>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                            <flux:heading size="lg">{{ __('Child profiles') }}</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ __('Purpose-scoped child data is kept separate and is never included in a normal customer export.') }}</flux:text>
                            <div class="mt-4 space-y-3">
                                @forelse ($customer->children as $child)
                                    <div class="rounded-xl border border-amber-100 bg-white/80 p-3 text-sm dark:border-amber-900 dark:bg-zinc-900/70"><div class="font-semibold">{{ app()->getLocale() === 'ar' ? $child->name_ar : $child->name_en }}</div><div class="mt-1 text-xs text-slate-500">{{ $child->purpose }} · {{ $child->birth_date?->format('Y-m-d') ?? __('Birth date not recorded') }}</div></div>
                                @empty
                                    <x-state.empty :title="__('No child profile recorded.')" :description="__('Child data remains optional and purpose-scoped.')" />
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('customers.children.store', $customer) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <flux:input name="name_ar" :label="__('Arabic name')" required dir="rtl" />
                                <flux:input name="name_en" :label="__('English name')" required dir="ltr" />
                                <flux:input name="birth_date" :label="__('Birth date')" type="date" />
                                <flux:input name="purpose" :label="__('Purpose')" required />
                                <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="subtle">{{ __('Add child profile') }}</flux:button></div>
                            </form>
                        </div>
                    </section>
                @endcan
            </div>

            <aside class="space-y-6">
                @can('loyalty.view')
                    <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">{{ __('Shared balance') }}</p>
                        <div class="mt-2 text-4xl font-black tabular-nums" dir="ltr">{{ number_format($balance) }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Points across the customer ledger') }}</flux:text>
                        @if ($dueExpiry > 0)<flux:callout class="mt-4" variant="warning">{{ __(':count points are due to expire.', ['count' => number_format($dueExpiry)]) }}</flux:callout>@endif
                        <flux:button class="mt-4 w-full" href="{{ route('customers.loyalty', $customer) }}" variant="primary">{{ __('Open loyalty') }}</flux:button>
                    </section>
                @endcan
                @can('product_wallet.view')
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">{{ __('Product Wallet') }}</p>
                        <div class="mt-2 break-all text-3xl font-black tabular-nums" dir="ltr">{{ $productWalletBalance }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Retail-only derived balance') }}</flux:text>
                        <flux:button class="mt-4 w-full" href="{{ route('customers.product-wallet', $customer) }}" variant="primary">{{ __('Open Product Wallet') }}</flux:button>
                    </section>
                @endcan
                @can('party_wallet.view')
                    <section class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5 shadow-sm dark:border-violet-900 dark:bg-violet-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700">{{ __('Party Wallet') }}</p>
                        <div class="mt-2 break-all text-3xl font-black tabular-nums" dir="ltr">{{ $partyWalletBalance }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Party-only derived balance') }}</flux:text>
                        <flux:button class="mt-4 w-full" href="{{ route('customers.party-wallet', $customer) }}" variant="primary">{{ __('Open Party Wallet') }}</flux:button>
                    </section>
                @endcan
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Scope and privacy') }}</flux:heading>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold text-slate-500">{{ __('Selling store') }}</dt><dd class="mt-1">{{ $storeName }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-500">{{ __('Scope records') }}</dt><dd class="mt-1">{{ $customer->scopes->count() }}</dd></div>
                        @can('customers.sensitive')
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Email') }}</dt><dd class="mt-1 break-all">{{ $customer->email ?? __('Not recorded') }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Arabic address') }}</dt><dd class="mt-1" dir="rtl">{{ $customer->address_ar ?? __('Not recorded') }}</dd></div>
                        @endcan
                    </dl>
                </section>
                @can('customers.merge')
                    <section class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm dark:border-rose-900 dark:bg-rose-950/20">
                        <flux:heading size="lg">{{ __('Controlled merge') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Merging is blocked when the duplicate has sales, loyalty, or child history. It is never an automatic duplicate resolution.') }}</flux:text>
                        <form method="POST" action="{{ route('customers.merge', $customer) }}" class="mt-4 space-y-3">
                            @csrf
                            <flux:input name="survivor_id" :label="__('Survivor customer ID')" type="number" min="1" required />
                            <flux:textarea name="reason" :label="__('Reason')" required />
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <flux:button type="submit" variant="danger">{{ __('Submit controlled merge') }}</flux:button>
                        </form>
                    </section>
                @endcan
            </aside>
        </div>
    </div>
</x-layouts::app>
