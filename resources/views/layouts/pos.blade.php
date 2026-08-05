<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950 font-sans antialiased text-zinc-900 dark:text-zinc-100 flex flex-col">
        <!-- Dedicated POS Application Top Bar -->
        <header class="border-b border-zinc-200 bg-white px-4 py-2.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sticky top-0 z-30">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                    <flux:badge size="sm" class="pos-mode-badge bg-primary-soft text-primary font-bold">{{ __('POS') }}</flux:badge>
                </div>

                <!-- Scope Context Indicators -->
                <div class="hidden md:flex items-center gap-2 text-xs">
                    <div class="flex items-center gap-1.5 rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Branch Context') }}:</span>
                        <span class="font-semibold">{{ __('Not configured') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Selling Store') }}:</span>
                        <span class="font-semibold">{{ __('Not configured') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Cash Drawer') }}:</span>
                        <span class="font-semibold">{{ __('Not configured') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <span class="font-semibold">{{ __('No active shift') }}</span>
                    </div>
                </div>

                <!-- Connectivity, Locale, and Navigation controls -->
                <div class="flex items-center gap-2">
                    <div x-data="{ online: navigator.onLine }"
                         x-on:online.window="online = true"
                         x-on:offline.window="online = false"
                         class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-700 dark:text-zinc-300"></span>
                    </div>

                    <!-- Locale switcher -->
                    <form method="POST" action="{{ route('locale.switch') }}" class="inline">
                        @csrf
                        @if (app()->getLocale() === 'ar')
                            <input type="hidden" name="locale" value="en" />
                            <flux:button type="submit" size="sm" variant="subtle" icon="language">
                                EN
                            </flux:button>
                        @else
                            <input type="hidden" name="locale" value="ar" />
                            <flux:button type="submit" size="sm" variant="subtle" icon="language">
                                عربي
                            </flux:button>
                        @endif
                    </form>

                    <flux:button href="{{ route('dashboard') }}" wire:navigate size="sm" variant="subtle" icon="arrow-left">
                        {{ __('Dashboard') }}
                    </flux:button>
                </div>
            </div>
        </header>

        @include('components.platform.dashboard-tools', ['pageGuide' => \App\Modules\Platform\Data\PageGuideContext::fromRequest(auth()->user())])

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col p-4">
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
