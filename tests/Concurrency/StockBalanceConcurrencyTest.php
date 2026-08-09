<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Str;

/**
 * CONC-INV-003 (testing/results/CONCURRENCY-SCENARIOS.md) — concurrent
 * stock-balance posting, proven against real MariaDB row locking. The
 * production-like MySQL-family connection is required because application
 * locking and idempotency races must be exercised across real transactions.
 *
 * Two claims are proven here, both requiring two genuinely overlapping
 * transactions racing the same StockBalance row:
 *  1. StockBalance's lockForUpdate() prevents a lost update when two
 *     DIFFERENT movements post concurrently (positive proof).
 *  2. PostInventoryMovement's idempotency check-then-insert has a TOCTOU
 *     window; the fix (2026-08-09) makes a genuine duplicate-key race
 *     collapse into a single safe replay instead of an unhandled
 *     UniqueConstraintViolationException (regression proof for the fix).
 */
final class StockBalanceConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_concurrent_distinct_movements_do_not_lose_an_update(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('CONC-INV-'.Str::random(6));
        $store = $this->store($branch, 'CONC-INV-'.Str::random(6));
        $admin = $this->administrator('conc-inv-admin-'.Str::random(6));
        $this->actingAs($admin);

        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CONC-INV-CAT-'.Str::random(6), 'name_ar' => 'فئة', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'CONC-INV-'.Str::random(8), 'name_ar' => 'منتج', 'name_en' => 'Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        app(PostInventoryMovement::class)->execute($product->id, $store->id, '100', 'opening_adjustment', '10.0000', 'SETUP-OPENING-'.$product->id);
        $balanceBefore = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('100.000000', (string) $balanceBefore->on_hand);
        $versionBefore = $balanceBefore->version;

        $keyA = 'RACE-DISTINCT-A-'.Str::random(10);
        $keyB = 'RACE-DISTINCT-B-'.Str::random(10);
        $results = $this->race([
            ['movement', ['user_id' => $admin->id, 'product_id' => $product->id, 'store_id' => $store->id, 'quantity' => '10', 'movement_type' => 'purchase_receipt', 'unit_cost' => '10.0000', 'idempotency_key' => $keyA]],
            ['movement', ['user_id' => $admin->id, 'product_id' => $product->id, 'store_id' => $store->id, 'quantity' => '-4', 'movement_type' => 'adjustment', 'idempotency_key' => $keyB]],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, 'Worker A failed: '.json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, 'Worker B failed: '.json_encode($results[1]));
        self::assertNotSame($results[0]['result']['movement_id'], $results[1]['result']['movement_id'], 'Two distinct movements must produce two distinct rows.');

        $balanceAfter = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('106.000000', (string) $balanceAfter->on_hand, 'lockForUpdate() must serialize the two concurrent posts with no lost update: 100 + 10 - 4 = 106.');
        self::assertSame($versionBefore + 2, $balanceAfter->version, 'Both concurrent writes must be individually recorded, not one clobbering the other.');
        self::assertSame(3, StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->count(), 'Opening + 2 raced movements, no duplicates and no drops.');
    }

    public function test_two_concurrent_identical_idempotency_key_submissions_collapse_to_one_movement(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('CONC-INV-'.Str::random(6));
        $store = $this->store($branch, 'CONC-INV-'.Str::random(6));
        $admin = $this->administrator('conc-inv-admin-'.Str::random(6));
        $this->actingAs($admin);

        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CONC-INV-CAT-'.Str::random(6), 'name_ar' => 'فئة', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'CONC-INV-'.Str::random(8), 'name_ar' => 'منتج', 'name_en' => 'Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        app(PostInventoryMovement::class)->execute($product->id, $store->id, '50', 'opening_adjustment', '10.0000', 'SETUP-OPENING-'.$product->id);

        // Same key, same payload, launched together: a genuine duplicate
        // submission (e.g. a flaky POS client retry firing before the first
        // response returns), not two different requests that merely happen
        // to share a key.
        $duplicateKey = 'RACE-DUPLICATE-'.Str::random(10);
        $params = ['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => '7', 'movement_type' => 'purchase_receipt', 'unit_cost' => '10.0000', 'idempotency_key' => $duplicateKey];
        $results = $this->race([
            ['movement', $params + ['user_id' => $admin->id]],
            ['movement', $params + ['user_id' => $admin->id]],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, 'Worker A must not surface a raw DB error: '.json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, 'Worker B must not surface a raw DB error: '.json_encode($results[1]));
        self::assertSame($results[0]['result']['movement_id'], $results[1]['result']['movement_id'], 'Both racers must resolve to the SAME movement row (idempotent replay), not two rows.');

        self::assertSame(1, StockMovement::query()->where('idempotency_key', $duplicateKey)->count(), 'Exactly one movement row for the duplicate key, never two.');
        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('57.000000', (string) $balance->on_hand, 'The quantity must be applied exactly once (50 + 7), not twice.');
    }
}
