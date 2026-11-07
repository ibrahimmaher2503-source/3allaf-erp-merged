<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance as InventoryStockBalance;
use App\Modules\Inventory\Support\InventoryMovementLabel;
use App\Modules\Platform\Actions\SaveUiPreferences;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ApplicationNavigation;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Reporting\Actions\EvaluateAlertsAction;
use App\Modules\Reporting\Models\Alert;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
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
            $this->get(route('inventory.balances', ['q' => $search, 'store_id' => '', 'include_zero' => 1]))->assertOk()->assertSee('000-INV-42');
        }
        $this->get(route('inventory.balances', ['q' => 'كوت', 'store_id' => $otherStore->id, 'stock_state' => 'out']))->assertOk()->assertSee('000-INV-42');
        $this->get(route('inventory.balances', ['q' => 'missing-product', 'store_id' => '']))->assertOk()->assertDontSee('000-INV-42');
    }

    public function test_balance_filters_share_one_location_dataset_and_preserve_state(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $companyId = $fixture['company']->id;
        $branchId = $fixture['branch']->id;
        $main = Store::factory()->standaloneWarehouse($fixture['company'])->create(['code' => 'LEGO-MAIN']);
        $low = Store::factory()->create(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'LEGO-LOW']);
        $zero = Store::factory()->create(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'LEGO-ZERO']);
        $noRow = Store::factory()->create(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'LEGO-NOROW']);
        $product = Product::factory()->create([
            'category_id' => $fixture['category']->id, 'item_code' => 'LEGO-QA-61',
            'name_ar' => 'ليجو تركيب', 'name_en' => 'Lego Assembly', 'reorder_threshold' => '2', 'sale_price' => '100.00',
        ]);
        $product->barcodes()->create(['barcode' => 'LEGO-BAR-61', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $post = app(PostInventoryMovement::class);
        foreach ([
            [$fixture['store'], '8', 'purchase_receipt'],
            [$low, '1', 'transfer_receipt'],
            [$main, '2', 'purchase_receipt'],
            [$main, '-2', 'purchase_return'],
            [$zero, '1', 'inventory_entry'],
            [$zero, '-1', 'inventory_exit'],
        ] as [$store, $quantity, $type]) {
            $post->execute($product->id, $store->id, $quantity, $type, '10', (string) Str::uuid());
        }
        self::assertFalse(InventoryStockBalance::query()->where('product_id', $product->id)->where('store_id', $noRow->id)->exists());
        $all = ['LEGO-LOW', 'LEGO-MAIN', 'LEGO-NOROW', 'LEGO-ZERO', 'QA-SELL'];
        $positive = ['LEGO-LOW', 'QA-SELL'];
        $out = ['LEGO-MAIN', 'LEGO-NOROW', 'LEGO-ZERO'];
        $codes = $all;

        foreach (['ليجو', 'Lego', 'LEGO-QA', 'LEGO-BAR'] as $term) {
            self::assertSame($positive, $this->legoStoreCodes(['q' => $term], $codes));
        }
        self::assertSame($positive, $this->legoStoreCodes(['q' => 'ليجو', 'include_zero' => '0'], $codes));
        self::assertSame($all, $this->legoStoreCodes(['q' => 'ليجو', 'include_zero' => '1'], $codes));
        self::assertSame(['QA-SELL'], $this->legoStoreCodes(['q' => 'ليجو', 'stock_state' => 'available'], $codes));
        self::assertSame(['LEGO-LOW'], $this->legoStoreCodes(['q' => 'ليجو', 'stock_state' => 'low'], $codes));
        self::assertSame($out, $this->legoStoreCodes(['q' => 'ليجو', 'stock_state' => 'out'], $codes));
        self::assertSame(['QA-SELL'], $this->legoStoreCodes(['q' => 'ليجو', 'store_id' => $fixture['store']->id], $codes));
        self::assertSame(['QA-SELL'], $this->legoStoreCodes(['q' => 'ليجو', 'movement_type' => 'purchase_receipt'], $codes));
        $movementLocations = [
            'purchase_receipt' => ['LEGO-MAIN', 'QA-SELL'],
            'purchase_return' => ['LEGO-MAIN'],
            'transfer_receipt' => ['LEGO-LOW'],
            'inventory_entry' => ['LEGO-ZERO'],
            'inventory_exit' => ['LEGO-ZERO'],
        ];
        foreach (InventoryMovementLabel::options() as $type => $label) {
            self::assertSame($movementLocations[$type] ?? [], $this->legoStoreCodes([
                'q' => 'ليجو', 'movement_type' => $type, 'include_zero' => '1',
            ], $codes), 'Movement type '.$type);
        }

        $matrix = [
            [['q' => 'ليجو', 'store_id' => $fixture['store']->id], ['QA-SELL']],
            [['q' => 'ليجو', 'stock_state' => 'out'], $out],
            [['q' => 'ليجو', 'include_zero' => '1'], $all],
            [['q' => 'ليجو', 'movement_type' => 'purchase_receipt'], ['QA-SELL']],
            [['store_id' => $main->id, 'stock_state' => 'out'], ['LEGO-MAIN']],
            [['store_id' => $main->id, 'include_zero' => '1'], ['LEGO-MAIN']],
            [['store_id' => $fixture['store']->id, 'movement_type' => 'purchase_receipt'], ['QA-SELL']],
            [['stock_state' => 'out', 'include_zero' => '1'], $out],
            [['stock_state' => 'out', 'movement_type' => 'purchase_receipt'], ['LEGO-MAIN']],
            [['include_zero' => '1', 'movement_type' => 'purchase_receipt'], ['LEGO-MAIN', 'QA-SELL']],
            [['q' => 'ليجو', 'store_id' => $main->id, 'stock_state' => 'out'], ['LEGO-MAIN']],
            [['q' => 'ليجو', 'store_id' => $main->id, 'include_zero' => '1'], ['LEGO-MAIN']],
            [['q' => 'ليجو', 'stock_state' => 'out', 'include_zero' => '1'], $out],
            [['store_id' => $main->id, 'stock_state' => 'out', 'include_zero' => '1'], ['LEGO-MAIN']],
            [['q' => 'ليجو', 'store_id' => $fixture['store']->id, 'movement_type' => 'purchase_receipt'], ['QA-SELL']],
            [['q' => 'ليجو', 'store_id' => $main->id, 'stock_state' => 'out', 'include_zero' => '1'], ['LEGO-MAIN']],
            [['q' => 'ليجو', 'store_id' => $main->id, 'stock_state' => 'out', 'include_zero' => '1', 'movement_type' => 'purchase_receipt'], ['LEGO-MAIN']],
        ];
        foreach ($matrix as $index => [$filters, $expected]) {
            self::assertSame($expected, $this->legoStoreCodes($filters, $codes), 'Filter combination '.($index + 1));
        }

        $permission = Permission::query()->firstOrCreate(['code' => 'inventory_stock_card.view'], ['module' => 'inventory_stock_card', 'action' => 'view', 'status' => 'active']);
        $role = Role::query()->create(['code' => 'qa-lego-scope-'.Str::random(8), 'name_ar' => 'نطاق مخزون', 'name_en' => 'Inventory scope', 'status' => 'active']);
        $role->permissions()->attach($permission);
        $fixture['user']->roles()->attach($role);
        $fixture['user']->storeScopes()->create(['store_id' => $fixture['store']->id, 'status' => 'active']);
        $this->actingAs($fixture['user']);
        self::assertSame(['QA-SELL'], $this->legoStoreCodes(['q' => 'ليجو', 'include_zero' => '1'], $codes));
        $this->actingAs($fixture['admin']);

        for ($number = 1; $number <= 26; $number++) {
            $pagedProduct = Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => sprintf('PAG-TEST-%02d', $number)]);
            InventoryStockBalance::query()->create(['product_id' => $pagedProduct->id, 'store_id' => $fixture['store']->id, 'on_hand' => 3]);
        }
        $page = $this->get(route('inventory.balances', ['q' => 'PAG-TEST', 'store_id' => $fixture['store']->id, 'stock_state' => 'available', 'include_zero' => '1']))->assertOk();
        $page->assertSee('balance_page=2', false)->assertSee('q=PAG-TEST', false);
        $form = $page->getContent();
        preg_match('/<form[^>]*data-inventory-filter[^>]*>(.*?)<\/form>/s', $form, $matches);
        self::assertNotEmpty($matches);
        self::assertStringNotContainsString('name="balance_page"', $matches[1]);
        $this->get(route('inventory.balances', ['q' => 'PAG-TEST', 'store_id' => $fixture['store']->id, 'stock_state' => 'available', 'include_zero' => '1', 'balance_page' => 2]))->assertOk()->assertSee('PAG-TEST-26');
        $reset = $this->get(route('inventory.balances'))->assertOk();
        self::assertStringNotContainsString('name="include_zero" value="1" checked', $reset->getContent());
    }

    /** @param list<string> $knownCodes @return list<string> */
    private function legoStoreCodes(array $filters, array $knownCodes): array
    {
        $response = $this->get(route('inventory.balances', $filters))->assertOk();
        preg_match_all('/balance_page=(\d+)/', $response->getContent(), $pages);
        $lastPage = $pages[1] === [] ? 1 : max(array_map('intval', $pages[1]));
        $found = [];
        for ($page = 1; $page <= $lastPage; $page++) {
            $html = $page === 1 ? $response->getContent() : $this->get(route('inventory.balances', [...$filters, 'balance_page' => $page]))->assertOk()->getContent();
            preg_match_all('/<tr data-stock-balance-row>(.*?)<\/tr>/s', $html, $matches);
            foreach ($matches[1] as $row) {
                if (! str_contains($row, 'LEGO-QA-61')) {
                    continue;
                }
                foreach ($knownCodes as $code) {
                    if (str_contains($row, $code)) {
                        $found[] = $code;
                    }
                }
            }
        }
        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    public function test_missing_balance_is_reported_as_zero_and_alert_is_location_specific(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $branchB = Branch::factory()->create(['company_id' => $fixture['company']->id]);
        $storeB = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $branchB->id, 'status' => 'active']);
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => 'ZERO-62', 'name_ar' => 'عربية سوستة اختبار', 'name_en' => 'Spring car test', 'sale_price' => '300.00']);
        $product->barcodes()->create(['barcode' => 'ZERO-BAR-62', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $fixture['store']->id, 'on_hand' => 5, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 50, 'version' => 1]);

        $rows = InventoryStockBalance::reportRows([$fixture['store']->id, $storeB->id])->where('product_id', $product->id)->orderBy('store_id')->get();
        self::assertCount(2, $rows);
        self::assertSame('0.000000', (string) $rows->firstWhere('store_id', $storeB->id)->on_hand);
        $this->get(route('inventory.balances', ['store_id' => $storeB->id, 'q' => 'سوستة', 'include_zero' => 1, 'stock_state' => 'out']))->assertOk()->assertSee('ZERO-62');
        $this->get(route('inventory.balances', ['store_id' => $storeB->id, 'q' => 'ZERO-BAR', 'stock_state' => 'out']))->assertOk()->assertSee('ZERO-62');

        $fixture['user']->storeScopes()->create(['store_id' => $fixture['store']->id, 'status' => 'active']);
        app(EvaluateAlertsAction::class)->execute($fixture['user']);
        self::assertFalse(Alert::query()->where('alert_key', 'zero-stock:'.$product->id.':'.$storeB->id)->exists());
        app(EvaluateAlertsAction::class)->execute($fixture['admin']);
        self::assertTrue(Alert::query()->where('alert_key', 'zero-stock:'.$product->id.':'.$storeB->id)->exists());
        self::assertFalse(Alert::query()->where('alert_key', 'zero-stock:'.$product->id.':'.$fixture['store']->id)->exists());
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $storeB->id, 'on_hand' => -1, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => -10, 'version' => 1]);
        app(EvaluateAlertsAction::class)->execute($fixture['admin']);
        self::assertSame(-1.0, (float) Alert::query()->where('alert_key', 'zero-stock:'.$product->id.':'.$storeB->id)->firstOrFail()->metadata['available_qty']);
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
