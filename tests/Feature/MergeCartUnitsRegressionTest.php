<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\PosCartSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class MergeCartUnitsRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bag_cart_availability_uses_bags_instead_of_kilograms(): void
    {
        $product = Product::query()->where('item_code', 'FEED-001')->firstOrFail();
        $bag = $product->productUnits()->whereHas('unit', fn ($q) => $q->where('code', 'BAG'))->firstOrFail();
        $store = Store::query()->where('type', 'selling')->firstOrFail();
        $actor = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $actor->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $this->actingAs($actor);
        request()->setLaravelSession(app('session')->driver());
        request()->session()->put('pos.cart', [['product_id' => $product->id, 'product_unit_id' => $bag->id, 'quantity' => '2']]);
        StockBalance::query()->updateOrCreate(['product_id' => $product->id, 'store_id' => $store->id], ['on_hand' => '100', 'reserved' => '0']);
        $context = new PosContext($store->branch, $store, new CashDrawer(['id' => 0]), new PosShift(['id' => 0]));
        $snapshot = app(PosCartSnapshot::class)->build($context);
        self::assertCount(1, $snapshot['lines']);
        self::assertSame('2.000000', $snapshot['lines'][0]['available_quantity']);
    }
}
