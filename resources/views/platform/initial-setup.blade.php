@php
    $setup ??= app(\App\Modules\Platform\Support\InitialSetupStatus::class)->snapshot();
    $nextStep = collect($setup['steps'])->first(static fn (array $step): bool => $step['required'] && ! $step['complete']);
@endphp

<x-layouts::app :title="__('Initial setup')">
    <x-app.page
        :title="__('Complete initial setup')"
        :description="__('Prepare the first operational data set without inventing production values or bypassing approvals.')"
        max-width="7xl"
        class="space-y-6"
        data-guide="initial-setup-header"
    >
        <x-slot:actions>
            <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                <flux:button type="submit" variant="subtle" icon="language">
                    {{ app()->getLocale() === 'ar' ? __('Switch to English') : __('Switch to Arabic') }}
                </flux:button>
            </form>
            <flux:button :href="route('dashboard')" variant="subtle" icon="arrow-left" wire:navigate>
                {{ __('Back to dashboard') }}
            </flux:button>
        </x-slot:actions>

        <section class="relative isolate overflow-hidden rounded-[1.75rem] bg-primary px-6 py-7 text-white shadow-xl shadow-primary/15 sm:px-8 sm:py-9" data-guide="initial-setup-hero">
            <div class="pointer-events-none absolute -end-12 -top-16 size-56 rounded-full border-[24px] border-white/10"></div>
            <div class="pointer-events-none absolute -bottom-24 start-1/3 size-64 rounded-full border-[28px] border-white/5"></div>
            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_240px] lg:items-center">
                <div class="max-w-2xl">
                    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/70">
                        <span>{{ __('First launch setup') }}</span>
                        <span class="size-1 rounded-full bg-white/60"></span>
                        <span>{{ __('Owner workspace') }}</span>
                    </div>
                    <flux:heading size="xl" class="max-w-xl text-white">
                        {{ __('Your operations, ready in order') }}
                    </flux:heading>
                    <flux:text class="mt-3 max-w-xl !text-white" style="color: #ffffff !important;">
                        {{ __('Enter the real owner data one step at a time. The system will show what is ready, what needs attention, and what still requires approval.') }}
                    </flux:text>
                    <div class="mt-6 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 font-medium"><span dir="ltr">{{ $setup['completed_count'] }}</span> {{ __('required steps completed') }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-1.5 font-medium"><span dir="ltr">{{ $setup['required_count'] - $setup['completed_count'] }}</span> {{ __('required steps remaining') }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-1.5 font-medium">{{ __('Owner data only') }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-4 lg:justify-end">
                    <div class="flex size-32 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 p-2 shadow-inner shadow-black/10">
                        <div class="flex size-full flex-col items-center justify-center rounded-full bg-primary text-center ring-1 ring-white/10">
                            <span class="text-3xl font-semibold tracking-tight">{{ $setup['progress_percent'] }}%</span>
                            <span class="mt-1 text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-white/70">{{ __('Ready') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]" data-guide="initial-setup-summary">
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-card sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Setup roadmap') }}</div>
                        <flux:heading size="lg" class="mt-2">{{ __('Configuration status') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Six guided steps keep the opening data visible and traceable.') }}</flux:text>
                    </div>
                    <div class="text-start sm:text-end">
                        <div class="text-2xl font-semibold tracking-tight text-primary"><span dir="ltr">{{ $setup['completed_count'] }} / {{ $setup['required_count'] }}</span></div>
                        <div class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('Required') }}</div>
                    </div>
                </div>
                <progress class="mt-5 h-2.5 w-full accent-primary" value="{{ $setup['progress_percent'] }}" max="100" aria-label="{{ __('Setup progress') }}">{{ $setup['progress_percent'] }}%</progress>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-text-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('Completed') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-500"></span>{{ __('Needs attention') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-zinc-400"></span>{{ __('Optional') }}</span>
                </div>
            </div>

            @if ($nextStep)
                <div class="flex flex-col justify-between gap-5 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 shadow-sm sm:p-6" data-guide="initial-setup-next-step">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-amber-800 dark:text-amber-200">
                            <flux:icon.arrow-left class="size-4" />
                            {{ __('Start here') }}
                        </div>
                        <flux:heading size="base" class="mt-3">{{ __('Next recommended step') }}</flux:heading>
                        <flux:text class="mt-1">{{ $nextStep['label'] }}</flux:text>
                    </div>
                    <flux:button :href="$nextStep['route']" variant="primary" icon="arrow-left" wire:navigate>
                        {{ __('Open next step') }}
                    </flux:button>
                </div>
            @else
                <flux:callout variant="success" icon="check-circle" title="{{ __('All required steps are ready') }}">
                    {{ __('The workspace can move to the normal operational dashboard after final review.') }}
                </flux:callout>
            @endif
        </section>

        @if ($setup['complete'])
            <flux:callout variant="success" icon="check-circle" title="{{ __('Initial setup is complete') }}">
                {{ __('All required setup data is present. Future changes still follow the normal permission and approval rules.') }}
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Complete the required steps before opening operations') }}">
                {{ __('Empty states are intentional until the owner supplies the values. Demo data never counts as production approval.') }}
            </flux:callout>
        @endif

        <section aria-label="{{ __('Initial setup steps') }}" data-guide="initial-setup-steps">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Owner checklist') }}</div>
                    <flux:heading size="lg" class="mt-2">{{ __('Initial setup steps') }}</flux:heading>
                </div>
                <div class="hidden text-xs text-text-muted sm:block">{{ __('No demo values') }}</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($setup['steps'] as $index => $step)
                    <article class="group relative flex h-full flex-col gap-5 overflow-hidden rounded-2xl border bg-surface p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-card sm:p-6 {{ $step['complete'] ? 'border-emerald-500/30' : ($step['required'] ? 'border-amber-500/35' : 'border-border') }}" data-guide="initial-setup-step-{{ $step['key'] }}">
                        <div class="absolute inset-y-0 start-0 w-1 {{ $step['complete'] ? 'bg-emerald-500' : ($step['required'] ? 'bg-amber-500' : 'bg-zinc-400') }}"></div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold {{ $step['complete'] ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300' : ($step['required'] ? 'bg-amber-500/12 text-amber-700 dark:text-amber-300' : 'bg-zinc-500/10 text-text-muted') }}">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="min-w-0">
                                    <flux:heading size="base">{{ $step['label'] }}</flux:heading>
                                    <flux:text class="mt-1 text-sm leading-6">{{ $step['description'] }}</flux:text>
                                </div>
                            </div>
                            <flux:badge size="sm" color="{{ $step['complete'] ? 'green' : ($step['required'] ? 'amber' : 'zinc') }}">
                                {{ $step['complete'] ? __('Completed') : ($step['required'] ? __('Required') : __('Optional')) }}
                            </flux:badge>
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-border pt-4">
                            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-text-muted">
                                <span class="size-2 rounded-full {{ $step['complete'] ? 'bg-emerald-500' : ($step['required'] ? 'bg-amber-500' : 'bg-zinc-400') }}"></span>
                                {{ $step['complete'] ? __('Ready') : __('Needs attention') }}
                            </span>
                            <flux:button :href="$step['route']" variant="{{ $step['complete'] ? 'subtle' : 'primary' }}" size="sm" icon="arrow-left" wire:navigate>
                                {{ $step['complete'] ? __('Review step') : __('Open step') }}
                            </flux:button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <flux:callout variant="info" icon="shield-check" title="{{ __('Approval and safety boundary') }}">
            {{ __('Financial settings become active only after an approved version is recorded. This setup page never creates production defaults or bypasses approval.') }}
        </flux:callout>
    </x-app.page>
</x-layouts::app>
