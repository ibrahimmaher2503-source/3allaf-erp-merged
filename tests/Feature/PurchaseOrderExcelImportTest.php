<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Purchasing\Actions\PreviewPurchaseOrderWorkbook;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

final class PurchaseOrderExcelImportTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
    }

    public function test_template_download_and_preview_preserve_leading_zero_code_without_saving_an_order(): void
    {
        $this->get(route('purchasing.orders.import-template'))->assertOk()->assertDownload('rajeh-purchase-order-lines.xlsx');
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'item_code' => '00012345', 'sale_price' => '25']);
        $product->barcodes()->create(['barcode' => '00012345', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $file = $this->workbook([['00012345', '', '2', '15.5000', 'QA note']]);

        $rows = app(PreviewPurchaseOrderWorkbook::class)->execute($file, (int) $this->fixture['company']->id);

        self::assertCount(1, $rows);
        self::assertSame('00012345', $rows[0]['identifier']);
        self::assertSame($product->id, $rows[0]['product_id']);
        self::assertNull($rows[0]['error']);
        self::assertDatabaseCount('purchase_orders', 0);
    }

    public function test_preview_reports_unknown_invalid_and_duplicate_rows(): void
    {
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '25']);
        $product->barcodes()->create(['barcode' => '00098765', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $file = $this->workbook([
            [$product->item_code, '', '1', '10', ''],
            [$product->item_code, '', '2', '10', ''],
            ['UNKNOWN', '', '1', '10', ''],
            [$product->item_code, '', '0', '10', ''],
            [$product->item_code, '', '1', '-1', ''],
        ]);

        $rows = app(PreviewPurchaseOrderWorkbook::class)->execute($file, (int) $this->fixture['company']->id);

        self::assertNull($rows[0]['error']);
        self::assertNotNull($rows[1]['error']);
        self::assertNotNull($rows[2]['error']);
        self::assertNotNull($rows[3]['error']);
        self::assertNotNull($rows[4]['error']);
    }

    public function test_oversized_and_malformed_workbooks_are_rejected_safely(): void
    {
        foreach ([UploadedFile::fake()->create('oversized.xlsx', 2049), UploadedFile::fake()->createWithContent('malformed.xlsx', 'not a workbook')] as $file) {
            try {
                app(PreviewPurchaseOrderWorkbook::class)->execute($file, (int) $this->fixture['company']->id);
                self::fail('Invalid workbook was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_formula_cells_are_reported_as_row_errors(): void
    {
        $file = $this->workbook([['=1+1', '', '1', '10', '']]);
        $rows = app(PreviewPurchaseOrderWorkbook::class)->execute($file, (int) $this->fixture['company']->id);
        self::assertCount(1, $rows);
        self::assertNotNull($rows[0]['error']);
    }

    public function test_preview_then_confirm_adds_unsaved_order_lines(): void
    {
        $supplier = Supplier::query()->create(['code' => 'QA-PO-IMPORT', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '25']);
        $product->barcodes()->create(['barcode' => '00000077', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $file = $this->workbook([['00000077', '', '3', '12.50', 'Import note']]);

        Livewire::test('purchasing::orders')
            ->set('orderForm.supplier_id', (string) $supplier->id)
            ->set('orderForm.branch_id', (string) $this->fixture['branch']->id)
            ->set('importFile', $file)
            ->call('previewOrderImport')
            ->assertCount('importPreview', 1)
            ->call('confirmOrderImport')
            ->assertCount('lineItems', 1)
            ->assertSet('lineItems.0.product_id', (string) $product->id)
            ->assertSet('lineItems.0.quantity_ordered', '3');

        self::assertDatabaseCount('purchase_orders', 0);
    }

    /** @param list<array<int, string>> $rows */
    private function workbook(array $rows): UploadedFile
    {
        $response = app(MasterDataDocument::class)->xlsx('qa-po.xlsx', PreviewPurchaseOrderWorkbook::HEADERS, $rows);

        return UploadedFile::fake()->createWithContent('qa-po.xlsx', file_get_contents($response->getFile()->getPathname()));
    }
}
