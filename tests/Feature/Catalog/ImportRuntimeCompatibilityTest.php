<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\StagePurchaseInvoiceImportAction;
use App\Modules\Purchasing\Models\PurchaseInvoiceImportBatch;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * PRC-01, PUR-04, NFR-04..NFR-06 import safety checks.
 *
 * OpenSpout is declared in composer.lock but is absent from this checkout's
 * vendor tree. The guarded tests therefore report BLOCKED_BY_ENVIRONMENT
 * rather than claiming formula or duplicate-batch coverage.
 */
final class ImportRuntimeCompatibilityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CanonicalAuthorizationSeeder::class);
        $this->actingAs($this->administrator('import-runtime-owner'));
        Storage::fake('local');
    }

    public function test_composer_lock_declares_openspout_but_runtime_must_be_available_for_imports(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);
        $package = collect($lock['packages'] ?? [])->firstWhere('name', 'openspout/openspout');

        self::assertNotNull($package);
        self::assertSame('v4.32.0', $package['version'] ?? null);

        if (! class_exists('OpenSpout\\Reader\\Common\\Creator\\ReaderFactory')) {
            $this->markTestSkipped('BLOCKED_BY_ENVIRONMENT: composer.lock declares openspout/openspout v4.32.0, but OpenSpout\\Reader\\Common\\Creator\\ReaderFactory is not installed/autoloadable.');
        }

        self::assertTrue(class_exists('OpenSpout\\Reader\\Common\\Creator\\ReaderFactory'));
    }

    public function test_product_formula_cells_are_rejected_before_import_approval(): void
    {
        $this->requireOpenSpout();
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-FORMULA', 'name_ar' => 'تصنيف', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        Storage::disk('local')->put('imports/product-formula.csv', "item_code,name_ar,name_en,category_code\nIMP-FORMULA,لعبة,=1+1,CAT-FORMULA\n");

        $batch = app(StageProductImportAction::class)->stage('imports/product-formula.csv', 'product-formula.csv', 'create_only', auth()->id());

        self::assertSame('ready_for_review', $batch->status);
        self::assertSame(1, $batch->invalid_rows);
        self::assertStringContainsString('Formula', implode(' ', $batch->rows()->firstOrFail()->errors ?? []));
        self::assertSame(0, ProductImportBatch::query()->where('status', 'completed')->count());
    }

    public function test_duplicate_product_import_file_is_rejected_for_the_same_user(): void
    {
        $this->requireOpenSpout();
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-DUP', 'name_ar' => 'تصنيف', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        Storage::disk('local')->put('imports/product-duplicate.csv', "item_code,name_ar,name_en,category_code\nIMP-DUP,لعبة,Game,CAT-DUP\n");
        app(StageProductImportAction::class)->stage('imports/product-duplicate.csv', 'product-duplicate.csv', 'create_only', auth()->id());

        $this->expectException(InvalidArgumentException::class);
        app(StageProductImportAction::class)->stage('imports/product-duplicate.csv', 'product-duplicate.csv', 'create_only', auth()->id());
    }

    public function test_purchase_invoice_formula_cells_are_retained_as_invalid_rows(): void
    {
        $this->requireOpenSpout();
        [$supplier, $product, $store] = $this->purchaseImportMasterData();
        Storage::disk('local')->put('imports/invoice-formula.csv', "supplier_code,supplier_invoice_number,invoice_date,receiving_store_code,item_code,quantity,unit_cost\n{$supplier->code},INV-FORMULA,2026-08-08,{$store->code},{$product->item_code},=1+1,10\n");

        $batch = app(StagePurchaseInvoiceImportAction::class)->stage('imports/invoice-formula.csv', 'invoice-formula.csv', 'text/csv', Storage::disk('local')->size('imports/invoice-formula.csv'), auth()->id());

        self::assertSame('ready_for_review', $batch->status);
        self::assertSame(1, $batch->invalid_rows);
        self::assertStringContainsString('Formula-like', implode(' ', $batch->rows()->firstOrFail()->errors ?? []));
        self::assertSame(0, PurchaseInvoiceImportBatch::query()->where('status', 'completed')->count());
    }

    public function test_purchase_invoice_duplicate_file_is_rejected_for_the_same_user(): void
    {
        $this->requireOpenSpout();
        [$supplier, $product, $store] = $this->purchaseImportMasterData();
        Storage::disk('local')->put('imports/invoice-duplicate.csv', "supplier_code,supplier_invoice_number,invoice_date,receiving_store_code,item_code,quantity,unit_cost\n{$supplier->code},INV-DUP,2026-08-08,{$store->code},{$product->item_code},1,10\n");
        $size = Storage::disk('local')->size('imports/invoice-duplicate.csv');
        app(StagePurchaseInvoiceImportAction::class)->stage('imports/invoice-duplicate.csv', 'invoice-duplicate.csv', 'text/csv', $size, auth()->id());

        $this->expectException(InvalidArgumentException::class);
        app(StagePurchaseInvoiceImportAction::class)->stage('imports/invoice-duplicate.csv', 'invoice-duplicate.csv', 'text/csv', $size, auth()->id());
    }

    public function test_purchase_invoice_macro_extensions_are_rejected_before_reader_invocation(): void
    {
        Storage::disk('local')->put('imports/invoice-macro.xlsm', 'not an executable workbook');

        $this->expectException(InvalidArgumentException::class);
        app(StagePurchaseInvoiceImportAction::class)->stage('imports/invoice-macro.xlsm', 'invoice-macro.xlsm', 'application/vnd.ms-excel.sheet.macroEnabled.12', Storage::disk('local')->size('imports/invoice-macro.xlsm'), auth()->id());
    }

    private function requireOpenSpout(): void
    {
        if (! class_exists('OpenSpout\\Reader\\Common\\Creator\\ReaderFactory')) {
            $this->markTestSkipped('BLOCKED_BY_ENVIRONMENT: import execution requires OpenSpout v4.32.0, but ReaderFactory is not installed/autoloadable.');
        }
    }

    /** @return array{0: Supplier, 1: Product, 2: Store} */
    private function purchaseImportMasterData(): array
    {
        $branch = $this->branch('BR-IMPORT');
        $store = $this->store($branch, 'ST-IMPORT');
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'SUP-IMPORT', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active',
        ]);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-IMPORT', 'name_ar' => 'تصنيف', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'ITEM-IMPORT', 'name_ar' => 'منتج', 'name_en' => 'Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        return [$supplier, $product, $store];
    }
}
