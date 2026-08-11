<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Dedicated local browser fixture for the US-008/017/018 POS vertical slice. */
final class PosBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('POS browser fixtures require local Demo Auth.');
        }

        $admin = User::query()->where('username', 'demo-admin')->firstOrFail();
        $cashier = User::query()->where('username', 'demo-cashier')->firstOrFail();
        $store = Store::query()->where('code', 'DEMO-SELL')->where('status', 'active')->firstOrFail();
        $cashierRole = Role::query()->where('code', 'cashier')->firstOrFail();
        $cashierRole->permissions()->syncWithoutDetaching([
            Permission::query()->where('code', 'pos_sales.open_price')->value('id'),
            Permission::query()->where('code', 'gift_cards.redeem')->value('id'),
        ]);

        $drawer = CashDrawer::query()->updateOrCreate(
            ['code' => 'BROWSER-POS-DR'],
            [
                'company_id' => $store->company_id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'assigned_user_id' => $cashier->id,
                'name_ar' => 'درج اختبار المتصفح',
                'name_en' => 'Browser POS drawer',
                'status' => 'active',
            ],
        );
        $shift = PosShift::query()->updateOrCreate(
            ['store_id' => $store->id, 'cash_drawer_id' => $drawer->id, 'cashier_id' => $cashier->id, 'status' => 'open'],
            ['branch_id' => $store->branch_id, 'opening_cash' => '0.00', 'currency_code' => $store->company->currency_code, 'opened_at' => now()],
        );
        DB::table('active_pos_shift_assignments')->updateOrInsert(
            ['shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id],
            ['created_at' => now(), 'updated_at' => now()],
        );

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'BROWSER-CASH'],
            ['name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'offline_eligible' => false, 'status' => 'active'],
        );
        PaymentMethod::query()->updateOrCreate(
            ['code' => 'BROWSER-GIFT-CARD'],
            ['name_ar' => 'بطاقة هدية', 'name_en' => 'Gift Card', 'type' => 'gift_card', 'requires_evidence' => false, 'offline_eligible' => false, 'status' => 'active'],
        );
        $this->setting(PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION, '0.05', $admin);
        $this->setting(PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT, '5', $admin);
        $this->setting(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT, '10', $admin);
        TaxSetting::query()->updateOrCreate(
            ['code' => 'BROWSER-TAX-14'],
            [
                'name_ar' => 'ضريبة اختبار المتصفح',
                'name_en' => 'Browser VAT',
                'rate' => '14.00',
                'is_tax_inclusive' => false,
                'effective_from' => now()->subMinute(),
                'effective_to' => null,
                'status' => 'active',
                'policy_notes' => 'Dedicated local browser verification policy.',
            ],
        );
        DocumentSequence::query()->updateOrCreate(
            ['document_type' => 'retail_sale'],
            ['prefix' => 'SALE-', 'padding_length' => 6, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1],
        );

        $category = Category::query()->where('code', 'DEMO-CAT-TOYS')->firstOrFail();
        $product = Product::query()->updateOrCreate(
            ['item_code' => 'BROWSER-OPEN-001'],
            ['name_ar' => 'لعبة سعر مفتوح', 'name_en' => 'Open-price demo toy', 'category_id' => $category->id, 'product_type' => 'standard', 'unit_of_measure' => 'unit', 'status' => 'active', 'barcode_mode' => 'internal', 'lock_version' => 1],
        );
        StockBalance::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_id' => $store->id],
            ['on_hand' => '20.000000', 'reserved' => '0.000000', 'in_transit' => '0.000000', 'average_cost' => '70.0000', 'total_value' => '1400.0000', 'version' => 1],
        );
        $list = PriceList::query()->updateOrCreate(
            ['company_id' => $store->company_id, 'code' => 'BROWSER-POS-PRICE'],
            ['name_ar' => 'سعر اختبار المتصفح', 'name_en' => 'Browser POS price list', 'status' => 'active'],
        );
        $version = PriceVersion::query()->firstOrCreate(
            ['price_list_id' => $list->id, 'version' => 1],
            ['state' => 'approved', 'source_type' => 'manual', 'approved_by' => $admin->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1],
        );
        PriceLine::query()->firstOrCreate(
            ['price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id],
            ['branch_id' => $store->branch_id, 'amount' => '100.000', 'reference_amount' => '100.000', 'open_price_allowed' => true, 'open_price_minimum' => '80.0000', 'open_price_maximum' => '120.0000', 'active_key' => $product->id.':'.$store->id],
        );
    }

    private function setting(string $key, string $value, User $creator): void
    {
        PosFinancialSettingVersion::query()->updateOrCreate(
            ['key' => $key, 'version' => 1],
            ['value' => $value, 'value_type' => 'decimal', 'created_by' => $creator->id],
        );
    }
}
