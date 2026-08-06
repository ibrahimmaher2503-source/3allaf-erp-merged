@php
    $setup ??= app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
@endphp

<x-layouts::app :title="__('Initial setup')">
    <x-app.page
        :title="__('Complete initial setup')"
        :description="__('Prepare the first operational data set without inventing production values or bypassing approvals.')"
        :eyebrow="__('First launch setup')"
        :badge="$setup['complete'] ? __('Completed') : __('Needs attention')"
        :badge-color="$setup['complete'] ? 'green' : 'amber'"
        max-width="7xl"
        class="space-y-6"
        data-guide="initial-setup-header"
    >
        <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-card" data-guide="initial-setup-progress">
            <div class="flex flex-col gap-4 border-b border-border bg-surface-muted/35 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <flux:heading size="lg">{{ __('Setup progress') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $setup['completed_count'] }} / {{ $setup['required_count'] }} {{ __('required steps completed') }}
                    </flux:text>
                </div>
                <div class="text-end">
                    <div class="text-3xl font-semibold tracking-tight text-primary">{{ $setup['progress_percent'] }}%</div>
                    <div class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('Required') }}</div>
                </div>
            </div>
            <progress class="h-2 w-full accent-primary" value="{{ $setup['progress_percent'] }}" max="100" aria-label="{{ __('Setup progress') }}">{{ $setup['progress_percent'] }}%</progress>
        </section>

        @if ($setup['complete'])
            <flux:callout variant="success" icon="check-circle" title="{{ __('Initial setup is complete') }}">
                {{ __('All required setup data is present. Future changes still follow the normal permission and approval rules.') }}
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Complete the required steps before opening operations') }}">
                {{ __('Use the links below to enter owner-provided data. Empty states are intentional until the owner supplies the values.') }}
            </flux:callout>
        @endif

        <section class="grid gap-4 md:grid-cols-2" aria-label="{{ __('Initial setup steps') }}" data-guide="initial-setup-steps">
            @foreach ($setup['steps'] as $step)
                <article class="flex h-full flex-col gap-4 rounded-2xl border border-border bg-surface p-5 shadow-sm {{ $step['complete'] ? 'border-emerald-500/30' : ($step['required'] ? 'border-amber-500/30' : '') }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full {{ $step['complete'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300' }}" aria-hidden="true">
                                @if ($step['complete'])
                                    <flux:icon.check class="size-5" />
                                @else
                                    <flux:icon.exclamation-triangle class="size-5" />
                                @endif
                            </span>
                            <div>
                                <flux:heading size="base">{{ $step['label'] }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ $step['description'] }}</flux:text>
                            </div>
                        </div>
                        <flux:badge size="sm" color="{{ $step['complete'] ? 'green' : ($step['required'] ? 'amber' : 'zinc') }}">
                            {{ $step['complete'] ? __('Completed') : ($step['required'] ? __('Required') : __('Optional')) }}
                        </flux:badge>
                    </div>
                    <div class="mt-auto flex items-center justify-between gap-3 border-t border-border pt-4">
                        <span class="text-xs font-medium uppercase tracking-wide text-text-muted">
                            {{ $step['complete'] ? __('Ready') : __('Needs attention') }}
                        </span>
                        <flux:button :href="$step['route']" variant="{{ $step['complete'] ? 'subtle' : 'primary' }}" size="sm" wire:navigate>
                            {{ __('Open step') }}
                        </flux:button>
                    </div>
                </article>
            @endforeach
        </section>

        <flux:callout variant="info" icon="shield-check" title="{{ __('Approval and safety boundary') }}">
            {{ __('Financial settings become active only after an approved version is recorded. This setup page never creates production defaults or bypasses approval.') }}
        </flux:callout>
    </x-app.page>
</x-layouts::app>
