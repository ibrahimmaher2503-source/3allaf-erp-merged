<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Models\CatalogReferenceImportBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Support\PlatformFixtures;

final class CatalogReferenceImportUiTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    public function test_supplier_import_screen_mounts_and_exposes_the_catalog_reference_import_entrypoint(): void
    {
        $admin = $this->administrator('catalog-import-ui-admin');

        $this->actingAs($admin)
            ->get(route('catalog.suppliers.import'))
            ->assertOk()
            ->assertSee('Supplier Import')
            ->assertSee('Upload and stage');
    }

    public function test_category_reference_import_stages_then_requires_an_independent_approver(): void
    {
        $requester = $this->administrator('catalog-reference-requester');
        $reviewer = $this->administrator('catalog-reference-reviewer');
        Storage::disk('local')->put('imports/categories.csv', "code,name_ar,name_en,parent_code,status,sort_order\nTOYS,ألعاب,Toys,,active,0\n");

        $this->actingAs($requester);
        $batch = app(StageCatalogReferenceImportAction::class)->stage('imports/categories.csv', 'categories.csv', 'category', 'create_only', $requester->id);
        $batch = app(StageCatalogReferenceImportAction::class)->validate($batch);

        self::assertSame('ready_for_review', $batch->status);
        self::assertSame(1, $batch->valid_rows);
        try {
            app(StageCatalogReferenceImportAction::class)->approve($batch);
            self::fail('The requester approved their own batch.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertStringContainsString('requester cannot approve', $exception->getMessage());
        }

        $this->actingAs($reviewer);
        app(StageCatalogReferenceImportAction::class)->approve(CatalogReferenceImportBatch::query()->findOrFail($batch->id));
        self::assertDatabaseHas('categories', ['code' => 'TOYS', 'name_ar' => 'ألعاب']);
    }

    public function test_update_existing_reference_import_updates_only_a_matching_code(): void
    {
        $requester = $this->administrator('catalog-reference-update-requester');
        $reviewer = $this->administrator('catalog-reference-update-reviewer');
        \App\Modules\Catalog\Models\Brand::query()->create(['code' => 'QA-BRAND', 'name_ar' => 'قديم', 'name_en' => 'Old', 'status' => 'active', 'created_by' => $requester->id, 'updated_by' => $requester->id]);
        Storage::disk('local')->put('imports/brands-update.csv', "code,name_ar,name_en,status,sort_order\nQA-BRAND,جديد,New,active,0\n");

        $this->actingAs($requester);
        $batch = app(StageCatalogReferenceImportAction::class)->stage('imports/brands-update.csv', 'brands-update.csv', 'brand', 'update_existing', $requester->id);
        $batch = app(StageCatalogReferenceImportAction::class)->validate($batch);
        self::assertSame(1, $batch->valid_rows);

        $this->actingAs($reviewer);
        app(StageCatalogReferenceImportAction::class)->approve($batch);
        self::assertDatabaseHas('brands', ['code' => 'QA-BRAND', 'name_ar' => 'جديد', 'name_en' => 'New']);
    }
}
