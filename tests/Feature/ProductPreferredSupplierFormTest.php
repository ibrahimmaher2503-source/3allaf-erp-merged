<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ProductPreferredSupplierFormTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_auto_coded_product_creation_does_not_offer_no_supplier_but_saved_identity_can(): void
    {
        Company::factory()->create();
        $category = Category::factory()->create();
        $actor = $this->createCatalogEditor();
        $this->actingAs($actor);

        Livewire::test('catalog::product-form')
            ->assertDontSee(__('No supplier'))
            ->assertSee(__('Select supplier to generate a product code'));

        $product = Product::factory()->for($category)->create();

        Livewire::test('catalog::product-form', ['product' => $product])
            ->assertSee(__('No supplier'));
    }

    public function test_missing_generated_code_supplier_returns_a_clear_field_error_without_writes(): void
    {
        Company::factory()->create();
        $category = Category::factory()->create();
        $actor = $this->createCatalogEditor();
        $beforeProductCount = Product::query()->count();
        $this->actingAs($actor);

        foreach (['en', 'ar', 'ar-EG'] as $locale) {
            app()->setLocale($locale);

            Livewire::test('catalog::product-form')
                ->set('productForm.barcode_registration_type', 'international')
                ->set('productForm.name_ar', 'اختبار المورد')
                ->set('productForm.name_en', 'Supplier validation test')
                ->set('productForm.model_number', 'QA-SUPPLIER-231')
                ->set('productForm.category_id', (string) $category->id)
                ->set('productForm.sale_price', '25.00')
                ->set('supplierBarcode', '6291234500005')
                ->call('save')
                ->assertHasErrors('productForm.preferred_supplier_id')
                ->assertSee(__('Select an active supplier to generate the product code.'))
                ->assertDontSee('The product form.preferred supplier id field is required.');
        }

        app()->setLocale('en');

        self::assertSame($beforeProductCount, Product::query()->count());
    }

    public function test_international_barcode_reports_invalid_supplier_code_on_final_save(): void
    {
        Company::factory()->create();
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'code' => 'QA-M10-A',
            'name_ar' => 'مورد اختبار',
            'name_en' => 'QA supplier',
            'status' => 'active',
        ]);
        $actor = $this->createCatalogEditor();
        $beforeProductCount = Product::query()->count();
        $this->actingAs($actor);

        Livewire::test('catalog::product-form')
            ->set('productForm.barcode_registration_type', 'international')
            ->set('productForm.name_ar', 'اختبار المورد')
            ->set('productForm.name_en', 'International supplier validation')
            ->set('productForm.model_number', 'QA-SUPPLIER-INT-7')
            ->set('productForm.category_id', (string) $category->id)
            ->set('productForm.preferred_supplier_id', (string) $supplier->id)
            ->set('productForm.sale_price', '25.00')
            ->set('supplierBarcode', '6291234500005')
            ->call('save')
            ->assertHasErrors('productForm.preferred_supplier_id')
            ->assertSee('The selected supplier code must be numeric with at least four digits or use the canonical SUP-#### format before a local product code can be generated.');

        self::assertSame($beforeProductCount, Product::query()->count());
    }

    public function test_noncanonical_supplier_can_remain_on_an_incomplete_draft_without_generating_a_code(): void
    {
        Company::factory()->create();
        $category = Category::factory()->create();
        $supplier = Supplier::query()->create([
            'code' => 'QA-M10-A',
            'name_ar' => 'مورد اختبار',
            'name_en' => 'QA supplier',
            'status' => 'active',
        ]);
        $this->actingAs($this->createCatalogEditor());

        Livewire::test('catalog::product-form')
            ->set('productForm.barcode_registration_type', 'international')
            ->set('productForm.name_ar', 'مسودة المورد')
            ->set('productForm.name_en', 'Supplier draft')
            ->set('productForm.model_number', 'QA-SUPPLIER-DRAFT-7')
            ->set('productForm.category_id', (string) $category->id)
            ->set('productForm.preferred_supplier_id', (string) $supplier->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $draft = Product::query()->where('model_number', 'QA-SUPPLIER-DRAFT-7')->firstOrFail();
        self::assertNull($draft->item_code);
        self::assertSame('inactive', $draft->status);
    }

    public function test_preferred_supplier_switch_records_before_and_after_audit_events(): void
    {
        Company::factory()->create();
        $product = Product::factory()->create();
        $first = Supplier::query()->create(['code' => 'QA-HISTORY-A', 'name_ar' => 'مورد أ', 'name_en' => 'History supplier A', 'status' => 'active']);
        $second = Supplier::query()->create(['code' => 'QA-HISTORY-B', 'name_ar' => 'مورد ب', 'name_en' => 'History supplier B', 'status' => 'active']);
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        Gate::before(static fn (): bool => true);
        $this->actingAs($actor);

        $action = app(SaveProductSupplierAction::class);
        $action->execute(['product_id' => $product->id, 'supplier_id' => $first->id, 'is_preferred' => true]);
        $action->execute(['product_id' => $product->id, 'supplier_id' => $second->id, 'is_preferred' => true]);

        self::assertTrue(ProductSupplier::query()->where('product_id', $product->id)->where('supplier_id', $second->id)->where('is_preferred', true)->exists());
        self::assertFalse(ProductSupplier::query()->where('product_id', $product->id)->where('supplier_id', $first->id)->where('is_preferred', true)->exists());
        self::assertDatabaseHas('audit_logs', ['event' => 'change_product_supplier_preference', 'source_id' => (string) ProductSupplier::query()->where('product_id', $product->id)->where('supplier_id', $first->id)->value('id')]);
        self::assertSame(3, AuditLog::query()->where('event', 'change_product_supplier_preference')->count());
    }

    public function test_international_barcode_validation_reports_duplicate_format_and_check_digit_reasons(): void
    {
        Company::factory()->create();
        $category = Category::factory()->create();
        $actor = $this->createCatalogEditor();
        $existingProduct = Product::factory()->for($category)->create();
        Barcode::query()->create([
            'product_id' => $existingProduct->id,
            'barcode' => '6291234500013',
            'source' => 'international',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $beforeProductCount = Product::query()->count();
        $this->actingAs($actor);
        app()->setLocale('en');

        foreach ([
            ['6291234500013', 'This barcode is already assigned and cannot be silently reassigned.'],
            ['ABC123', 'International barcodes must be a valid GTIN-8, UPC-12, EAN-13, or GTIN-14 number.'],
            ['6291234500012', 'The international barcode check digit is invalid.'],
        ] as [$barcode, $message]) {
            $component = Livewire::actingAs($actor)
                ->test('catalog::products')
                ->call('openBarcodeModal', $existingProduct->id)
                ->set('barcodeForm.source', 'supplier')
                ->set('barcodeForm.barcode', $barcode)
                ->call('addBarcode')
                ->assertHasErrors('barcodeForm.barcode')
                ->assertSee($message);
            self::assertSame([$message], $component->instance()->getErrorBag()->get('barcodeForm.barcode'));

            self::assertSame($beforeProductCount, Product::query()->count());
            self::assertSame(1, Barcode::query()->where('barcode', '6291234500013')->count());
        }

        Livewire::actingAs($actor)
            ->test('catalog::product-form')
            ->set('productForm.item_code', 'QA-BARCODE-'.Str::upper(Str::random(8)))
            ->set('productForm.name_ar', 'اختبار الباركود')
            ->set('productForm.name_en', 'Barcode validation test')
            ->set('productForm.model_number', 'QA-BARCODE-'.Str::upper(Str::random(8)))
            ->set('productForm.category_id', (string) $category->id)
            ->set('productForm.sale_price', '25.00')
            ->set('supplierBarcode', '6291234500014')
            ->call('save')
            ->assertHasErrors('supplierBarcode')
            ->assertSee('The international barcode check digit is invalid.');

        self::assertSame($beforeProductCount, Product::query()->count());
    }

    private function createCatalogEditor(): User
    {
        $permissions = collect(['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'])
            ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
                ['code' => $code],
                ['module' => 'products_categories_brands', 'action' => Str::afterLast($code, '.'), 'sensitivity' => 'medium', 'status' => 'active'],
            ));
        $role = Role::query()->create([
            'code' => 'qa-product-supplier-'.Str::lower(Str::random(8)),
            'name_ar' => 'محرر منتج QA',
            'name_en' => 'QA Product Editor',
            'status' => 'active',
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());

        $actor = User::factory()->create(['is_super_admin' => true]);
        $actor->roles()->attach($role->getKey());
        $actor->refresh();
        self::assertTrue($actor->hasPermission('products_categories_brands.view'));
        self::assertTrue($actor->hasPermission('products_categories_brands.create'));

        return $actor;
    }
}
