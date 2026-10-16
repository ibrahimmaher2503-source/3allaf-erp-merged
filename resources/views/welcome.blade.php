<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-5 sm:px-8 lg:px-12">
            <header class="flex min-h-20 items-center justify-between border-b border-zinc-200 dark:border-zinc-800">
                <a href="{{ route('home') }}" aria-label="{{ __('TOY & JOY home') }}" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-teal-700 text-white">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-bold tracking-wide">{{ config('app.name', 'TOY & JOY') }}</span>
                </a>

                <nav aria-label="{{ __('Account navigation') }}" class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                            {{ __('Open dashboard') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" wire:navigate>
                            {{ __('Sign in') }}
                        </flux:button>

                    @endauth
                </nav>
            </header>

            <main class="flex flex-1 items-center py-16 lg:py-24">
                <section class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-teal-700 dark:text-teal-300">
                        {{ __('TOY & JOY operations') }}
                    </p>
                    <div class="mt-5 flex items-start gap-3">
                        <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            {{ __('One dependable workspace for daily TOY & JOY operations.') }}
                        </h1>
                        <x-context-help :title="__('Platform foundation')" :label="__('Open contextual help')">
                            <p>{{ __('A browser-based operational platform for retail, inventory, customers, point of sale, and party services, built with clear controls and traceable workflows.') }}</p>
                            <ul class="list-disc space-y-2 ps-5">
                                <li>{{ __('Daily retail sales and payments') }}</li>
                                <li>{{ __('Inventory and purchasing visibility') }}</li>
                                <li>{{ __('Customer, party, and asset workflows') }}</li>
                                <li>{{ __('Arabic RTL and English LTR direction support') }}</li>
                            </ul>
                            <p>{{ __('Use the workspace to manage the work assigned to your role.') }}</p>
                        </x-context-help>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right" wire:navigate>
                                {{ __('Continue to workspace') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('login')" variant="primary" icon-trailing="arrow-right" wire:navigate>
                                {{ __('Sign in to continue') }}
                            </flux:button>
                        @endauth
                    </div>
                </section>
            </main>

            <footer class="border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                {{ __('TOY & JOY operational workspace') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
