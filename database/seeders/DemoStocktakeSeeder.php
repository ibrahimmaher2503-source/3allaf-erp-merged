<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\CompleteStockCountLineAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SetStockCountQuantityAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Store;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoStocktakeSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $warehouse = Store::query()->where('code', 'QA-SAHIL-WH')->firstOrFail();
            $shortage = Product::query()->where('item_code', 'QA-PRICE-400')->firstOrFail();
            $surplus = Product::query()->where('item_code', 'QA-PURCHASE')->firstOrFail();
            Auth::login($administrator);
            try {
                $count = app(SaveStockCountAction::class)->execute([
                    'location_ids' => [$warehouse->id],
                    'member_ids' => [$administrator->id],
                    'manager_id' => $administrator->id,
                    'count_type' => 'partial',
                    'scope_type' => 'partial',
                    'idempotency_key' => 'qa-demo-stocktake',
                    'notes' => fake()->sentence(),
                ]);
                app(OpenStockCountSessionAction::class)->execute($count->id);
                foreach ([[$shortage, -1], [$surplus, 2]] as [$product, $difference]) {
                    $expected = (int) StockBalance::query()->where('store_id', $warehouse->id)->where('product_id', $product->id)->value('on_hand');
                    $line = app(SetStockCountQuantityAction::class)->execute(
                        $count->id,
                        $warehouse->id,
                        $product->item_code,
                        (string) ($expected + $difference),
                        'qa-demo-count-'.$product->id,
                        'qa-demo-device',
                        fake()->sentence(),
                    );
                    app(CompleteStockCountLineAction::class)->execute($line->id);
                }
                app(SubmitStockCountAction::class)->execute($count->id);
            } finally {
                Auth::logout();
            }
        });
    }
}
