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
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\MasterDataDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ProductImportCompanyScopeReviewTest extends TestCase
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
            ProductImportBatch::query()
                ->whereIn('created_by', $this->createdUserIds)
                ->pluck('storage_path')
                ->filter()
                ->each(fn (string $path): bool => Storage::disk((string) config('attachments.disk', 'local'))->delete($path));
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_update_existing_cannot_change_product_assigned_only_to_another_company(): void
    {
        [$foreignCompany, $foreignStore] = $this->companyAndStore('foreign');
        [$importCompany, $importStore] = $this->companyAndStore('import');
        $category = Category::query()->create([
            'code' => 'QIR'.$this->suffix,
            'name_ar' => 'فئة مراجعة',
            'name_en' => 'Review category',
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'item_code' => 'QIR-'.$this->suffix,
            'category_id' => $category->id,
            'name_ar' => 'منتج الشركة الأخرى',
            'name_en' => 'Foreign company product',
            'average_cost' => '45.00',
        ]);
        ProductStoreAssignment::query()->create([
            'company_id' => $foreignCompany->id,
            'branch_id' => $foreignStore->branch_id,
            'store_id' => $foreignStore->id,
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        self::assertFalse(Product::query()->availableToCompany((int) $importCompany->id)->whereKey($product->id)->exists());

        $writer = $this->scopedImporter('writer', $importCompany, $importStore, [
            'products_categories_brands.view',
            'products_categories_brands.create',
            'products_categories_brands.edit',
        ]);
        $reviewer = $this->scopedImporter('reviewer', $importCompany, $importStore, [
            'products_categories_brands.view',
            'products_categories_brands.create',
            'products_categories_brands.edit',
            'products_categories_brands.approve',
        ]);
        $workbook = $this->workbook($importCompany->code, [[
            'item_code' => $product->item_code,
            'name_ar' => 'تعديل غير مصرح',
            'name_en' => 'Unauthorized cross-company update',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
            'sale_price' => '99.00',
        ]]);

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('mode', 'update_existing')
            ->set('importFile', $workbook)
            ->call('stage')
            ->assertHasNoErrors();

        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();
        $this->actingAs($writer);
        app(StageProductImportAction::class)->applyMapping(
            $batch,
            array_combine(StageProductImportAction::supportedFields(), StageProductImportAction::supportedFields()),
        );

        $batch->refresh();
        if ($batch->valid_rows > 0) {
            $this->actingAs($reviewer);
            try {
                app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
            } catch (\Throwable) {
                // The approval guard is expected to refuse an out-of-company target.
            }
        }

        self::assertSame(0, $batch->fresh()->valid_rows, 'The foreign-company product must be rejected while mapping.');
        self::assertSame(1, $batch->fresh()->invalid_rows);
        self::assertSame('Foreign company product', $product->fresh()->name_en);
        self::assertSame('45.00', $product->fresh()->average_cost);
    }

    public function test_update_existing_rejects_product_changed_after_mapping(): void
    {
        [$company, $store] = $this->companyAndStore('stale');
        $category = Category::query()->create([
            'code' => 'QIS'.$this->suffix,
            'name_ar' => 'فئة مراجعة',
            'name_en' => 'Review category',
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'item_code' => 'QIS-'.$this->suffix,
            'category_id' => $category->id,
            'name_ar' => 'اسم أصلي',
            'name_en' => 'Original name',
            'average_cost' => '45.00',
            'sale_price' => '20.00',
        ]);
        $writer = $this->scopedImporter('stale-writer', $company, $store, [
            'products_categories_brands.view',
            'products_categories_brands.create',
            'products_categories_brands.edit',
        ]);
        $reviewer = $this->scopedImporter('stale-reviewer', $company, $store, [
            'products_categories_brands.view',
            'products_categories_brands.create',
            'products_categories_brands.edit',
            'products_categories_brands.approve',
        ]);
        $workbook = $this->workbook($company->code, [[
            'item_code' => $product->item_code,
            'name_ar' => 'تعديل مستورد قديم',
            'name_en' => 'Stale imported update',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
            'sale_price' => '30.00',
        ]]);

        Livewire::actingAs($writer)
            ->test('catalog::product-import')
            ->set('mode', 'update_existing')
            ->set('importFile', $workbook)
            ->call('stage')
            ->assertHasNoErrors();
        $batch = ProductImportBatch::query()->where('created_by', $writer->id)->latest('id')->firstOrFail();
        $this->actingAs($writer);
        app(StageProductImportAction::class)->applyMapping(
            $batch,
            array_combine(StageProductImportAction::supportedFields(), StageProductImportAction::supportedFields()),
        );

        $versionAtMapping = $product->fresh()->lock_version;
        app(SaveProductAction::class)->execute([
            'item_code' => $product->item_code,
            'name_ar' => 'اسم متزامن',
            'name_en' => 'Concurrent edit',
            'product_type' => 'standard',
            'status' => 'active',
            'category_id' => $category->id,
            'sale_price' => '25.00',
        ], $product->id, $versionAtMapping);

        $this->actingAs($reviewer);
        $approvalFailure = null;
        try {
            app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
        } catch (\Throwable $exception) {
            $approvalFailure = $exception;
        }

        self::assertSame('Concurrent edit', $product->fresh()->name_en);
        self::assertInstanceOf(StaleCatalogRecordException::class, $approvalFailure);
        self::assertSame('ready_for_review', $batch->fresh()->status);
    }

    public function test_company_context_cannot_change_between_source_staging_and_mapping(): void
    {
        [$sourceCompany] = $this->companyAndStore('source');
        [$currentCompany, $currentStore] = $this->companyAndStore('current');
        $sourceStore = Store::query()->where('company_id', $sourceCompany->id)->firstOrFail();
        $category = Category::query()->create([
            'code' => 'QIC'.$this->suffix,
            'name_ar' => 'فئة مراجعة',
            'name_en' => 'Review category',
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'item_code' => 'QIC-'.$this->suffix,
            'category_id' => $category->id,
            'name_ar' => 'منتج الشركة الحالية',
            'name_en' => 'Current company product',
            'average_cost' => '45.00',
        ]);
        ProductStoreAssignment::query()->create([
            'company_id' => $currentCompany->id,
            'branch_id' => $currentStore->branch_id,
            'store_id' => $currentStore->id,
            'product_id' => $product->id,
            'status' => 'active',
        ]);
        self::assertFalse(Product::query()->availableToCompany((int) $sourceCompany->id)->whereKey($product->id)->exists());
        self::assertTrue(Product::query()->availableToCompany((int) $currentCompany->id)->whereKey($product->id)->exists());

        $permissions = [
            'products_categories_brands.view',
            'products_categories_brands.create',
            'products_categories_brands.edit',
        ];
        $writer = $this->scopedImporter('context-writer', $sourceCompany, $sourceStore, $permissions);
        $reviewer = $this->scopedImporter('context-reviewer', $sourceCompany, $sourceStore, [
            ...$permissions,
            'products_categories_brands.approve',
        ]);
        UserStoreScope::query()->create(['user_id' => $writer->id, 'store_id' => $currentStore->id, 'status' => 'active']);
        UserStoreScope::query()->create(['user_id' => $reviewer->id, 'store_id' => $currentStore->id, 'status' => 'active']);

        $this->actingAs($writer);
        request()->merge(['company_id' => $sourceCompany->id]);
        $attachment = app(StoreAttachment::class)->execute($this->workbook($sourceCompany->code, [[
            'item_code' => $product->item_code,
            'name_ar' => 'تغيير من ملف الشركة الأخرى',
            'name_en' => 'Changed through another company workbook',
            'category_code' => $category->code,
            'product_type' => 'standard',
            'status' => 'active',
            'sale_price' => '99.00',
        ]]), 'import_source');
        $batch = app(StageProductImportAction::class)->stage(
            $attachment,
            'source-company-product-template.xlsx',
            'update_existing',
            $writer->id,
        );

        request()->merge(['company_id' => $currentCompany->id]);
        self::assertSame($currentCompany->id, request()->input('company_id'));
        self::assertSame($currentCompany->id, app(AuthorizedCompanyContext::class)->resolve($writer, request()->input('company_id'))->id);
        try {
            $batch = app(StageProductImportAction::class)->applyMapping(
                $batch,
                array_combine(StageProductImportAction::supportedFields(), StageProductImportAction::supportedFields()),
            );
        } catch (\Throwable) {
            // Failing closed when the selected company changed is acceptable.
        }

        $batch->refresh();
        if ($batch->valid_rows > 0) {
            $this->actingAs($reviewer);
            try {
                app(StageProductImportAction::class)->approve($batch, app(SaveProductAction::class));
            } catch (\Throwable) {
                // A scope recheck at approval is also required if mapping accepted the row.
            }
        }

        self::assertSame('Current company product', $product->fresh()->name_en);
        self::assertSame('45.00', $product->fresh()->average_cost);
    }

    /** @return array{Company, Store} */
    private function companyAndStore(string $label): array
    {
        $company = Company::query()->create([
            'code' => 'QI'.$label.$this->suffix,
            'name_ar' => 'شركة مراجعة '.$label,
            'name_en' => 'Review company '.$label,
            'status' => 'active',
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'QIB'.$label.$this->suffix,
            'name_ar' => 'فرع مراجعة',
            'name_en' => 'Review branch',
            'status' => 'active',
        ]);
        $store = Store::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'QIS'.$label.$this->suffix,
            'type' => 'selling',
            'name_ar' => 'منفذ مراجعة',
            'name_en' => 'Review store',
            'status' => 'active',
        ]);

        return [$company, $store];
    }

    /** @param list<string> $permissionCodes */
    private function scopedImporter(string $label, Company $company, Store $store, array $permissionCodes): User
    {
        $role = Role::query()->create([
            'code' => 'qir-'.$label.'-'.$this->suffix,
            'name_ar' => 'دور مراجعة',
            'name_en' => 'Review '.$label,
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
            'username' => 'qir-'.$label.'-'.$this->suffix,
            'status' => 'active',
        ]);
        $this->createdUserIds[] = $user->id;
        $user->roles()->sync([$role->id]);
        UserStoreScope::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    /** @param list<array<string, mixed>> $records */
    private function workbook(string $companyCode, array $records): UploadedFile
    {
        $headers = StageProductImportAction::supportedFields();
        $rows = array_map(
            fn (array $record): array => array_map(fn (string $header): mixed => $record[$header] ?? null, $headers),
            $records,
        );
        $response = app(MasterDataDocument::class)->importTemplate(
            'products-import-template.xlsx',
            'toyjoy.products.staged.v2',
            $headers,
            $rows,
            [['ar' => 'تعليمات اختبار', 'en' => 'QA template instructions']],
            companyIdentity: $companyCode.' — QA company',
        );
        $path = $response->getFile()->getPathname();
        $content = file_get_contents($path);
        unlink($path);

        self::assertIsString($content);

        return UploadedFile::fake()->createWithContent('products-import-template.xlsx', $content);
    }
}
