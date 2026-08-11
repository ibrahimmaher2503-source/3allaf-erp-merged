<?php

declare(strict_types=1);

use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Store;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportBatch;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

$router = app('router');

$router->middleware(['auth', 'verified'])->group(function () use ($router): void {
    $purchaseHistory = static function (Request $request, string $mode) {
        $user = $request->user();
        abort_unless($user !== null && $user->can('purchase_invoices_supplier_returns.view'), 403);
        $visibleStoreIds = Store::query()->visibleTo($user)->pluck('id');
        $supplierId = $request->integer('supplier_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));

        $approvedInvoices = PurchaseInvoice::query()
            ->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($productId, fn ($query) => $query->whereHas('lines', fn ($lines) => $lines->where('product_id', $productId)))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $dateTo));

        $suppliers = Supplier::query()->whereIn('id', (clone $approvedInvoices)->select('supplier_id'))->orderBy('name_en')->get();
        $products = Product::query()->whereIn('id', PurchaseInvoiceLine::query()
            ->whereHas('invoice', fn ($query) => $query->where('status', 'approved')->whereIn('store_id', $visibleStoreIds))
            ->select('product_id'))->orderBy('item_code')->get(['id', 'item_code', 'name_ar', 'name_en']);

        $invoices = $mode === 'supplier'
            ? (clone $approvedInvoices)->with(['supplier', 'store'])->latest('invoice_date')->latest('id')->paginate(20)->withQueryString()
            : null;
        $costLines = $mode === 'cost'
            ? PurchaseInvoiceLine::query()->with(['invoice.supplier', 'invoice.store', 'product'])
                ->whereHas('invoice', fn ($query) => $query->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
                    ->when($supplierId, fn ($scope) => $scope->where('supplier_id', $supplierId))
                    ->when($dateFrom !== '', fn ($scope) => $scope->whereDate('invoice_date', '>=', $dateFrom))
                    ->when($dateTo !== '', fn ($scope) => $scope->whereDate('invoice_date', '<=', $dateTo)))
                ->when($productId, fn ($query) => $query->where('product_id', $productId))
                ->latest('id')->paginate(30)->withQueryString()
            : null;
        $supplierReturns = $mode === 'supplier'
            ? PurchaseReturn::query()->with(['supplier', 'store', 'purchaseInvoice'])
                ->where('status', 'approved')->whereIn('store_id', $visibleStoreIds)
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when($dateFrom !== '', fn ($query) => $query->whereDate('return_date', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($query) => $query->whereDate('return_date', '<=', $dateTo))
                ->latest('return_date')->latest('id')->limit(20)->get()
            : collect();
        $lastPrices = $mode === 'supplier' && $supplierId
            ? ProductSupplier::query()->with('product')->where('supplier_id', $supplierId)
                ->whereNotNull('last_purchase_price')->latest('last_purchase_date')->limit(50)->get()
            : collect();

        return view('purchasing.history', compact('mode', 'invoices', 'costLines', 'supplierReturns', 'lastPrices', 'suppliers', 'products', 'supplierId', 'productId', 'dateFrom', 'dateTo'));
    };

    $router->livewire('purchasing/orders', 'purchasing::orders')
        ->middleware('can:purchase_orders.view')
        ->name('purchasing.orders');

    $router->livewire('purchasing/invoices', 'purchasing::invoices')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices');

    $router->get('purchasing/supplier-history', fn (Request $request) => $purchaseHistory($request, 'supplier'))
        ->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.history.suppliers');
    $router->get('purchasing/cost-history', fn (Request $request) => $purchaseHistory($request, 'cost'))
        ->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.history.costs');

    $router->livewire('purchasing/invoices/import', 'purchasing::invoice-import')
        ->middleware('can:purchase_invoices_supplier_returns.view')
        ->name('purchasing.invoices.import');
    $router->get('purchasing/invoices/import/{batch}/source/{attachment}', function (PurchaseInvoiceImportBatch $batch, Attachment $attachment) {
        abort_unless($attachment->purpose === 'import_source' && $attachment->source_type === PurchaseInvoiceImportBatch::class && $attachment->source_id === (string) $batch->id, 404);

        return app(DeliverAttachment::class)->execute(
            $attachment,
            fn ($user, Attachment $candidate): bool => Gate::forUser($user)->allows('purchase_invoices_supplier_returns.view')
                && $candidate->source_type === PurchaseInvoiceImportBatch::class
                && $candidate->source_id === (string) $batch->id,
        );
    })->whereNumber('batch')->middleware('can:purchase_invoices_supplier_returns.view')->name('purchasing.invoices.import.source');

    $router->livewire('purchasing/returns', 'purchasing::returns')
        ->middleware('can:purchase_returns.view')
        ->name('purchasing.returns');

    $router->livewire('purchasing/returns/settings', 'purchasing::return-settings')
        ->middleware('can:company_settings.view')
        ->name('purchasing.returns.settings');

    $router->get('purchasing/returns/{return}', function (PurchaseReturn $return) {
        Gate::authorize('purchase_returns.view');
        $user = Auth::user();
        abort_unless($user?->is_super_admin || ($user !== null && Store::query()->visibleTo($user)->whereKey($return->store_id)->exists()), 403);
        $return->load(['supplier', 'store', 'purchaseInvoice', 'reason', 'creator', 'submitter', 'approver', 'lines.product']);

        return view('purchasing.return-detail', [
            'return' => $return,
            'movements' => StockMovement::query()->where('source_type', PurchaseReturn::class)->where('source_id', $return->id)->latest('id')->get(),
            'audits' => AuditLog::query()->where('source_type', PurchaseReturn::class)->where('source_id', (string) $return->id)->latest('id')->limit(50)->get(),
            'approvals' => ApprovalRecord::query()->where('source_type', 'purchase_returns')->where('source_id', (string) $return->id)->latest('id')->get(),
        ]);
    })->whereNumber('return')->middleware('can:purchase_returns.view')->name('purchasing.returns.show');

    $router->get('purchasing/returns/{return}/print', function (PurchaseReturn $return) {
        Gate::authorize('purchase_returns.print');
        $user = Auth::user();
        abort_unless($user?->is_super_admin || ($user !== null && Store::query()->visibleTo($user)->whereKey($return->store_id)->exists()), 403);

        return view('purchasing.return-print', [
            'return' => $return->load(['supplier', 'store', 'purchaseInvoice', 'reason', 'creator', 'approver', 'lines.product']),
            'policy' => app(SupplierReturnPolicy::class),
        ]);
    })->whereNumber('return')->middleware('can:purchase_returns.print')->name('purchasing.returns.print');

    $router->get('purchasing/invoices/{invoice}/print', function (PurchaseInvoice $invoice) {
        Gate::authorize('purchase_invoices_supplier_returns.print');
        $user = Auth::user();
        abort_unless($user?->is_super_admin || ($user !== null && Store::query()->visibleTo($user)->whereKey($invoice->store_id)->exists()), 403);
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
        $user = Auth::user();
        abort_unless(
            $order->store_id === null || $user?->is_super_admin || ($user !== null && Store::query()->visibleTo($user)->whereKey($order->store_id)->exists()),
            403,
        );

        return view('purchasing.print', [
            'order' => $order->load(['supplier', 'store', 'creator', 'lines.product']),
        ]);
    })->whereNumber('order')->middleware('can:purchase_orders.print')->name('purchasing.orders.print');
});
