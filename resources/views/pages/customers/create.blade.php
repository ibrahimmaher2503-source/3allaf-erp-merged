<x-layouts::app :title="__('New customer')">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Register customer')" :description="__('Add a customer profile with contact details and consent.')">
            <x-slot:actions><flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button></x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif
        @if ($policyError)
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('Registration is blocked until the required consent and child-data policies are configured.') }} · {{ $policyError }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5" data-customer-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Identity and contact') }}</flux:heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input name="phone" :label="__('Primary phone')" :value="old('phone')" required autocomplete="tel" :description="__('The phone is normalized and globally unique.')" />
                    <flux:input name="email" :label="__('Email')" :value="old('email')" type="email" autocomplete="email" />
                    <flux:input name="name_ar" :label="__('Arabic name')" :value="old('name_ar')" required dir="rtl" />
                    <flux:input name="name_en" :label="__('English name')" :value="old('name_en')" required dir="ltr" />
                    <flux:input name="secondary_phone" :label="__('Secondary phone')" :value="old('secondary_phone')" autocomplete="tel" />
                    <div></div>
                    <flux:textarea name="address_ar" :label="__('Arabic address')" :value="old('address_ar')" dir="rtl" />
                    <flux:textarea name="address_en" :label="__('English address')" :value="old('address_en')" dir="ltr" />
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                <flux:heading size="lg">{{ __('Consent and privacy') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Consent is stored as an append-only event with wording version, actor, source, scope, and retention.') }}</flux:text>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select name="consent_purpose" :label="__('Consent purpose')" required :disabled="$consentPurposes === []">
                        @foreach ($consentPurposes as $purpose)
                            <flux:select.option value="{{ $purpose }}">{{ $purpose }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select name="consent_status" :label="__('Consent status')" required>
                        <flux:select.option value="granted">{{ __('Granted') }}</flux:select.option>
                        <flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option>
                        <flux:select.option value="denied">{{ __('Denied') }}</flux:select.option>
                    </flux:select>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Child profile (optional)') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Child data requires a configured purpose scope and is stored separately from the customer identity.') }}</flux:text>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input name="child_name_ar" :label="__('Child Arabic name')" :value="old('child_name_ar')" dir="rtl" />
                    <flux:input name="child_name_en" :label="__('Child English name')" :value="old('child_name_en')" dir="ltr" />
                    <flux:input name="child_birth_date" :label="__('Birth date')" :value="old('child_birth_date')" type="date" />
                    <flux:select name="child_purpose" :label="__('Child-data purpose')" :disabled="$childPurposes === []">
                        <flux:select.option value="">{{ __('No child profile') }}</flux:select.option>
                        @foreach ($childPurposes as $purpose)
                            <flux:select.option value="{{ $purpose }}">{{ $purpose }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text class="text-xs text-slate-500">{{ __('The selected selling store is') }}: <span class="font-semibold">{{ app()->getLocale() === 'ar' ? $store->name_ar : $store->name_en }}</span></flux:text>
                <flux:button type="submit" variant="primary" :disabled="$consentPurposes === []">{{ __('Create customer profile') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
