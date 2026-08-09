<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: INV-04, INV-05, NFR-06. Test cases: TC-INV-008, TC-PUR-009, AC-XCUT-09.
 */
final class InventoryMovementIntegrityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_posting_updates_quantity_value_and_weighted_average_cost_atomically(): void
    {
        [$productId, $storeId] = $this->productAndStore();

        $action = app(PostInventoryMovement::class);
        $action->execute($productId, $storeId, '2.000000', 'opening_adjustment', '10.0000', 'INV-OPEN-1');
        $action->execute($productId, $storeId, '3.000000', 'purchase_receipt', '20.0000', 'INV-RECEIPT-1');

        $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->firstOrFail();
        self::assertSame('5.000000', $balance->on_hand);
        self::assertSame('16.0000', $balance->average_cost);
        self::assertSame('80.0000', $balance->total_value);
        self::assertSame(2, StockMovement::query()->count());
    }

    public function test_idempotency_key_replay_with_conflicting_payload_is_rejected_without_duplicate_effect(): void
    {
        [$productId, $storeId] = $this->productAndStore();
        $action = app(PostInventoryMovement::class);

        $first = $action->execute($productId, $storeId, '2', 'opening_adjustment', '10', 'INV-IDEMPOTENT-1');

        try {
            $action->execute($productId, $storeId, '999', 'inventory_entry', '999', 'INV-IDEMPOTENT-1');
            self::fail('A conflicting idempotency replay must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue($first->is(StockMovement::query()->sole()));
        }
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_negative_stock_rejection_rolls_back_without_movement_or_balance_change(): void
    {
        [$productId, $storeId] = $this->productAndStore();

        try {
            app(PostInventoryMovement::class)->execute($productId, $storeId, '-1', 'inventory_exit', null, 'INV-NEGATIVE-1');
            self::fail('Negative stock was expected to be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, StockMovement::query()->count());
            self::assertSame(0, StockBalance::query()->count());
        }
    }

    public function test_zero_quantity_is_rejected_without_creating_any_row(): void
    {
        [$productId, $storeId] = $this->productAndStore();
        $this->expectException(InvalidArgumentException::class);

        try {
            app(PostInventoryMovement::class)->execute($productId, $storeId, '0', 'inventory_entry', '10', 'INV-ZERO-1');
        } finally {
            self::assertSame(0, StockMovement::query()->count());
            self::assertSame(0, StockBalance::query()->count());
        }
    }

    /** @return array{int, int} */
    private function productAndStore(): array
    {
        $branch = $this->branch('INV-BR');
        $store = $this->store($branch, 'INV-ST', 'warehouse');
        $category = Category::query()->create(['code' => 'INV-CAT', 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'INV-PROD', 'name_ar' => 'Test', 'name_en' => 'Test', 'category_id' => $category->id, 'status' => 'active']);

        return [$product->id, $store->id];
    }
}
