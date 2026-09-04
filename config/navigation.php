<?php

return [
    ['key' => 'home', 'icon' => 'home', 'label' => ['ar' => 'الرئيسية', 'en' => 'Home'], 'items' => [
        ['route' => 'dashboard', 'permission' => 'dashboard_reports.view', 'icon' => 'squares-2x2', 'label' => ['ar' => 'لوحة القيادة', 'en' => 'Dashboard']],
        ['route' => 'alerts.index', 'permission' => 'dashboard_reports.view', 'icon' => 'bell-alert', 'label' => ['ar' => 'التنبيهات والمهام', 'en' => 'Alerts & tasks']],
    ]],
    ['key' => 'sales', 'icon' => 'shopping-cart', 'label' => ['ar' => 'المبيعات ونقطة البيع', 'en' => 'Sales & POS'], 'items' => [
        ['route' => 'sales.index', 'permission' => 'pos_sales.view', 'icon' => 'presentation-chart-line', 'label' => ['ar' => 'نظرة عامة على المبيعات', 'en' => 'Sales overview']],
        ['route' => 'pos', 'permission' => 'pos_sales.view', 'icon' => 'shopping-cart', 'label' => ['ar' => 'نقطة البيع', 'en' => 'Point of sale']],
        ['route' => 'sales.invoices', 'permission' => 'pos_sales.view', 'icon' => 'document-text', 'label' => ['ar' => 'فواتير المبيعات', 'en' => 'Sales invoices']],
        ['route' => 'customers.index', 'permission' => 'customers.view', 'icon' => 'user-group', 'label' => ['ar' => 'العملاء', 'en' => 'Customers']],
        ['route' => 'customers.groups.index', 'permission' => 'customers.view', 'icon' => 'rectangle-stack', 'label' => ['ar' => 'مجموعات العملاء', 'en' => 'Customer groups']],
        ['route' => 'pos.shift', 'permission' => 'shifts_cash_movements.view', 'icon' => 'banknotes', 'label' => ['ar' => 'الورديات والتحصيل', 'en' => 'Shifts & collections']],
    ]],
    ['key' => 'purchasing', 'icon' => 'truck', 'label' => ['ar' => 'المشتريات والموردون', 'en' => 'Purchasing & suppliers'], 'items' => [
        ['route' => 'purchasing.orders', 'permission' => 'purchase_orders.view', 'icon' => 'chart-bar', 'label' => ['ar' => 'نظرة عامة وأوامر الشراء', 'en' => 'Overview & purchase orders']],
        ['route' => 'purchasing.invoices', 'permission' => 'purchase_invoices_supplier_returns.view', 'icon' => 'receipt-percent', 'label' => ['ar' => 'فواتير المشتريات والاستلام', 'en' => 'Invoices & receiving']],
        ['route' => 'purchasing.returns', 'permission' => 'purchase_returns.view', 'icon' => 'arrow-path', 'label' => ['ar' => 'مرتجعات الموردين', 'en' => 'Supplier returns']],
        ['route' => 'catalog.suppliers', 'permission' => 'suppliers.view', 'icon' => 'building-storefront', 'label' => ['ar' => 'الموردون', 'en' => 'Suppliers']],
    ]],
    ['key' => 'inventory', 'icon' => 'archive-box', 'label' => ['ar' => 'المنتجات والمخزون', 'en' => 'Products & inventory'], 'items' => [
        ['route' => 'inventory.index', 'permission' => 'inventory_stock_card.view', 'icon' => 'chart-bar', 'label' => ['ar' => 'نظرة عامة على المخزون', 'en' => 'Inventory overview']],
        ['route' => 'catalog.products', 'permission' => 'products_categories_brands.view', 'icon' => 'cube', 'label' => ['ar' => 'المنتجات', 'en' => 'Products']],
        ['route' => 'catalog.categories', 'permission' => 'products_categories_brands.view', 'icon' => 'squares-2x2', 'label' => ['ar' => 'التصنيفات والخيارات', 'en' => 'Categories & options']],
        ['route' => 'inventory.balances', 'permission' => 'inventory_stock_card.view', 'icon' => 'archive-box', 'label' => ['ar' => 'الأرصدة وحركة المخزون', 'en' => 'Balances & movements']],
        ['route' => 'inventory.transfers', 'permission' => 'transfers.view', 'icon' => 'arrows-right-left', 'label' => ['ar' => 'التحويلات', 'en' => 'Transfers']],
        ['route' => 'inventory.counts', 'permission' => 'inventory_stock_card.view', 'icon' => 'clipboard-document-list', 'label' => ['ar' => 'الجرد والتسويات', 'en' => 'Counts & adjustments']],
        ['route' => 'pricing.labels', 'permission' => 'pricing_labels.view', 'icon' => 'qr-code', 'label' => ['ar' => 'الباركود', 'en' => 'Barcodes']],
    ]],
    ['key' => 'pricing', 'icon' => 'tag', 'label' => ['ar' => 'التسعير', 'en' => 'Pricing'], 'items' => [
        ['route' => 'pricing.lists', 'permission' => 'pricing_lists.view', 'icon' => 'banknotes', 'label' => ['ar' => 'قوائم الأسعار', 'en' => 'Pricing Lists']],
        ['route' => 'pricing.index', 'permission' => 'pricing_labels.view', 'icon' => 'scale', 'label' => ['ar' => 'اعتمادات الأسعار', 'en' => 'Price approvals']],
    ]],
    ['key' => 'parties', 'enabled' => false, 'icon' => 'cake', 'label' => ['ar' => 'الحفلات والحجوزات', 'en' => 'Parties & bookings'], 'items' => [
        ['route' => 'parties.bookings.index', 'permission' => 'party_bookings_invoices.view', 'icon' => 'chart-bar-square', 'label' => ['ar' => 'نظرة عامة والحجوزات', 'en' => 'Overview & bookings']],
        ['route' => 'parties.calendar', 'permission' => 'rental_assets.view', 'icon' => 'calendar-days', 'label' => ['ar' => 'التقويم والأجندة', 'en' => 'Calendar & agenda']],
        ['route' => 'parties.invoices.index', 'permission' => 'party_bookings_invoices.view', 'icon' => 'document-text', 'label' => ['ar' => 'فواتير ومدفوعات الحفلات', 'en' => 'Party invoices & payments']],
        ['route' => 'parties.orders.index', 'permission' => 'party_operating_orders_consumables.view', 'icon' => 'clipboard-document-list', 'label' => ['ar' => 'أوامر التشغيل', 'en' => 'Operating orders']],
    ]],
    ['key' => 'assets', 'icon' => 'building-office-2', 'label' => ['ar' => 'الأصول والتأجير', 'en' => 'Assets & rental'], 'items' => [
        ['route' => 'party.assets.index', 'parameters' => ['mode' => 'dashboard'], 'permission' => 'rental_assets.view', 'icon' => 'chart-bar-square', 'label' => ['ar' => 'نظرة عامة على الأصول', 'en' => 'Assets overview']],
        ['route' => 'party.assets.index', 'parameters' => ['mode' => 'catalog'], 'permission' => 'rental_assets.view', 'icon' => 'archive-box', 'label' => ['ar' => 'سجل الأصول', 'en' => 'Asset catalog']],
        ['route' => 'party.assets.index', 'parameters' => ['mode' => 'calendar'], 'permission' => 'rental_assets.view', 'icon' => 'calendar-days', 'label' => ['ar' => 'الإتاحة والحجوزات', 'en' => 'Availability & reservations']],
        ['route' => 'party.assets.index', 'parameters' => ['mode' => 'returns'], 'permission' => 'rental_assets.view', 'icon' => 'arrow-uturn-left', 'label' => ['ar' => 'الإرجاع والحالة', 'en' => 'Returns & condition']],
        ['route' => 'party.assets.index', 'parameters' => ['mode' => 'history'], 'permission' => 'rental_assets.view', 'icon' => 'clock', 'label' => ['ar' => 'الصيانة وسجل الأحداث', 'en' => 'Maintenance & history']],
    ]],
    ['key' => 'reports', 'icon' => 'chart-bar', 'label' => ['ar' => 'التقارير', 'en' => 'Reports'], 'items' => [
        ['route' => 'reports.index', 'permission' => 'dashboard_reports.view', 'icon' => 'squares-2x2', 'label' => ['ar' => 'مركز التقارير', 'en' => 'Reports center']],
        ['route' => 'reports.sales', 'permission' => 'pos_sales.view', 'icon' => 'shopping-cart', 'label' => ['ar' => 'المبيعات', 'en' => 'Sales']],
        ['route' => 'reports.purchasing', 'permission' => 'purchase_orders.view', 'icon' => 'truck', 'label' => ['ar' => 'المشتريات', 'en' => 'Purchasing']],
        ['route' => 'reports.inventory', 'permission' => 'inventory_stock_card.view', 'icon' => 'archive-box', 'label' => ['ar' => 'المخزون', 'en' => 'Inventory']],
        ['route' => 'reports.customers', 'permission' => 'customers.view', 'icon' => 'user-group', 'label' => ['ar' => 'العملاء والموردون', 'en' => 'Customers & suppliers']],
        ['route' => 'reports.cash', 'permission' => 'shifts_cash_movements.view', 'icon' => 'banknotes', 'label' => ['ar' => 'النقدية والورديات', 'en' => 'Cash & shifts']],
        ['route' => 'reports.parties', 'permission' => 'party_bookings_invoices.view', 'icon' => 'calendar-days', 'label' => ['ar' => 'الحفلات والحجوزات', 'en' => 'Parties & bookings']],
        ['route' => 'reports.assets', 'permission' => 'rental_assets.view', 'icon' => 'archive-box', 'label' => ['ar' => 'الأصول والتأجير', 'en' => 'Assets & rental']],
    ]],
    ['key' => 'administration', 'icon' => 'cog-6-tooth', 'label' => ['ar' => 'الإدارة والإعدادات', 'en' => 'Administration & settings'], 'items' => [
        ['route' => 'admin.overview', 'permission' => 'company_settings.view', 'icon' => 'squares-2x2', 'label' => ['ar' => 'مركز الإدارة', 'en' => 'Administration center']],
        ['route' => 'initial-setup', 'permission' => 'company_settings.edit', 'icon' => 'circle-stack', 'label' => ['ar' => 'البيانات الأساسية', 'en' => 'Basic Data']],
        ['route' => 'admin.audit', 'permission' => 'audit_logs.view', 'icon' => 'clock', 'label' => ['ar' => 'سجل النشاط', 'en' => 'Activity log']],
        ['route' => 'admin.approvals', 'permission' => 'company_settings.view', 'icon' => 'check-badge', 'label' => ['ar' => 'صندوق الاعتمادات', 'en' => 'Approval inbox']],
    ]],
];
