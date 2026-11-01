<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\SaveUiPreferences;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ApplicationNavigation;
use App\Modules\Purchasing\Models\StockBalance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class InventoryScannerMenuBFocusedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_balances_apply_partial_search_across_stores_and_filters(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $otherStore = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id]);
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => '000-INV-42', 'name_ar' => 'كوتشينة اختبار', 'name_en' => 'Playing cards']);
        $product->barcodes()->create(['barcode' => '000987654321', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        foreach ([$fixture['store'], $otherStore] as $store) {
            StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '0', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '10', 'total_value' => '0', 'version' => 1]);
        }

        foreach (['كوت', 'Play', '000-INV', '98765'] as $search) {
            $this->get(route('inventory.balances', ['q' => $search, 'store_id' => '']))->assertOk()->assertSee('000-INV-42');
        }
        $this->get(route('inventory.balances', ['q' => 'كوت', 'store_id' => $otherStore->id, 'stock_state' => 'out']))->assertOk()->assertSee('000-INV-42');
        $this->get(route('inventory.balances', ['q' => 'missing-product', 'store_id' => '']))->assertOk()->assertDontSee('000-INV-42');
    }

    public function test_menu_preference_is_account_owned_and_navigation_is_permission_filtered(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $admin = $fixture['admin'];
        $this->actingAs($admin)->withoutVite();
        app(SaveUiPreferences::class)->execute($admin, ['menu_variant' => 'a']);
        $admin->unsetRelation('uiPreference');
        $navigation = app(ApplicationNavigation::class);
        self::assertSame(array_column(config('navigation'), 'key'), array_column($navigation->for($admin, 'ar-EG'), 'key'));

        $this->postJson(route('platform.ui-preferences'), ['menu_variant' => 'b'])->assertOk()->assertJsonPath('menu_variant', 'b');
        $admin->unsetRelation('uiPreference');
        self::assertSame('b', $admin->uiPreference->menu_variant);
        $groups = $navigation->for($admin, 'ar-EG');
        self::assertSame(['home', 'products', 'inventory', 'purchasing', 'sales', 'reports'], array_column($groups, 'key'));
        $items = collect($groups)->flatMap(fn (array $group): array => $group['items']);
        self::assertNotNull($items->firstWhere('label', 'تقرير أرصدة المخزون'));
        self::assertNotNull($items->firstWhere('label', 'تقرير حركة المخزون'));
        self::assertNotNull($items->firstWhere('label', 'تحضير الجرد'));
        self::assertNotNull($items->firstWhere('label', 'صندوق الاعتمادات'));

        $restricted = $fixture['user'];
        app(SaveUiPreferences::class)->execute($restricted, ['menu_variant' => 'b']);
        $restricted->unsetRelation('uiPreference');
        $restrictedItems = collect($navigation->for($restricted, 'ar-EG'))->flatMap(fn (array $group): array => $group['items']);
        self::assertNull($restrictedItems->firstWhere('label', 'إذن إضافة'));
        self::assertNull($restrictedItems->firstWhere('label', 'صندوق الاعتمادات'));
    }
}
