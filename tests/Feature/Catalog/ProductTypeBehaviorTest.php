<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * US-005 / PRC-02: type-specific catalog behavior is enforced at the action
 * and stock-posting boundaries, not only in the product form.
 */
final class ProductTypeBehaviorTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('product-type-owner'));
    }

    public function test_a_composite_requires_at_least_one_positive_component_without_partial_write(): void
    {
        $category = $this->category('TYPE-EMPTY');

        try {
            app(SaveProductAction::class)->execute([
                ...$this->productData($category, 'TYPE-COMPOSITE-EMPTY'),
                'product_type' => 'composite',
                'components' => [],
            ]);
            self::fail('A composite product without component quantities must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, Product::query()->count());
            self::assertSame(0, AuditLog::query()->count());
        }
    }

    public function test_a_composite_persists_active_positive_components_and_audits_the_composition(): void
    {
        $category = $this->category('TYPE-COMPOSITE');
        $first = app(SaveProductAction::class)->execute($this->productData($category, 'TYPE-COMPONENT-ONE'));
        $second = app(SaveProductAction::class)->execute($this->productData($category, 'TYPE-COMPONENT-TWO'));

        $composite = app(SaveProductAction::class)->execute([
            ...$this->productData($category, 'TYPE-COMPOSITE'),
            'product_type' => 'composite',
            'components' => [
                ['component_product_id' => $first->id, 'quantity' => '2.500'],
                ['component_product_id' => $second->id, 'quantity' => '1'],
            ],
        ]);

        self::assertSame('composite', $composite->product_type);
        self::assertSame([
            ['component_product_id' => $first->id, 'quantity' => '2.500000'],
            ['component_product_id' => $second->id, 'quantity' => '1.000000'],
        ], DB::table('product_components')->where('product_id', $composite->id)->orderBy('component_product_id')->get(['component_product_id', 'quantity'])->map(fn (object $row): array => [
            'component_product_id' => (int) $row->component_product_id,
            'quantity' => (string) $row->quantity,
        ])->all());
        self::assertTrue(AuditLog::query()->where('event', 'create_product_card')->where('source_id', (string) $composite->id)->whereJsonContains('after_values->components', [
            ['component_product_id' => $first->id, 'quantity' => '2.500000'],
            ['component_product_id' => $second->id, 'quantity' => '1.000000'],
        ])->exists());
    }

    public function test_a_composite_rejects_self_references_inactive_components_and_non_positive_quantities(): void
    {
        $category = $this->category('TYPE-INVALID');
        $inactive = app(SaveProductAction::class)->execute([
            ...$this->productData($category, 'TYPE-INACTIVE-COMPONENT'),
            'status' => 'inactive',
        ]);
        $candidate = app(SaveProductAction::class)->execute($this->productData($category, 'TYPE-SELF-COMPOSITE'));

        foreach ([
            [['component_product_id' => $candidate->id, 'quantity' => '1']],
            [['component_product_id' => $inactive->id, 'quantity' => '1']],
            [['component_product_id' => $candidate->id, 'quantity' => '0']],
        ] as $components) {
            try {
                app(SaveProductAction::class)->execute([
                    ...$this->productData($category, $candidate->item_code),
                    'product_type' => 'composite',
                    'components' => $components,
                ], $candidate->id, $candidate->fresh()->lock_version);
                self::fail('Invalid composite components must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame('standard', $candidate->fresh()->product_type);
                self::assertSame(0, DB::table('product_components')->where('product_id', $candidate->id)->count());
            }
        }
    }

    public function test_a_service_cannot_be_posted_to_stock(): void
    {
        $category = $this->category('TYPE-SERVICE');
        $service = app(SaveProductAction::class)->execute([
            ...$this->productData($category, 'TYPE-SERVICE'),
            'product_type' => 'service',
        ]);
        $store = $this->store($this->branch('TYPE-SERVICE-BR'), 'TYPE-SERVICE-ST');

        $this->expectException(InvalidArgumentException::class);
        app(PostInventoryMovement::class)->execute($service->id, $store->id, '1', 'opening_adjustment', '10.0000', 'TYPE-SERVICE-STOCK');

        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_a_product_type_cannot_change_after_stock_transaction_history_exists(): void
    {
        $category = $this->category('TYPE-HISTORY');
        $product = app(SaveProductAction::class)->execute($this->productData($category, 'TYPE-HISTORY-PRODUCT'));
        $store = $this->store($this->branch('TYPE-HISTORY-BR'), 'TYPE-HISTORY-ST');
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '1', 'opening_adjustment', '10.0000', 'TYPE-HISTORY-STOCK');

        $this->expectException(InvalidArgumentException::class);
        app(SaveProductAction::class)->execute([
            ...$this->productData($category, $product->item_code),
            'product_type' => 'service',
        ], $product->id, $product->lock_version);
    }

    private function category(string $code): Category
    {
        return app(SaveCategoryAction::class)->execute([
            'code' => $code,
            'name_ar' => 'تصنيف '.$code,
            'name_en' => 'Category '.$code,
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function productData(Category $category, string $itemCode): array
    {
        return [
            'item_code' => $itemCode,
            'name_ar' => 'منتج '.$itemCode,
            'name_en' => 'Product '.$itemCode,
            'category_id' => $category->id,
            'product_type' => 'standard',
            'status' => 'active',
        ];
    }
}
