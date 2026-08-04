<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TSK-010 — Product, category, brand, code, and barcode masters.
 *
 * TSK-010 is Not Started. No product, category, brand, supplier, or barcode
 * entity exists, so no behavioral test can be written without fabricating the
 * feature. This class is the absence guard: it documents the verified gap and
 * fails the moment TSK-010 lands, at which point the real coverage listed in
 * `.ai/AUTOMATED_TEST_REPORT_TSK_001_010.md` must be written.
 *
 * @group tsk-010
 */
class CatalogImplementationAbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_catalog_table_exists_yet(): void
    {
        foreach (['products', 'categories', 'brands', 'suppliers', 'product_suppliers', 'barcodes'] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Table [{$table}] now exists: TSK-010 coverage must be implemented.",
            );
        }
    }

    public function test_no_catalog_model_or_action_exists_yet(): void
    {
        foreach (['Product', 'Category', 'Brand', 'Supplier', 'Barcode'] as $class) {
            $this->assertSame([], glob(app_path('Modules/*/Models/'.$class.'.php')) ?: []);
            $this->assertSame([], glob(app_path('Models/'.$class.'.php')) ?: []);
        }
    }

    public function test_no_catalog_route_or_screen_exists_yet(): void
    {
        $catalogRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => (bool) preg_match('/product|categor|brand|barcode|supplier/i', $uri));

        $this->assertTrue($catalogRoutes->isEmpty(), 'A catalog route now exists: TSK-010 coverage must be implemented.');

        foreach (['products', 'categories', 'brands', 'barcodes'] as $screen) {
            $this->assertFalse(view()->exists('platform.catalog.'.$screen));
        }
    }
}
