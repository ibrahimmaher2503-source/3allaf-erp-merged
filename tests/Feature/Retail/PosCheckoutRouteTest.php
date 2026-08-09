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
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: POS-01, POS-03. Policy: docs/48 §6 (DEC-066).
 * Test cases: TC-POS-050..053.
 *
 * The rest of the POS suite drives `RetailSaleAction` directly. These exercise
 * the actual HTTP route so a drift between the route contract and the checkout
 * screen is caught rather than shipped.
 */
final class PosCheckoutRouteTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_the_pos_screen_renders_the_tender_fields_the_checkout_route_requires(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $response = $this->get(route('pos'));

        $response->assertOk();
        // If these names ever drift from the route's validation rules, checkout
        // silently starts rejecting every real submission.
        $response->assertSee('payments[0][method_id]', escape: false);
        $response->assertSee('payments[0][amount]', escape: false);
    }

    public function test_checkout_over_http_settles_the_sale_and_records_the_tender(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '2'])
            ->assertRedirect();

        // 2 x 15.00 = 30.00
        $response = $this->post(route('pos.checkout'), [
            'checkout_token' => '11111111-1111-4111-8111-111111111111',
            'payments' => [['method_id' => $scenario['cash']->id, 'amount' => '30.00']],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $sale = Sale::query()->sole();
        self::assertSame('approved', $sale->status);
        self::assertSame('30.00', (string) $sale->paid_total);
        self::assertSame('30.00', (string) $sale->total);
        self::assertSame(1, SalePayment::query()->where('sale_id', $sale->id)->count());
    }

    public function test_checkout_over_http_is_rejected_when_no_tender_is_submitted(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '2']);

        $response = $this->post(route('pos.checkout'), []);

        $response->assertSessionHasErrors('payments');
        self::assertSame(0, Sale::query()->count());
    }

    public function test_checkout_over_http_surfaces_an_underpayment_as_a_validation_error(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '2']);

        $response = $this->post(route('pos.checkout'), [
            'checkout_token' => '22222222-2222-4222-8222-222222222222',
            'payments' => [['method_id' => $scenario['electronic']->id, 'amount' => '10.00']],
        ]);

        // The domain exception must reach the cashier as a form error rather
        // than a 500.
        $response->assertSessionHasErrors('payments');
        self::assertSame(0, Sale::query()->where('status', 'approved')->count());
        self::assertSame(0, SalePayment::query()->count());
    }

    /** @return array{cashier: User, store: Store, product: Product, cash: PaymentMethod, electronic: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('ROUTE-BR');
        $store = $this->store($branch, 'ROUTE-ST');
        $cashier = $this->userWith('route-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'ROUTE-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        $category = Category::query()->create(['code' => 'ROUTE-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'ROUTE-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'ROUTE-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active',
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
        $electronic = PaymentMethod::query()->create([
            'code' => 'manual-card', 'name_ar' => 'Ø¨Ø·Ø§Ù‚Ø©', 'name_en' => 'Manual card', 'type' => 'manual',
            'requires_evidence' => false, 'status' => 'active',
        ]);
        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '0.05', 'value_type' => 'decimal', 'version' => 1, 'created_by' => $cashier->id,
        ]);

        return compact('cashier', 'store', 'product', 'cash', 'electronic');
    }
}
