<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Pos\Cart;
use App\Livewire\Pos\CheckoutPanel;
use App\Livewire\Pos\ProductBrowser;
use App\Mail\PosInvoiceReceipt;
use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Support\ResidenceGeography;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\InvoiceDeliveryService;
use App\Modules\Retail\Support\PosCartSnapshot;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosOpenOrderManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class Module5PosQaTest extends TestCase
{
    use DatabaseTransactions;

    private User $cashier;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['pos_sales.view', 'pos_sales.create', 'pos_sales.payment_create', 'pos_sales.apply_discount', 'pos_sales.payment_evidence_upload', 'customers.view', 'customers.create', 'suspended_sales.view', 'suspended_sales.retrieve', 'loyalty.earn', 'company_settings.view', 'company_settings.edit'] as $code) {
            Permission::query()->firstOrCreate(['code' => $code], ['module' => strstr($code, '.', true), 'action' => substr(strstr($code, '.'), 1), 'status' => 'active']);
        }
        $this->cashier = User::factory()->create(['status' => 'active']);
        $this->cashier->forceFill(['is_super_admin' => true])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $this->store = Store::factory()->for($branch)->create(['company_id' => $company->id]);
        $drawer = CashDrawer::factory()->create(['store_id' => $this->store->id, 'branch_id' => $branch->id, 'company_id' => $company->id, 'assigned_user_id' => $this->cashier->id]);
        $shift = PosShift::query()->create(['branch_id' => $branch->id, 'store_id' => $this->store->id, 'cash_drawer_id' => $drawer->id, 'cashier_id' => $this->cashier->id, 'opened_by' => $this->cashier->id, 'status' => 'open', 'opening_cash' => '0', 'currency_code' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'opened_at' => now()]);
        DB::table('active_pos_shift_assignments')->insert(['shift_id' => $shift->id, 'cashier_id' => $this->cashier->id, 'cash_drawer_id' => $drawer->id]);
        $this->product = Product::factory()->create(['sale_price' => '30', 'model_number' => 'M5', 'name_ar' => 'لعبة اختبار', 'name_en' => '']);
        $this->product->barcodes()->create(['barcode' => 'M5-'.Str::random(12), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        StockBalance::query()->create(['product_id' => $this->product->id, 'store_id' => $this->store->id, 'on_hand' => '2', 'average_cost' => '10', 'total_value' => '20']);
        $this->actingAs($this->cashier)->withoutVite();
        $this->withSession([]);
        request()->setLaravelSession(app('session')->driver());
        $context = app(PosContextResolver::class)->resolve($this->cashier);
        $order = app(PosOpenOrderManager::class)->create($this->cashier, $context);
        $order->lines()->create(['product_id' => $this->product->id, 'line_position' => 1, 'quantity' => '1', 'draft_payload' => ['product_id' => $this->product->id, 'quantity' => '1']]);
        $this->withSession([PosOpenOrderManager::SESSION_KEY => $order->uuid]);
        app(PosOpenOrderManager::class)->hydrateSession(request(), $order);
        PosFinancialSettingVersion::query()->create(['key' => 'pos.discount_approval_limit_percent', 'value' => '100', 'value_type' => 'decimal', 'version' => 1, 'created_by' => $this->cashier->id]);
    }

    public function test_quantity_buttons_accept_whole_decimal_snapshots_and_preserve_stock_limits(): void
    {
        $component = new Cart;
        $component->quantity($this->product->id, '2.000000', app(PosCartAction::class));
        self::assertFalse($component->getErrorBag()->any());
        self::assertSame('2', session('pos.cart.0.quantity'));
        $component->quantity($this->product->id, '3.000000', app(PosCartAction::class));
        self::assertTrue($component->getErrorBag()->has('cart'));
        self::assertSame('2.000000', session('pos.cart.0.quantity'));
        self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_card_checkout_rejects_wrong_allocations_and_posts_once(): void
    {
        $method = PaymentMethod::factory()->create(['type' => 'card']);
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'card', 'payments' => [['method_id' => $method->id, 'amount' => '29']]];
        foreach (['29', '31'] as $amount) {
            $payload['payments'][0]['amount'] = $amount;
            $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors();
            self::assertSame(0, Sale::query()->count());
            self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
        }
        $payload['payments'][0]['amount'] = '30';
        session()->forget('errors');
        $response = $this->post(route('pos.checkout'), $payload);
        self::assertSame(1, Sale::query()->count());
        $response->assertSessionHasNoErrors();
        $this->post(route('pos.checkout'), $payload)->assertRedirect();
        self::assertSame(1, Sale::query()->count());
        self::assertSame(1, DB::table('sale_payments')->count());
        self::assertSame(1, DB::table('stock_movements')->count());
        self::assertSame('1.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_hold_preserves_customer_items_and_resumes_once(): void
    {
        $customer = Customer::query()->create(['idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل الاختبار', 'name_en' => 'QA Customer', 'phone_normalized' => '201011223344', 'phone_display' => '01011223344', 'status' => 'active', 'created_by' => $this->cashier->id, 'created_branch_id' => $this->store->branch_id, 'created_store_id' => $this->store->id]);
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $order->update(['customer_id' => $customer->id]);
        foreach (['loyalty.retail_rule' => ['earn_points_per_currency' => '0.0001', 'redeem_currency_per_point' => '1'], 'loyalty.expiry_policy' => ['days' => 365], 'loyalty.rounding_policy' => ['earn' => 'floor', 'redeem' => 'floor'], 'loyalty.ledger_integrity' => ['enabled' => true]] as $key => $value) {
            CustomerPolicySettingVersion::query()->create(['key' => $key, 'value' => json_encode($value), 'value_type' => 'json', 'version' => 1, 'created_by' => $this->cashier->id]);
        }
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_denomination', '5');
        $this->post(route('pos.suspend'))->assertSessionHasNoErrors()->assertSessionMissing('pos.cart');
        $sale = Sale::query()->firstOrFail();
        self::assertSame('suspended', $sale->status);
        self::assertSame($customer->id, $sale->customer_id);
        self::assertSame($this->product->id, $sale->lines()->firstOrFail()->product_id);
        self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
        $this->get(route('pos.suspended'))->assertOk()->assertViewHas('sales', fn ($sales) => $sales->contains('id', $sale->id));
        $this->get(route('pos.suspended.resume', $sale))->assertOk();
        $method = PaymentMethod::factory()->create(['type' => 'card']);
        $payload = ['resume_token' => session('pos.suspended.resume_token.'.$sale->id), 'payments' => [['method_id' => $method->id, 'amount' => '30']]];
        $this->post(route('pos.suspended.finalize', $sale), $payload)->assertSessionHasNoErrors();
        self::assertSame('approved', $sale->fresh()->status);
        $this->post(route('pos.suspended.finalize', $sale), $payload)->assertStatus(419);
        self::assertSame(1, DB::table('sale_payments')->count());
        self::assertSame(1, DB::table('stock_movements')->count());
    }

    public function test_cash_and_mixed_checkout_use_the_approved_rounding_setting(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $card = PaymentMethod::factory()->create(['type' => 'card']);
        $this->product->update(['sale_price' => '32']);
        $this->get(route('pos'))->assertOk();
        $nextVersion = ((int) PosFinancialSettingVersion::query()->where('key', 'pos.cash_rounding_denomination')->max('version')) + 1;
        PosFinancialSettingVersion::query()->create(['key' => 'pos.cash_rounding_denomination', 'value' => '1.2345', 'version' => $nextVersion, 'created_by' => $this->cashier->id]);
        $this->get(route('pos'))->assertOk();
        $versions = PosFinancialSettingVersion::query()->count();
        try {
            app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_denomination', '0.001');
            self::fail('A subcent cash rounding denomination was saved.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('value', $exception->errors());
        }
        self::assertSame($versions, PosFinancialSettingVersion::query()->count());
        PosFinancialSettingVersion::query()->create(['key' => 'pos.cash_rounding_denomination', 'value' => '0.001', 'version' => $nextVersion + 1, 'created_by' => $this->cashier->id]);
        try {
            app(PosCalculationService::class)->cashRoundingDenomination();
            self::fail('Cash settlement accepted a stored subcent denomination.');
        } catch (PosFinancialConfigurationException $exception) {
            self::assertStringContainsString('cash rounding setting is incomplete', $exception->getMessage());
        }
        $this->post(route('admin.settings.pos-financial.save'), ['key' => 'pos.cash_rounding_denomination', 'value' => '5'])->assertSessionHasNoErrors();
        $this->get(route('pos.financial-readiness', ['company_id' => $this->store->company_id]))->assertOk()->assertSee(__('Enter the approved positive denomination in whole units or cents. Existing 5 EGP settings remain active until changed.'));
        foreach (['cash', 'split'] as $mode) {
            $context = app(PosContextResolver::class)->resolve($this->cashier);
            $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, $context, false);
            if ($order === null) {
                $order = app(PosOpenOrderManager::class)->create($this->cashier, $context);
                $order->lines()->create(['product_id' => $this->product->id, 'line_position' => 1, 'quantity' => '1', 'draft_payload' => ['product_id' => $this->product->id, 'quantity' => '1']]);
            }
            $payments = $mode === 'cash' ? [['method_id' => $cash->id, 'tendered' => '30']] : [['method_id' => $card->id, 'amount' => '20'], ['method_id' => $cash->id, 'tendered' => '10']];
            $this->post(route('pos.checkout'), ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => $mode, 'payments' => $payments])->assertSessionHasNoErrors();
            $sale = Sale::query()->latest('id')->firstOrFail();
            self::assertSame('32.00', $sale->total);
            self::assertSame('30.00', $sale->payable_total);
            self::assertSame('-2.00', $sale->cash_rounding_amount);
        }
        self::assertSame(2, Sale::query()->count());
        self::assertSame('0.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_exact_cash_sale_posts_once_when_rounding_is_disabled(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $this->product->update(['sale_price' => '550.00']);
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_enabled', '0');
        self::assertSame('0.00', app(PosCalculationService::class)->cashRoundingAdjustment('550.37'));
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'cash', 'payments' => [['method_id' => $cash->id, 'tendered' => '550.00']]];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->post(route('pos.checkout'), $payload)->assertRedirect();
        $sale = Sale::query()->sole();
        self::assertSame('550.00', $sale->total);
        self::assertSame('0.00', $sale->cash_rounding_amount);
        self::assertSame('550.00', $sale->payable_total);
        self::assertSame(1, SalePayment::query()->count());
    }

    public function test_two_line_customer_cash_checkout_ignores_inactive_loyalty_and_posts_once(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $this->product->update(['sale_price' => '1000.00']);
        $second = Product::factory()->create(['sale_price' => '1100.00', 'model_number' => 'M5-SECOND']);
        $second->barcodes()->create(['barcode' => 'M5-SECOND-'.Str::random(12), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        StockBalance::query()->create(['product_id' => $second->id, 'store_id' => $this->store->id, 'on_hand' => '2', 'average_cost' => '10', 'total_value' => '20']);
        $customer = Customer::query()->create([
            'idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل الاختبار', 'name_en' => 'QA Customer',
            'phone_normalized' => '201011223344', 'phone_display' => '01011223344', 'status' => 'active',
            'created_by' => $this->cashier->id, 'created_branch_id' => $this->store->branch_id, 'created_store_id' => $this->store->id,
        ]);
        $context = app(PosContextResolver::class)->resolve($this->cashier);
        $orders = app(PosOpenOrderManager::class);
        $order = $orders->active(request(), $this->cashier, $context);
        $order->lines()->create(['product_id' => $second->id, 'line_position' => 2, 'quantity' => '1', 'draft_payload' => ['product_id' => $second->id, 'quantity' => '1']]);
        $orders->setCustomer(request(), $this->cashier, $context, $customer);
        $orders->hydrateSession(request(), $order->fresh());
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'cash', 'payments' => [['method_id' => $cash->id, 'tendered' => '2200.00']]];

        config()->set('pos.loyalty_enabled', true);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors('payments');
        self::assertSame(0, Sale::query()->count());
        self::assertSame(0, SalePayment::query()->count());
        self::assertSame(0, DB::table('stock_movements')->count());
        self::assertSame('open', $order->fresh()->status);

        config()->set('pos.loyalty_enabled', false);
        session()->forget('errors');
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $sale = Sale::query()->sole();
        self::assertSame('2100.00', $sale->payable_total);
        self::assertSame('2100.00', $sale->paid_total);
        self::assertSame('100.00', $sale->change_total);
        self::assertSame('completed', $order->fresh()->status);
        self::assertSame(1, SalePayment::query()->count());
        self::assertSame(2, DB::table('stock_movements')->count());
        self::assertSame(0, DB::table('loyalty_ledger')->count());
        $this->post(route('pos.checkout'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(1, Sale::query()->count());
        self::assertSame(1, SalePayment::query()->count());
        self::assertSame(2, DB::table('stock_movements')->count());
    }

    public function test_exact_cash_sale_posts_with_enabled_but_missing_denomination(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $this->product->update(['sale_price' => '550.00']);
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_enabled', '1');
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_denomination', null);
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'cash', 'payments' => [['method_id' => $cash->id, 'tendered' => '550.00']]];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $sale = Sale::query()->sole();
        self::assertSame('0.00', $sale->cash_rounding_amount);
        self::assertSame('550.00', $sale->payable_total);
        self::assertSame('550.00', SalePayment::query()->sole()->amount);
    }

    public function test_configured_half_unit_rounds_cash_and_preserves_card_checkout(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $this->product->update(['sale_price' => '550.37']);
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_enabled', '1');
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_denomination', '0.50');
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'cash', 'payments' => [['method_id' => $cash->id, 'tendered' => '550.50']]];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $sale = Sale::query()->sole();
        self::assertSame('550.37', $sale->total);
        self::assertSame('0.13', $sale->cash_rounding_amount);
        self::assertSame('550.50', $sale->payable_total);
        self::assertSame('550.50', SalePayment::query()->sole()->amount);
        $this->post(route('pos.checkout'), $payload)->assertRedirect();
        self::assertSame(1, SalePayment::query()->count());
    }

    public function test_nonzero_cash_adjustment_with_missing_denomination_blocks_but_card_posts(): void
    {
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $card = PaymentMethod::factory()->create(['type' => 'card']);
        $this->product->update(['sale_price' => '550.37']);
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_enabled', '1');
        app(SavePosFinancialSettingAction::class)->execute('pos.cash_rounding_denomination', null);
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $cashPayload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'cash', 'payments' => [['method_id' => $cash->id, 'tendered' => '550.37']]];
        $this->post(route('pos.checkout'), $cashPayload)->assertSessionHasErrors('payments');
        self::assertSame(0, Sale::query()->count());
        $cardPayload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'card', 'payments' => [['method_id' => $card->id, 'amount' => '550.37']]];
        $this->post(route('pos.checkout'), $cardPayload)->assertSessionHasNoErrors();
        self::assertSame('0.00', Sale::query()->sole()->cash_rounding_amount);
        self::assertSame('550.37', SalePayment::query()->sole()->amount);
    }

    public function test_role_based_administrator_can_configure_rounding_but_ordinary_cashier_cannot(): void
    {
        $administrator = User::factory()->create(['status' => 'active', 'is_super_admin' => false]);
        $role = Role::query()->firstOrCreate(['code' => 'system-administrator'], ['name_ar' => 'مسؤول النظام', 'name_en' => 'System Administrator', 'status' => 'active']);
        $administrator->roles()->attach($role);
        $settingsUrl = route('pos.financial-readiness', ['company_id' => $this->store->company_id]);
        $this->actingAs($administrator)->get($settingsUrl)->assertOk()->assertSee(__('Cash rounding denomination'));
        $this->post(route('admin.settings.pos-financial.save'), ['key' => 'pos.cash_rounding_denomination', 'value' => '0.50', 'notes' => 'QA approved half unit'])
            ->assertSessionHasNoErrors();
        self::assertSame('0.5000', PosFinancialSettingVersion::query()->where('key', 'pos.cash_rounding_denomination')->latest('version')->value('value'));

        $ordinary = User::factory()->create(['status' => 'active', 'is_super_admin' => false]);
        $this->actingAs($ordinary)->get($settingsUrl)->assertForbidden();
        $this->post(route('admin.settings.pos-financial.save'), ['key' => 'pos.cash_rounding_enabled', 'value' => '0'])->assertForbidden();
        self::assertNull(PosFinancialSettingVersion::query()->where('key', 'pos.cash_rounding_enabled')->value('value'));
    }

    public function test_above_gross_discount_is_rejected_before_approval_or_cart_mutation(): void
    {
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $order->lines()->update(['quantity' => '2', 'draft_payload' => json_encode(['product_id' => $this->product->id, 'quantity' => '2'])]);
        app(PosOpenOrderManager::class)->hydrateSession(request(), $order->fresh());
        foreach (['61', '-1', '0'] as $amount) {
            $this->post(route('pos.cart.discount'), ['product_id' => $this->product->id, 'discount_type' => 'line', 'discount_amount' => $amount, 'expected_revision' => 0, 'reason' => 'QA'])->assertSessionHasErrors();
            self::assertSame('2.000000', session('pos.cart.0.quantity'));
            self::assertNull(session('pos.cart.0.discount_amount'));
            self::assertSame(0, DB::table('approval_records')->count());
        }
        $this->get(route('pos'))->assertOk();
        self::assertSame('60.00', $order->fresh()->total);
        $this->post(route('pos.cart.discount'), ['product_id' => $this->product->id, 'discount_type' => 'line', 'discount_amount' => '60', 'expected_revision' => 0, 'reason' => 'QA'])->assertSessionHasNoErrors();
        self::assertSame('60.00', session('pos.cart.0.discount_amount'));
    }

    public function test_payment_evidence_uses_the_existing_protected_upload_contract(): void
    {
        Storage::fake('local');
        $method = PaymentMethod::factory()->create(['type' => 'e_wallet', 'requires_evidence' => true]);
        $this->get(route('pos'))->assertOk();
        $panel = app(CheckoutPanel::class);
        $panel->mode = 'card';
        $panel->paymentLines = [['method_id' => (string) $method->id, 'amount' => '30']];
        $render = fn (): string => $panel->render(app(PosCartSnapshot::class), app(PosContextResolver::class), app(PosOpenOrderManager::class))->with(['mode' => $panel->mode, 'paymentLines' => $panel->paymentLines])->render();
        self::assertStringContainsString('name="payments[0][evidence]" type="file" required', $render());
        $this->actingAs(User::factory()->create(['status' => 'active']));
        self::assertStringNotContainsString('name="payments[0][evidence]"', $render());
        self::assertStringContainsString('payment evidence upload permission', $render());
        $this->actingAs($this->cashier);
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $payload = ['checkout_token' => $order->checkout_token, 'open_order_uuid' => $order->uuid, 'payment_mode' => 'card', 'payments' => [['method_id' => $method->id, 'amount' => '30', 'evidence_reference' => 'M5-WALIT']]];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors();
        foreach ([UploadedFile::fake()->create('proof.txt', 1, 'text/plain'), UploadedFile::fake()->create('proof.png', 8193, 'image/png')] as $file) {
            $payload['payments'][0]['evidence'] = $file;
            $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors('payments.0.evidence');
            self::assertSame(0, Sale::query()->count());
            self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
        }
        $this->get(route('pos'))->assertOk();
        $payload['payments'][0]['evidence'] = UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg=='));
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $payment = SalePayment::query()->firstOrFail();
        $attachment = $payment->evidenceAttachment;
        self::assertNotNull($attachment);
        self::assertSame('private', $attachment->visibility);
        self::assertSame(SalePayment::class, $attachment->source_type);
        self::assertSame((string) $payment->id, $attachment->source_id);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_arabic_only_product_name_is_visible_in_english_pos(): void
    {
        app()->setLocale('en');
        Livewire::test(ProductBrowser::class)->call('chooseCategory', $this->product->category_id)->assertSee('لعبة اختبار');
        request()->setLaravelSession(app('session')->driver());
        self::assertStringContainsString('لعبة اختبار', app(Cart::class)->render(app(PosCartSnapshot::class), app(PosContextResolver::class), app(PosOpenOrderManager::class))->with('errors', new ViewErrorBag)->render());
    }

    public function test_pos_category_browser_exposes_all_eligible_categories_beyond_twenty_four(): void
    {
        app()->setLocale('ar');
        for ($number = 1; $number <= 30; $number++) {
            $category = Category::factory()->create([
                'code' => sprintf('POS-CAP-%02d', $number),
                'name_ar' => sprintf('فئة اختبار السقف %02d', $number),
                'sort_order' => $number,
            ]);
            Product::factory()->create(['category_id' => $category->id]);
        }

        Livewire::test(ProductBrowser::class)
            ->assertSee('فئة اختبار السقف 25')
            ->assertSee('فئة اختبار السقف 30');
    }

    public function test_customer_lookup_and_quick_create_do_not_require_consent_policy(): void
    {
        $customer = Customer::query()->create(['idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل الاختبار', 'name_en' => 'QA Customer', 'phone_normalized' => '201011223344', 'phone_display' => '01011223344', 'status' => 'active', 'created_by' => $this->cashier->id, 'created_branch_id' => $this->store->branch_id, 'created_store_id' => $this->store->id]);
        $this->get(route('pos', ['customer_q' => $customer->public_id]))->assertOk()->assertViewHas('customerSearchResults', fn ($results) => $results->contains('id', $customer->id))->assertSee(__('Add New Customer'));
        $this->get(route('pos', ['customer_q' => '0101122']))->assertOk();
        $this->get(route('pos', ['customer_q' => 'M5-NO-MATCH']))->assertOk()->assertViewHas('customerSearchResults', fn ($results) => $results->isEmpty())->assertSee(__('No customer matched this search.'));
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $otherStore = Store::factory()->for($otherBranch)->create(['company_id' => $otherCompany->id]);
        $outside = Customer::query()->create(['idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل خارجي', 'name_en' => 'Other company', 'phone_normalized' => '201099887766', 'phone_display' => '01099887766', 'status' => 'active', 'created_by' => $this->cashier->id, 'created_branch_id' => $otherStore->branch_id, 'created_store_id' => $otherStore->id]);
        $this->get(route('pos', ['customer_q' => $outside->public_id]))->assertOk()->assertViewHas('customerSearchResults', fn ($results) => $results->isEmpty());
    }

    public function test_pos_customer_governorate_is_required_in_arabic_while_city_can_be_omitted(): void
    {
        $this->withSession(['locale' => 'ar']);
        $this->post(route('pos.customer.create'), [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => '01012345678',
            'first_name_ar' => 'أحمد',
            'last_name_ar' => 'محمد',
        ])->assertSessionHasErrors(['governorate_id' => 'يرجى اختيار المحافظة.']);

        $governorate = Governorate::query()->create(['code' => 'QA-POS', 'name_ar' => 'محافظة الاختبار', 'name_en' => 'QA Governorate', 'status' => 'active']);
        self::assertSame([$governorate->id, null], ResidenceGeography::validate((int) $this->store->company_id, $governorate->id, null, cityOptional: true));
        CustomerPolicySettingVersion::query()->create(['key' => 'customer.phone_normalization', 'value' => 'digits_only', 'value_type' => 'string', 'version' => 1, 'created_by' => $this->cashier->id]);
        $this->post(route('pos.customer.create'), [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => '01012345678',
            'first_name_ar' => 'أحمد',
            'last_name_ar' => 'محمد',
            'governorate_id' => $governorate->id,
        ])->assertSessionHasNoErrors();
        self::assertSame(1, Customer::query()->count());
        self::assertSame(0, DB::table('customer_consents')->count());
    }

    public function test_invoice_lookup_shows_approved_sale_without_mutation(): void
    {
        $sale = Sale::query()->create([
            'branch_id' => $this->store->branch_id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'document_number' => 'QA-POS-LOOKUP-001',
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => '30.00',
            'total' => '30.00',
        ]);
        $sale->lines()->create([
            'product_id' => $this->product->id,
            'line_number' => 1,
            'item_code' => $this->product->item_code,
            'name_ar' => $this->product->name_ar,
            'name_en' => 'QA product',
            'quantity' => '1',
            'unit_price' => '30',
            'gross_amount' => '30',
            'net_amount' => '30',
        ]);
        $sale->update(['status' => 'approved', 'approved_at' => now()]);
        Sale::query()->create([
            'branch_id' => $this->store->branch_id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'document_number' => 'QA-POS-LOOKUP-DRAFT',
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->get(route('pos', ['lookup' => 'invoices', 'invoice_q' => 'QA-POS-LOOKUP']))
            ->assertOk()
            ->assertSee('QA-POS-LOOKUP-001')
            ->assertViewHas('invoiceResults', fn ($results) => $results->count() === 1 && $results->first()->is($sale));
        self::assertSame('approved', $sale->fresh()->status);
        self::assertSame('2.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_held_page_reuses_active_context_and_handles_unavailable_shift(): void
    {
        $this->get(route('pos.suspended'))->assertOk()->assertViewHas('store', fn ($store) => $store?->id === $this->store->id)->assertViewHas('shift', fn ($shift) => $shift?->cashier_id === $this->cashier->id);
        DB::table('active_pos_shift_assignments')->delete();
        $this->get(route('pos.suspended'))->assertOk()->assertViewHas('store', null)->assertViewHas('shift', null);
    }

    public function test_product_thumbnails_fall_back_safely_and_preserve_authorization(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('product.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg=='));
        $attachment = app(StoreAttachment::class)->execute($file, 'product_image', new AttachmentSourceReference(sourceType: Product::class, sourceId: (string) $this->product->id), fn () => true);
        $this->product->images()->create(['attachment_id' => $attachment->id, 'role' => 'main', 'sort_order' => 0, 'status' => 'active']);
        $url = route('pos.products.thumbnail', [$this->product, $attachment]);
        $response = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertContains($response->headers->get('Content-Type'), ['image/png', 'image/webp']);
        Storage::disk('local')->put($attachment->storage_path, 'malformed image');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        Storage::disk('local')->delete($attachment->storage_path);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        DB::table('attachments')->where('id', $attachment->id)->update(['storage_disk' => 'unavailable']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $otherProduct = Product::factory()->create();
        $this->get(route('pos.products.thumbnail', [$otherProduct, $attachment]))->assertForbidden();
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $this->get($url)->assertForbidden();
    }

    public function test_pos_quick_create_persists_email_preference_without_internal_purpose_field(): void
    {
        CustomerPolicySettingVersion::query()->create(['key' => 'customer.phone_normalization', 'value' => 'digits_only', 'value_type' => 'string', 'version' => 1, 'created_by' => $this->cashier->id]);
        $governorate = Governorate::query()->create(['code' => 'QA-EMAIL', 'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'active']);
        $this->post(route('pos.customer.create'), [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => '01012345678',
            'first_name_ar' => 'عميل',
            'last_name_ar' => 'بريد',
            'governorate_id' => $governorate->id,
            'email' => 'buyer@example.test',
            'invoice_delivery_method' => 'email',
        ])->assertSessionHasNoErrors();
        $customer = Customer::query()->sole();
        self::assertSame('email', $customer->invoice_delivery_method);
        self::assertSame((int) $this->store->id, (int) $customer->created_store_id);
        self::assertSame(0, $customer->consents()->count());
    }

    public function test_invoice_delivery_defaults_to_print_and_missing_email_falls_back(): void
    {
        Mail::fake();
        $customer = $this->deliveryCustomer(null);
        self::assertSame('print', $customer->fresh()->invoice_delivery_method);
        $sale = $this->deliverySale($customer);
        self::assertSame('print', app(InvoiceDeliveryService::class)->deliver($sale));
        $customer->update(['invoice_delivery_method' => 'email']);
        self::assertSame('print', app(InvoiceDeliveryService::class)->deliver($sale));
        Mail::assertNothingSent();
        self::assertSame(0, DB::table('sale_invoice_delivery_attempts')->count());
    }

    public function test_invoice_delivery_sends_once_from_the_approved_sale_snapshot(): void
    {
        config()->set('mail.default', 'smtp');
        Mail::fake();
        $customer = $this->deliveryCustomer('buyer@example.test', 'email');
        $sale = $this->deliverySale($customer);
        self::assertSame('email_sent', app(InvoiceDeliveryService::class)->deliver($sale));
        self::assertSame('already_attempted', app(InvoiceDeliveryService::class)->deliver($sale));
        Mail::assertSent(PosInvoiceReceipt::class, 1);
        self::assertSame('sent', DB::table('sale_invoice_delivery_attempts')->where('sale_id', $sale->id)->value('status'));
        self::assertStringContainsString($sale->document_number, (new PosInvoiceReceipt($sale->load('store.company', 'branch', 'customer', 'lines')))->render());
    }

    public function test_log_mailer_is_reported_as_failed_instead_of_fake_email_success(): void
    {
        config()->set('mail.default', 'log');
        Mail::fake();
        $sale = $this->deliverySale($this->deliveryCustomer('buyer@example.test', 'email'));
        self::assertSame('email_failed', app(InvoiceDeliveryService::class)->deliver($sale));
        Mail::assertNothingSent();
        self::assertSame('failed', DB::table('sale_invoice_delivery_attempts')->where('sale_id', $sale->id)->value('status'));
        self::assertSame('approved', $sale->fresh()->status);
    }

    public function test_mail_transport_failure_preserves_approved_sale_and_is_not_retried(): void
    {
        config()->set('mail.default', 'smtp');
        $customer = $this->deliveryCustomer('buyer@example.test', 'email');
        $sale = $this->deliverySale($customer);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('QA transport failure'));
        self::assertSame('email_failed', app(InvoiceDeliveryService::class)->deliver($sale));
        self::assertSame('approved', $sale->fresh()->status);
        self::assertSame('failed', DB::table('sale_invoice_delivery_attempts')->where('sale_id', $sale->id)->value('status'));
        self::assertSame('already_attempted', app(InvoiceDeliveryService::class)->deliver($sale));
    }

    public function test_checkout_stays_successful_when_post_sale_invoice_email_fails(): void
    {
        config()->set('mail.default', 'smtp');
        $customer = $this->deliveryCustomer('buyer@example.test', 'email');
        $order = app(PosOpenOrderManager::class)->active(request(), $this->cashier, app(PosContextResolver::class)->resolve($this->cashier));
        $order->update(['customer_id' => $customer->id]);
        foreach (['loyalty.retail_rule' => ['earn_points_per_currency' => '0.0001', 'redeem_currency_per_point' => '1'], 'loyalty.expiry_policy' => ['days' => 365], 'loyalty.rounding_policy' => ['earn' => 'floor', 'redeem' => 'floor'], 'loyalty.ledger_integrity' => ['enabled' => true]] as $key => $value) {
            CustomerPolicySettingVersion::query()->create(['key' => $key, 'value' => json_encode($value), 'value_type' => 'json', 'version' => 1, 'created_by' => $this->cashier->id]);
        }
        $method = PaymentMethod::factory()->create(['type' => 'card']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('QA transport failure'));
        $this->post(route('pos.checkout'), [
            'checkout_token' => $order->checkout_token,
            'open_order_uuid' => $order->uuid,
            'payment_mode' => 'card',
            'payments' => [['method_id' => $method->id, 'amount' => '30']],
        ])->assertSessionHasNoErrors()->assertRedirect(route('pos'));
        $sale = Sale::query()->sole();
        self::assertSame('approved', $sale->status);
        self::assertSame('failed', DB::table('sale_invoice_delivery_attempts')->where('sale_id', $sale->id)->value('status'));
        self::assertSame('1.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_invoice_delivery_rejects_customer_from_another_company(): void
    {
        Mail::fake();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $otherStore = Store::factory()->for($otherBranch)->create(['company_id' => $otherCompany->id]);
        $customer = $this->deliveryCustomer('other@example.test', 'email', $otherStore);
        self::assertSame('print', app(InvoiceDeliveryService::class)->deliver($this->deliverySale($customer)));
        Mail::assertNothingSent();
        self::assertSame(0, DB::table('sale_invoice_delivery_attempts')->count());
    }

    private function deliveryCustomer(?string $email, ?string $method = null, ?Store $store = null): Customer
    {
        $store ??= $this->store;

        return Customer::query()->create([
            'idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل الفاتورة', 'name_en' => 'Invoice customer',
            'phone_normalized' => '2010'.random_int(10000000, 99999999), 'phone_display' => '01012345678',
            'email' => $email,
            'status' => 'active', 'created_by' => $this->cashier->id,
            'created_branch_id' => $store->branch_id, 'created_store_id' => $store->id,
        ] + ($method === null ? [] : ['invoice_delivery_method' => $method]));
    }

    private function deliverySale(Customer $customer): Sale
    {
        $sale = Sale::query()->create([
            'branch_id' => $this->store->branch_id, 'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id, 'customer_id' => $customer->id,
            'document_number' => 'QA-POS-EMAIL-'.Str::random(8), 'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(), 'subtotal' => '30.00', 'total' => '30.00', 'currency_code' => 'EGP',
        ]);
        $sale->update(['status' => 'approved', 'approved_at' => now()]);

        return $sale->fresh();
    }
}
