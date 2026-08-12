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
            class="lg:col-start-1 lg:sticky lg:top-0 z-1 flex h-dvh min-h-dvh max-h-dvh flex-col gap-4 [:where(&)]:w-64 overflow-hidden p-4 max-lg:data-flux-sidebar-cloak:hidden data-flux-sidebar-on-mobile:data-flux-sidebar-collapsed-mobile:-translate-x-full data-flux-sidebar-on-mobile:data-flux-sidebar-collapsed-mobile:rtl:translate-x-full z-20! data-flux-sidebar-on-mobile:start-0! data-flux-sidebar-on-mobile:fixed! data-flux-sidebar-on-mobile:top-0! data-flux-sidebar-on-mobile:min-h-dvh! data-flux-sidebar-on-mobile:max-h-dvh! app-sidebar border-e transition-[transform,width,padding,box-shadow] duration-200 ease-out"
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

            @php
                $salesActive = request()->routeIs('pos*')
                    || request()->routeIs('pos.shift*')
                    || request()->routeIs('returns.*')
                    || request()->routeIs('gift.*')
                    || request()->routeIs('sales.*')
                    || request()->routeIs('quotations.readiness');
                $customersActive = request()->routeIs('customers.*')
                    || request()->routeIs('wallets.product')
                    || request()->routeIs('sales.*');
                $catalogActive = request()->routeIs('catalog.products*') || request()->routeIs('catalog.product-options') || request()->routeIs('catalog.categories') || request()->routeIs('catalog.brands');
                $suppliersActive = request()->routeIs('catalog.suppliers*') || request()->routeIs('suppliers.*') || request()->routeIs('purchasing.history.suppliers');
                $purchasingActive = request()->routeIs('purchasing.*') && ! request()->routeIs('purchasing.history.suppliers');
                $pricingActive = request()->routeIs('pricing.*');
                $inventoryActive = request()->routeIs('inventory.*');
                $partyActive = request()->routeIs('party.*') || request()->routeIs('parties.*') || request()->routeIs('wallets.party');
                $rentalAssetsActive = request()->routeIs('party.assets.*') || request()->routeIs('party.asset-events.*');
                $reportsActive = request()->routeIs('reports.*') || request()->routeIs('exports.*');
                $administrationActive = request()->routeIs('admin.settings*')
                    || request()->routeIs('admin.branches')
                    || request()->routeIs('admin.stores')
                    || request()->routeIs('admin.cash-drawers')
                    || request()->routeIs('initial-setup')
                    || request()->routeIs('purchasing.invoices.settings')
                    || request()->routeIs('purchasing.returns.settings');
                $controlActive = request()->routeIs('admin.audit')
                    || request()->routeIs('admin.approvals*')
                    || request()->routeIs('system.*')
                    || request()->routeIs('operations.readiness')
                    || request()->routeIs('pos.offline-readiness');
            @endphp

            <nav aria-label="{{ __('Workspace') }}" class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overscroll-contain pe-1" data-flux-sidebar-nav>
                @canany(['dashboard_reports.view'])
                    <flux:sidebar.group :heading="__('Workspace')" class="sidebar-nav-group sidebar-nav-workspace">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="bell-alert" :href="route('alerts.index')" :current="request()->routeIs('alerts.*')" wire:navigate>{{ __('Operational alerts') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>{{ __('Reports') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @canany(['pos_sales.view', 'pos_sales.payment_view', 'pos_sales.payment_evidence_view', 'shifts_cash_movements.view', 'returns_exchanges_gift_instruments.view', 'dashboard_reports.view'])
                    <flux:sidebar.group
                        :heading="__('Sales')"
                        icon="shopping-cart"
                        expandable
                        :expanded="$salesActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @can('pos_sales.view')
                            <flux:sidebar.item icon="shopping-cart" class="pos-nav-item" :href="route('pos')" :current="request()->routeIs('pos')" wire:navigate>{{ __('POS') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="shopping-bag" :href="route('sales.index')" :current="request()->routeIs('sales.index', 'sales.show')" wire:navigate>{{ __('Sales') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="document-text" :href="route('sales.invoices')" :current="request()->routeIs('sales.invoices')" wire:navigate>{{ __('Sales Invoices') }}</flux:sidebar.item>
                            @can('suspended_sales.view')<flux:sidebar.item icon="pause" :href="route('pos.suspended')" :current="request()->routeIs('pos.suspended*')" wire:navigate>{{ __('Suspended Sales') }}</flux:sidebar.item>@endcan
                        @endcan
                        @can('pos_sales.payment_view')
                            <flux:sidebar.item icon="banknotes" :href="route('payments.index')" :current="request()->routeIs('payments.index')" wire:navigate>{{ __('Payments') }}</flux:sidebar.item>
                        @endcan
                        @can('pos_sales.payment_evidence_view')
                            <flux:sidebar.item icon="paper-clip" :href="route('payments.evidence')" :current="request()->routeIs('payments.evidence*')" wire:navigate>{{ __('Payment Evidence') }}</flux:sidebar.item>
                        @endcan
                        @can('shifts_cash_movements.view')
                            <flux:sidebar.item icon="lock-closed" :href="route('pos.shift')" :current="request()->routeIs('pos.shift*')" wire:navigate>{{ __('Shifts & cash movements') }}</flux:sidebar.item>
                        @endcan
                        @can('returns_exchanges_gift_instruments.view')
                            <flux:sidebar.item icon="arrow-path" :href="route('returns.index')" :current="request()->routeIs('returns.*')" wire:navigate>{{ __('Returns & exchanges') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="gift" :href="route('gift.cards.index')" :current="request()->routeIs('gift.cards.*')" wire:navigate>{{ __('Gift Cards') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="receipt-percent" :href="route('gift.receipts.index')" :current="request()->routeIs('gift.receipts.*')" wire:navigate>{{ __('Gift Receipts') }}</flux:sidebar.item>
                        @endcan
                        @can('dashboard_reports.view')
                            <flux:sidebar.item icon="document-text" :href="route('quotations.index')" :current="request()->routeIs('quotations.*')" wire:navigate>{{ __('Quotations') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan

                @canany(['pos_sales.view', 'customers.view', 'loyalty.view', 'product_wallet.view'])
                    <flux:sidebar.group
                        :heading="__('Customers')"
                        icon="user-group"
                        expandable
                        :expanded="$customersActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @can('customers.view')
                            <flux:sidebar.item icon="user-group" :href="route('customers.index')" :current="request()->routeIs('customers.create', 'customers.show') || (request()->routeIs('customers.index') && request('mode', 'master') === 'master')" wire:navigate>{{ __('Customer master, privacy & history') }}</flux:sidebar.item>
                        @endcan
                        @can('loyalty.view')
                            <flux:sidebar.item icon="star" :href="route('customers.index', ['mode' => 'loyalty'])" :current="request()->routeIs('customers.loyalty*') || (request()->routeIs('customers.index') && request('mode') === 'loyalty')" wire:navigate>{{ __('Loyalty & points') }}</flux:sidebar.item>
                        @endcan
                        @can('customers.view')
                            <flux:sidebar.item icon="clock" :href="route('customers.index', ['mode' => 'history'])" :current="request()->routeIs('customers.index') && request('mode') === 'history'" wire:navigate>{{ __('Transaction history') }}</flux:sidebar.item>
                        @endcan
                        @can('product_wallet.view')
                            <flux:sidebar.item icon="wallet" :href="route('wallets.product')" :current="request()->routeIs('wallets.product')" wire:navigate>{{ __('Product Wallet') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @can('products_categories_brands.view')
                    <flux:sidebar.group
                        :heading="__('Catalog')"
                        icon="cube"
                        expandable
                        :expanded="$catalogActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        <flux:sidebar.item icon="cube" :href="route('catalog.products')" :current="request()->routeIs('catalog.products')" wire:navigate>
                            {{ __('Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="swatch" :href="route('catalog.product-options')" :current="request()->routeIs('catalog.product-options')" wire:navigate>
                            {{ __('Product Options') }}
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
                    </flux:sidebar.group>
                @endcan

                @canany(['suppliers.view', 'purchase_invoices_supplier_returns.view', 'purchase_returns.view'])
                    <flux:sidebar.group
                        :heading="__('Suppliers')"
                        icon="truck"
                        expandable
                        :expanded="$suppliersActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @can('suppliers.view')
                            <flux:sidebar.item icon="truck" :href="route('catalog.suppliers')" :current="request()->routeIs('catalog.suppliers*') || request()->routeIs('suppliers.*')" wire:navigate>
                                {{ __('Suppliers') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('purchase_invoices_supplier_returns.view')
                            <flux:sidebar.item icon="receipt-percent" :href="route('purchasing.history.suppliers')" :current="request()->routeIs('purchasing.history.suppliers')" wire:navigate>{{ __('Supplier invoices & cost history') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['purchase_orders.view', 'purchase_invoices_supplier_returns.view', 'purchase_returns.view'])
                    <flux:sidebar.group
                        :heading="__('Purchasing')"
                        icon="truck"
                        expandable
                        :expanded="$purchasingActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @can('purchase_orders.view')
                            <flux:sidebar.item icon="document-text" :href="route('purchasing.orders')" :current="request()->routeIs('purchasing.orders*')" wire:navigate>
                                {{ __('Purchase Orders') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('purchase_invoices_supplier_returns.view')
                            <flux:sidebar.item icon="receipt-percent" :href="route('purchasing.invoices')" :current="request()->routeIs('purchasing.invoices')" wire:navigate>{{ __('Purchase invoices') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="arrow-up-tray" :href="route('purchasing.invoices.import')" :current="request()->routeIs('purchasing.invoices.import')" wire:navigate>{{ __('Invoice import') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="clipboard-document-check" :href="route('purchasing.invoices.readiness')" :current="request()->routeIs('purchasing.invoices.readiness')" wire:navigate>{{ __('Purchase receiving & matching') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="chart-bar" :href="route('purchasing.history.costs')" :current="request()->routeIs('purchasing.history.costs')" wire:navigate>{{ __('Purchase cost history') }}</flux:sidebar.item>
                        @endcan
                        @can('purchase_returns.view')
                            <flux:sidebar.item icon="arrow-path" :href="route('purchasing.returns')" :current="request()->routeIs('purchasing.returns')" wire:navigate>{{ __('Supplier returns') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @can('pricing_labels.view')
                    <flux:sidebar.group
                        :heading="__('Pricing')"
                        icon="banknotes"
                        expandable
                        :expanded="$pricingActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        <flux:sidebar.item icon="banknotes" :href="route('pricing.index')" :current="request()->routeIs('pricing.index')" wire:navigate>
                            {{ __('Pricing Workspace') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="printer" :href="route('pricing.labels')" :current="request()->routeIs('pricing.labels')" wire:navigate>
                            {{ __('Barcode & label printing') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('pricing.focus', ['mode' => 'versions'])" :current="request()->routeIs('pricing.focus') && request()->route('mode') === 'versions'" wire:navigate>{{ __('Price lists & versions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="exclamation-triangle" :href="route('pricing.focus', ['mode' => 'unpriced'])" :current="request()->routeIs('pricing.focus') && request()->route('mode') === 'unpriced'" wire:navigate>{{ __('Unpriced products') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('pricing.focus', ['mode' => 'history'])" :current="request()->routeIs('pricing.focus') && request()->route('mode') === 'history'" wire:navigate>{{ __('Price change history') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @canany(['inventory_stock_card.view', 'transfers.view'])
                    <flux:sidebar.group
                        :heading="__('Inventory')"
                        icon="archive-box"
                        expandable
                        :expanded="$inventoryActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        <flux:sidebar.item icon="archive-box" :href="route('inventory.index')" :current="request()->routeIs('inventory.index')" wire:navigate>{{ __('Inventory Control Center') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="scale" :href="route('inventory.balances')" :current="request()->routeIs('inventory.balances')" wire:navigate>{{ __('Balances') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" :href="route('inventory.movements')" :current="request()->routeIs('inventory.movements')" wire:navigate>{{ __('Stock movements') }}</flux:sidebar.item>
                        @can('transfers.view')
                            <flux:sidebar.item icon="arrow-path" :href="route('inventory.transfers')" :current="request()->routeIs('inventory.transfers')" wire:navigate>{{ __('Transfers') }}</flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('inventory.adjustments')" :current="request()->routeIs('inventory.adjustments')" wire:navigate>{{ __('Stock in / out & adjustments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('inventory.counts')" :current="request()->routeIs('inventory.counts')" wire:navigate>{{ __('Stock counts') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @canany(['party_bookings_invoices.view', 'party_wallet.view'])
                    <flux:sidebar.group
                        :heading="__('Parties')"
                        icon="cake"
                        expandable
                        :expanded="$partyActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @can('party_bookings_invoices.view')
                            <flux:sidebar.item icon="cake" :href="route('parties.bookings.index')" :current="request()->routeIs('parties.bookings.*')" wire:navigate>{{ __('Party bookings') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="document-text" :href="route('parties.invoices.index', ['mode' => 'working'])" :current="request()->routeIs('parties.invoices.index') && request('mode', 'working') === 'working'" wire:navigate>{{ __('Working invoice') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="banknotes" :href="route('parties.invoices.index', ['mode' => 'payments'])" :current="request()->routeIs('parties.invoices.payments*') || (request()->routeIs('parties.invoices.index') && request('mode') === 'payments')" wire:navigate>{{ __('Party payments') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('parties.orders.index')" :current="request()->routeIs('parties.orders.*')" wire:navigate>{{ __('Operating orders & consumables') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="check-circle" :href="route('parties.invoices.index', ['mode' => 'settlement'])" :current="request()->routeIs('parties.invoices.settle') || (request()->routeIs('parties.invoices.index') && request('mode') === 'settlement')" wire:navigate>{{ __('Final close & settlement') }}</flux:sidebar.item>
                        @endcan
                        @can('party_wallet.view')
                            <flux:sidebar.item icon="wallet" :href="route('wallets.party')" :current="request()->routeIs('wallets.party')" wire:navigate>{{ __('Party Wallet') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @can('rental_assets.view')
                    <flux:sidebar.group
                        :heading="__('Rental Assets')"
                        icon="cube"
                        expandable
                        :expanded="$rentalAssetsActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        <flux:sidebar.item icon="cube" :href="route('party.assets.index', ['mode' => 'workspace'])" :current="request()->routeIs('party.assets.index') && request('mode', 'workspace') === 'workspace'" wire:navigate>{{ __('Rental assets & calendar') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('party.assets.index', ['mode' => 'reservations'])" :current="request()->routeIs('party.assets.index') && request('mode') === 'reservations'" wire:navigate>{{ __('Asset reservations & checkout') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('party.assets.index', ['mode' => 'returns'])" :current="request()->routeIs('party.assets.index') && request('mode') === 'returns'" wire:navigate>{{ __('Return, condition & damages') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('party.assets.index', ['mode' => 'history'])" :current="request()->routeIs('party.assets.index') && request('mode') === 'history'" wire:navigate>{{ __('Depreciation & asset history') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @can('dashboard_reports.view')
                    <flux:sidebar.group
                        :heading="__('Reports')"
                        icon="chart-bar"
                        expandable
                        :expanded="$reportsActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.index')" wire:navigate>{{ __('Dashboard & KPI reports') }}</flux:sidebar.item>
                        @can('pos_sales.view')
                            <flux:sidebar.item icon="shopping-cart" :href="route('reports.sales')" :current="request()->routeIs('reports.sales')" wire:navigate>{{ __('Sales reports') }}</flux:sidebar.item>
                        @endcan
                        @can('customers.view')
                            <flux:sidebar.item icon="user-group" :href="route('reports.customers')" :current="request()->routeIs('reports.customers')" wire:navigate>{{ __('Customer & loyalty reports') }}</flux:sidebar.item>
                        @endcan
                        @can('shifts_cash_movements.view')
                            <flux:sidebar.item icon="banknotes" :href="route('reports.cash')" :current="request()->routeIs('reports.cash')" wire:navigate>{{ __('Cash & shift reports') }}</flux:sidebar.item>
                        @endcan
                        @can('purchase_orders.view')
                            <flux:sidebar.item icon="truck" :href="route('reports.purchasing')" :current="request()->routeIs('reports.purchasing')" wire:navigate>{{ __('Purchasing reports') }}</flux:sidebar.item>
                        @endcan
                        @can('inventory_stock_card.view')
                            <flux:sidebar.item icon="archive-box" :href="route('reports.inventory')" :current="request()->routeIs('reports.inventory')" wire:navigate>{{ __('Inventory reports') }}</flux:sidebar.item>
                        @endcan
                        @can('party_bookings_invoices.view')
                            <flux:sidebar.item icon="cake" :href="route('reports.parties')" :current="request()->routeIs('reports.parties')" wire:navigate>{{ __('Party reports') }}</flux:sidebar.item>
                        @endcan
                        @can('rental_assets.view')
                            <flux:sidebar.item icon="cube" :href="route('reports.assets')" :current="request()->routeIs('reports.assets')" wire:navigate>{{ __('Rental asset reports') }}</flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('exports.index')" :current="request()->routeIs('exports.*')" wire:navigate>{{ __('PDF / Excel export center') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @canany(['company_settings.view', 'branches_stores.view', 'drawers_payments_tax_numbering_printers.view', 'users_roles_permissions.view'])
                    <flux:sidebar.group
                        :heading="__('Administration')"
                        icon="cog-6-tooth"
                        expandable
                        :expanded="$administrationActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
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
                        @can('company_settings.view')
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.settings.customer-loyalty')" :current="request()->routeIs('admin.settings.customer-loyalty')" wire:navigate>{{ __('Work policies & customer settings') }}</flux:sidebar.item>
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
                                {{ __('Users, roles & permissions') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('company_settings.view')
                            <flux:sidebar.item icon="banknotes" :href="route('purchasing.invoices.settings')" :current="request()->routeIs('purchasing.invoices.settings')" wire:navigate>{{ __('Taxes & payment settings') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="arrow-path" :href="route('purchasing.returns.settings')" :current="request()->routeIs('purchasing.returns.settings')" wire:navigate>{{ __('Returns settings') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['audit_logs.view', 'dashboard_reports.view', 'pos_sales.view', 'pricing_labels.approve', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve', 'purchase_returns.approve', 'inventory_stock_card.approve', 'stock_counts.reconcile'])
                    <flux:sidebar.group
                        :heading="__('System & Control')"
                        icon="shield-check"
                        expandable
                        :expanded="$controlActive"
                        data-sidebar-expandable
                        class="sidebar-nav-group"
                    >
                        @canany(['audit_logs.view', 'pricing_labels.approve', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve', 'purchase_returns.approve', 'inventory_stock_card.approve', 'stock_counts.reconcile'])
                            <flux:sidebar.item icon="check-badge" :href="route('admin.approvals')" :current="request()->routeIs('admin.approvals*')" wire:navigate>{{ __('Approvals') }}</flux:sidebar.item>
                        @endcanany
                        @can('audit_logs.view')
                            <flux:sidebar.item icon="clock" :href="route('admin.audit')" :current="request()->routeIs('admin.audit') && request('mode', 'all') === 'all'" wire:navigate>{{ __('Audit logs') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="exclamation-triangle" :href="route('admin.audit', ['mode' => 'override'])" :current="request()->routeIs('admin.audit') && request('mode') === 'override'" wire:navigate>{{ __('Override log') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="printer" :href="route('admin.audit', ['mode' => 'print'])" :current="request()->routeIs('admin.audit') && request('mode') === 'print'" wire:navigate>{{ __('Print log') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="server" :href="route('system.health')" :current="request()->routeIs('system.health')" wire:navigate>{{ __('Service status & system health') }}</flux:sidebar.item>
                        @endcan
                        @can('pos_sales.view')
                            <flux:sidebar.item icon="signal-slash" :href="route('pos.offline-readiness')" :current="request()->routeIs('pos.offline-readiness')" wire:navigate>{{ __('Offline POS & sync') }}</flux:sidebar.item>
                        @endcan
                        @can('audit_logs.view')
                            <flux:sidebar.item icon="arrow-path" :href="route('operations.readiness')" :current="request()->routeIs('operations.readiness')" wire:navigate>{{ __('Failed operations & handover readiness') }}</flux:sidebar.item>
                        @endcan
                        @can('dashboard_reports.view')
                            <flux:sidebar.item icon="device-phone-mobile" :href="route('system.app')" :current="request()->routeIs('system.app')" wire:navigate>{{ __('System App Shell') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="paint-brush" :href="route('system.ui-showcase')" :current="request()->routeIs('system.ui-showcase')" wire:navigate>{{ __('UI Pattern Showcase') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany
            </nav>

            <div class="sidebar-status px-4 py-3 hidden lg:flex items-center justify-between border-t border-border text-xs">
                <div x-data="{ online: navigator.onLine }"
                     x-on:online.window="online = true"
                     x-on:offline.window="online = false"
                     class="flex items-center gap-1.5 font-medium">
                    <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span data-status-label x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-600 dark:text-zinc-400"></span>
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
