<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\LabelPrintEvent;
use App\Modules\Pricing\Models\LabelQueue;
use App\Modules\Pricing\Models\PriceVersion;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * US-004/US-007 remedial maker-checker and authenticated UI workflow gaps.
 *
 * These tests deliberately use scoped, non-super-admin actors. They name
 * missing user-observable workflow behavior rather than source structure.
 */
final class ProductImportAndLabelWorkflowTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonicalAuthorizationSeeder::class);
        Storage::fake('local');
    }

    public function test_scoped_importer_stages_a_mixed_csv_and_explicitly_maps_columns_without_writing_products(): void
    {
        $this->requireOpenSpout();
        [$branch, $store] = $this->branchAndStore('import-mixed');
        $importer = $this->scopedActor('importer-mixed', ['products_categories_brands.view', 'products_categories_brands.create'], $branch->id, $store->id);
        $this->actingAs($importer);
        app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-IMPORT-MIXED',
            'name_ar' => 'استيراد مختلط',
            'name_en' => 'Mixed import',
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        Storage::disk('local')->put('imports/mixed-products.csv', implode("\n", [
            'SKU,Arabic title,English title,Category',
            'IMPORTED-VALID,لعبة,Valid toy,CAT-IMPORT-MIXED',
            'IMPORTED-INVALID,لعبة أخرى,,CAT-IMPORT-MIXED',
        ]));

        $action = app(StageProductImportAction::class);
        $staged = $action->stage('imports/mixed-products.csv', 'mixed-products.csv', 'create_only', $importer->id);
        self::assertSame('mapping_required', $staged->status);

        $mapped = $action->applyMapping($staged, [
            'sku' => 'item_code',
            'arabic_title' => 'name_ar',
            'english_title' => 'name_en',
            'category' => 'category_code',
        ]);

        self::assertSame('ready_for_review', $mapped->status);
        self::assertSame(1, $mapped->valid_rows);
        self::assertSame(1, $mapped->invalid_rows);
        self::assertSame(0, Product::query()->whereIn('item_code', ['IMPORTED-VALID', 'IMPORTED-INVALID'])->count());
    }

    public function test_scoped_reviewer_can_open_another_requesters_import_batch_in_the_authenticated_ui(): void
    {
        [$branch, $store] = $this->branchAndStore('import-review-ui');
        $requester = $this->scopedActor('import-requester-ui', ['products_categories_brands.view', 'products_categories_brands.create'], $branch->id, $store->id);
        $reviewer = $this->scopedActor('import-reviewer-ui', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve'], $branch->id, $store->id);
        $batch = $this->reviewableBatch($requester, 'reviewer-visible.csv');

        $this->actingAs($reviewer);

        Livewire::test('catalog::product-import')
            ->call('selectBatch', $batch->id)
            ->assertSet('selectedBatchId', $batch->id)
            ->assertSee('reviewer-visible.csv')
            ->assertSee('Approve valid rows');
    }

    public function test_requester_cannot_self_approve_a_valid_import_batch(): void
    {
        [$branch, $store] = $this->branchAndStore('import-maker-checker');
        $requester = $this->scopedActor('import-requester-checker', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve'], $branch->id, $store->id);
        $batch = $this->reviewableBatch($requester, 'maker-checker.csv');

        $this->actingAs($requester);
        $this->expectException(ValidationException::class);
        app(StageProductImportAction::class)->approve($batch, app(\App\Modules\Catalog\Actions\SaveProductAction::class));
    }

    public function test_separate_scoped_reviewer_can_approve_a_requesters_valid_batch(): void
    {
        [$branch, $store] = $this->branchAndStore('import-reviewer-approval');
        $requester = $this->scopedActor('import-requester-approval', ['products_categories_brands.view', 'products_categories_brands.create'], $branch->id, $store->id);
        $reviewer = $this->scopedActor('import-reviewer-approval', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve'], $branch->id, $store->id);
        $batch = $this->reviewableBatch($requester, 'reviewer-approval.csv');

        $this->actingAs($reviewer);
        $approved = app(StageProductImportAction::class)->approve($batch->fresh(), app(\App\Modules\Catalog\Actions\SaveProductAction::class));

        self::assertSame('completed', $approved->status);
        self::assertNotNull($approved->approved_at);
        self::assertDatabaseHas('products', ['item_code' => 'IMPORTED-REVIEWABLE']);
    }

    public function test_reviewer_can_download_rejected_rows_but_an_equally_permitted_out_of_scope_reviewer_cannot(): void
    {
        [$branch, $store] = $this->branchAndStore('import-error-export');
        [$otherBranch, $otherStore] = $this->branchAndStore('import-error-export-other');
        $requester = $this->scopedActor('import-error-requester', ['products_categories_brands.view', 'products_categories_brands.create'], $branch->id, $store->id);
        $reviewer = $this->scopedActor('import-error-reviewer', ['products_categories_brands.view', 'products_categories_brands.export'], $branch->id, $store->id);
        $outOfScopeReviewer = $this->scopedActor('import-error-outsider', ['products_categories_brands.view', 'products_categories_brands.export'], $otherBranch->id, $otherStore->id);
        $batch = $this->rejectedBatch($requester, 'rejected-export.csv');

        $this->actingAs($reviewer);
        $download = $this->get(route('catalog.products.import.errors', $batch));
        $download->assertOk()->assertDownload('product-import-errors-'.$batch->id.'.csv');
        self::assertStringContainsString('IMPORTED-REJECTED', $download->streamedContent());

        $this->actingAs($outOfScopeReviewer);
        $this->get(route('catalog.products.import.errors', $batch))->assertNotFound();
    }

    public function test_scoped_user_can_queue_an_approved_price_for_labels_without_creating_inventory_or_financial_side_effects(): void
    {
        [$product, $store, $labelOperator, $version] = $this->approvedPriceContext('label-queue');
        $stockMovementsBefore = \App\Modules\Inventory\Models\StockMovement::query()->count();
        $salesBefore = \App\Modules\Retail\Models\Sale::query()->count();
        $this->actingAs($labelOperator);

        $this->post('/pricing/labels/queues', [
            'price_version_id' => $version->id,
            'quantity' => 3,
            'generation_key' => 'remediation-label-queue-'.$version->id,
        ])->assertRedirect(route('pricing.labels'));

        self::assertDatabaseHas('label_queues', [
            'price_version_id' => $version->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'required_quantity' => 3,
            'status' => 'pending',
        ]);
        self::assertSame($stockMovementsBefore, \App\Modules\Inventory\Models\StockMovement::query()->count());
        self::assertSame($salesBefore, \App\Modules\Retail\Models\Sale::query()->count());
    }

    public function test_scoped_user_can_open_a_label_print_preview_without_creating_a_print_event_or_inventory_side_effect(): void
    {
        [$product, $store, $labelOperator, $version] = $this->approvedPriceContext('label-preview');
        $line = $version->lines->firstOrFail();
        $queue = LabelQueue::query()->create([
            'price_version_id' => $version->id,
            'price_line_id' => $line->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'branch_id' => $store->branch_id,
            'required_quantity' => 3,
            'printed_quantity' => 0,
            'status' => 'pending',
            'template_name' => 'local-remediation',
            'paper_size' => 'A4',
            'generation_key' => 'remediation-label-preview-'.$version->id,
        ]);
        $eventsBefore = LabelPrintEvent::query()->count();
        $stockMovementsBefore = \App\Modules\Inventory\Models\StockMovement::query()->count();
        $this->actingAs($labelOperator);

        $this->get('/pricing/labels/'.$queue->id.'/preview')
            ->assertOk()
            ->assertSee($product->item_code)
            ->assertSee('29.500');

        self::assertSame($eventsBefore, LabelPrintEvent::query()->count());
        self::assertSame($stockMovementsBefore, \App\Modules\Inventory\Models\StockMovement::query()->count());
    }

    /** @return array{0: \App\Modules\Platform\Models\Branch, 1: Store} */
    private function branchAndStore(string $suffix): array
    {
        $branch = $this->branch('BR-'.$suffix);

        return [$branch, $this->store($branch, 'ST-'.$suffix)];
    }

    /** @param array<int, string> $permissions */
    private function scopedActor(string $username, array $permissions, int $branchId, int $storeId): User
    {
        $role = Role::query()->create([
            'code' => 'role-'.$username,
            'name_ar' => 'دور '.$username,
            'name_en' => 'Role '.$username,
            'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id'));

        return $this->userWith($username, [$role->code], false, [$branchId], [$storeId]);
    }

    private function reviewableBatch(User $requester, string $filename): ProductImportBatch
    {
        $this->actingAs($requester);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-'.strtoupper(str_replace(['-', '.'], '', $filename)),
            'name_ar' => 'فئة مراجعة',
            'name_en' => 'Review category',
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $batch = ProductImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => $filename,
            'storage_path' => 'imports/'.$filename,
            'mime_type' => 'text/csv',
            'size_bytes' => 1,
            'sha256' => hash('sha256', $filename),
            'mode' => 'create_only',
            'status' => 'ready_for_review',
            'headers' => ['item_code', 'name_ar', 'name_en', 'category_code'],
            'column_mapping' => ['item_code' => 'item_code', 'name_ar' => 'name_ar', 'name_en' => 'name_en', 'category_code' => 'category_code'],
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
        ]);
        $batch->rows()->create([
            'row_number' => 2,
            'raw_data' => ['item_code' => 'IMPORTED-REVIEWABLE', 'name_ar' => 'منتج مراجعة', 'name_en' => 'Review product', 'category_code' => $category->code],
            'mapped_data' => ['item_code' => 'IMPORTED-REVIEWABLE', 'name_ar' => 'منتج مراجعة', 'name_en' => 'Review product', 'category_code' => $category->code, 'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active'],
            'errors' => [],
            'status' => 'valid',
        ]);

        return $batch;
    }

    private function rejectedBatch(User $requester, string $filename): ProductImportBatch
    {
        $batch = ProductImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => $filename,
            'storage_path' => 'imports/'.$filename,
            'mime_type' => 'text/csv',
            'size_bytes' => 1,
            'sha256' => hash('sha256', $filename),
            'mode' => 'create_only',
            'status' => 'ready_for_review',
            'headers' => ['item_code'],
            'total_rows' => 1,
            'valid_rows' => 0,
            'invalid_rows' => 1,
        ]);
        $batch->rows()->create([
            'row_number' => 2,
            'raw_data' => ['item_code' => 'IMPORTED-REJECTED'],
            'mapped_data' => ['item_code' => 'IMPORTED-REJECTED'],
            'errors' => ['English product name is required.'],
            'status' => 'invalid',
        ]);

        return $batch;
    }

    /** @return array{0: Product, 1: Store, 2: User, 3: PriceVersion} */
    private function approvedPriceContext(string $suffix): array
    {
        [$branch, $store] = $this->branchAndStore($suffix);
        $proposer = $this->scopedActor('price-proposer-'.$suffix, ['products_categories_brands.create', 'pricing_labels.create', 'pricing_labels.submit'], $branch->id, $store->id);
        $approver = $this->scopedActor('price-approver-'.$suffix, ['pricing_labels.approve'], $branch->id, $store->id);
        $labelOperator = $this->scopedActor('label-operator-'.$suffix, ['pricing_labels.view', 'pricing_labels.create'], $branch->id, $store->id);
        $this->actingAs($proposer);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-LABEL-'.strtoupper($suffix),
            'name_ar' => 'فئة ملصق',
            'name_en' => 'Label category',
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $product = app(\App\Modules\Catalog\Actions\SaveProductAction::class)->execute([
            'item_code' => 'LABEL-'.strtoupper($suffix),
            'name_ar' => 'منتج ملصق',
            'name_en' => 'Label product',
            'category_id' => $category->id,
            'product_type' => 'standard',
            'status' => 'active',
        ]);
        $version = app(CreatePriceProposalAction::class)->execute(
            $product,
            $store,
            'REM-LABEL-'.strtoupper($suffix),
            'ملصقات المعالجة',
            'Remediation labels',
            '29.500',
            'product_card',
            'REM-LABEL-'.$suffix,
            null,
            null,
            null,
        );
        $version = app(SubmitPriceProposalAction::class)->execute($version);
        $this->actingAs($approver);
        $version = app(ApprovePriceProposalAction::class)->execute($version);

        return [$product, $store, $labelOperator, $version];
    }

    private function requireOpenSpout(): void
    {
        if (! class_exists('OpenSpout\\Reader\\Common\\Creator\\ReaderFactory')) {
            $this->markTestSkipped('BLOCKED_BY_ENVIRONMENT: CSV staging requires the locked OpenSpout runtime.');
        }
    }
}
