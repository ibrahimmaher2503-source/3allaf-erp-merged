<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Services\ShiftExpectedTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CSH-01..CSH-04, POS-01..POS-05 offline boundary, NFR-04.
 *
 * The shift assertions now cover the real TSK-025 workflow (DEC-066, docs/32).
 * The offline assertions still cover a readiness boundary, because BLK-004
 * remains open and TSK-026 is deliberately unimplemented.
 */
final class CashShiftOfflineBoundaryTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    /**
     * CSH-02 / docs/32 §10 — the cashier must not be able to learn the expected
     * drawer total before submitting a blind count. This drives the real
     * cashier screen with a genuine expectation in play and asserts the figure
     * appears nowhere in the response body, which is where a hidden field or a
     * preloaded payload would show up.
     */
    public function test_the_cashier_shift_screen_never_discloses_expected_totals(): void
    {
        $scenario = $this->shiftScenario();

        // Settle a sale so a real expectation exists: 100.00 float + 30.00 cash.
        app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'SHIFT-LEAK-SALE-1',
            false,
            [['method' => $scenario['cash'], 'amount' => '30.00']],
        );

        $expected = app(ShiftExpectedTotalsService::class)->derive($scenario['shift']->fresh());
        self::assertSame('130.00', $expected['expected_cash'], 'Guard the fixture: the expectation must be non-trivial for this test to mean anything.');

        $response = $this->actingAs($scenario['cashier'])->get(route('pos.shift'));

        $response->assertOk();
        $response->assertDontSee('130.00');
        $response->assertDontSee('expected_cash');
        $response->assertDontSee('expected_by_method');
        $response->assertDontSee('cash_variance');
        $response->assertDontSee('total_variance');
    }

    /**
     * docs/32 §13 — expected versus actual is manager territory. A cashier who
     * navigates straight to the review URL must be denied rather than shown
     * the figures.
     */
    public function test_a_cashier_cannot_reach_the_variance_review_screen(): void
    {
        $scenario = $this->shiftScenario();

        $this->actingAs($scenario['cashier'])->get(route('pos.shift-variance'))->assertForbidden();
    }

    /**
     * @return array{cashier: User, store: Store, product: Product, cash: PaymentMethod, shift: PosShift}
     */
    private function shiftScenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('SHIFT-LEAK-BR');
        $store = $this->store($branch, 'SHIFT-LEAK-ST');
        $cashier = $this->userWith('shift-leak-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'SHIFT-LEAK-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);

        $this->actingAs($cashier);
        $shift = app(OpenShiftAction::class)->execute($cashier, $drawer, '100.00', 'SHIFT-LEAK-OPEN-1');

        $category = Category::query()->create(['code' => 'SHIFT-LEAK-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'SHIFT-LEAK-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'SHIFT-LEAK-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual',
            'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'branch_id' => $branch->id, 'amount' => '15.000', 'active_key' => $product->id.':'.$store->id,
        ]);
        $cash = PaymentMethod::query()->create([
            'code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash',
            'requires_evidence' => false, 'status' => 'active',
        ]);

        return compact('cashier', 'store', 'product', 'cash', 'shift');
    }

    public function test_offline_readiness_is_explicitly_pending_and_has_no_transactional_surface(): void
    {
        $administrator = $this->administrator('offline-boundary-admin');

        $response = $this->actingAs($administrator)->get(route('pos.offline-readiness'));
        $response->assertOk()
            ->assertSee('TSK-026 Offline Readiness')
            ->assertSee('Transactional offline POS is disabled by default')
            ->assertSee('OFF-01 / PENDING')
            ->assertSee('OFF-05 / PENDING')
            ->assertSee('No offline queue, sync, replay, conflict, or transaction is enabled here.')
            ->assertDontSee('offline/sync')
            ->assertDontSee('offline/queue/approve');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
