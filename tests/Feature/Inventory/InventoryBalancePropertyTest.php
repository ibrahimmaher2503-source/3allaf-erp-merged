<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Deterministic inventory properties for INV-01, INV-04, INV-05 and NFR-06.
 * The sequence is fixed and intentionally contains receipts, an exit, and a
 * fractional movement so the ledger/value invariants are exercised repeatedly.
 */
final class InventoryBalancePropertyTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_fixed_seed_movement_sequence_preserves_ledger_balance_and_value_invariants(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('INV-PROPERTY-BR');
        $store = $this->store($branch, 'INV-PROPERTY-ST', 'warehouse');
        $user = $this->userWith('inventory-property', ['warehouse-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $category = Category::query()->create(['code' => 'INV-PROPERTY-CAT', 'name_ar' => 'مخزون', 'name_en' => 'Inventory', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'INV-PROPERTY-PROD', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'status' => 'active', 'fractional_quantity' => true]);
        $this->actingAs($user);

        $sequence = [
            ['quantity' => '4', 'type' => 'opening_adjustment', 'cost' => '5'],
            ['quantity' => '3', 'type' => 'purchase_receipt', 'cost' => '7'],
            ['quantity' => '-2', 'type' => 'inventory_exit', 'cost' => null],
            ['quantity' => '1.25', 'type' => 'inventory_entry', 'cost' => '6'],
            ['quantity' => '-0.25', 'type' => 'sale', 'cost' => null],
        ];
        $action = app(PostInventoryMovement::class);
        foreach ($sequence as $index => $movement) {
            $action->execute($product->id, $store->id, $movement['quantity'], $movement['type'], $movement['cost'], 'INV-PROPERTY-'.$index);
        }

        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        $movements = StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->orderBy('id')->get();
        $quantitySum = $movements->reduce(fn (string $sum, StockMovement $movement): string => bcadd($sum, (string) $movement->quantity, 6), '0.000000');
        $valueSum = $movements->reduce(fn (string $sum, StockMovement $movement): string => bcadd($sum, (string) $movement->total_cost, 4), '0.0000');

        self::assertSame('6.000000', (string) $balance->on_hand);
        self::assertSame((string) $balance->on_hand, $quantitySum);
        self::assertSame((string) $balance->total_value, $valueSum);
        self::assertGreaterThanOrEqual(0, bccomp((string) $balance->total_value, '0', 4));
        $exits = $movements->whereIn('movement_type', ['inventory_exit', 'sale']);
        self::assertCount(2, $exits);
        foreach ($exits as $exit) {
            self::assertGreaterThan(0, bccomp((string) $exit->unit_cost, '0', 4));
            self::assertGreaterThan(0, bccomp((string) $exit->consumed_cost, '0', 4));
        }
        self::assertSame(5, $balance->version);
    }
}
