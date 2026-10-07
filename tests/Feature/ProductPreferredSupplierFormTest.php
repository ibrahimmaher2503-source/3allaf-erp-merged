<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
