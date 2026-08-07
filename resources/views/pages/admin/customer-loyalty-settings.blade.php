<x-layouts::app :title="__('Customer Policy Settings')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">TSK-027 · Local/Dev</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ __('Customer Policy Settings') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('Change decision values here. Every save creates a new append-only local version and audit event; no value becomes approved policy.') }}</p>
            </div>
            <a href="{{ route('customers.loyalty-readiness') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300 hover:text-cyan-800">{{ __('View readiness') }}</a>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ __('Settings boundary') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ __('Local configuration only') }}</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-amber-900">{{ __('Do not enter secrets, personal customer data, payment values, or unapproved legal wording. Values are displayed as pending owner decisions and are not consumed by customer or loyalty workflows.') }}</p>
                </div>
                <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-bold text-amber-800">{{ __('Owner approval required') }}</span>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($settings as $key => $setting)
                @php($record = $setting['record'])
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-setting-key="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-slate-950">{{ __($setting['title']) }}</h2>
                            <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $key }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $record?->value ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-100 text-slate-600' }}">{{ $record?->value ? __('Configured locally') : __('PENDING') }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ __($setting['description']) }}</p>
                    @can('company_settings.edit')
                        <form method="POST" action="{{ route('admin.settings.customer-loyalty.save') }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="key" value="{{ $key }}">
                            <div>
                                <label for="value-{{ $loop->index }}" class="block text-sm font-semibold text-slate-800">{{ __('Local decision value') }}</label>
                                <textarea id="value-{{ $loop->index }}" name="value" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="{{ __('Leave blank to keep this decision PENDING') }}">{{ old('key') === $key ? old('value') : ($record?->value ?? '') }}</textarea>
                            </div>
                            <div>
                                <label for="notes-{{ $loop->index }}" class="block text-sm font-semibold text-slate-800">{{ __('Notes') }}</label>
                                <textarea id="notes-{{ $loop->index }}" name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="{{ __('Optional local context; no secrets') }}">{{ old('key') === $key ? old('notes') : ($record?->notes ?? '') }}</textarea>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="text-xs text-amber-700">{{ __('Saving creates a new version; approval remains pending.') }}</span>
                                <flux:button type="submit" variant="primary">{{ __('Save local version') }}</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-600">{{ __('You have view-only access to these settings.') }}</div>
                    @endcan
                    @if ($record)
                        <p class="mt-3 text-xs text-slate-500">{{ __('Current version') }}: {{ $record->version }} · {{ __('Approval') }}: {{ __('Owner approval required') }}</p>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-layouts::app>
