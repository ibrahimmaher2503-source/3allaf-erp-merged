<!DOCTYPE html>
@props(['title' => 'POS', 'store' => null, 'shift' => null, 'showCloseShift' => false])
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-page font-sans text-text-primary antialiased">
        @php($posStore = $store ?? null)
        @php($posShift = $shift ?? null)
        @php($posBranch = $posStore?->branch)
        @php($posReady = $posStore !== null && $posShift?->status->acceptsActivity() === true)
        @php($isPosArabic = str_starts_with(app()->getLocale(), 'ar'))
        <!-- Dedicated POS Application Top Bar -->
        <header
            class="sticky top-0 z-30 border-b border-indigo-950 bg-[#454b79] px-3 py-1 text-white shadow-xs"
            data-pos-shell
            x-data="{
                savingAppearance: false,
                async persistAppearance(appearance) {
                    const preferences = { ...(window.__toyJoyUiPreferences || {}), appearance };
                    window.__toyJoyUiPreferences = preferences;
                    this.savingAppearance = true;

                    try {
                        const response = await fetch(@js(route('platform.ui-preferences')), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            body: JSON.stringify(preferences),
                        });

                        if (! response.ok) {
                            throw new Error(`Appearance preference save failed: ${response.status}`);
                        }
                    } catch (error) {
                        console.warn('Appearance preference could not be saved to the account.', error);
                    } finally {
                        this.savingAppearance = false;
                    }
                },
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-1 xl:flex-nowrap">
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="whitespace-nowrap text-sm font-black text-white">{{ __('POS') }}</a>
                </div>

                <!-- Scope Context Indicators -->
                <div class="flex max-w-full flex-wrap items-center gap-1 overflow-x-auto text-[11px]" data-pos-readiness="{{ $posReady ? 'ready' : 'blocked' }}">
                    <div class="flex shrink-0 flex-col border border-white/20 bg-white/10 px-1.5 py-0.5">
                        <span class="text-[9px] font-medium text-white/70">{{ __('Branch') }}</span>
                        <span class="font-semibold">{{ $posBranch ? $posBranch->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posBranch->name_ar : $posBranch->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col border border-white/20 bg-white/10 px-1.5 py-0.5">
                        <span class="text-[9px] font-medium text-white/70">{{ __('POS selling location') }}</span>
                        <span class="font-semibold">{{ $posStore ? $posStore->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posStore->name_ar : $posStore->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col border border-white/20 bg-white/10 px-1.5 py-0.5">
                        <span class="text-[9px] font-medium text-white/70">{{ __('Drawer') }}</span>
                        <span class="font-semibold">{{ $posShift?->cashDrawer ? $posShift->cashDrawer->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posShift->cashDrawer->name_ar : $posShift->cashDrawer->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col border {{ $posReady ? 'border-emerald-300/60 bg-emerald-700/50' : 'border-amber-300/60 bg-amber-700/50' }} px-1.5 py-0.5">
                        <span class="text-[9px] font-medium text-white/70">{{ __('Shift') }}</span>
                        @if($posShift)<x-status.badge :status="$posShift->status->value" />@else<span class="font-semibold">{{ __('Not ready') }}</span>@endif
                    </div>
                    @if($showCloseShift && $posShift?->status->acceptsActivity())
                        @can('shifts_cash_movements.submit')
                            <a href="{{ route('pos.shift').'#shift-close-'.$posShift->id }}" class="inline-flex items-center gap-1 px-1.5 py-1 text-xs font-bold text-white hover:bg-white/10"><flux:icon name="lock-closed" class="size-4" />{{ __('Close shift') }}</a>
                        @endcan
                    @endif
                </div>

                <!-- Connectivity, Locale, and Navigation controls -->
                <div class="flex min-w-0 max-w-full flex-wrap items-center justify-end gap-1">
                    <div x-data="{ online: navigator.onLine }"
                         x-on:online.window="online = true"
                         x-on:offline.window="online = false"
                         class="inline-flex items-center gap-1.5 border border-white/20 bg-white/10 px-1.5 py-1 text-xs font-medium">
                        <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-white"></span>
                    </div>

                    @if (auth()->user()->hasPermission('offline_queue_conflicts.view'))
                        @php($offlineEnabled = ! app()->isProduction() && config('offline.enabled'))
                        <a href="{{ route('pos.offline.queue') }}" wire:navigate class="inline-flex items-center gap-1 px-1.5 py-1 text-xs font-bold text-white hover:bg-white/10"><flux:icon name="signal-slash" class="size-4" />
                            <span class="hidden sm:inline">{{ $offlineEnabled ? __('offline.queue_title') : __('offline.disabled') }}</span>
                            <span class="sm:hidden" aria-label="{{ __('offline.queue_title') }}">{{ $offlineEnabled ? __('offline.queued') : __('Offline') }}</span>
                        </a>
                    @endif

                    <!-- Locale switcher -->
                    <x-locale-switcher compact />

                    <button
                        type="button"
                        data-pos-theme-toggle
                        x-on:click="const appearance = $flux.dark ? 'light' : 'dark'; $flux.appearance = appearance; persistAppearance(appearance)"
                        x-bind:aria-label="$flux.dark ? @js($isPosArabic ? 'التبديل إلى الوضع الفاتح' : 'Switch to light mode') : @js($isPosArabic ? 'التبديل إلى الوضع الداكن' : 'Switch to dark mode')"
                        x-bind:title="$flux.dark ? @js($isPosArabic ? 'التبديل إلى الوضع الفاتح' : 'Switch to light mode') : @js($isPosArabic ? 'التبديل إلى الوضع الداكن' : 'Switch to dark mode')"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded border border-white/30 bg-white/10 text-white transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white disabled:opacity-60"
                        x-bind:disabled="savingAppearance"
                    >
                        <flux:icon name="sun" class="size-4" x-show="$flux.dark" x-cloak aria-hidden="true" />
                        <flux:icon name="moon" class="size-4" x-show="! $flux.dark" aria-hidden="true" />
                    </button>

                    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1 px-1.5 py-1 text-xs font-bold text-white hover:bg-white/10"><flux:icon name="arrow-left" class="size-4" />{{ __('Dashboard') }}</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 px-1.5 py-1 text-xs font-bold text-white hover:bg-white/10"><flux:icon name="arrow-right-start-on-rectangle" class="size-4" />{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col p-0">
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
