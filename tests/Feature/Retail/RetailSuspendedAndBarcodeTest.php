<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: POS-01, POS-02, NFR-03, NFR-06.
 * Test cases: TC-POS-001, TC-POS-010..012.
 */
final class RetailSuspendedAndBarcodeTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_barcode_lookup_can_suspend_then_resume_without_posting_twice(): void
    {
        $scenario = $this->saleScenario();
        $this->actingAs($scenario['cashier']);
        $action = app(RetailSaleAction::class);

        $suspended = $action->create($scenario['cashier'], $scenario['store'], [['barcode' => '890000001', 'quantity' => '2']], 'POS-SUSPEND-001', true);
        self::assertSame('suspended', $suspended->status);
        self::assertNotNull($suspended->suspendedSale);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame('5.000000', (string) StockBalance::query()->where('store_id', $scenario['store']->id)->value('on_hand'));

        $completed = $action->finalizeSuspended($scenario['cashier'], $suspended, $this->cashTender($scenario['cash'], '30.00'));
        self::assertSame('approved', $completed->status);
        self::assertSame('3.000000', (string) StockBalance::query()->where('store_id', $scenario['store']->id)->value('on_hand'));
        self::assertSame(1, Sale::query()->count());
        self::assertSame(1, StockMovement::query()->where('movement_type', 'sale')->count());
        self::assertSame('30.00', (string) $completed->total);
        self::assertSame('890000001', Barcode::query()->sole()->barcode);
    }

    public function test_cashier_cannot_resume_another_cashiers_suspended_sale(): void
    {
        $scenario = $this->saleScenario();
        $otherCashier = $this->userWith('pos-other-cashier', ['cashier'], branchIds: [$scenario['branch']->id], storeIds: [$scenario['store']->id]);
        $this->actingAs($scenario['cashier']);
        $suspended = app(RetailSaleAction::class)->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'POS-SUSPEND-002', true);

        $this->actingAs($otherCashier);
        try {
            app(RetailSaleAction::class)->finalizeSuspended($otherCashier, $suspended);
            self::fail('A cashier must not resume another cashier’s suspended sale.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('suspended', $suspended->fresh()->status);
            self::assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_checkout_requires_an_active_shift_before_creating_a_sale(): void
    {
        $scenario = $this->saleScenario(withShift: false);
        $this->actingAs($scenario['cashier']);

        $this->expectException(\RuntimeException::class);
        app(RetailSaleAction::class)->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'POS-NO-SHIFT-001');
    }

    public function test_identical_sale_replay_returns_the_original_sale_without_duplicate_stock(): void
    {
        $scenario = $this->saleScenario();
        $this->actingAs($scenario['cashier']);
        $action = app(RetailSaleAction::class);

        $first = $action->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'POS-IDEMPOTENT-SAME-001', false, $this->cashTender($scenario['cash'], '15.00'));
        $replay = $action->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'POS-IDEMPOTENT-SAME-001', false, $this->cashTender($scenario['cash'], '15.00'));

        self::assertTrue($first->is($replay));
        self::assertSame(1, Sale::query()->count());
        self::assertSame(1, StockMovement::query()->where('movement_type', 'sale')->count());
        self::assertSame('4.000000', (string) StockBalance::query()->where('store_id', $scenario['store']->id)->value('on_hand'));
    }

    /** @return array{branch: Branch, store: Store, cashier: User, product: Product} */
    private function saleScenario(bool $withShift = true): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('POS-SUSPEND-BR');
        $store = $this->store($branch, 'POS-SUSPEND-ST');
        $cashier = $this->userWith('pos-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        if ($withShift) {
            $drawer = CashDrawer::query()->create([
                'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
                'assigned_user_id' => $cashier->id, 'code' => 'POS-SUSPEND-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
            ]);
            PosShift::query()->create([
                'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
                'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
            ]);
        }
        $category = Category::query()->create(['code' => 'POS-SUSPEND-CAT', 'name_ar' => 'منتج', 'name_en' => 'Product', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'POS-SUSPEND-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1]);
        $priceList = PriceList::query()->create(['company_id' => $this->company()->id, 'code' => 'POS-SUSPEND-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active']);
        $version = PriceVersion::query()->create(['price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual', 'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1]);
        PriceLine::query()->create(['price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id, 'branch_id' => $branch->id, 'amount' => '15.000', 'active_key' => $product->id.':'.$store->id]);
        Barcode::query()->create(['product_id' => $product->id, 'barcode' => '890000001', 'source' => 'manual', 'status' => 'active', 'is_primary' => true, 'allocation_key' => 'POS-SUSPEND-BARCODE']);

        $cash = PaymentMethod::query()->create([
            'code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash',
            'requires_evidence' => false, 'status' => 'active',
        ]);

        return compact('branch', 'store', 'cashier', 'product', 'cash');
    }

    /**
     * @return array<int, array{method: PaymentMethod, amount: numeric-string}>
     */
    private function cashTender(PaymentMethod $cash, string $amount): array
    {
        return [['method' => $cash, 'amount' => $amount]];
    }
}
