<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException;
use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Permission;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * MD-02..MD-05, PUR-01..PUR-02, and TSK-010..013 catalog/supplier controls.
 * These tests exercise actions rather than relying on UI controls for security.
 */
final class CatalogMasterBehaviorTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CanonicalAuthorizationSeeder::class);
        $this->actingAs($this->administrator('catalog-owner'));
    }

    public function test_item_code_is_normalized_and_immutable_with_stale_update_protection(): void
    {
        $category = $this->category('TOYS');
        $product = app(SaveProductAction::class)->execute([
            ...$this->productData($category),
            'item_code' => ' toy-001 ',
        ]);

        self::assertSame('TOY-001', $product->item_code);
        $updated = app(SaveProductAction::class)->execute([
            ...$this->productData($category),
            'item_code' => 'TOY-001',
            'name_en' => 'Updated Toy',
        ], $product->id, $product->lock_version);
        self::assertSame(1, $updated->lock_version);

        $this->expectException(ImmutableItemCodeChangeException::class);
        app(SaveProductAction::class)->execute([
            ...$this->productData($category),
            'item_code' => 'TOY-999',
        ], $product->id, $updated->lock_version);
    }

    public function test_product_update_rejects_a_stale_lock_version(): void
    {
        $category = $this->category('STALE');
        $product = app(SaveProductAction::class)->execute($this->productData($category));
        app(SaveProductAction::class)->execute([
            ...$this->productData($category),
            'item_code' => $product->item_code,
            'name_en' => 'First update',
        ], $product->id, 0);

        $this->expectException(StaleCatalogRecordException::class);
        app(SaveProductAction::class)->execute([
            ...$this->productData($category),
            'item_code' => $product->item_code,
            'name_en' => 'Stale update',
        ], $product->id, 0);
    }

    public function test_category_hierarchy_rejects_self_and_descendant_cycles(): void
    {
        $root = $this->category('ROOT');
        $child = app(SaveCategoryAction::class)->execute([
            'code' => 'CHILD', 'name_ar' => 'طفل', 'name_en' => 'Child',
            'parent_id' => $root->id, 'status' => 'active', 'sort_order' => 0,
        ]);

        try {
            app(SaveCategoryAction::class)->execute([
                'code' => 'ROOT', 'name_ar' => 'جذر', 'name_en' => 'Root',
                'parent_id' => $root->id, 'status' => 'active', 'sort_order' => 0,
            ], $root->id);
            self::fail('A category cannot be its own parent.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('own parent', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        app(SaveCategoryAction::class)->execute([
            'code' => 'CHILD', 'name_ar' => 'طفل', 'name_en' => 'Child',
            'parent_id' => $child->id, 'status' => 'active', 'sort_order' => 0,
        ], $root->id);
    }

    public function test_barcode_allocation_is_idempotent_and_duplicate_supplier_codes_are_rejected(): void
    {
        $product = app(SaveProductAction::class)->execute($this->productData($this->category('BAR')));
        $action = app(AddBarcodeAction::class);

        $local = $action->allocateLocalBarcode($product->id, '1234', 'request-1');
        $replayed = $action->allocateLocalBarcode($product->id, '1234', 'request-1');
        self::assertSame($local->id, $replayed->id);
        self::assertSame('1234000001', $local->barcode);
        self::assertSame('local', $product->fresh()->barcode_mode);

        $supplier = $action->addSupplierBarcode($product->id, 'SUP-001');
        self::assertSame('mixed', $product->fresh()->barcode_mode);
        self::assertTrue($supplier->is_primary === false);

        $this->expectException(InvalidArgumentException::class);
        $action->addSupplierBarcode($product->id, 'SUP-001');
    }

    public function test_preferred_supplier_is_unique_per_product_and_actual_link_remains(): void
    {
        $product = app(SaveProductAction::class)->execute($this->productData($this->category('SUP')));
        $first = app(SaveSupplierAction::class)->execute($this->supplierData('S-001'));
        $second = app(SaveSupplierAction::class)->execute($this->supplierData('S-002'));

        app(SaveProductSupplierAction::class)->execute([
            'product_id' => $product->id, 'supplier_id' => $first->id,
            'supplier_item_code' => 'FIRST-ITEM', 'is_preferred' => true,
        ]);
        app(SaveProductSupplierAction::class)->execute([
            'product_id' => $product->id, 'supplier_id' => $second->id,
            'supplier_item_code' => 'SECOND-ITEM', 'is_preferred' => true,
        ]);

        self::assertSame(1, ProductSupplier::query()->where('product_id', $product->id)->where('is_preferred', true)->count());
        self::assertFalse(ProductSupplier::query()->where('product_id', $product->id)->where('supplier_id', $first->id)->value('is_preferred'));
        self::assertSame('SECOND-ITEM', $product->fresh()->productSuppliers()->where('supplier_id', $second->id)->value('supplier_item_code'));
        self::assertCount(2, $product->fresh()->productSuppliers);
        self::assertSame(2, Supplier::query()->count());
    }

    public function test_preferred_supplier_change_requires_its_separate_permission(): void
    {
        $product = app(SaveProductAction::class)->execute($this->productData($this->category('AUTH')));
        $supplier = app(SaveSupplierAction::class)->execute($this->supplierData('S-AUTH'));
        $purchasing = $this->userWith('tsk013-no-preferred', ['purchasing-officer']);
        $preferredPermission = Permission::query()->where('code', 'suppliers.preferred_change')->firstOrFail();
        $purchasing->roles()->firstOrFail()->permissions()->detach($preferredPermission->id);

        $this->actingAs($purchasing);
        try {
            app(SaveProductSupplierAction::class)->execute([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_item_code' => 'AUTH-ITEM',
                'is_preferred' => true,
            ]);
            self::fail('A preferred-supplier change without the sensitive permission must be denied.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        self::assertDatabaseMissing('product_suppliers', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function productData(Category $category): array
    {
        return [
            'item_code' => 'ITEM-'.strtoupper($category->code),
            'name_ar' => 'لعبة '.$category->code,
            'name_en' => 'Toy '.$category->code,
            'category_id' => $category->id,
            'product_type' => 'standard',
            'status' => 'active',
            'colour' => 'Red',
            'size' => 'Medium',
            'target_age' => '6+',
            'keywords_en' => 'toy, test',
            'fractional_quantity' => false,
        ];
    }

    private function category(string $code): Category
    {
        return app(SaveCategoryAction::class)->execute([
            'code' => $code, 'name_ar' => 'تصنيف '.$code, 'name_en' => 'Category '.$code,
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function supplierData(string $code): array
    {
        return [
            'code' => $code, 'name_ar' => 'مورد '.$code, 'name_en' => 'Supplier '.$code,
            'status' => 'active', 'payment_terms' => '30 days',
        ];
    }
}
