<x-layouts::app :title="__('New customer')">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Register customer')" :description="__('Add a customer profile with contact details and consent.')">
            <x-slot:actions><flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button></x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif
        @if (session('duplicate_candidate'))
            @php($candidate = session('duplicate_candidate'))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p>{{ __('A visible customer profile already uses this phone number or email address. Review it before creating another profile; the system never merges profiles automatically.') }}</p>
                    <p class="text-sm"><span class="font-semibold">{{ app()->getLocale() === 'ar' ? $candidate['name_ar'] : $candidate['name_en'] }}</span> · <span dir="ltr" class="font-mono">{{ $candidate['phone_display'] }}</span></p>
                    <flux:button href="{{ route('customers.show', $candidate['id']) }}" variant="primary" size="sm">{{ __('Review matching customer profile') }}</flux:button>
                </div>
            </flux:callout>
        @endif
        @if ($consentPolicyError)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div class="space-y-2">
                    <p>{{ app()->getLocale() === 'ar' ? 'لا يمكن إنشاء ملف عميل لأن نطاق أغراض الموافقة على بيانات العميل غير مُعد. هذا الإعداد يحدد الغرض الذي يجوز تسجيل بيانات العميل من أجله، وهو مطلوب لحفظ سجل موافقة صحيح.' : 'Customer creation is unavailable because the customer consent-purpose scope has not been configured. This setting explains why the customer data may be recorded and is required for a valid consent record.' }}</p>
                    @can('company_settings.edit')
                        <flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="primary" size="sm">{{ app()->getLocale() === 'ar' ? 'إعداد نطاق أغراض الموافقة' : 'Configure consent purposes' }}</flux:button>
                    @else
                        <p class="text-xs">{{ app()->getLocale() === 'ar' ? 'اطلب من مسؤول إعدادات الشركة تهيئة هذا الإعداد.' : 'Ask a company-settings administrator to configure this setting.' }}</p>
                    @endcan
                </div>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5" data-customer-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Identity and contact') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Customer Arabic first and last names are required. English names are optional and fall back to Arabic for display when omitted.') }}</flux:text>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input name="phone" :label="__('Primary phone')" :value="old('phone')" required autocomplete="tel" :placeholder="__('e.g. 01012345678 or +20 1012345678')" :description="__('Egyptian numbers accept local, +20, 0020, spaces, and Arabic numerals.')" dir="ltr" />
                    <flux:input name="email" :label="__('Email')" :value="old('email')" type="email" autocomplete="email" />
                    <flux:input name="first_name_ar" :label="__('First name (Arabic)')" :value="old('first_name_ar')" required dir="rtl" />
                    <flux:input name="last_name_ar" :label="__('Last name (Arabic)')" :value="old('last_name_ar')" required dir="rtl" />
                    <flux:input name="first_name_en" :label="__('First name (English, optional)')" :value="old('first_name_en')" dir="ltr" />
                    <flux:input name="last_name_en" :label="__('Last name (English, optional)')" :value="old('last_name_en')" dir="ltr" />
                    <flux:input name="secondary_phone" :label="__('Secondary phone')" :value="old('secondary_phone')" autocomplete="tel" :placeholder="__('Optional Egyptian phone number')" dir="ltr" />
                    <flux:select name="customer_group_id" :label="__('Customer group')" :description="__('Optional hierarchical group for customer search and reporting.')">
                        <flux:select.option value="">{{ __('No group assigned') }}</flux:select.option>
                        @foreach ($groupOptions as $group)
                            <flux:select.option value="{{ $group->id }}">{{ $group->parent ? '↳ ' : '' }}{{ app()->getLocale() === 'ar' ? $group->name_ar : $group->name_en }} · {{ app()->getLocale() === 'ar' ? $group->name_en : $group->name_ar }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div></div>
                    <flux:textarea name="address_ar" :label="__('Arabic address')" :value="old('address_ar')" dir="rtl" />
                    <flux:textarea name="address_en" :label="__('English address')" :value="old('address_en')" dir="ltr" />
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                <flux:heading size="lg">{{ __('Privacy and consent record') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Choose the configured purpose and record the customer\'s current response. The event stores its purpose, state, capture time, actor, source, wording version, and scope.') }}</flux:text>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select name="consent_purpose" :label="__('Configured consent purpose')" :description="__('Purpose categories come from the customer policy settings.')" required :disabled="$consentPurposes === []">
                        @foreach ($consentPurposes as $purpose)
                            <flux:select.option value="{{ $purpose }}">{{ $purpose }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select name="consent_status" :label="__('Consent response')" :description="__('Granted records permission, withdrawn records a later withdrawal, and denied records a refusal.')" required>
                        <flux:select.option value="granted">{{ __('Granted') }}</flux:select.option>
                        <flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option>
                        <flux:select.option value="denied">{{ __('Denied') }}</flux:select.option>
                    </flux:select>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Child profile (optional)') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Child data requires a configured purpose scope and is stored separately from the customer identity.') }}</flux:text>
                @if ($childPolicyError)
                    <flux:callout class="mt-3" variant="info">{{ app()->getLocale() === 'ar' ? 'إعداد غرض بيانات الأطفال غير مُتاح حالياً؛ يمكن إنشاء العميل بدون ملف طفل.' : 'The child-data purpose scope is not configured; you can still create the customer without a child profile.' }}</flux:callout>
                @endif
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
