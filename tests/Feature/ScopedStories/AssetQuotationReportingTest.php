<?php

declare(strict_types=1);

namespace Tests\Feature\ScopedStories;

use App\Modules\Assets\Actions\ApproveAssetEventAction;
use App\Modules\Assets\Actions\CheckoutAssetAction;
use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Assets\Actions\CreateAssetEventAction;
use App\Modules\Assets\Actions\InspectAssetAction;
use App\Modules\Assets\Actions\ReserveAssetAction;
use App\Modules\Assets\Actions\ReturnAssetAction;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Quotation\Actions\CreateQuotationAction;
use App\Modules\Quotation\Actions\UpdateQuotationAction;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Reporting\Actions\CreateExportJobAction;
use App\Modules\Reporting\Actions\EvaluateAlertsAction;
use App\Modules\Reporting\Models\Alert;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SalePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class AssetQuotationReportingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    protected function beforeRefreshingDatabase(): void
    {
        // Existing pre-scope gift-card migration uses a non-null timestamp
        // without a default. Keep this compatibility setting local to this
        // dedicated MariaDB test connection; no product migration is changed.
        config(['database.connections.mysql.strict' => false]);
        DB::purge('mysql');
        DB::connection('mysql')->statement("SET SESSION sql_mode = ''");
    }

    public function test_asset_reservation_checkout_return_and_inspection_preserve_history(): void
    {
        $manager = $this->administrator('stories-assets');
        $this->actingAs($manager);
        $branch = $this->branch('AST-BR');
        $store = $this->store($branch, 'AST-STORE');
        $asset = app(CreateAssetAction::class)->execute($manager, [
            'code' => 'AST-001', 'name_ar' => 'أصل', 'name_en' => 'Inflatable',
            'category' => 'Play', 'branch_id' => $branch->id, 'store_id' => $store->id,
            'location' => 'Service center', 'condition' => 'good',
        ]);

        $reservation = app(ReserveAssetAction::class)->execute($manager, $asset, [
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDays(2)->startOfHour(),
            'timezone' => 'Africa/Cairo', 'source_reference' => 'PARTY-001',
            'idempotency_key' => 'asset-reservation-001',
        ]);

        try {
            app(ReserveAssetAction::class)->execute($manager, $asset->fresh(), [
                'starts_at' => now()->addDays(1)->startOfHour(),
                'ends_at' => now()->addDays(3)->startOfHour(),
                'timezone' => 'Africa/Cairo', 'source_reference' => 'PARTY-002',
                'idempotency_key' => 'asset-reservation-002',
            ]);
            $this->fail('An overlapping reservation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
        }

        $checkout = app(CheckoutAssetAction::class)->execute($manager, $asset->fresh(), $reservation, [
            'source_reference' => 'PARTY-001', 'location_after' => 'Party venue',
            'notes' => 'Pre-condition recorded.', 'idempotency_key' => 'asset-checkout-001',
        ]);
        $return = app(ReturnAssetAction::class)->execute($manager, $asset->fresh(), $checkout, [
            'location_after' => 'Service center', 'condition_after' => 'good',
            'notes' => 'Returned complete.', 'idempotency_key' => 'asset-return-001',
        ]);
        $this->assertDatabaseHas('asset_returns', ['id' => $return->id, 'outcome' => 'under_inspection']);
        app(InspectAssetAction::class)->execute($manager, $asset->fresh(), $return, 'available', 'Inspection passed.');

        $this->assertSame('available', $asset->fresh()->status);
        $this->assertDatabaseHas('asset_checkouts', ['id' => $checkout->id, 'source_reference' => 'PARTY-001']);
        $this->assertDatabaseHas('asset_returns', ['id' => $return->id, 'outcome' => 'available']);
        $this->assertGreaterThanOrEqual(4, AuditLog::query()->where('category', 'assets')->count());
    }

    public function test_approved_damage_event_changes_state_and_duplicate_retry_has_no_second_effect(): void
    {
        $requester = $this->administrator('stories-event-requester');
        $approver = $this->administrator('stories-event-approver');
        $this->actingAs($requester);
        $branch = $this->branch('EVT-BR');
        $store = $this->store($branch, 'EVT-STORE');
        $asset = app(CreateAssetAction::class)->execute($requester, [
            'code' => 'AST-002', 'name_ar' => 'أصل', 'name_en' => 'Slide',
            'branch_id' => $branch->id, 'store_id' => $store->id,
        ]);
        $event = app(CreateAssetEventAction::class)->execute($requester, $asset, [
            'event_type' => 'damage', 'assessment' => 'Torn seam', 'party_reference' => 'PARTY-003',
            'resulting_status' => 'damaged', 'cost_value' => '125.00', 'idempotency_key' => 'asset-event-001',
        ]);

        $this->actingAs($approver);
        app(ApproveAssetEventAction::class)->execute($approver, $event->fresh());
        $this->assertSame('damaged', $asset->fresh()->status);
        $this->assertSame('approved', $event->fresh()->status);
        $this->assertDatabaseHas('alerts', ['alert_type' => 'asset_issue', 'source_id' => (string) $asset->id, 'status' => 'open']);

        $this->assertSame($event->id, app(CreateAssetEventAction::class)->execute($requester, $asset->fresh(), [
            'event_type' => 'damage', 'assessment' => 'Torn seam', 'party_reference' => 'PARTY-003',
            'resulting_status' => 'damaged', 'cost_value' => '125.00', 'idempotency_key' => 'asset-event-001',
        ])->id);
        $this->assertSame(1, AssetEvent::query()->where('idempotency_key', 'asset-event-001')->count());
    }

    public function test_quotation_is_typed_and_has_no_stock_payment_or_wallet_effect(): void
    {
        $user = $this->administrator('stories-quote');
        $this->actingAs($user);
        $branch = $this->branch('QTN-BR');
        $store = $this->store($branch, 'QTN-STORE');
        $category = Category::query()->create(['code' => 'QTN-CAT', 'name_ar' => 'Category', 'name_en' => 'Quotation category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'QTN-PROD', 'name_ar' => 'Toy', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        $customer = Customer::query()->create([
            'phone_normalized' => '201000000001', 'phone_display' => '+201000000001',
            'name_ar' => 'عميل', 'name_en' => 'Quote Customer', 'status' => 'active',
            'created_by' => $user->id, 'created_branch_id' => $branch->id, 'created_store_id' => $store->id,
            'idempotency_key' => 'quote-customer-001',
        ]);
        CustomerScope::query()->create(['customer_id' => $customer->id, 'branch_id' => $branch->id, 'store_id' => $store->id, 'created_by' => $user->id]);

        $before = [
            'stock' => StockMovement::query()->count(),
            'payments' => SalePayment::query()->count(),
            'product_wallet' => ProductWalletLedger::query()->count(),
            'party_wallet' => PartyWalletLedger::query()->count(),
        ];
        $quotation = app(CreateQuotationAction::class)->execute($user, [
            'activity_type' => 'retail', 'customer_id' => $customer->id,
            'branch_id' => $branch->id, 'store_id' => $store->id,
            'valid_until' => now()->addDays(7)->toDateString(),
            'terms' => 'Valid for seven days.', 'notes' => 'No posting.',
            'idempotency_key' => 'quote-001',
            'lines' => [[
                'product_id' => $product->id,
                'line_type' => 'product', 'description_ar' => 'لعبة', 'description_en' => 'Toy',
                'quantity' => 2, 'unit_price' => 50,
            ]],
        ]);

        $this->assertInstanceOf(Quotation::class, $quotation);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame($before['stock'], StockMovement::query()->count());
        $this->assertSame($before['payments'], SalePayment::query()->count());
        $this->assertSame($before['product_wallet'], ProductWalletLedger::query()->count());
        $this->assertSame($before['party_wallet'], PartyWalletLedger::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'quotation_created')->count());
        $updated = app(UpdateQuotationAction::class)->execute($user, $quotation, [
            'valid_until' => now()->addDays(10)->toDateString(), 'terms' => 'Updated terms.', 'notes' => 'Still no posting.',
            'lines' => [[
                'line_type' => 'product', 'product_id' => $product->id, 'description_ar' => 'Toy', 'description_en' => 'Updated toy',
                'quantity' => 3, 'unit_price' => 50,
            ]],
        ]);
        $this->assertSame('150.00', (string) $updated->total);
        $this->assertSame($before['stock'], StockMovement::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'quotation_updated')->count());
    }

    public function test_report_snapshot_and_export_reconcile_and_foreign_download_is_denied(): void
    {
        $owner = $this->administrator('stories-report-owner');
        $other = $this->administrator('stories-report-other');
        $this->actingAs($owner);
        $branch = $this->branch('RPT-BR');
        $store = $this->store($branch, 'RPT-STORE');

        $report = app(ReportSnapshot::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
            'branch_id' => $branch->id, 'store_id' => $store->id,
        ]);

        $this->assertSame(0, $report['sources']['approved_sales_count']);
        $this->assertSame(0.0, $report['sources']['approved_sales_total']);
        $this->assertSame($report['sources']['approved_sales_total'], $report['kpis']['net_sales']);
        $this->assertSame(0, $report['sources']['asset_rows']);
        foreach (['sales', 'inventory', 'purchasing', 'cash', 'customers', 'parties'] as $requiredModule) {
            $this->assertContains($requiredModule, $report['modules']);
        }

        $job = app(CreateExportJobAction::class)->execute($owner, $report['filters']);
        $this->assertSame('ready', $job->status);
        $this->assertTrue(Storage::disk('local')->exists($job->storage_path));
        $this->actingAs($owner)
            ->get(route('exports.download', $job))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->assertDatabaseHas('audit_logs', ['event' => 'export_downloaded']);
        $this->actingAs($other)->get(route('exports.download', $job))->assertForbidden();
    }

    public function test_report_filters_by_user_and_module_and_uses_sale_snapshot_arithmetic(): void
    {
        $owner = $this->administrator('stories-report-filter-owner');
        $cashier = $this->administrator('stories-report-filter-cashier');
        $otherCashier = $this->administrator('stories-report-filter-other');
        $branch = $this->branch('RPT-FILTER-BR');
        $store = $this->store($branch, 'RPT-FILTER-STORE');

        Sale::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'document_number' => 'RPT-FILTER-001',
            'status' => 'approved',
            'idempotency_key' => 'rpt-filter-sale-001',
            'subtotal' => '100.00',
            'discount_total' => '10.00',
            'tax_total' => '18.00',
            'total' => '108.00',
            'paid_total' => '108.00',
            'approved_at' => now()->subHour(),
        ]);
        Sale::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cashier_id' => $otherCashier->id,
            'document_number' => 'RPT-FILTER-002',
            'status' => 'approved',
            'idempotency_key' => 'rpt-filter-sale-002',
            'subtotal' => '200.00',
            'discount_total' => '20.00',
            'tax_total' => '36.00',
            'total' => '216.00',
            'paid_total' => '216.00',
            'approved_at' => now()->subHour(),
        ]);

        $report = app(ReportSnapshot::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'module' => 'sales',
        ]);

        $this->assertSame(1, $report['kpis']['invoice_count']);
        $this->assertSame(100.0, $report['kpis']['gross_sales']);
        $this->assertSame(90.0, $report['kpis']['net_sales']);
        $this->assertSame(18.0, $report['kpis']['tax']);
        $this->assertSame(1, $report['sources']['approved_sales_count']);
        $this->assertCount(1, $report['sales']);
        $this->assertCount(0, $report['assets']);
    }

    public function test_report_source_filters_reconcile_product_category_payment_and_customer(): void
    {
        $owner = $this->administrator('stories-report-source-filters');
        $branch = $this->branch('RPT-SOURCE-BR');
        $store = $this->store($branch, 'RPT-SOURCE-STORE');
        $category = Category::query()->create([
            'code' => 'RPT-SOURCE-CAT', 'name_ar' => 'Category', 'name_en' => 'Source category', 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'RPT-SOURCE-PROD', 'name_ar' => 'Product', 'name_en' => 'Source product',
            'category_id' => $category->id, 'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'phone_normalized' => '201000000009', 'phone_display' => '+201000000009',
            'name_ar' => 'Customer', 'name_en' => 'Source customer', 'status' => 'active',
            'created_by' => $owner->id, 'created_branch_id' => $branch->id, 'created_store_id' => $store->id,
            'idempotency_key' => 'rpt-source-customer',
        ]);
        CustomerScope::query()->create([
            'customer_id' => $customer->id, 'branch_id' => $branch->id, 'store_id' => $store->id, 'created_by' => $owner->id,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'RPT-CARD', 'name_ar' => 'Card', 'name_en' => 'Report card', 'type' => 'card', 'status' => 'active',
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $owner->id, 'customer_id' => $customer->id,
            'document_number' => 'RPT-SOURCE-001', 'status' => 'approved', 'idempotency_key' => 'rpt-source-sale',
            'subtotal' => '50.00', 'discount_total' => '5.00', 'tax_total' => '9.00', 'total' => '54.00', 'paid_total' => '54.00',
            'approved_at' => now()->subHour(),
        ]);
        SaleLine::query()->create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code,
            'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => 1, 'unit_price' => 50,
            'gross_amount' => 50, 'discount_amount' => 5, 'net_amount' => 45,
        ]);
        SalePayment::query()->create([
            'sale_id' => $sale->id, 'payment_method_id' => $paymentMethod->id, 'method_code' => $paymentMethod->code,
            'method_type' => $paymentMethod->type, 'amount' => 54, 'idempotency_key' => 'rpt-source-payment', 'created_by' => $owner->id,
        ]);
        $otherCategory = Category::query()->create([
            'code' => 'RPT-SOURCE-OTHER-CAT', 'name_ar' => 'Other category', 'name_en' => 'Other source category', 'status' => 'active',
        ]);
        $otherProduct = Product::query()->create([
            'item_code' => 'RPT-SOURCE-OTHER-PROD', 'name_ar' => 'Other product', 'name_en' => 'Other source product',
            'category_id' => $otherCategory->id, 'status' => 'active',
        ]);
        $otherPaymentMethod = PaymentMethod::query()->create([
            'code' => 'RPT-CASH', 'name_ar' => 'Cash', 'name_en' => 'Report cash', 'type' => 'cash', 'status' => 'active',
        ]);
        $otherSale = Sale::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $owner->id,
            'document_number' => 'RPT-SOURCE-002', 'status' => 'approved', 'idempotency_key' => 'rpt-source-sale-other',
            'subtotal' => '70.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '70.00', 'paid_total' => '70.00',
            'approved_at' => now()->subHour(),
        ]);
        SaleLine::query()->create([
            'sale_id' => $otherSale->id, 'product_id' => $otherProduct->id, 'line_number' => 1, 'item_code' => $otherProduct->item_code,
            'name_ar' => $otherProduct->name_ar, 'name_en' => $otherProduct->name_en, 'quantity' => 1, 'unit_price' => 70,
            'gross_amount' => 70, 'discount_amount' => 0, 'net_amount' => 70,
        ]);
        SalePayment::query()->create([
            'sale_id' => $otherSale->id, 'payment_method_id' => $otherPaymentMethod->id, 'method_code' => $otherPaymentMethod->code,
            'method_type' => $otherPaymentMethod->type, 'amount' => 70, 'idempotency_key' => 'rpt-source-payment-other', 'created_by' => $owner->id,
        ]);

        $report = app(ReportSnapshot::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
            'branch_id' => $branch->id, 'store_id' => $store->id, 'module' => 'sales',
            'customer_id' => $customer->id, 'product_id' => $product->id, 'category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $this->assertSame(1, $report['kpis']['invoice_count']);
        $this->assertSame(45.0, $report['kpis']['net_sales']);
        $this->assertSame(54.0, $report['kpis']['payments_collected']);
        $this->assertSame(1, $report['sources']['approved_sales_count']);
    }

    public function test_report_http_route_forwards_customer_and_document_filters_to_the_snapshot(): void
    {
        $owner = $this->administrator('stories-report-http-filters');
        $branch = $this->branch('RPT-HTTP-BR');
        $store = $this->store($branch, 'RPT-HTTP-STORE');
        $customer = Customer::query()->create([
            'phone_normalized' => '201000000061', 'phone_display' => '+201000000061',
            'name_ar' => 'Customer', 'name_en' => 'Included customer', 'status' => 'active',
            'created_by' => $owner->id, 'created_branch_id' => $branch->id, 'created_store_id' => $store->id,
            'idempotency_key' => 'report-http-customer',
        ]);
        CustomerScope::query()->create(['customer_id' => $customer->id, 'branch_id' => $branch->id, 'store_id' => $store->id, 'created_by' => $owner->id]);

        foreach ([
            ['number' => 'RPT-HTTP-INCLUDED', 'customer_id' => $customer->id],
            ['number' => 'RPT-HTTP-EXCLUDED', 'customer_id' => null],
        ] as $saleData) {
            Sale::query()->create([
                'branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $owner->id,
                'customer_id' => $saleData['customer_id'], 'document_number' => $saleData['number'],
                'status' => 'approved', 'idempotency_key' => strtolower($saleData['number']),
                'subtotal' => 10, 'discount_total' => 0, 'tax_total' => 0, 'total' => 10,
                'paid_total' => 10, 'approved_at' => now()->subHour(),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('reports.index', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'module' => 'sales',
            'customer_id' => $customer->id,
            'document_status' => 'approved',
        ]));

        $response->assertOk()->assertViewHas('report', function (array $report) use ($customer): bool {
            return $report['filters']['customer_id'] === $customer->id
                && $report['filters']['document_status'] === 'approved'
                && $report['kpis']['invoice_count'] === 1
                && $report['sales']->first()?->document_number === 'RPT-HTTP-INCLUDED';
        });
    }

    public function test_each_advertised_report_route_opens_one_authorized_focused_report(): void
    {
        $owner = $this->administrator('stories-focused-reports');

        foreach ([
            'reports.sales' => ['sales', 'Sales reports', 'gross_sales', 'stock_on_hand'],
            'reports.customers' => ['customers', 'Customer & loyalty reports', 'customer_count', 'gross_sales'],
            'reports.cash' => ['cash', 'Cash & shift reports', 'open_shifts', 'customer_count'],
            'reports.purchasing' => ['purchasing', 'Purchasing reports', 'purchase_order_count', 'gross_sales'],
            'reports.inventory' => ['inventory', 'Inventory reports', 'stock_on_hand', 'gross_sales'],
            'reports.parties' => ['parties', 'Party reports', 'party_booking_count', 'gross_sales'],
            'reports.assets' => ['assets', 'Rental asset reports', 'assets_available', 'gross_sales'],
        ] as $routeName => [$module, $heading, $requiredKpi, $unrelatedKpi]) {
            $this->actingAs($owner)->get(route($routeName))
                ->assertOk()
                ->assertSeeText($heading)
                ->assertViewHas('report', fn (array $report): bool => $report['modules'] === [$module]
                    && array_key_exists($requiredKpi, $report['kpis'])
                    && ! array_key_exists($unrelatedKpi, $report['kpis']));
        }
    }

    public function test_focused_reports_return_bounded_domain_details_and_exports_keep_the_report_identity(): void
    {
        $owner = $this->administrator('stories-report-domain-detail');
        $branch = $this->branch('RPT-DETAIL-BR');
        $store = $this->store($branch, 'RPT-DETAIL-STORE');

        foreach (['sales', 'customers', 'cash', 'purchasing', 'inventory', 'parties', 'assets'] as $module) {
            $report = app(ReportSnapshot::class)->execute($owner, [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'module' => $module,
            ]);

            $this->assertNotEmpty($report['detail_sections'], $module.' must expose a genuine bounded detail surface.');
            foreach ($report['detail_sections'] as $section) {
                $this->assertLessThanOrEqual(50, count($section['rows']));
            }
        }

        $job = app(CreateExportJobAction::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'module' => 'inventory',
        ]);

        $this->assertSame('inventory', $job->report_key);
    }

    public function test_focused_reports_expose_bounded_source_reconciled_visual_series(): void
    {
        $owner = $this->administrator('stories-report-visuals');
        $branch = $this->branch('RPT-VISUAL-BR');
        $store = $this->store($branch, 'RPT-VISUAL-STORE');
        Sale::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $owner->id,
            'document_number' => 'RPT-VISUAL-SALE', 'status' => 'approved', 'idempotency_key' => 'rpt-visual-sale',
            'subtotal' => 100, 'discount_total' => 10, 'tax_total' => 18, 'total' => 108,
            'paid_total' => 108, 'approved_at' => now()->subHour(),
        ]);

        foreach (['sales', 'customers', 'cash', 'purchasing', 'inventory', 'parties', 'assets'] as $module) {
            $report = app(ReportSnapshot::class)->execute($owner, [
                'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
                'branch_id' => $branch->id, 'store_id' => $store->id, 'module' => $module,
            ]);

            $this->assertArrayHasKey('visuals', $report, $module.' must expose chart data.');
            $this->assertNotEmpty($report['visuals'], $module.' must expose useful chart data.');
            foreach ($report['visuals'] as $visual) {
                $this->assertContains($visual['type'], ['bar', 'line', 'donut']);
                $this->assertNotEmpty($visual['description']);
                $this->assertContains($visual['unit'], ['money', 'number', 'points', 'percent']);
                $this->assertLessThanOrEqual(31, count($visual['labels']));
                $this->assertLessThanOrEqual(4, count($visual['series']));
                foreach ($visual['series'] as $series) {
                    $this->assertNotEmpty($series['key']);
                    $this->assertNotEmpty($series['label']);
                    $this->assertSame(count($visual['labels']), count($series['data']));
                    $this->assertContainsOnly('numeric', $series['data']);
                }
            }

            if ($module === 'sales') {
                $financial = collect($report['visuals'])->firstWhere('key', 'sales_financials');
                $this->assertSame(['Gross', 'Net before tax', 'Tax', 'Final'], $financial['labels']);
                $this->assertSame([100.0, 90.0, 18.0, 108.0], $financial['series'][0]['data']);
            }
        }
    }

    public function test_inventory_visual_aggregates_all_scoped_balances_not_only_bounded_detail_rows(): void
    {
        $owner = $this->administrator('stories-inventory-visual-total');
        $branch = $this->branch('RPT-VIS-INV-BR');
        $store = $this->store($branch, 'RPT-VIS-INV-STORE');
        $category = Category::query()->create(['code' => 'RPT-VIS-INV-CAT', 'name_ar' => 'Category', 'name_en' => 'Visual category', 'status' => 'active']);

        foreach (range(1, 51) as $index) {
            $product = Product::query()->create([
                'item_code' => 'RPT-VIS-INV-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'name_ar' => 'Product '.$index, 'name_en' => 'Visual product '.$index,
                'category_id' => $category->id, 'status' => 'active',
            ]);
            StockBalance::query()->create([
                'product_id' => $product->id, 'store_id' => $store->id,
                'on_hand' => 2, 'reserved' => 1, 'in_transit' => 0.5,
                'average_cost' => 4, 'total_value' => 8, 'version' => 1,
            ]);
        }

        $report = app(ReportSnapshot::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
            'branch_id' => $branch->id, 'store_id' => $store->id, 'module' => 'inventory',
        ]);
        $visual = collect($report['visuals'])->firstWhere('key', 'inventory_quantity');

        $this->assertCount(50, collect($report['detail_sections'])->firstWhere('key', 'inventory_balances')['rows']);
        $this->assertSame([102.0, 51.0, 51.0, 25.5], $visual['series'][0]['data']);
    }

    public function test_document_status_filter_reconciles_kpis_and_detail_rows(): void
    {
        $owner = $this->administrator('stories-report-document-status');
        $branch = $this->branch('RPT-DOC-STATUS-BR');
        $store = $this->store($branch, 'RPT-DOC-STATUS-STORE');

        foreach ([
            ['number' => 'RPT-DOC-APPROVED', 'status' => 'approved', 'amount' => 10.00],
            ['number' => 'RPT-DOC-CANCELLED', 'status' => 'cancelled', 'amount' => 99.00],
        ] as $saleData) {
            Sale::query()->create([
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'cashier_id' => $owner->id,
                'document_number' => $saleData['number'],
                'status' => $saleData['status'],
                'idempotency_key' => strtolower($saleData['number']),
                'subtotal' => $saleData['amount'],
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => $saleData['amount'],
                'paid_total' => 0,
                'approved_at' => $saleData['status'] === 'approved' ? now()->subHour() : null,
            ]);
        }

        $report = app(ReportSnapshot::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'module' => 'sales',
            'document_status' => 'cancelled',
        ]);

        $this->assertSame(1, $report['kpis']['invoice_count']);
        $this->assertSame(99.0, $report['kpis']['gross_sales']);
        $this->assertSame(99.0, $report['kpis']['net_sales']);
        $this->assertSame(1, $report['sources']['sales_count']);
        $this->assertCount(1, $report['sales']);
        $this->assertSame('RPT-DOC-CANCELLED', $report['sales']->first()->document_number);
    }

    public function test_scoped_report_route_rejects_foreign_branch_filter(): void
    {
        $allowedBranch = $this->branch('RPT-SCOPE-ALLOWED');
        $foreignBranch = $this->branch('RPT-SCOPE-FOREIGN');
        $viewer = $this->userWith('stories-report-scope-viewer', ['accountant-reviewer'], false, [$allowedBranch->id]);

        $this->actingAs($viewer)
            ->get(route('reports.index', ['branch_id' => $foreignBranch->id]))
            ->assertForbidden();
    }

    public function test_pdf_export_is_created_as_a_queued_job(): void
    {
        Queue::fake();
        $owner = $this->administrator('stories-report-pdf');
        $branch = $this->branch('RPT-PDF-BR');
        $store = $this->store($branch, 'RPT-PDF-STORE');

        $job = app(CreateExportJobAction::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'branch_id' => $branch->id,
            'store_id' => $store->id,
        ], 'pdf');

        $this->assertSame('queued', $job->status);
        Queue::assertPushed('App\Modules\Reporting\Jobs\GenerateReportExportJob');
    }

    public function test_pdf_export_generates_a_private_pdf_after_queue_execution(): void
    {
        $owner = $this->administrator('stories-report-pdf-ready');
        $branch = $this->branch('RPT-PDF-READY-BR');
        $store = $this->store($branch, 'RPT-PDF-READY-STORE');

        $job = app(CreateExportJobAction::class)->execute($owner, [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
            'branch_id' => $branch->id, 'store_id' => $store->id,
        ], 'pdf');

        $job = $job->fresh();
        $this->assertSame('ready', $job->status);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($job->storage_path));
    }

    public function test_inventory_cost_is_omitted_without_cost_permission(): void
    {
        $branch = $this->branch('RPT-COST-BR');
        $store = $this->store($branch, 'RPT-COST-STORE');
        $viewer = $this->userWith('stories-report-cost-viewer', ['branch-manager'], false, [$branch->id]);
        $category = Category::query()->create([
            'code' => 'RPT-COST-CAT', 'name_ar' => 'Category', 'name_en' => 'Cost category', 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'RPT-COST-PROD', 'name_ar' => 'Product', 'name_en' => 'Cost product',
            'category_id' => $category->id, 'status' => 'active',
        ]);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => 3,
            'reserved' => 0, 'in_transit' => 0, 'average_cost' => 25, 'total_value' => 75, 'version' => 1,
        ]);

        $report = app(ReportSnapshot::class)->execute($viewer, [
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(),
            'branch_id' => $branch->id, 'store_id' => $store->id, 'module' => 'inventory',
        ]);

        $this->assertSame(3.0, $report['kpis']['stock_on_hand']);
        $this->assertArrayNotHasKey('stock_value', $report['kpis']);
    }

    public function test_low_stock_and_unpriced_alerts_are_scoped_and_deduplicated(): void
    {
        $owner = $this->administrator('stories-report-alerts');
        $branch = $this->branch('RPT-ALERT-BR');
        $store = $this->store($branch, 'RPT-ALERT-STORE');
        $category = Category::query()->create([
            'code' => 'RPT-ALERT-CAT', 'name_ar' => 'Category', 'name_en' => 'Alert category', 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'RPT-ALERT-PROD', 'name_ar' => 'Product', 'name_en' => 'Alert product',
            'category_id' => $category->id, 'reorder_threshold' => 5, 'status' => 'active',
        ]);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => 2,
            'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 20, 'version' => 1,
        ]);

        $this->assertSame(2, app(EvaluateAlertsAction::class)->execute($owner));
        $this->assertSame(0, app(EvaluateAlertsAction::class)->execute($owner));
        $this->assertDatabaseHas('alerts', ['alert_key' => 'low-stock:'.$product->id.':'.$store->id, 'alert_type' => 'low_stock']);
        $this->assertDatabaseHas('alerts', ['alert_key' => 'unpriced:'.$product->id.':'.$store->id, 'alert_type' => 'unpriced_product']);
        $this->assertSame(2, Alert::query()->whereIn('alert_type', ['low_stock', 'unpriced_product'])->count());
    }
}
