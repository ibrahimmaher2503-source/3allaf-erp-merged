<?php

declare(strict_types=1);

use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $router->livewire('purchasing/orders', 'purchasing::orders')
        ->middleware('can:purchase_orders.view')
        ->name('purchasing.orders');

    $router->livewire('purchasing/invoices', 'purchasing::invoices')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices');

    $router->livewire('purchasing/invoices/import', 'purchasing::invoice-import')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices.import');

    $router->get('purchasing/invoices/{invoice}/print', function (PurchaseInvoice $invoice) {
        Gate::authorize('purchase_invoices_supplier_returns.print');
        $invoice->load(['supplier', 'store', 'purchaseOrder', 'lines.product']);

        return view('purchasing.invoice-print', compact('invoice'));
    })->name('purchasing.invoices.print');

    $router->get('purchasing/invoices/export', function () {
        Gate::authorize('purchase_invoices_supplier_returns.export');
        $invoices = PurchaseInvoice::query()->with(['supplier', 'store'])->latest('id')->limit(5000)->get();

        return response()->streamDownload(function () use ($invoices): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                abort(500, 'Unable to open the CSV output stream.');
            }
            fputcsv($handle, ['invoice_number', 'supplier_code', 'supplier_reference', 'store_code', 'status', 'invoice_date', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, array_map(static fn (mixed $value): string => is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'".$value : (string) $value, [$invoice->invoice_number, $invoice->supplier?->code, $invoice->supplier_reference, $invoice->store?->code, $invoice->status, $invoice->invoice_date?->format('Y-m-d'), $invoice->subtotal, $invoice->tax_amount, $invoice->discount_amount, $invoice->total_amount]));
            }
            fclose($handle);
        }, 'purchase-invoices.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('purchasing.invoices.export');

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
