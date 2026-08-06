<?php

use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('purchasing/orders', 'purchasing::orders')
        ->middleware('can:purchase_orders.view')
        ->name('purchasing.orders');

    $router->get('purchasing/invoices/settings', function () {
        Gate::authorize('company_settings.view');

        $versions = FinancialSettingVersion::query()
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('key')
            ->map(static fn ($items) => $items->first());

        return view('purchasing.invoice-settings', [
            'versions' => $versions,
        ]);
    })->middleware('can:company_settings.view')->name('purchasing.invoices.settings');
    $router->get('purchasing/invoices/readiness', function () {
        Gate::authorize('purchase_orders.view');

        return view('purchasing.invoice-readiness', [
            'decisionGroups' => [
                ['key' => 'cost', 'title' => ['ar' => 'سياسة التكلفة', 'en' => 'Cost policy'], 'items' => 10, 'reference' => 'OI-COST-01 … OI-COST-10'],
                ['key' => 'tax', 'title' => ['ar' => 'الضريبة', 'en' => 'Tax'], 'items' => 5, 'reference' => 'OI-TAX-01 … OI-TAX-05'],
                ['key' => 'discount', 'title' => ['ar' => 'الخصم', 'en' => 'Discount'], 'items' => 7, 'reference' => 'OI-DISC-01 … OI-DISC-07'],
                ['key' => 'import', 'title' => ['ar' => 'استيراد الفواتير', 'en' => 'Invoice import'], 'items' => 10, 'reference' => 'OI-IMP-01 … OI-IMP-10'],
                ['key' => 'receiving', 'title' => ['ar' => 'الاستلام والمطابقة', 'en' => 'Receiving and matching'], 'items' => 15, 'reference' => 'OI-RCV-01 … OI-PRT-05'],
                ['key' => 'opening-stock', 'title' => ['ar' => 'المخزون الافتتاحي', 'en' => 'Opening stock'], 'items' => 9, 'reference' => 'OI-OPEN-01 … OI-OPEN-09'],
                ['key' => 'master-data', 'title' => ['ar' => 'الفروع والمتاجر والبيانات الفعلية', 'en' => 'Branches, stores, and real master data'], 'items' => 6, 'reference' => 'OI-MD-01 … OI-MD-06'],
                ['key' => 'authorization', 'title' => ['ar' => 'الصلاحيات والحدود', 'en' => 'Authorization and limits'], 'items' => 19, 'reference' => 'OI-PERM-01 … OI-LIMIT-05'],
            ],
            'blockers' => [
                ['key' => 'BLK-006', 'title' => ['ar' => 'بيانات الفروع والمتاجر الفعلية', 'en' => 'Real branch and store data'], 'detail' => ['ar' => 'القائمة الإنتاجية وسياسة التفعيل والتخصيص لم تعتمد بعد.', 'en' => 'The production list and activation/scope policy are not approved yet.']],
                ['key' => 'BLK-008', 'title' => ['ar' => 'السياسات التجارية والمالية', 'en' => 'Commercial and financial policies'], 'detail' => ['ar' => 'الضرائب والخصومات والترقيم والطباعة ما زالت تنتظر قرار المالك.', 'en' => 'Tax, discount, numbering, and print policies still require owner decisions.']],
                ['key' => 'BLK-010', 'title' => ['ar' => 'بيانات الموردين وشروطهم', 'en' => 'Supplier data and terms'], 'detail' => ['ar' => 'البيانات التجارية الفعلية للموردين لم تصل بعد.', 'en' => 'Actual supplier and commercial data has not been supplied yet.']],
                ['key' => 'BLK-012', 'title' => ['ar' => 'الافتراضات الهندسية المحلية', 'en' => 'Local engineering defaults'], 'detail' => ['ar' => 'الافتراضات الحالية لا تمثل اعتمادًا إنتاجيًا.', 'en' => 'Current defaults are not production approval.']],
            ],
        ]);
    })->middleware('can:purchase_orders.view')->name('purchasing.invoices.readiness');

    $router->get('purchasing/orders/{order}/print', function (PurchaseOrder $order) {
        Gate::authorize('purchase_orders.print');

        return view('purchasing.print', [
            'order' => $order->load(['supplier', 'store', 'creator', 'lines.product']),
        ]);
    })->whereNumber('order')->middleware('can:purchase_orders.print')->name('purchasing.orders.print');
});
