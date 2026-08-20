<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\ProductImportRow;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ProductImportPhaseCExpansionTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_csv_shaped_mapping_persists_expanded_master_fields_and_lookup_codes(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('phase-c-requester'));
        $category = app(SaveCategoryAction::class)->execute(['code' => 'PC-CAT', 'name_ar' => 'تصنيف', 'name_en' => 'Category', 'parent_id' => null, 'sort_order' => 0, 'status' => 'active']);
        $supplier = Supplier::query()->create(['code' => 'PC-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $age = AgeLabel::query()->create(['code' => 'PC-A', 'name_ar' => '٣', 'name_en' => '3', 'status' => 'active']);
        $batch = ProductImportBatch::query()->create(['created_by' => auth()->id(), 'original_filename' => 'phase-c.csv', 'storage_path' => 'imports/phase-c.csv', 'sha256' => hash('sha256', 'phase-c'), 'mode' => 'create_only', 'status' => 'mapping_required', 'headers' => ['item_code', 'name_ar', 'name_en', 'category_code', 'preferred_supplier_code', 'sale_price', 'age_codes'], 'total_rows' => 1]);
        ProductImportRow::query()->create(['product_import_batch_id' => $batch->id, 'row_number' => 2, 'raw_data' => ['item_code' => 'PC-001', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_code' => 'PC-CAT', 'preferred_supplier_code' => 'PC-SUP', 'sale_price' => '19.99', 'age_codes' => 'PC-A']]);

        $mapped = app(StageProductImportAction::class)->applyMapping($batch, array_combine($batch->headers, $batch->headers));
        $this->assertSame(1, $mapped->valid_rows);
        $row = $mapped->rows()->firstOrFail();
        $product = app(SaveProductAction::class)->execute($row->mapped_data);
        $this->assertSame('19.99', (string) $product->sale_price);
        $this->assertSame($supplier->id, $product->preferredProductSupplier()->value('supplier_id'));
        $this->assertTrue($product->ages()->whereKey($age)->exists());
    }

    public function test_csv_shaped_mapping_rejects_inactive_lookup_codes(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('phase-c-reviewer'));
        $category = Category::factory()->create(['code' => 'PC-INACTIVE']);
        $batch = ProductImportBatch::query()->create(['created_by' => auth()->id(), 'original_filename' => 'phase-c-invalid.csv', 'storage_path' => 'imports/phase-c-invalid.csv', 'sha256' => hash('sha256', 'phase-c-invalid'), 'mode' => 'create_only', 'status' => 'mapping_required', 'headers' => ['item_code', 'name_ar', 'name_en', 'category_code', 'age_codes'], 'total_rows' => 1]);
        ProductImportRow::query()->create(['product_import_batch_id' => $batch->id, 'row_number' => 2, 'raw_data' => ['item_code' => 'PC-002', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_code' => $category->code, 'age_codes' => 'MISSING']]);

        $mapped = app(StageProductImportAction::class)->applyMapping($batch, array_combine($batch->headers, $batch->headers));
        $this->assertSame(0, $mapped->valid_rows);
        $this->assertStringContainsString('missing or inactive', implode(' ', $mapped->rows()->firstOrFail()->errors ?? []));
    }
}
