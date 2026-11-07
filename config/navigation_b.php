<?php

declare(strict_types=1);

$current = require __DIR__.'/navigation.php';

$item = static function (string $route, string $ar, string $en, array $parameters = []) use ($current): array {
    foreach ($current as $group) {
        foreach ($group['items'] as $candidate) {
            if (($candidate['route'] ?? null) === $route && ($candidate['parameters'] ?? []) === $parameters) {
                return array_replace($candidate, ['label' => ['ar' => $ar, 'en' => $en]]);
            }
        }
    }

    throw new LogicException("Missing canonical navigation entry: {$route}");
};

$heading = static fn (string $ar, string $en): array => ['type' => 'heading', 'label' => ['ar' => $ar, 'en' => $en]];
$group = static fn (string $key, string $icon, string $ar, string $en, array $items): array => [
    'key' => $key, 'icon' => $icon, 'label' => ['ar' => $ar, 'en' => $en], 'items' => $items,
];
$additional = static fn (string $route, string $permission, string $icon, string $ar, string $en, array $parameters = [], array $permissionsAll = []): array => [
    'route' => $route, 'permission' => $permission, 'permissions_all' => $permissionsAll,
    'icon' => $icon, 'label' => ['ar' => $ar, 'en' => $en], 'parameters' => $parameters,
];

return [
    $group('home', 'home', 'الرئيسية', 'Home', [
        $item('dashboard', 'لوحة القيادة', 'Dashboard'),
        $item('alerts.index', 'التنبيهات والمهام', 'Alerts & tasks'),
        $item('admin.approvals', 'صندوق الاعتمادات', 'Approval inbox'),
    ]),
    $group('products', 'cube', 'المنتجات', 'Products', [
        $item('catalog.products', 'بطاقات المنتجات', 'Product cards'),
        $item('catalog.categories', 'التصنيفات', 'Categories'),
        $item('catalog.brands', 'العلامات التجارية', 'Brands'),
        $item('catalog.product-options', 'مرشحات المنتجات', 'Product filters'),
        $item('pricing.labels', 'الباركود', 'Barcodes'),
        $heading('التسعير', 'Pricing'),
        $additional('pricing.index', 'pricing_labels.view', 'scale', 'نظرة عامة', 'Overview'),
        $item('pricing.lists', 'قوائم الأسعار', 'Price lists'),
        $additional('admin.audit', 'access-activity-log', 'clock', 'سجل تغييرات المنتجات', 'Product change log', ['mode' => 'product']),
    ]),
    $group('inventory', 'archive-box', 'المخزون', 'Inventory', [
        $item('inventory.index', 'نظرة عامة عن المخزون', 'Inventory overview'),
        $item('inventory.transfers', 'التحويلات', 'Transfers'),
        $additional('inventory.transfers.receiving-inbox', 'transfers.receive', 'inbox-arrow-down', 'التحويلات الواردة', 'Incoming transfers'),
        $item('inventory.adjustments.create', 'إذن إضافة', 'Stock addition', ['type' => 'entry']),
        $item('inventory.adjustments.create', 'إذن خصم', 'Stock deduction', ['type' => 'exit']),
        $additional('inventory.counts.create', 'stock_counts.create', 'clipboard-document-check', 'تحضير الجرد', 'Stocktake preparation'),
        $item('inventory.counts', 'الجرد والتسويات', 'Counts & adjustments'),
    ]),
    $group('purchasing', 'truck', 'المشتريات والموردون', 'Purchasing & suppliers', [
        $item('purchasing.orders', 'نظرة عامة عن المشتريات', 'Purchasing overview'),
        $item('purchasing.orders.create', 'إضافة أمر شراء', 'New purchase order'),
        $item('purchasing.invoices', 'فواتير المشتريات', 'Purchase invoices'),
        $item('purchasing.returns', 'مرتجعات الموردين', 'Supplier returns'),
        $item('catalog.suppliers', 'بيانات الموردين', 'Suppliers', ['section' => 'supplier-masters']),
        $item('catalog.suppliers', 'مجموعات الموردين', 'Supplier groups', ['section' => 'supplier-groups']),
    ]),
    $group('sales', 'shopping-cart', 'المبيعات ونقطة البيع', 'Sales & POS', [
        $item('sales.index', 'نظرة عامة عن المبيعات', 'Sales overview'),
        $item('pos.shift', 'الورديات والتحصيل', 'Shifts & collections'),
        $item('pos', 'نقطة البيع', 'Point of sale'),
        $item('gift.receipts.index', 'إيصالات الهدايا', 'Gift receipts'),
        $item('pos.suspended', 'المبيعات المعلقة', 'Suspended sales'),
        $item('returns.index', 'مرتجعات واستبدال العملاء', 'Customer returns & exchanges'),
        $item('sales.invoices', 'فواتير المبيعات', 'Sales invoices'),
        $item('quotations.index', 'عروض الأسعار', 'Quotations'),
        $item('gift.cards.index', 'بطاقات الهدايا', 'Gift cards'),
        $item('customers.index', 'بيانات العملاء', 'Customers'),
        $item('customers.groups.index', 'مجموعات العملاء', 'Customer groups'),
    ]),
    $group('reports', 'chart-bar', 'التقارير', 'Reports', [
        $heading('تقارير المخازن', 'Inventory reports'),
        [...$additional('inventory.balances', 'inventory_stock_card.view', 'archive-box', 'تقرير أرصدة المخزون', 'Inventory balance report'), 'active_routes' => ['inventory.balances']],
        [...$additional('inventory.movements', 'inventory_stock_card.view', 'arrows-right-left', 'تقرير حركة المخزون', 'Inventory movement report'), 'active_routes' => ['inventory.movements']],
        $additional('reports.phase-one.inventory', 'dashboard_reports.view', 'table-cells', 'تقرير المخزون الحالي', 'Current inventory report', [], ['inventory_stock_card.view', 'inventory_stock_card.cost_view']),
        $heading('تقارير المشتريات', 'Purchasing reports'),
        $additional('reports.purchasing', 'dashboard_reports.view', 'truck', 'لوحة تقارير المشتريات', 'Purchasing reports overview'),
        $additional('purchasing.history.suppliers', 'purchase_invoices_supplier_returns.view', 'clock', 'سجل معاملات الموردين', 'Supplier transaction history'),
        $heading('تقارير المبيعات', 'Sales reports'),
        $additional('reports.sales', 'dashboard_reports.view', 'chart-bar', 'لوحة تقارير المبيعات', 'Sales reports overview'),
        $additional('reports.phase-one.sales', 'dashboard_reports.view', 'table-cells', 'تقرير المبيعات حسب الأصناف', 'Product sales report', [], ['pos_sales.view', 'inventory_stock_card.cost_view']),
        $additional('reports.phase-one.daily', 'dashboard_reports.view', 'calendar-days', 'ملخص المبيعات اليومية حسب الفرع', 'Daily sales by branch', [], ['pos_sales.view', 'pos_sales.payment_view']),
        $heading('تقارير العملاء', 'Customer reports'),
        $additional('reports.customers', 'dashboard_reports.view', 'users', 'لوحة تقارير العملاء', 'Customer reports overview'),
    ]),
];
