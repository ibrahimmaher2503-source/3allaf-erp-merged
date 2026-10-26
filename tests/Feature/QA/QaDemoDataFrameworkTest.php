<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Retail\Models\Sale;
use App\Support\QaDemoSafety;
use Database\Seeders\FullDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class QaDemoDataFrameworkTest extends TestCase
{
    #[Test]
    public function production_guard_rejects_before_touching_the_database(): void
    {
        config()->set('qa-demo.enabled', true);
        app()->instance('env', 'production');

        try {
            QaDemoSafety::assertAllowed();
            self::fail('Production must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('QA/Demo database reset is disabled in production.', $exception->getMessage());
        } finally {
            app()->instance('env', 'testing');
        }
    }

    #[Test]
    public function production_artisan_does_not_register_the_command_or_allow_direct_demo_seeding(): void
    {
        $process = new Process([PHP_BINARY, 'artisan', 'help', 'rajeh:qa-reset'], base_path(), [
            'APP_ENV' => 'production',
            'RAJEH_QA_DEMO_ENABLED' => 'true',
        ]);
        $process->run();
        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('Command "rajeh:qa-reset" is not defined', $process->getOutput().$process->getErrorOutput());

        app()->instance('env', 'production');
        try {
            app(FullDemoSeeder::class)->run();
            self::fail('Production seeding must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('QA/Demo database reset is disabled in production.', $exception->getMessage());
        } finally {
            app()->instance('env', 'testing');
        }
    }

    #[Test]
    public function catalog_mode_creates_only_catalog_dependencies_and_a_branchless_warehouse(): void
    {
        config()->set('qa-demo.enabled', true);
        config()->set('qa-demo.password', 'QaOnlyDemoPassword!2026');

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--catalog' => true]));
        self::assertSame(13, Product::query()->count());
        self::assertSame(2, Product::query()->whereNotNull('parent_product_id')->count());
        self::assertSame(0, DB::table('stock_balances')->where('product_id', Product::query()->where('item_code', 'QA-ZERO-STOCK')->value('id'))->count());
        self::assertTrue(Store::query()->where('code', 'QA-STANDALONE-WH')->whereNull('branch_id')->exists());
        self::assertSame(0, DB::table('sales')->count());
        self::assertSame(0, DB::table('purchase_invoices')->count());
    }

    #[Test]
    public function inventory_and_pos_modes_include_only_their_required_scenarios(): void
    {
        config()->set('qa-demo.enabled', true);
        config()->set('qa-demo.password', 'QaOnlyDemoPassword!2026');

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--inventory' => true]));
        self::assertGreaterThan(0, DB::table('stock_balances')->count());
        self::assertGreaterThan(0, DB::table('stock_counts')->count());
        self::assertSame(0, DB::table('sales')->count());
        self::assertSame(0, DB::table('purchase_invoices')->count());

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--pos' => true]));
        self::assertSame(1, DB::table('sales')->count());
        self::assertSame(1, DB::table('pos_shifts')->count());
        self::assertSame(0, DB::table('stock_counts')->count());
        self::assertSame(0, DB::table('purchase_invoices')->count());
    }

    #[Test]
    public function full_demo_contains_valid_business_scenarios_and_can_be_reset_cleanly(): void
    {
        config()->set('qa-demo.enabled', true);
        config()->set('qa-demo.password', 'QaOnlyDemoPassword!2026');

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--demo' => true]));
        $this->assertFullDemo();

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--demo' => true]));
        $this->assertFullDemo();
        self::assertSame(13, Product::query()->count());

        self::assertSame(0, Artisan::call('rajeh:qa-reset', ['--empty' => true]));
        foreach (['users', 'products', 'customers', 'suppliers', 'sales', 'purchase_invoices', 'stock_balances', 'stock_transfers', 'stock_counts', 'pos_shifts'] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
        self::assertGreaterThan(0, DB::table('permissions')->count());
    }

    private function assertFullDemo(): void
    {
        self::assertSame(2, DB::table('users')->count());
        self::assertSame(2, DB::table('branches')->count());
        self::assertTrue(Store::query()->where('code', 'QA-STANDALONE-WH')->whereNull('branch_id')->exists());
        self::assertSame(13, Product::query()->count());
        self::assertSame(2, DB::table('product_variant_values')->count());
        self::assertGreaterThanOrEqual(10, DB::table('barcodes')->count());
        self::assertSame(0, DB::table('stock_balances')->where('product_id', Product::query()->where('item_code', 'QA-ZERO-STOCK')->value('id'))->count());
        self::assertSame('400.00', (string) Product::query()->where('item_code', 'QA-PRICE-400')->value('sale_price'));
        self::assertTrue(PriceLine::query()->where('amount', '460.000')->exists());
        self::assertTrue(DB::table('purchase_invoices')->where('supplier_reference', 'QA-DRAFT-INVOICE')->where('status', 'draft')->exists());
        self::assertTrue(DB::table('purchase_invoices')->where('supplier_reference', 'QA-APPROVED-INVOICE')->where('status', 'approved')->exists());
        self::assertGreaterThan(0, DB::table('purchase_invoice_lines')->count());
        self::assertGreaterThan(0, DB::table('purchase_invoice_lines')->join('barcodes', 'purchase_invoice_lines.product_id', '=', 'barcodes.product_id')->join('purchase_invoices', 'purchase_invoice_lines.purchase_invoice_id', '=', 'purchase_invoices.id')->where('purchase_invoices.supplier_reference', 'QA-DRAFT-INVOICE')->where('barcodes.status', 'active')->count());
        self::assertTrue(DB::table('stock_transfers')->where('status', 'received')->exists());
        self::assertTrue(DB::table('stock_transfers')->where('status', 'draft')->exists());
        self::assertSame(2, DB::table('stock_transfers')->distinct()->count('destination_store_id'));
        self::assertGreaterThan(0, DB::table('stock_balances')->count());
        self::assertTrue(StockCountLine::query()->where('variance_quantity', '<', 0)->exists());
        self::assertTrue(StockCountLine::query()->where('variance_quantity', '>', 0)->exists());
        self::assertTrue(DB::table('pos_shifts')->where('status', 'open')->exists());
        $sale = Sale::query()->where('idempotency_key', 'qa-demo-split-sale')->with('payments')->firstOrFail();
        self::assertSame('64.00', $sale->total);
        self::assertEqualsCanonicalizing(['14.00', '50.00'], $sale->payments->pluck('amount')->all());
        self::assertSame(1, DB::table('retail_returns')->where('status', 'draft')->count());
        self::assertSame(1, DB::table('cash_movements')->count());
        self::assertSame(1, DB::table('pos_open_orders')->where('status', 'open')->count());
        self::assertSame(0, DB::table('purchase_invoice_lines')->leftJoin('purchase_invoices', 'purchase_invoice_lines.purchase_invoice_id', '=', 'purchase_invoices.id')->whereNull('purchase_invoices.id')->count());
        self::assertSame(0, DB::table('sale_payments')->leftJoin('sales', 'sale_payments.sale_id', '=', 'sales.id')->whereNull('sales.id')->count());
        self::assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
    }
}
