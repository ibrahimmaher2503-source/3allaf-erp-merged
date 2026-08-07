<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
    class="overflow-x-hidden"
>
    <head>
        @include('partials.head')
    </head>
    <body class="app-layout min-h-screen overflow-x-hidden">
        <ui-sidebar
            class="lg:col-start-1 z-1 flex flex-col gap-4 [:where(&)]:w-64 p-4 max-lg:data-flux-sidebar-cloak:hidden data-flux-sidebar-on-mobile:data-flux-sidebar-collapsed-mobile:-translate-x-full data-flux-sidebar-on-mobile:data-flux-sidebar-collapsed-mobile:rtl:translate-x-full z-20! data-flux-sidebar-on-mobile:start-0! data-flux-sidebar-on-mobile:fixed! data-flux-sidebar-on-mobile:top-0! data-flux-sidebar-on-mobile:min-h-dvh! data-flux-sidebar-on-mobile:max-h-dvh! max-h-dvh overflow-y-auto overscroll-contain app-sidebar border-e transition-[transform,width,padding,box-shadow] duration-200 ease-out"
            collapsible="mobile"
            sticky
            x-data
            data-flux-sidebar-cloak
            data-flux-sidebar
        >
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <nav class="flex flex-col overflow-visible min-h-auto gap-5" data-flux-sidebar-nav>
                @can('dashboard_reports.view')
                    <flux:sidebar.group :heading="__('Dashboard')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    @can('company_settings.view')
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>
                            {{ __('System Settings') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('company_settings.edit')
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('initial-setup')" :current="request()->routeIs('initial-setup')" wire:navigate>
                            {{ __('Initial setup') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('pos_sales.view')
                        <flux:sidebar.item icon="shopping-cart" :href="route('pos')" :current="request()->routeIs('pos')" wire:navigate>{{ __('POS') }}</flux:sidebar.item>
                    @endcan
                    @can('branches_stores.view')
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.branches')" :current="request()->routeIs('admin.branches')" wire:navigate>
                            {{ __('Branches') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-storefront" :href="route('admin.stores')" :current="request()->routeIs('admin.stores')" wire:navigate>
                            {{ __('Stores & Mapping') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('drawers_payments_tax_numbering_printers.view')
                        <flux:sidebar.item icon="inbox-stack" :href="route('admin.cash-drawers')" :current="request()->routeIs('admin.cash-drawers')" wire:navigate>{{ __('Cash Drawers') }}</flux:sidebar.item>
                    @endcan
                    @can('users_roles_permissions.view')
                        <flux:sidebar.item icon="shield-check" :href="route('admin.authorization-baseline')" :current="request()->routeIs('admin.authorization-baseline')" wire:navigate>
                            {{ __('Authorization Baseline') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('audit_logs.view')
                        <flux:sidebar.item icon="clock" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>
                            {{ __('Audit Logs') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="server" :href="route('system.health')" :current="request()->routeIs('system.health')" wire:navigate>
                            {{ __('System Health') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('dashboard_reports.view')
                        <flux:sidebar.item icon="device-phone-mobile" :href="route('system.app')" :current="request()->routeIs('system.app')" wire:navigate>{{ __('System App Shell') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="paint-brush" :href="route('system.ui-showcase')" :current="request()->routeIs('system.ui-showcase')" wire:navigate>{{ __('UI Pattern Showcase') }}</flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @can('products_categories_brands.view')
                    <flux:sidebar.group :heading="__('Catalog')" class="grid">
                        <flux:sidebar.item icon="cube" :href="route('catalog.products')" :current="request()->routeIs('catalog.products')" wire:navigate>
                            {{ __('Products') }}
                        </flux:sidebar.item>
                        @can('products_categories_brands.create')
                            <flux:sidebar.item icon="arrow-up-tray" :href="route('catalog.products.import')" :current="request()->routeIs('catalog.products.import')" wire:navigate>
                                {{ __('Product Import') }}
                            </flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="squares-2x2" :href="route('catalog.categories')" :current="request()->routeIs('catalog.categories')" wire:navigate>
                            {{ __('Categories') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('catalog.brands')" :current="request()->routeIs('catalog.brands')" wire:navigate>
                            {{ __('Brands') }}
                        </flux:sidebar.item>
                    @endcan
                @canany(['purchase_orders.view', 'suppliers.view'])
                    <flux:sidebar.group :heading="__('Purchasing')" class="grid">
                        @can('purchase_orders.view')
                            <flux:sidebar.item icon="document-text" :href="route('purchasing.orders')" :current="request()->routeIs('purchasing.orders*')" wire:navigate>
                                {{ __('Purchase Orders') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('suppliers.view')
                            <flux:sidebar.item icon="truck" :href="route('catalog.suppliers')" :current="request()->routeIs('catalog.suppliers*') || request()->routeIs('suppliers.*')" wire:navigate>
                                {{ __('Suppliers') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
                @can('pricing_labels.view')
                    <flux:sidebar.group :heading="__('Pricing')" class="grid">
                        <flux:sidebar.item icon="banknotes" :href="route('pricing.index')" :current="request()->routeIs('pricing.index')" wire:navigate>
                            {{ __('Pricing Workspace') }}
                        </flux:sidebar.item>
                        @can('pricing_labels.approve')
                            <flux:sidebar.item icon="check-badge" :href="route('pricing.approvals')" :current="request()->routeIs('pricing.approvals')" wire:navigate>
                                {{ __('Price Approvals') }}
                            </flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="printer" :href="route('pricing.labels')" :current="request()->routeIs('pricing.labels')" wire:navigate>
                            {{ __('Label Queue Readiness') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
                @can('inventory_stock_card.view')
                    <flux:sidebar.group :heading="__('Inventory')" class="grid">
                        <flux:sidebar.item icon="archive-box" :href="route('inventory.index')" :current="request()->routeIs('inventory.*')" wire:navigate>
                            {{ __('Inventory Control Center') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </nav>

            <flux:spacer />

            <div class="px-4 py-3 hidden lg:flex items-center justify-between border-t border-border text-xs">
                <div x-data="{ online: navigator.onLine }"
                     x-on:online.window="online = true"
                     x-on:offline.window="online = false"
                     class="flex items-center gap-1.5 font-medium">
                    <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-600 dark:text-zinc-400"></span>
                </div>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </ui-sidebar>

        <div class="app-layout__content min-w-0">
        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <div x-data="{ online: navigator.onLine }"
                 x-on:online.window="online = true"
                 x-on:offline.window="online = false"
                 class="me-2 flex items-center gap-1 text-xs font-medium">
                <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-600 dark:text-zinc-400"></span>
            </div>

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('locale.switch') }}" class="w-full">
                        @csrf
                        @if (app()->getLocale() === 'ar')
                            <input type="hidden" name="locale" value="en" />
                            <flux:menu.item as="button" type="submit" icon="language" class="w-full cursor-pointer">
                                {{ __('Switch to English') }}
                            </flux:menu.item>
                        @else
                            <input type="hidden" name="locale" value="ar" />
                            <flux:menu.item as="button" type="submit" icon="language" class="w-full cursor-pointer">
                                {{ __('Switch to Arabic') }}
                            </flux:menu.item>
                        @endif
                    </form>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @include('components.platform.dashboard-tools', ['pageGuide' => \App\Modules\Platform\Data\PageGuideContext::fromRequest(auth()->user())])

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist
        </div>

        @fluxScripts
    </body>
</html>
