<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImportBatch;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use Tests\TestCase;

final class ProductImportWorkflowTest extends TestCase
{
    private string $suffix;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->suffix = strtoupper(Str::random(10));
    }

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            Attachment::query()
                ->whereIn('uploaded_by', $this->createdUserIds)
                ->where('purpose', 'import_source')
                ->get()
                ->each(fn (Attachment $attachment) => Storage::disk($attachment->storage_disk)->delete($attachment->storage_path));
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_downloaded_template_maps_mixed_rows_and_approves_only_valid_rows(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve', 'products_categories_brands.export']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve', 'products_categories_brands.cost_view']);
        $itemCode = 'QI-'.$this->suffix.'-01';
        $upload = $this->productWorkbook($company->code, [
            ['item_code' => $itemCode, 'name_ar' => 'صنف صحيح', 'name_en' => 'Valid item', 'category_code' => $category->code, 'average_cost' => '18.25', 'sale_price' => '40.00'],
            ['item_code' => 'QI-'.$this->suffix.'-02', 'name_ar' => 'اسم عربي', 'name_en' => '', 'category_code' => $category->code, 'product_type' => 'service', 'sale_price' => '12.00'],
            ['item_code' => 'QI-'.$this->suffix.'-03', 'name_ar' => 'فئة غير معروفة', 'name_en' => 'Unknown category', 'category_code' => 'MISSING-'.$this->suffix, 'product_type' => 'service', 'sale_price' => '12.00'],
            ['item_code' => 'QI-'.$this->suffix.'-04', 'name_ar' => 'سعر سالب', 'name_en' => 'Negative price', 'category_code' => $category->code, 'product_type' => 'service', 'sale_price' => '-1.00'],
            ['item_code' => $itemCode, 'name_ar' => 'كود مكرر', 'name_en' => 'Duplicate code', 'category_code' => $category->code, 'product_type' => 'service', 'sale_price' => '11.00'],
        ]);

        $component = Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('mode', 'create_only')
            ->set('importFile', $upload)
            ->call('stage')
            ->assertHasNoErrors()
            ->assertSee('Map columns and validate rows');

        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();
        self::assertSame('mapping_required', $batch->status);
        self::assertSame(5, $batch->total_rows);
        self::assertSame(0, $batch->valid_rows);
        self::assertSame(0, $batch->invalid_rows);
        self::assertSame(5, $batch->rows()->where('status', 'staged')->count());

        $component->call('mapColumns')
            ->assertHasNoErrors()
            ->assertSee('Valid: 1')
            ->assertSee('Rejected: 4');

        $batch->refresh();
        self::assertSame('ready_for_review', $batch->status);
        self::assertSame(1, $batch->valid_rows);
        self::assertSame(4, $batch->invalid_rows);
        self::assertSame(1, $batch->rows()->where('status', 'valid')->count());
        self::assertSame(4, $batch->rows()->where('status', 'invalid')->count());
        self::assertStringContainsString('duplicated', implode(' ', $batch->rows()->where('row_number', 6)->firstOrFail()->errors));

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('selectedBatchId', $batch->id)
            ->call('approve')
            ->assertHasNoErrors();

        $batch->refresh();
        self::assertSame('ready_for_review', $batch->status, 'A requester with approval permission still cannot approve their own batch.');
        self::assertDatabaseMissing('products', ['item_code' => $itemCode]);

        Livewire::actingAs($reviewer)
            ->test('catalog::product-import')
            ->set('selectedBatchId', $batch->id)
            ->call('approve')
            ->assertHasNoErrors();

        $batch->refresh();
        self::assertSame('completed', $batch->status);
        self::assertSame(1, $batch->rows()->where('status', 'created')->count());
        self::assertSame(4, $batch->rows()->where('status', 'invalid')->count());
        $product = Product::query()->where('item_code', $itemCode)->firstOrFail();
        self::assertSame('Valid item', $product->name_en);
        self::assertSame('standard', $product->product_type, 'Blank optional product type uses the documented standard default.');
        self::assertNull($product->average_cost, 'A user without cost-view permission must not import cost.');
        self::assertDatabaseMissing('products', ['item_code' => 'QI-'.$this->suffix.'-02']);
        self::assertDatabaseMissing('products', ['item_code' => 'QI-'.$this->suffix.'-03']);
        self::assertDatabaseMissing('products', ['item_code' => 'QI-'.$this->suffix.'-04']);
    }

    public function test_source_columns_can_be_mapped_to_supported_product_fields(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create']);
        $upload = $this->productWorkbook(
            $company->code,
            [['item_code' => 'QI-'.$this->suffix.'-31', 'name_ar' => 'منتج مخصص', 'name_en' => 'Mapped product', 'category_code' => $category->code, 'sale_price' => '24.00']],
            ['SKU', 'Arabic product', 'English product', 'Category', 'Selling price'],
            ['SKU' => 'item_code', 'Arabic product' => 'name_ar', 'English product' => 'name_en', 'Category' => 'category_code', 'Selling price' => 'sale_price'],
        );

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('importFile', $upload)
            ->call('stage')
            ->assertHasNoErrors()
            ->assertSee('Map columns and validate rows');

        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();
        self::assertSame(['sku', 'arabic_product', 'english_product', 'category', 'selling_price'], $batch->headers);

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->call('selectBatch', $batch->id)
            ->set('columnMapping', [
                'sku' => 'item_code',
                'arabic_product' => 'name_ar',
                'english_product' => 'name_en',
                'category' => 'category_code',
                'selling_price' => 'sale_price',
            ])
            ->call('mapColumns')
            ->assertHasNoErrors()
            ->assertSee('Valid: 1');

        self::assertSame(1, $batch->fresh()->valid_rows);
        self::assertSame('QI-'.$this->suffix.'-31', $batch->rows()->firstOrFail()->mapped_data['item_code']);
    }

    public function test_formula_cells_are_rejected_and_error_export_neutralizes_formula_text(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.export']);
        $upload = $this->formulaWorkbook($company->code, $category->code);

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('importFile', $upload)
            ->call('stage')
            ->assertHasNoErrors();

        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();
        self::assertSame(1, $batch->invalid_rows);
        self::assertStringContainsString('Formula cells are not accepted', implode(' ', $batch->rows()->firstOrFail()->errors));

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->call('selectBatch', $batch->id)
            ->call('mapColumns')
            ->assertHasNoErrors();
        self::assertSame(0, $batch->fresh()->valid_rows);
        self::assertSame(1, $batch->fresh()->invalid_rows);

        $response = $this->actingAs($writer)->get(route('catalog.products.import.errors', $batch));
        $response->assertOk();
        self::assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        self::assertDatabaseMissing('products', ['item_code' => 'QI-'.$this->suffix.'-41']);
    }

    public function test_user_without_edit_permission_cannot_stage_update_existing_mode(): void
    {
        [$company] = $this->catalogScope();
        $creator = $this->userWithPermissions('create-only', $company, ['products_categories_brands.view', 'products_categories_brands.create']);

        Livewire::actingAs($creator)
            ->test('catalog::product-import')
            ->set('mode', 'update_existing')
            ->call('stage')
            ->assertForbidden();

        self::assertDatabaseMissing('product_import_batches', ['created_by' => $creator->id]);
    }

    public function test_external_workbook_gets_actionable_template_guidance_instead_of_a_generic_error(): void
    {
        [$company] = $this->catalogScope();
        $creator = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create']);
        $upload = $this->productWorkbook($company->code, [], schema: 'external.product-export.v1');

        Livewire::actingAs($creator)
            ->test('catalog::product-import')
            ->set('importFile', $upload)
            ->call('stage')
            ->assertHasErrors(['importFile'])
            ->assertSee('Download the current company-specific XLSX template')
            ->assertDontSee('Unable to complete the request');

        self::assertDatabaseMissing('product_import_batches', ['created_by' => $creator->id]);
    }

    public function test_csv_upload_is_clearly_rejected_because_only_versioned_company_xlsx_is_supported(): void
    {
        [$company] = $this->catalogScope();
        $creator = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create']);
        $upload = UploadedFile::fake()->createWithContent('external-products.csv', "item_code,name_ar,name_en\nQI-EXTERNAL,اسم,Name\n");

        Livewire::actingAs($creator)
            ->test('catalog::product-import')
            ->set('importFile', $upload)
            ->call('stage')
            ->assertHasErrors(['importFile'])
            ->assertSee('Only the current company-specific XLSX template is supported.');

        self::assertDatabaseMissing('product_import_batches', ['created_by' => $creator->id]);
    }

    /** @return array{Company, Category} */
    private function catalogScope(): array
    {
        $scopeSuffix = strtoupper(Str::random(6));
        $company = Company::query()->create([
            'code' => 'QI'.$scopeSuffix,
            'name_ar' => 'شركة اختبار',
            'name_en' => 'QA company',
            'status' => 'active',
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'QIB'.$scopeSuffix,
            'name_ar' => 'فرع اختبار',
            'name_en' => 'QA branch',
            'status' => 'active',
        ]);
        Store::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'QIS'.$scopeSuffix,
            'type' => 'selling',
            'name_ar' => 'مخزن اختبار',
            'name_en' => 'QA store',
            'status' => 'active',
        ]);
        $category = Category::query()->create([
            'code' => 'QIC'.$scopeSuffix,
            'name_ar' => 'فئة اختبار',
            'name_en' => 'QA category',
            'status' => 'active',
        ]);

        return [$company, $category];
    }

    /** @param list<string> $permissionCodes */
    private function userWithPermissions(string $label, Company $company, array $permissionCodes): User
    {
        $branchId = Store::query()->where('company_id', $company->id)->value('branch_id');
        $role = Role::query()->create([
            'code' => 'qi-'.$label.'-'.$this->suffix,
            'name_ar' => 'دور اختبار',
            'name_en' => 'QA '.$label,
            'status' => 'active',
        ]);
        $permissionIds = [];
        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = array_pad(explode('.', $permissionCode, 2), 2, 'view');
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $module, 'action' => $action, 'sensitivity' => 'normal', 'status' => 'active'],
            );
            $permissionIds[] = $permission->id;
        }
        $role->permissions()->sync($permissionIds);
        $user = User::factory()->create([
            'username' => 'qi-'.$label.'-'.$this->suffix,
            'status' => 'active',
        ]);
        $this->createdUserIds[] = $user->id;
        $user->roles()->sync([$role->id]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'branch_id' => $branchId, 'status' => 'active']);

        return $user->fresh();
    }

    /** @param list<array<string, mixed>> $records */
    private function productWorkbook(string $companyCode, array $records, ?array $headers = null, array $sourceTargets = [], string $schema = 'toyjoy.products.staged.v2'): UploadedFile
    {
        $headers ??= StageProductImportAction::supportedFields();
        $rows = array_map(
            fn (array $record): array => array_map(fn (string $header): mixed => $record[$sourceTargets[$header] ?? $header] ?? null, $headers),
            $records,
        );
        $response = app(MasterDataDocument::class)->importTemplate(
            'products-import-template.xlsx',
            $schema,
            $headers,
            $rows,
            [
                ['ar' => 'تعليمات اختبار', 'en' => 'QA template instructions'],
                ['ar' => 'لا تغير الرؤوس', 'en' => 'Keep the headers unchanged.'],
            ],
            companyIdentity: $companyCode.' — QA company',
        );
        $path = $response->getFile()->getPathname();
        $content = file_get_contents($path);
        unlink($path);

        self::assertIsString($content);

        return UploadedFile::fake()->createWithContent('products-import-template.xlsx', $content);
    }

    private function formulaWorkbook(string $companyCode, string $categoryCode): UploadedFile
    {
        $basePath = tempnam(sys_get_temp_dir(), 'qa-product-import-');
        $path = $basePath.'.xlsx';
        unlink($basePath);
        $writer = WriterFactory::createFromFile($path);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Instructions');
        $schema = 'toyjoy.products.staged.v2';
        $writer->addRow(Row::fromValues(['Import instructions', $schema]));
        $writer->addRow(Row::fromValues(['Schema identity', $schema]));
        $writer->addRow(Row::fromValues(['Generated at', now()->utc()->toIso8601String()]));
        $writer->addRow(Row::fromValues(['Company scope', $companyCode.' — QA company']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Data Entry');
        $headers = StageProductImportAction::supportedFields();
        $writer->addRow(Row::fromValues($headers));
        $record = [
            'item_code' => 'QI-'.$this->suffix.'-41',
            'name_ar' => 'نص معادلة',
            'name_en' => '=HYPERLINK("https://example.invalid","unsafe")',
            'category_code' => $categoryCode,
            'product_type' => 'service',
            'sale_price' => '20.00',
        ];
        $writer->addRow(Row::fromValues(array_map(fn (string $header): mixed => $record[$header] ?? null, $headers)));
        $writer->close();
        $content = file_get_contents($path);
        unlink($path);

        self::assertIsString($content);

        return UploadedFile::fake()->createWithContent('products-formula-import.xlsx', $content);
    }

    /** @param list<array<string, mixed>> $records */
    private function stageAndMap(User $writer, string $companyCode, array $records, string $mode = 'create_only'): ProductImportBatch
    {
        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('mode', $mode)
            ->set('importFile', $this->productWorkbook($companyCode, $records))
            ->call('stage')
            ->assertHasNoErrors();

        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->call('selectBatch', $batch->id)
            ->call('mapColumns')
            ->assertHasNoErrors();

        return $batch->fresh();
    }

    public function test_create_only_approval_rechecks_identity_and_rolls_back_earlier_valid_rows(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('writer', $company, ['products_categories_brands.view', 'products_categories_brands.create']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.approve']);
        $firstCode = 'QI-'.$this->suffix.'-11';
        $lateCode = 'QI-'.$this->suffix.'-12';
        $batch = $this->stageAndMap($writer, $company->code, [
            ['item_code' => $firstCode, 'name_ar' => 'الأول', 'name_en' => 'First', 'category_code' => $category->code, 'product_type' => 'service', 'sale_price' => '10.00'],
            ['item_code' => $lateCode, 'name_ar' => 'الثاني', 'name_en' => 'Second', 'category_code' => $category->code, 'product_type' => 'service', 'sale_price' => '20.00'],
        ]);

        self::assertSame(2, $batch->valid_rows);
        Product::factory()->create([
            'item_code' => $lateCode,
            'category_id' => $category->id,
            'name_ar' => 'سجل سابق',
            'name_en' => 'Pre-existing record',
            'average_cost' => '75.00',
        ]);

        $this->actingAs($reviewer);
        try {
            app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
            self::fail('A Create Only batch must not update a product that appeared after row validation.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Create Only', $exception->getMessage());
        }

        self::assertDatabaseMissing('products', ['item_code' => $firstCode]);
        self::assertDatabaseHas('products', ['item_code' => $lateCode, 'name_en' => 'Pre-existing record', 'average_cost' => '75.00']);
        self::assertSame('ready_for_review', $batch->fresh()->status);
        self::assertSame(2, $batch->rows()->where('status', 'valid')->count());
    }

    public function test_update_existing_changes_importable_fields_without_replacing_cost_or_item_code(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('editor', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit', 'products_categories_brands.approve', 'products_categories_brands.cost_view']);
        $itemCode = 'QI-'.$this->suffix.'-21';
        $existing = Product::factory()->create([
            'item_code' => $itemCode,
            'category_id' => $category->id,
            'name_ar' => 'اسم سابق',
            'name_en' => 'Old name',
            'average_cost' => '77.25',
            'sale_price' => '25.00',
        ]);
        $batch = $this->stageAndMap($writer, $company->code, [[
            'item_code' => $itemCode,
            'name_ar' => 'اسم محدث',
            'name_en' => 'Updated name',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'average_cost' => '999.99',
            'sale_price' => '30.00',
        ]], 'update_existing');

        self::assertSame(1, $batch->valid_rows);
        Livewire::actingAs($reviewer)
            ->test('catalog::product-import')
            ->set('selectedBatchId', $batch->id)
            ->call('approve')
            ->assertHasNoErrors();

        $existing->refresh();
        self::assertSame($itemCode, $existing->item_code);
        self::assertSame('Updated name', $existing->name_en);
        self::assertSame('30.00', $existing->sale_price);
        self::assertSame('77.25', $existing->average_cost, 'Update Existing must preserve cost when the importer cannot view cost.');
        self::assertSame(1, $batch->fresh()->rows()->where('status', 'updated')->count());
    }

    public function test_update_existing_cannot_map_or_modify_product_assigned_to_another_company(): void
    {
        [$company, $category] = $this->catalogScope();
        [$foreignCompany] = $this->catalogScope();
        $writer = $this->userWithPermissions('editor', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.edit', 'products_categories_brands.approve']);
        $foreignStore = Store::query()->where('company_id', $foreignCompany->id)->firstOrFail();
        $itemCode = 'QI-'.$this->suffix.'-51';
        $foreignProduct = Product::factory()->create([
            'item_code' => $itemCode,
            'category_id' => $category->id,
            'name_ar' => 'منتج الشركة الأخرى',
            'name_en' => 'Foreign company product',
        ]);
        ProductStoreAssignment::query()->create([
            'company_id' => $foreignCompany->id,
            'branch_id' => $foreignStore->branch_id,
            'store_id' => $foreignStore->id,
            'product_id' => $foreignProduct->id,
            'status' => 'active',
            'created_by' => $writer->id,
            'updated_by' => $writer->id,
        ]);

        self::assertFalse(Product::query()->availableToCompany($company->id)->whereKey($foreignProduct->id)->exists());

        $batch = $this->stageAndMap($writer, $company->code, [[
            'item_code' => $itemCode,
            'name_ar' => 'محاولة تحديث غير مصرح بها',
            'name_en' => 'Unauthorized cross-company update',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
        ]], 'update_existing');

        self::assertSame(0, $batch->valid_rows);
        self::assertSame(1, $batch->invalid_rows);
        self::assertStringContainsString('does not exist', implode(' ', $batch->rows()->firstOrFail()->errors));
        Livewire::actingAs($reviewer)
            ->test('catalog::product-import')
            ->set('selectedBatchId', $batch->id)
            ->call('approve')
            ->assertHasNoErrors();

        $foreignProduct->refresh();
        self::assertSame('Foreign company product', $foreignProduct->name_en);
        self::assertSame('ready_for_review', $batch->fresh()->status);
    }

    public function test_update_existing_rechecks_company_scope_at_approval(): void
    {
        [$company, $category] = $this->catalogScope();
        [$foreignCompany] = $this->catalogScope();
        $writer = $this->userWithPermissions('editor', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.edit', 'products_categories_brands.approve']);
        $itemCode = 'QI-'.$this->suffix.'-52';
        $product = Product::factory()->create([
            'item_code' => $itemCode,
            'category_id' => $category->id,
            'name_ar' => 'اسم قبل النقل',
            'name_en' => 'Before reassignment',
        ]);
        $batch = $this->stageAndMap($writer, $company->code, [[
            'item_code' => $itemCode,
            'name_ar' => 'اسم بعد النقل',
            'name_en' => 'After reassignment',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
        ]], 'update_existing');
        self::assertSame(1, $batch->valid_rows);

        $foreignStore = Store::query()->where('company_id', $foreignCompany->id)->firstOrFail();
        ProductStoreAssignment::query()->create([
            'company_id' => $foreignCompany->id,
            'branch_id' => $foreignStore->branch_id,
            'store_id' => $foreignStore->id,
            'product_id' => $product->id,
            'status' => 'active',
            'created_by' => $writer->id,
            'updated_by' => $writer->id,
        ]);

        $this->actingAs($reviewer);
        try {
            app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
            self::fail('Approval must recheck the product is still available to the mapped company.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('removed after validation', $exception->getMessage());
        }

        $product->refresh();
        self::assertSame('Before reassignment', $product->name_en);
        self::assertSame('ready_for_review', $batch->fresh()->status);
    }

    public function test_update_existing_rejects_a_product_changed_after_mapping(): void
    {
        [$company, $category] = $this->catalogScope();
        $writer = $this->userWithPermissions('editor', $company, ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit']);
        $reviewer = $this->userWithPermissions('reviewer', $company, ['products_categories_brands.view', 'products_categories_brands.edit', 'products_categories_brands.approve']);
        $itemCode = 'QI-'.$this->suffix.'-53';
        $product = Product::factory()->create([
            'item_code' => $itemCode,
            'category_id' => $category->id,
            'name_ar' => 'اسم متزامن',
            'name_en' => 'Original name',
        ]);
        $mappedVersion = $product->lock_version;
        $batch = $this->stageAndMap($writer, $company->code, [[
            'item_code' => $itemCode,
            'name_ar' => 'اسم الاستيراد',
            'name_en' => 'Imported name',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
        ]], 'update_existing');
        self::assertSame($mappedVersion, $batch->rows()->firstOrFail()->mapped_data['_import_lock_version']);

        $product->forceFill(['name_en' => 'Concurrent edit', 'lock_version' => $mappedVersion + 1])->save();

        $this->actingAs($reviewer);
        try {
            app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
            self::fail('Approval must reject a target changed after row mapping.');
        } catch (StaleCatalogRecordException $exception) {
            self::assertStringContainsString('changed after the import was validated', $exception->getMessage());
        }

        $product->refresh();
        self::assertSame('Concurrent edit', $product->name_en);
        self::assertSame($mappedVersion + 1, $product->lock_version);
        self::assertSame('ready_for_review', $batch->fresh()->status);
        self::assertNull($batch->fresh()->approved_at);
    }
}
