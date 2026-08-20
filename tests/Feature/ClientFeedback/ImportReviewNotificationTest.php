<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Models\User;
use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Catalog\Models\CatalogReferenceImportBatch;
use App\Modules\Catalog\Models\CatalogReferenceImportRow;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Catalog\Models\ProductImportRow;
use App\Modules\Catalog\Models\SupplierImportBatch;
use App\Modules\Catalog\Models\SupplierImportRow;
use App\Modules\Customer\Actions\StageCustomerImportAction;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ImportReviewNotificationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_ready_imports_notify_authorized_reviewers_but_not_the_requester_or_an_unauthorized_user(): void
    {
        $this->seedCanonicalAuthorization();
        $requester = $this->userWith('import-requester', ['system-administrator']);
        $reviewer = $this->userWith('import-reviewer', ['system-administrator']);
        $administrator = $this->administrator('import-super-administrator');
        $unauthorized = $this->userWith('import-unauthorized', ['cashier']);
        $store = $this->store($this->branch('IMPORT-NOTIFY'), 'IMPORT-NOTIFY-SALES');

        $this->actingAs($requester);

        $category = Category::query()->create([
            'code' => 'IMPORT-NOTIFY-CATEGORY',
            'name_ar' => 'تصنيف التنبيه',
            'name_en' => 'Notification category',
            'status' => 'active',
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);
        $productBatch = ProductImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => 'products-ready.xlsx',
            'storage_path' => 'imports/products-ready.xlsx',
            'sha256' => hash('sha256', 'products-ready'),
            'mode' => 'create_only',
            'status' => 'mapping_required',
            'headers' => ['item_code', 'name_ar', 'name_en', 'category_code'],
            'total_rows' => 1,
        ]);
        ProductImportRow::query()->create([
            'product_import_batch_id' => $productBatch->id,
            'row_number' => 2,
            'raw_data' => ['item_code' => 'IMPORT-NOTIFY-001', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_code' => $category->code],
            'status' => 'staged',
            'errors' => [],
        ]);
        app(StageProductImportAction::class)->applyMapping($productBatch, array_combine($productBatch->headers, $productBatch->headers));

        $supplierBatch = SupplierImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => 'suppliers-ready.xlsx',
            'storage_path' => 'imports/suppliers-ready.xlsx',
            'sha256' => hash('sha256', 'suppliers-ready'),
            'mode' => 'create_only',
            'status' => 'mapping_required',
            'headers' => StageSupplierImportAction::templateHeaders(),
            'total_rows' => 1,
        ]);
        SupplierImportRow::query()->create([
            'supplier_import_batch_id' => $supplierBatch->id,
            'row_number' => 2,
            'raw_data' => ['code' => 'IMPORT-NOTIFY-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier'],
            'status' => 'staged',
            'errors' => [],
        ]);
        app(StageSupplierImportAction::class)->applyMapping($supplierBatch, array_combine($supplierBatch->headers, $supplierBatch->headers));

        $customerBatch = app(StageCustomerImportAction::class)->stage([[
            'first_name_ar' => 'أحمد', 'last_name_ar' => 'عميل', 'first_name_en' => 'Ahmed', 'last_name_en' => 'Customer',
            'phone' => '01012345678', 'email' => 'customer-import@example.test', 'customer_group' => '', 'consent_purpose' => 'registration', 'consent_status' => 'granted',
        ]], 'customers-ready.xlsx', 'create_only', $requester->id, $store);

        $referenceBatch = CatalogReferenceImportBatch::query()->create([
            'type' => 'brand',
            'mode' => 'create_only',
            'created_by' => $requester->id,
            'original_filename' => 'brands-ready.xlsx',
            'storage_path' => 'imports/brands-ready.xlsx',
            'sha256' => hash('sha256', 'brands-ready'),
            'status' => 'staged',
            'headers' => StageCatalogReferenceImportAction::templateHeaders('brand'),
            'total_rows' => 1,
        ]);
        CatalogReferenceImportRow::query()->create([
            'catalog_reference_import_batch_id' => $referenceBatch->id,
            'row_number' => 2,
            'raw_data' => ['code' => 'IMPORT-NOTIFY-BRAND', 'name_ar' => 'علامة', 'name_en' => 'Brand', 'status' => 'active', 'sort_order' => 0],
            'status' => 'staged',
            'errors' => [],
        ]);
        app(StageCatalogReferenceImportAction::class)->validate($referenceBatch);

        $this->assertSame(4, $administrator->notifications()->count());
        $this->assertSame(0, $requester->notifications()->count());
        $this->assertSame(0, $unauthorized->notifications()->count());
        $this->assertSame(3, $reviewer->notifications()->count());

        $alerts = $administrator->notifications()->get()->map(fn ($notification): array => $notification->data)->keyBy('import_type');
        $this->assertSame('products-ready.xlsx', $alerts['product']['filename']);
        $this->assertSame(route('catalog.products.import', ['batch' => $productBatch->id]), $alerts['product']['url']);
        $this->assertSame('suppliers-ready.xlsx', $alerts['supplier']['filename']);
        $this->assertSame(route('catalog.suppliers.import', ['batch' => $supplierBatch->id]), $alerts['supplier']['url']);
        $this->assertSame('customers-ready.xlsx', $alerts['customer']['filename']);
        $this->assertSame(route('customers.import', ['batch' => $customerBatch->id]), $alerts['customer']['url']);
        $this->assertSame('brands-ready.xlsx', $alerts['catalog_reference']['filename']);
        $this->assertSame(route('catalog.reference-import', ['batch' => $referenceBatch->id]), $alerts['catalog_reference']['url']);
    }

    public function test_notifications_screen_renders_a_delivered_alert_and_only_an_authorized_reviewer_can_open_its_product_batch(): void
    {
        $this->seedCanonicalAuthorization();
        $requester = $this->userWith('direct-review-requester', ['system-administrator']);
        $reviewer = $this->administrator('direct-review-administrator');
        $unauthorized = $this->userWith('direct-review-unauthorized', ['cashier']);
        $batch = ProductImportBatch::query()->create([
            'created_by' => $requester->id,
            'original_filename' => 'direct-review-products.xlsx',
            'storage_path' => 'imports/direct-review-products.xlsx',
            'sha256' => hash('sha256', 'direct-review-products'),
            'mode' => 'create_only',
            'status' => 'ready_for_review',
            'headers' => ['item_code'],
        ]);
        $url = route('catalog.products.import', ['batch' => $batch->id]);
        $reviewer->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\ImportReadyForReviewNotification',
            'data' => ['title' => 'Import ready for review', 'message' => 'Review direct-review-products.xlsx.', 'import_type' => 'product', 'filename' => $batch->original_filename, 'url' => $url],
        ]);

        $this->actingAs($reviewer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('direct-review-products.xlsx')
            ->assertSee($url, false);

        $this->get($url)->assertOk()->assertSee('direct-review-products.xlsx');
        $this->actingAs($unauthorized)->get($url)->assertForbidden();
    }
}
