<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use App\Modules\Platform\Actions\DecideApprovalSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: PRC-08, POS-01, NFR-01, NFR-03.
 *
 * The first scenario proves the manager-approval branch preserves the cart
 * and creates a source-linked pending decision before checkout.
 */
final class OpenPriceApprovalTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_a_configured_open_price_deviation_creates_a_pending_manager_approval_and_preserves_the_cart(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), [
            'product_id' => $scenario['product']->id,
            'quantity' => '1',
        ])->assertRedirect();

        $this->post(route('pos.cart.open-price'), [
            'product_id' => $scenario['product']->id,
            'amount' => '115.00',
            'reason' => 'Customer exception',
            'expected_revision' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = ApprovalRecord::query()->where('source_type', 'pos_open_price')->sole();
        self::assertSame('pending', $approval->approval_state->value);
        self::assertSame($scenario['cashier']->id, $approval->requester_id);
        self::assertSame($scenario['store']->id, $approval->store_id);
        self::assertSame('approve_open_price', $approval->requested_action);
        self::assertSame('115.0000', (string) $approval->limit_context['requested_amount']);
        self::assertSame('115.0000', (string) session('pos.cart.0.open_price_amount'));
        self::assertSame($approval->id, (int) session('pos.cart.0.open_price_approval_id'));
    }

    public function test_a_store_scoped_cashier_may_request_branch_recorded_approval_for_their_store(): void
    {
        $scenario = $this->scenario();
        $scenario['cashier']->branchScopes()->delete();
        $this->actingAs($scenario['cashier']);

        $this->post(route('pos.cart.add'), [
            'product_id' => $scenario['product']->id,
            'quantity' => '1',
        ]);

        $this->post(route('pos.cart.open-price'), [
            'product_id' => $scenario['product']->id,
            'amount' => '115.00',
            'reason' => 'Store-scoped customer exception',
            'expected_revision' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = ApprovalRecord::query()->where('source_type', 'pos_open_price')->sole();
        self::assertSame($scenario['store']->branch_id, $approval->branch_id);
        self::assertSame($scenario['store']->id, $approval->store_id);
    }

    public function test_an_independent_manager_approval_is_required_and_is_persisted_on_the_final_sale(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1']);
        $this->post(route('pos.cart.open-price'), [
            'product_id' => $scenario['product']->id,
            'amount' => '115.00',
            'reason' => 'Customer exception',
            'expected_revision' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = ApprovalRecord::query()->where('source_type', 'pos_open_price')->sole();
        $manager = $scenario['manager'];
        $this->actingAs($manager);
        Gate::forUser($manager)->authorize('decide', $approval);
        app(DecideApprovalSource::class)->approve($approval);

        $approval->refresh();
        self::assertSame('approved', $approval->approval_state->value);
        self::assertSame($manager->id, $approval->approver_id);
        self::assertNotSame($approval->requester_id, $approval->approver_id);

        $this->actingAs($scenario['cashier']);
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            session('pos.cart'),
            'CHECKOUT:open-price-approval',
            false,
            [['method' => $scenario['cash'], 'amount' => '115.00', 'tendered' => '115.00']],
        );

        self::assertSame('approved', $sale->status);
        $line = $sale->lines()->firstOrFail();
        self::assertSame('115.0000', (string) $line->unit_price);
        self::assertSame('100.0000', (string) $line->reference_price);
        self::assertSame($approval->id, (int) $line->open_price_approval_record_id);
        self::assertSame($manager->id, (int) $line->open_price_authorized_by);
        self::assertSame(1, AuditLog::query()->where('event', 'sale_open_price_applied')->where('source_id', (string) $line->id)->count());
        $this->get(route('sales.receipt.thermal', $sale))
            ->assertOk()
            ->assertSee('Selling price')
            ->assertSee('115.00')
            ->assertSee('Cash rounding')
            ->assertSee('Payments');
        $this->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee('Selling price')
            ->assertSee('115.00')
            ->assertSee('Payable total');
    }

    public function test_rejected_or_stale_open_price_approval_cannot_post_and_the_cart_is_preserved(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1']);
        $this->post(route('pos.cart.open-price'), [
            'product_id' => $scenario['product']->id,
            'amount' => '115.00',
            'reason' => 'Customer exception',
            'expected_revision' => 0,
        ]);
        $approval = ApprovalRecord::query()->where('source_type', 'pos_open_price')->sole();

        $this->actingAs($scenario['manager']);
        Gate::forUser($scenario['manager'])->authorize('decide', $approval);
        app(DecideApprovalSource::class)->reject($approval, 'Manager rejected the exception.');
        $this->actingAs($scenario['cashier']);

        $this->expectException(InvalidArgumentException::class);
        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                session('pos.cart'),
                'CHECKOUT:rejected-open-price',
                false,
                [['method' => $scenario['cash'], 'amount' => '115.00', 'tendered' => '115.00']],
            );
        } finally {
            self::assertNotNull(session('pos.cart.0.open_price_approval_id'));
            self::assertSame(0, \App\Modules\Retail\Models\Sale::query()->count());
        }
    }

    public function test_checkout_rejects_an_approval_after_the_effective_price_revision_changes(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1']);
        $this->post(route('pos.cart.open-price'), [
            'product_id' => $scenario['product']->id,
            'amount' => '115.00',
            'reason' => 'Customer exception',
            'expected_revision' => 0,
        ]);
        $approval = ApprovalRecord::query()->where('source_type', 'pos_open_price')->sole();

        DB::table('price_lines')->where('id', $scenario['price_line_id'])->update(['updated_at' => now()->addSecond()]);
        $this->actingAs($scenario['manager']);
        Gate::forUser($scenario['manager'])->authorize('decide', $approval);
        app(DecideApprovalSource::class)->approve($approval);
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                session('pos.cart'),
                'CHECKOUT:stale-open-price',
                false,
                [['method' => $scenario['cash'], 'amount' => '115.00', 'tendered' => '115.00']],
            );
            self::fail('A checkout using a stale open-price approval must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('stale', strtolower($exception->getMessage()));
            self::assertSame(0, \App\Modules\Retail\Models\Sale::query()->count());
            self::assertNotNull(session('pos.cart.0.open_price_approval_id'));
        }
    }

    public function test_an_above_limit_discount_requires_independent_approval_and_is_persisted_on_the_sale(): void
    {
        $scenario = $this->scenario();
        $this->grant('pos_sales.apply_discount');

        $this->actingAs($scenario['cashier']);
        $this->post(route('pos.cart.add'), ['product_id' => $scenario['product']->id, 'quantity' => '1']);
        $this->post(route('pos.cart.discount'), [
            'product_id' => $scenario['product']->id,
            'discount_type' => 'line',
            'discount_amount' => '20.00',
            'reason' => 'Customer exception',
            'expected_revision' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = ApprovalRecord::query()->where('source_type', 'pos_discount')->sole();
        self::assertSame('pending', $approval->approval_state->value);
        self::assertSame($scenario['cashier']->id, $approval->requester_id);
        self::assertSame('20.00', (string) session('pos.cart.0.discount_amount'));

        $this->actingAs($scenario['manager']);
        Gate::forUser($scenario['manager'])->authorize('decide', $approval);
        app(DecideApprovalSource::class)->approve($approval);

        $this->actingAs($scenario['cashier']);
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            session('pos.cart'),
            'CHECKOUT:discount-approval',
            false,
            [['method' => $scenario['cash'], 'amount' => '80.00', 'tendered' => '80.00']],
        );

        $line = $sale->lines()->firstOrFail();
        self::assertSame('20.00', (string) $line->discount_amount);
        self::assertSame($approval->id, (int) $line->discount_approval_record_id);
        self::assertSame('80.00', (string) $sale->total);
        self::assertSame(1, AuditLog::query()->where('event', 'sale_discount_approved')->where('source_id', (string) $line->id)->count());
        $this->get(route('sales.receipt.thermal', $sale))->assertOk()->assertSee('Discount');
    }

    /** @return array{cashier: User, manager: User, store: Store, product: Product, price_line_id: int, cash: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->grant('pos_sales.create', 'pos_sales.open_price', 'pos_sales.payment_create', 'pos_sales.print');
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('OPEN-BR');
        $store = $this->store($branch, 'OPEN-ST');
        $cashier = $this->userWith('open-price-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $cashier->roles()->attach(Role::query()->where('code', 'open-price-test-role')->value('id'));
        $manager = $this->userWith('open-price-manager', ['branch-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => $cashier->id,
            'code' => 'OPEN-DR',
            'name_ar' => 'درج',
            'name_en' => 'Drawer',
            'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0',
            'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = Category::query()->create(['code' => 'OPEN-CAT', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'OPEN-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '70', 'total_value' => '350', 'version' => 1]);
        $list = PriceList::query()->create(['company_id' => $this->company()->id, 'code' => 'OPEN-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active']);
        $version = PriceVersion::query()->create(['price_list_id' => $list->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual', 'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1]);
        $priceLine = PriceLine::query()->create(['price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id, 'branch_id' => $branch->id, 'amount' => '100.000', 'reference_amount' => '100.000', 'open_price_allowed' => true, 'open_price_minimum' => '80.0000', 'open_price_maximum' => '120.0000', 'active_key' => $product->id.':'.$store->id]);
        PaymentMethod::query()->create(['code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);
        PosFinancialSettingVersion::query()->create(['key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION, 'value' => '0.05', 'value_type' => 'decimal', 'version' => 1, 'created_by' => $cashier->id]);
        PosFinancialSettingVersion::query()->create(['key' => PosFinancialSettingRegistry::OPEN_PRICE_APPROVAL_LIMIT, 'value' => '5', 'value_type' => 'decimal', 'version' => 1, 'created_by' => $cashier->id]);

        return ['cashier' => $cashier, 'manager' => $manager, 'store' => $store, 'product' => $product, 'price_line_id' => $priceLine->id, 'cash' => PaymentMethod::query()->where('code', 'cash')->firstOrFail()];
    }

    private function grant(string ...$codes): void
    {
        $role = Role::query()->firstOrCreate(['code' => 'open-price-test-role'], ['name_ar' => 'اختبار', 'name_en' => 'Open price test', 'status' => 'active']);
        $ids = [];
        foreach ($codes as $code) {
            [$module, $action] = explode('.', $code, 2);
            $ids[] = Permission::query()->firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'sensitivity' => 'sensitive', 'status' => 'active'])->id;
        }
        $role->permissions()->syncWithoutDetaching($ids);
    }
}
