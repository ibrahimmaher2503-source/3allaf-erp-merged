<?php

namespace Tests\Support\Scenarios;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Tests\Support\Datasets\DatasetSize;

final class PosScenario
{
    /** @return array<string, mixed> */
    public static function ready(DatasetSize $size = DatasetSize::TINY): array
    {
        $platform = PlatformScenario::ready($size);
        $company = $platform['company'];
        $branch = $platform['branch'];
        $store = $platform['store'];
        $user = $platform['user'];
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'assigned_user_id' => $user->id,
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $user->id, 'status' => 'open', 'opening_cash' => 0, 'opened_at' => now(),
            'policy_notes' => 'Deterministic automated test fixture.',
        ]);
        if ($size === DatasetSize::LARGE) {
            $category = Category::factory()->create();
            $rows = [];
            for ($index = 1; $index <= $size->products(); $index++) {
                $code = 'BULK-SKU-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
                $rows[] = ['item_code' => $code, 'name_ar' => 'منتج '.$code, 'name_en' => 'Product '.$code, 'category_id' => $category->id, 'status' => 'active', 'barcode_mode' => 'single', 'product_type' => 'standard', 'unit_of_measure' => 'piece', 'average_cost' => 10, 'reorder_threshold' => 1, 'fractional_quantity' => false, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
                if (count($rows) === 500) {
                    DB::table('products')->insert($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                DB::table('products')->insert($rows);
            }
            $products = Product::query()->where('item_code', 'like', 'BULK-SKU-%')->get();
        } else {
            $products = Product::factory()->count($size->products())->create();
        }
        $priceList = PriceList::query()->create([
            'company_id' => $company->id, 'code' => 'PL-TEST-'.$company->id,
            'name_ar' => 'قائمة اختبار', 'name_en' => 'Test Price List', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => PriceVersionState::Approved,
            'source_type' => 'test', 'approved_by' => $user->id, 'approved_at' => now(), 'effective_from' => now(),
        ]);
        foreach ($products as $product) {
            PriceLine::query()->create(['price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id, 'branch_id' => $branch->id, 'amount' => 25.000, 'active_key' => 'test-'.$version->id.'-'.$product->id]);
            StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => 10, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 100, 'version' => 1]);
        }
        $payment = PaymentMethod::factory()->create(['code' => 'cash-test-'.$company->id]);

        return $platform + compact('drawer', 'shift', 'products', 'priceList', 'version', 'payment');
    }
}
