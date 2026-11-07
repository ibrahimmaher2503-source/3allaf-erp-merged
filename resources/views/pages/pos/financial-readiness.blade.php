<x-layouts::app :title="__('POS Financial Controls')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-2">
                <flux:heading size="xl">{{ __('POS Financial Controls') }}</flux:heading>
                <x-context-help :title="__('POS Financial Controls')" :label="__('Open contextual help')"><p>{{ __('Current payment, tax, and POS configuration used to control checkout amounts.') }}</p></x-context-help>
            </div>
            <flux:button href="{{ route('pos') }}" variant="primary">{{ __('Back to POS') }}</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Active payment methods') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $activePaymentMethods }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Active tax policies') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $activeTaxSettings }}</flux:heading>
                @if ($activeTaxSettings !== 1)
                    <flux:text class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ __('Tax-on checkout requires exactly one effective policy with a configured rate.') }}</flux:text>
                @endif
            </div>
        </div>

        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Versioned POS settings') }}</flux:heading>
                <x-context-help :title="__('Versioned POS settings')" :label="__('Open contextual help')"><p>{{ __('No value is defaulted. An unset required value blocks the related financial action explicitly.') }}</p></x-context-help>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($definitions as $key => $definition)
                    @php($record = $latestSettings->get($key))
                    <article class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2"><flux:heading size="sm">{{ __($definition['title']) }}</flux:heading><x-context-help :title="__($definition['title'])" :label="__('Open contextual help')"><p>{{ __($definition['description']) }}</p></x-context-help></div>
                            <flux:badge :color="$record && filled($record->value) ? 'green' : 'amber'">{{ $record && filled($record->value) ? ($key === \App\Modules\Retail\Support\PosFinancialSettingRegistry::CASH_ROUNDING_ENABLED ? ($record->value === '1' ? __('Enabled') : __('Disabled')) : __('Configured')) : __('Unset') }}</flux:badge>
                        </div>
                        @if ($key === \App\Modules\Retail\Support\PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION)
                            <p class="mt-3 text-xs text-zinc-500">{{ __('Enter the approved positive denomination in whole units or cents. Existing 5 EGP settings remain active until changed.') }}</p>
                        @endif
                        <dl class="mt-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Current value') }}</dt><dd class="font-mono font-semibold">{{ $record?->value ?? __('Unset') }}</dd></div>
                            <div class="mt-1 flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Version') }}</dt><dd>{{ $record?->version ?? 0 }}</dd></div>
                        </dl>
                        @can('company_settings.edit')
                            <form method="POST" action="{{ route('admin.settings.pos-financial.save') }}" class="mt-4 grid gap-3">
                                @csrf
                                <input type="hidden" name="key" value="{{ $key }}">
                                @if ($key === \App\Modules\Retail\Support\PosFinancialSettingRegistry::CASH_ROUNDING_ENABLED)
                                    <flux:select name="value" :label="__('Cash rounding state')" required><flux:select.option value="1">{{ __('Enabled') }}</flux:select.option><flux:select.option value="0">{{ __('Disabled') }}</flux:select.option></flux:select>
                                @else
                                    <div><flux:input name="value" type="number" min="{{ $key === \App\Modules\Retail\Support\PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION ? '0.01' : '0.0001' }}" step="{{ $key === \App\Modules\Retail\Support\PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION ? '0.01' : '0.0001' }}" :label="__('New value')" /><x-context-help :title="__('New value')" :label="__('Open contextual help')"><p>{{ __('Saving creates a new immutable version.') }}</p></x-context-help></div>
                                @endif
                                <flux:input name="notes" :label="__('Configuration reason / note')" />
                                <flux:button type="submit" size="sm" variant="subtle">{{ __('Save new version') }}</flux:button>
                            </form>
                        @endcan
                    </article>
                @endforeach
            </div>
        </section>

        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>{{ __('Financial settings require approval') }}</flux:callout.heading>
            <flux:callout.text>{{ __('These controls keep payment and tax behavior explicit. Values require owner approval before operational use.') }}</flux:callout.text>
        </flux:callout>
    </div>
</x-layouts::app>
