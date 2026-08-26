<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}" class="overflow-x-hidden">
<head>@include('partials.head')</head>
<body class="app-layout min-h-screen overflow-x-hidden">
@php
    $actor = auth()->user();
    $isArabic = app()->getLocale() === 'ar';
    $contextStore = \App\Modules\Platform\Models\Store::query()->visibleTo($actor)->where('status', 'active')->with('company:id,name_ar,name_en')->orderBy('code')->first(['id', 'company_id', 'code', 'name_ar', 'name_en']);
    $contextName = $contextStore ? ($isArabic ? $contextStore->name_ar : $contextStore->name_en) : null;
    $companyName = $contextStore?->company ? ($isArabic ? $contextStore->company->name_ar : $contextStore->company->name_en) : config('app.name');
@endphp
<ui-sidebar class="app-sidebar z-20 flex h-dvh min-h-dvh max-h-dvh w-[260px] flex-col overflow-hidden border-e p-3 transition-[transform,width] duration-200 max-lg:fixed max-lg:inset-y-0 max-lg:start-0 max-lg:data-flux-sidebar-cloak:hidden max-lg:data-flux-sidebar-collapsed-mobile:-translate-x-full max-lg:data-flux-sidebar-collapsed-mobile:rtl:translate-x-full" collapsible="mobile" sticky x-data data-flux-sidebar-cloak data-flux-sidebar>
    <flux:sidebar.header class="app-brand-block"><x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate /><flux:sidebar.collapse class="lg:hidden" /></flux:sidebar.header>
    <div class="app-company-context" title="{{ $companyName }}"><span class="app-company-context__dot" aria-hidden="true"></span><span class="min-w-0"><strong>{{ $companyName }}</strong><small>{{ $contextName ?: ($isArabic ? 'كل المواقع المصرح بها' : 'All authorized locations') }}</small></span></div>
    <x-app-navigation />
    <div class="sidebar-status"><div x-data="{ online: navigator.onLine }" x-on:online.window="online = true" x-on:offline.window="online = false" class="flex items-center gap-2"><span class="size-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-400'"></span><span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'"></span></div></div>
    <x-desktop-user-menu class="hidden lg:block" :name="$actor->name" />
</ui-sidebar>
<div class="app-layout__content min-w-0">
    <header class="app-topbar">
        <div class="flex min-w-0 items-center gap-2"><flux:sidebar.toggle class="lg:hidden" icon="bars-2" /><button type="button" class="app-topbar__search" x-data x-on:click="$dispatch('open-navigation-search')" aria-label="{{ $isArabic ? 'بحث سريع في النظام' : 'Quick system search' }}"><flux:icon.magnifying-glass class="size-5" /><span>{{ $isArabic ? 'بحث سريع…' : 'Quick search…' }}</span><kbd dir="ltr">⌘ K</kbd></button></div>
        <div class="app-topbar__actions">
            @if ($contextName)<span class="app-topbar__context hidden md:inline-flex"><flux:icon.building-storefront class="size-4" />{{ $contextName }}</span>@endif
            <flux:button :href="route('notifications.index')" variant="ghost" square icon="bell" aria-label="{{ $isArabic ? 'الإشعارات' : 'Notifications' }}" wire:navigate />
            <form method="POST" action="{{ route('locale.switch') }}">@csrf<input type="hidden" name="locale" value="{{ $isArabic ? 'en' : 'ar' }}"><flux:button type="submit" variant="ghost" class="min-h-11">{{ $isArabic ? 'EN' : 'ع' }}</flux:button></form>
            <flux:button :href="route('appearance.edit')" variant="ghost" square icon="sun" aria-label="{{ $isArabic ? 'المظهر' : 'Appearance' }}" wire:navigate />
            <flux:dropdown position="bottom" align="end"><flux:profile :initials="$actor->initials()" icon-trailing="chevron-down" /><flux:menu><div class="px-3 py-2"><strong class="block truncate text-sm">{{ $actor->name }}</strong><span class="block truncate text-xs text-text-muted">{{ $actor->email }}</span></div><flux:menu.separator /><flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item><form method="POST" action="{{ route('logout') }}">@csrf<flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ __('Log out') }}</flux:menu.item></form></flux:menu></flux:dropdown>
        </div>
    </header>
    <div x-data="navigationSearch(@js(app(\App\Modules\Platform\Support\ApplicationNavigation::class)->for($actor, app()->getLocale())))" x-on:open-navigation-search.window="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.meta.k.window.prevent="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.ctrl.k.window.prevent="open = true; $nextTick(() => $refs.query.focus())" x-on:keydown.escape.window="open = false">
        <div x-cloak x-show="open" class="navigation-search__backdrop" x-on:click="open = false"></div>
        <section x-cloak x-show="open" x-transition class="navigation-search" role="dialog" aria-modal="true" aria-label="{{ $isArabic ? 'بحث التنقل' : 'Navigation search' }}"><div class="navigation-search__input"><flux:icon.magnifying-glass class="size-5" /><input x-ref="query" x-model="query" type="search" placeholder="{{ $isArabic ? 'اكتب اسم الشاشة…' : 'Type a screen name…' }}"></div><div class="navigation-search__results"><template x-for="item in results" :key="item.url"><a :href="item.url" x-on:click="open = false"><span x-text="item.label"></span><small x-text="item.group"></small></a></template><p x-show="results.length === 0" class="p-5 text-center text-sm text-text-muted">{{ $isArabic ? 'لا توجد شاشة مطابقة ضمن صلاحياتك.' : 'No matching screen is available in your permissions.' }}</p></div></section>
    </div>
    @include('components.platform.dashboard-tools', ['pageGuide' => \App\Modules\Platform\Data\PageGuideContext::fromRequest($actor)])
    <flux:main class="app-main">{{ $slot }}</flux:main>
    <x-app-footer />
    @persist('toast')<flux:toast.group><flux:toast /></flux:toast.group>@endpersist
</div>
@fluxScripts
</body>
</html>
