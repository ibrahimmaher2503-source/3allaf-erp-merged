@php
    $groupLabels = [
        'customer' => __('Customer profile'),
        'loyalty' => __('Loyalty'),
        'wallet' => __('Wallets'),
        'gift' => __('Gift instruments'),
        'return' => __('Returns'),
        'party' => __('Party operations'),
        'asset' => __('Rental assets'),
        'quotation' => __('Quotations'),
        'report' => __('Reports'),
        'export' => __('Exports'),
        'migration' => __('Data migration'),
        'operation' => __('Operations'),
        'uat' => __('Acceptance review'),
        'release' => __('Release controls'),
        'audit' => __('Audit'),
        'alert' => __('Alerts'),
    ];
    $settingGroups = $settings->groupBy(fn (array $setting, string $key): string => \Illuminate\Support\Str::before($key, '.'));
@endphp

<x-layouts::app :title="__('Customer Policy Settings')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4" data-guide="customer-settings-header">
            <div>
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ __('Customer settings') }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Customer Policy Settings') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('Change decision values here. Every save creates a new append-only local version and audit event; no value becomes approved policy.') }}</p>
            </div>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-800 shadow-sm hover:border-cyan-300">{{ __('Open customer master') }}</a>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm" data-guide="customer-settings-boundary">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ __('Settings boundary') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ __('Local configuration only') }}</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-amber-900">{{ __('Do not enter secrets, personal customer data, payment values, or unapproved legal wording. Configured values are used only by the enabled customer and loyalty workflows; missing or invalid values stop the action, and owner approval is still required before publishing a policy.') }}</p>
                </div>
                <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-bold text-amber-800">{{ __('Owner approval required') }}</span>
            </div>
        </section>

        <div class="space-y-4" data-guide="customer-settings-groups">
            @foreach ($settingGroups as $group => $groupSettings)
                @php
                    $configuredCount = $groupSettings->filter(fn (array $setting): bool => filled($setting['record']?->value))->count();
                    $groupTitle = $groupLabels[$group] ?? str($group)->headline();
                @endphp
                <details class="group rounded-2xl border border-border bg-surface shadow-card" @if (in_array($group, ['customer', 'loyalty'], true)) open @endif data-setting-group="{{ $group }}">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:px-6">
                        <span class="min-w-0">
                            <span class="block text-base font-semibold text-text-primary">{{ $groupTitle }}</span>
                            <span class="mt-1 block text-xs text-text-muted">{{ $configuredCount }} / {{ $groupSettings->count() }} {{ __('configured') }}</span>
                        </span>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary transition group-open:rotate-180" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="grid gap-4 border-t border-border p-4 lg:grid-cols-2 sm:p-5">
                        @foreach ($groupSettings as $key => $setting)
                            @php($record = $setting['record'])
                            <section class="rounded-xl border border-border-subtle bg-surface-muted/20 p-5" data-setting-key="{{ $key }}" @if ($loop->parent->first && $loop->first) data-guide="customer-settings-first-card" @endif>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 class="font-semibold text-text-primary" data-guide="customer-settings-first-card-heading">{{ __($setting['title']) }}</h2>
                                        <p class="mt-1 font-mono text-[11px] text-text-muted">{{ $key }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $record?->value ? 'bg-primary-soft text-primary' : 'bg-surface-muted text-text-muted' }}">{{ $record?->value ? __('Configured locally') : __('Pending') }}</span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-text-muted">{{ __($setting['description']) }}</p>
                                @can('company_settings.edit')
                                    <form method="POST" action="{{ route('admin.settings.customer-loyalty.save') }}" class="mt-4 space-y-3">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $key }}">
                                        <div>
                                            <label for="value-{{ \Illuminate\Support\Str::slug($key) }}" class="block text-sm font-semibold text-text-primary">{{ __('Local decision value') }}</label>
                                            <textarea id="value-{{ \Illuminate\Support\Str::slug($key) }}" name="value" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-border bg-surface text-sm shadow-sm focus:border-primary focus:ring-primary" placeholder="{{ __('Leave blank to keep this decision pending') }}">{{ old('key') === $key ? old('value') : ($record?->value ?? '') }}</textarea>
                                        </div>
                                        <div>
                                            <label for="notes-{{ \Illuminate\Support\Str::slug($key) }}" class="block text-sm font-semibold text-text-primary">{{ __('Notes') }}</label>
                                            <textarea id="notes-{{ \Illuminate\Support\Str::slug($key) }}" name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border-border bg-surface text-sm shadow-sm focus:border-primary focus:ring-primary" placeholder="{{ __('Optional local context; no secrets') }}">{{ old('key') === $key ? old('notes') : ($record?->notes ?? '') }}</textarea>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <span class="text-xs text-text-muted">{{ __('Saving creates a new version; approval remains pending.') }}</span>
                                            <flux:button type="submit" variant="primary" data-guide="customer-settings-save-action">{{ __('Save local version') }}</flux:button>
                                        </div>
                                    </form>
                                @else
                                    <div class="mt-4 rounded-xl border border-dashed border-border bg-surface-muted p-3 text-sm text-text-muted">{{ __('You have view-only access to these settings.') }}</div>
                                @endcan
                                @if ($record)
                                    <p class="mt-3 text-xs text-text-muted">{{ __('Current version') }}: {{ $record->version }} · {{ __('Approval') }}: {{ __('Owner approval required') }}</p>
                                @endif
                            </section>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</x-layouts::app>
