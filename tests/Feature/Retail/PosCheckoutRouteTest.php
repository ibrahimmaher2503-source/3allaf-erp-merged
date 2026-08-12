<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
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

    public function test_the_pos_screen_suppresses_tenders_until_the_cart_has_a_preview_then_renders_the_checkout_contract(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $response = $this->get(route('pos'));

        $response->assertOk();
        $response->assertDontSee('payments[0][method_id]', escape: false);
        $response->assertSee(__('Payment options appear after the cart has an amount to settle.'));

        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1'])
            ->assertRedirect();

        // Once there is an authoritative preview, the visible tender fields
        // must still match the route validation contract.
        $this->get(route('pos'))
            ->assertOk()
            ->assertSee('payments[0][method_id]', escape: false)
            ->assertSee('payments[0][amount]', escape: false);
    }

    public function test_the_pos_product_search_matches_barcode_code_and_name_and_can_return_empty_results(): void
    {
        $scenario = $this->scenario();
        Barcode::query()->create([
            'product_id' => $scenario['product']->id,
            'barcode' => '890000010',
            'source' => 'manual',
            'status' => 'active',
            'is_primary' => true,
            'allocation_key' => 'POS-ROUTE-BARCODE',
        ]);
        $this->actingAs($scenario['cashier']);

        $this->get(route('pos', ['product_q' => '890000010']))
            ->assertOk()
            ->assertSee('ROUTE-PROD')
            ->assertSee('890000010');

        $this->get(route('pos', ['product_q' => 'does-not-exist']))
            ->assertOk()
            ->assertSee(__('No products available.'));
    }

    public function test_the_pos_can_filter_the_visible_catalog_by_an_authoritative_category(): void
    {
        $scenario = $this->scenario();
        $category = Category::query()->create([
            'code' => 'ROUTE-SECOND-CAT',
            'name_ar' => 'فئة ثانية',
            'name_en' => 'Second category',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'ROUTE-SECOND-PROD',
            'name_ar' => 'لعبة ثانية',
            'name_en' => 'Second toy',
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'product_id' => $product->id,
            'store_id' => $scenario['store']->id,
            'on_hand' => '5',
            'reserved' => '0',
            'in_transit' => '0',
            'average_cost' => '10',
            'total_value' => '50',
            'version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => PriceVersion::query()->sole()->id,
            'product_id' => $product->id,
            'store_id' => $scenario['store']->id,
            'branch_id' => $scenario['store']->branch_id,
            'amount' => '15.000',
            'active_key' => $product->id.':'.$scenario['store']->id,
        ]);
        $this->actingAs($scenario['cashier']);

        $this->get(route('pos', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('Second category')
            ->assertSee('Second toy')
            ->assertDontSee('ROUTE-PROD');
    }

    public function test_pos_search_shows_read_only_other_store_availability_without_selling_it(): void
    {
        $scenario = $this->scenario();
        $otherStore = $this->store($scenario['store']->branch, 'ROUTE-OTHER-ST');
        StockBalance::query()->create([
            'product_id' => $scenario['product']->id,
            'store_id' => $otherStore->id,
            'on_hand' => '4',
            'reserved' => '1',
            'in_transit' => '0',
            'average_cost' => '10',
            'total_value' => '40',
            'version' => 1,
        ]);
        $this->actingAs($scenario['cashier']);

        $response = $this->get(route('pos', ['product_q' => 'ROUTE-PROD']))
            ->assertOk();
        $body = $response->getContent();
        self::assertSame(
            [true, true, true],
            [
                str_contains($body, 'Other store availability'),
                str_contains($body, 'ROUTE-OTHER-ST'),
                str_contains($body, '3.000000'),
            ],
            'POS availability markers: '.json_encode([
                'heading' => str_contains($body, 'Other store availability'),
                'store' => str_contains($body, 'ROUTE-OTHER-ST'),
                'quantity' => str_contains($body, '3.000000'),
            ], JSON_THROW_ON_ERROR),
        );
    }

    public function test_the_pos_cart_quantity_can_be_updated_without_losing_the_cart_line(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1'])
            ->assertRedirect();

        $this->post(route('pos.cart.quantity'), [
            'product_id' => $scenario['product']->id,
            'quantity' => '3',
        ])->assertRedirect();

        self::assertSame('3', (string) session('pos.cart.0.quantity'));
        $this->get(route('pos'))
            ->assertOk()
            ->assertSee('name="quantity"', escape: false)
            ->assertSee('value="3"', escape: false);
    }

    public function test_the_pos_explains_the_missing_shift_and_offers_the_cashier_the_open_shift_workflow(): void
    {
        $scenario = $this->scenario();
        \Illuminate\Support\Facades\DB::table('active_pos_shift_assignments')->delete();
        PosShift::query()->delete();
        $this->actingAs($scenario['cashier']);

        $this->get(route('pos'))
            ->assertOk()
            ->assertSee(__('Shift not open'))
            ->assertSee(__('Open shift'))
            ->assertSee(route('pos.shift'), escape: false)
            ->assertDontSee(__('Not configured'));
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
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id,
            'created_at' => now(), 'updated_at' => now(),
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
