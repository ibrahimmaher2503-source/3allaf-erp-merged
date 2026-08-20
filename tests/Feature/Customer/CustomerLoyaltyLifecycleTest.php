<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\ApproveLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\EarnLoyaltyAction;
use App\Modules\Customer\Actions\ExpireLoyaltyAction;
use App\Modules\Customer\Actions\MergeCustomersAction;
use App\Modules\Customer\Actions\RecordCustomerConsentAction;
use App\Modules\Customer\Actions\RedeemLoyaltyAction;
use App\Modules\Customer\Actions\RequestLoyaltyAdjustmentAction;
use App\Modules\Customer\Actions\PostProductWalletEntryAction;
use App\Modules\Customer\Actions\SaveCustomerChildAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\LoyaltyPointAllocation;
use App\Modules\Customer\Support\ProductWalletBalance;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;
use Database\Seeders\ConsentQaPolicySeeder;

final class CustomerLoyaltyLifecycleTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    private Branch $branch;

    private Store $store;

    private User $administrator;

    private User $cashier;

    private User $manager;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->branch = $this->branch('CUS-BR');
        $this->store = $this->store($this->branch, 'CUS-ST');
        $this->administrator = $this->administrator('tsk027-admin');
        $this->cashier = $this->userWith('tsk027-cashier', ['cashier'], branchIds: [$this->branch->id], storeIds: [$this->store->id]);
        $this->manager = $this->userWith('tsk027-manager', ['branch-manager'], branchIds: [$this->branch->id]);
        $this->reviewer = $this->userWith('tsk027-reviewer', ['accountant-reviewer'], branchIds: [$this->branch->id]);
        $this->configureCustomerLoyaltyPolicies($this->administrator);
    }

    public function test_customer_master_is_unique_bilingual_scoped_idempotent_and_consented(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $customer = $this->createTestCustomer($this->cashier, $this->store, '010 1234 5678', $idempotencyKey);
        $replay = $this->createTestCustomer($this->cashier, $this->store, '010 1234 5678', $idempotencyKey);

        self::assertSame($customer->id, $replay->id);
        self::assertSame('01012345678', $customer->phone_normalized);
        self::assertSame(1, $customer->consents()->count());
        self::assertSame(1, $customer->scopes()->where('store_id', $this->store->id)->count());
        self::assertSame('TSK027-LOCAL-V1', $customer->consents()->firstOrFail()->wording_version);
        self::assertSame(1, AuditLog::query()->where('event', 'customer_created')->where('source_id', (string) $customer->id)->count());

        try {
            $this->createTestCustomer($this->cashier, $this->store, '010-1234-5678');
            self::fail('The unique normalized phone must reject a duplicate customer.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('already exists', $exception->getMessage());
        }
    }

    public function test_consent_qa_policy_fixture_enables_deterministic_customer_creation(): void
    {
        CustomerPolicySettingVersion::query()->delete();
        app(ConsentQaPolicySeeder::class)->run();

        $customer = app(\App\Modules\Customer\Actions\CreateCustomerAction::class)->execute(
            $this->administrator,
            $this->store,
            [
                'idempotency_key' => 'consent-qa-customer-001',
                'phone' => '01070000001',
                'name_ar' => 'عميل اختبار الموافقة',
                'name_en' => 'Consent QA Customer',
                'consents' => [['purpose' => 'service_delivery', 'status' => 'granted', 'source' => 'qa_fixture']],
            ],
        );

        self::assertSame('QA-CONSENT-V1', $customer->consents()->firstOrFail()->wording_version);
        self::assertSame('service_delivery', $customer->consents()->firstOrFail()->purpose);
        self::assertSame('child_profile', json_decode((string) CustomerPolicySettingVersion::query()->where('key', 'customer.children.purpose_scope')->latest('version')->value('value'), true)['purpose']);
    }

    public function test_direct_http_customer_and_pos_routes_enforce_scope_and_sensitive_rbac(): void
    {
        $this->actingAs($this->cashier);
        $phone = '01044444444';
        $response = $this->post(route('customers.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => $phone,
            'name_ar' => 'عميل HTTP',
            'name_en' => 'HTTP Customer',
            'consent_purpose' => 'loyalty',
            'consent_status' => 'granted',
        ]);
        $response->assertRedirect();

        $customer = Customer::query()->where('phone_normalized', $phone)->firstOrFail();
        $this->post(route('pos.customer.select'), ['customer_id' => $customer->id])->assertRedirect();
        self::assertSame($customer->id, (int) session('pos.customer_id'));
        $this->get(route('customers.loyalty.export', $customer))->assertForbidden();

        $originalEmail = $customer->email;
        $this->put(route('customers.update', $customer), [
            'phone' => $customer->phone_display,
            'name_ar' => $customer->name_ar,
            'name_en' => $customer->name_en,
            'email' => 'forged-change@example.test',
            'address_ar' => 'forged',
            'address_en' => 'forged',
        ])->assertForbidden();
        self::assertSame($originalEmail, $customer->fresh()->email);

        $foreignBranch = $this->branch('CUS-HTTP-FOREIGN-BR');
        $foreignStore = $this->store($foreignBranch, 'CUS-HTTP-FOREIGN-ST');
        $foreignCashier = $this->userWith('tsk027-http-foreign', ['cashier'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignCashier);
        $this->get(route('customers.show', $customer))->assertNotFound();
        $this->post(route('pos.customer.select'), ['customer_id' => $customer->id])->assertNotFound();

        $partyManager = $this->userWith('tsk027-party-manager', ['party-manager'], branchIds: [$this->branch->id]);
        $this->actingAs($partyManager);
        $this->get(route('customers.show', $customer))->assertOk();
        $this->get(route('customers.loyalty', $customer))->assertForbidden();
        $this->get(route('customers.loyalty.export', $customer))->assertForbidden();
    }

    public function test_customer_history_and_loyalty_modes_are_addressable_entry_points(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store, '01055555555');
        $this->actingAs($this->administrator);

        $this->get(route('customers.index', ['mode' => 'history', 'q' => $customer->phone_normalized]))
            ->assertOk()
            ->assertSee('Customer transaction history')
            ->assertSee(route('customers.show', $customer), false);

        $this->get(route('customers.index', ['mode' => 'loyalty', 'q' => $customer->phone_normalized]))
            ->assertOk()
            ->assertSee('Loyalty & points')
            ->assertSee(route('customers.loyalty', $customer), false);
    }

    public function test_consent_and_child_history_are_append_only_and_purpose_scoped(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $this->actingAs($this->manager);
        $consent = app(RecordCustomerConsentAction::class)->execute($this->manager, $customer, $this->store, 'service', 'withdrawn', 'profile', 'CONSENT-'.Str::uuid());

        self::assertSame(2, $customer->consents()->count());
        self::assertSame('withdrawn', $consent->status);
        try {
            $consent->update(['status' => 'granted']);
            self::fail('Consent rows must be append-only.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $child = app(SaveCustomerChildAction::class)->execute($this->manager, $customer, $this->store, [
            'name_ar' => 'طفل الاختبار', 'name_en' => 'Test Child', 'birth_date' => '2020-01-02', 'purpose' => 'birthday',
        ]);
        self::assertSame('TSK027-LOCAL-V1', $child->consent_wording_version);
        self::assertSame('birthday', $child->purpose);
        $updated = app(SaveCustomerChildAction::class)->execute($this->manager, $customer, $this->store, [
            'name_ar' => 'طفل محدث', 'name_en' => 'Updated Child', 'birth_date' => '2020-01-03', 'purpose' => 'birthday',
        ], $child);
        self::assertSame(2, $updated->lock_version);
        self::assertSame('Updated Child', $updated->fresh()->name_en);

        try {
            $updated->update(['name_en' => 'Untracked direct update']);
            self::fail('Child profile changes must use the named mutation action.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $child->delete();
    }

    public function test_merge_is_controlled_and_blocks_unsafe_history(): void
    {
        $survivor = $this->createTestCustomer($this->cashier, $this->store, '01011111111');
        $duplicate = $this->createTestCustomer($this->cashier, $this->store, '01022222222');
        $this->actingAs($this->manager);
        $merged = app(MergeCustomersAction::class)->execute($this->manager, $duplicate, $survivor, $this->store, 'Confirmed duplicate profile.', 'MERGE-'.Str::uuid());

        self::assertSame($survivor->id, $merged->id);
        self::assertSame('merged', $duplicate->fresh()->status);
        self::assertSame($survivor->id, $duplicate->fresh()->merged_into_id);
        self::assertDatabaseCount('customer_merge_events', 1);

        $unsafe = $this->createTestCustomer($this->cashier, $this->store, '01033333333');
        $this->approvedCustomerSale($unsafe, $this->store, $this->cashier);
        try {
            app(MergeCustomersAction::class)->execute($this->manager, $unsafe, $survivor, $this->store, 'Unsafe history test.', 'MERGE-'.Str::uuid());
            self::fail('A duplicate with approved sales must not be merged.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('unsafe', strtolower($exception->getMessage()));
        }
        self::assertSame('active', $unsafe->fresh()->status);
        self::assertSame(1, AuditLog::query()->where('event', 'customer_merge_blocked')->count());
    }

    public function test_business_chain_sale_earn_balance_fifo_redeem_and_audit(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $firstSale = $this->approvedCustomerSale($customer, $this->store, $this->cashier, 100, 10);
        $secondSale = $this->approvedCustomerSale($customer, $this->store, $this->cashier, 50);
        $this->actingAs($this->cashier);

        $firstEarn = app(EarnLoyaltyAction::class)->executeForSale($this->cashier, $firstSale);
        $secondEarn = app(EarnLoyaltyAction::class)->executeForSale($this->cashier, $secondSale);
        self::assertSame(90, $firstEarn?->points);
        self::assertSame(50, $secondEarn?->points);
        self::assertSame(140, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));

        $replayedEarn = app(EarnLoyaltyAction::class)->executeForSale($this->cashier, $firstSale->fresh(['customer', 'store']));
        self::assertSame($firstEarn?->id, $replayedEarn?->id);
        $redeemed = app(RedeemLoyaltyAction::class)->execute($this->cashier, $customer, $this->store, $firstSale, 100, 'REDEEM-'.Str::uuid());
        self::assertSame(-100, $redeemed->points);
        self::assertSame(2, $redeemed->debitAllocations()->count());
        self::assertSame(40, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
        self::assertSame(1, AuditLog::query()->where('event', 'loyalty_redeemed')->where('source_id', (string) $redeemed->id)->count());

        try {
            app(RedeemLoyaltyAction::class)->execute($this->cashier, $customer, $this->store, $firstSale, 1, 'REDEEM-'.Str::uuid());
            self::fail('An approved sale may receive at most one redemption.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('already has', $exception->getMessage());
        }
    }

    public function test_business_chain_uses_the_real_pos_sale_before_earn_balance_redeem_and_audit(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $this->documentSequence('retail_sale', 'TSK027-POS-');
        $this->actingAs($this->administrator);
        app(SavePosFinancialSettingAction::class)->execute(
            PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            '1.00',
            'TSK027 real POS business-chain fixture.',
        );

        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'assigned_user_id' => $this->cashier->id,
            'code' => 'TSK027-POS-DR',
            'name_ar' => 'درج اختبار TSK027',
            'name_en' => 'TSK027 Test Drawer',
            'status' => 'active',
        ]);
        app(OpenShiftAction::class)->execute($this->cashier, $drawer, '0.00', 'TSK027-POS-SHIFT-1');

        $category = Category::query()->create([
            'code' => 'TSK027-POS-CAT',
            'name_ar' => 'فئة اختبار TSK027',
            'name_en' => 'TSK027 Test Category',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'TSK027-POS-PROD',
            'name_ar' => 'منتج اختبار TSK027',
            'name_en' => 'TSK027 Test Product',
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'on_hand' => '5',
            'reserved' => '0',
            'in_transit' => '0',
            'average_cost' => '10',
            'total_value' => '50',
            'version' => 1,
        ]);

        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id,
            'code' => 'TSK027-POS-PRICE',
            'name_ar' => 'قائمة أسعار اختبار TSK027',
            'name_en' => 'TSK027 Test Price List',
            'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id,
            'version' => 1,
            'state' => 'approved',
            'source_type' => 'manual',
            'approved_by' => $this->cashier->id,
            'approved_at' => now(),
            'effective_from' => now()->subMinute(),
            'lock_version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => $version->id,
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'amount' => '15.000',
            'active_key' => $product->id.':'.$this->store->id,
        ]);
        $cash = PaymentMethod::query()->create([
            'code' => 'tsk027-cash',
            'name_ar' => 'نقدي',
            'name_en' => 'Cash',
            'type' => 'cash',
            'requires_evidence' => false,
            'status' => 'active',
        ]);

        $this->actingAs($this->cashier);
        $sale = app(RetailSaleAction::class)->create(
            $this->cashier,
            $this->store,
            [['product_id' => $product->id, 'quantity' => '2']],
            'TSK027-POS-SALE-1',
            tenders: [['method' => $cash, 'amount' => '30.00']],
            customer: $customer,
        );

        self::assertInstanceOf(Sale::class, $sale);
        self::assertSame('approved', $sale->status);
        self::assertSame($customer->id, (int) $sale->customer_id);
        self::assertSame(3, (int) StockBalance::query()->where('product_id', $product->id)->where('store_id', $this->store->id)->value('on_hand'));

        $earn = LoyaltyLedger::query()->where('source_type', Sale::class)->where('source_id', (string) $sale->id)->where('event_type', 'earn')->sole();
        self::assertSame(30, (int) $earn->points);
        self::assertSame(30, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));

        $redeemed = app(RedeemLoyaltyAction::class)->execute($this->cashier, $customer, $this->store, $sale, 10, 'TSK027-POS-REDEEM-1');
        self::assertSame(-10, (int) $redeemed->points);
        self::assertSame(20, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
        self::assertSame(1, AuditLog::query()->where('event', 'finalize_sale')->where('source_id', (string) $sale->id)->count());
        self::assertSame(1, AuditLog::query()->where('event', 'loyalty_earned')->where('source_id', (string) $earn->id)->count());
        self::assertSame(1, AuditLog::query()->where('event', 'loyalty_redeemed')->where('source_id', (string) $redeemed->id)->count());

        // TSK-028 consumes the already-real TSK-027 POS/customer source. The
        // wallet mutation remains an explicit, separately-authorized action;
        // no Product/Party Wallet balance is inferred from a sale by default.
        $this->configureWalletPolicies($this->administrator);
        $this->actingAs($this->cashier);
        $walletEntry = app(PostProductWalletEntryAction::class)->credit($this->cashier, $customer, $this->store, '30.0000', Sale::class, (string) $sale->id, 'TSK028-REAL-POS-WALLET-1');
        self::assertSame('30.0000', app(ProductWalletBalance::class)->forCustomer($customer, $this->cashier));
        self::assertSame(Sale::class, $walletEntry->source_type);
        self::assertSame(1, AuditLog::query()->where('event', 'product_wallet_credit_posted')->where('source_id', (string) $walletEntry->id)->count());
    }

    public function test_expiry_is_fifo_idempotent_and_reconciles_the_balance(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->cashier, 25);
        $this->actingAs($this->cashier);
        $earn = app(EarnLoyaltyAction::class)->executeForSale($this->cashier, $sale);
        self::assertNotNull($earn);
        DB::table('loyalty_ledger')->where('id', $earn->id)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->manager);
        self::assertSame(1, app(ExpireLoyaltyAction::class)->execute($this->manager, $customer, $this->store));
        self::assertSame(0, app(ExpireLoyaltyAction::class)->execute($this->manager, $customer, $this->store));
        self::assertSame(0, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
        self::assertSame(1, LoyaltyLedger::query()->where('event_type', 'expiry')->count());
    }

    public function test_adjustment_uses_canonical_approval_sod_and_allocates_negative_points(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $this->actingAs($this->manager);
        $positive = app(RequestLoyaltyAdjustmentAction::class)->execute($this->manager, $customer, $this->store, 20, 'Service recovery.', 'ADJUST-'.Str::uuid(), 'CASE-001');
        self::assertSame('pending', $positive->status);
        self::assertSame(ApprovalState::Pending, $positive->approvalRecord?->approval_state);

        $this->actingAs($this->reviewer);
        app(ApproveLoyaltyAdjustmentAction::class)->execute($this->reviewer, $positive->approvalRecord, $this->store);
        self::assertSame('approved', $positive->fresh()->status);
        self::assertSame(ApprovalState::Approved, $positive->approvalRecord()->firstOrFail()->approval_state);
        self::assertSame(20, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));

        $this->actingAs($this->manager);
        $negative = app(RequestLoyaltyAdjustmentAction::class)->execute($this->manager, $customer, $this->store, -5, 'Correction.', 'ADJUST-'.Str::uuid(), 'CASE-002');
        $this->actingAs($this->reviewer);
        app(ApproveLoyaltyAdjustmentAction::class)->execute($this->reviewer, $negative->approvalRecord, $this->store);
        $negativeLedger = LoyaltyLedger::query()->where('source_type', LoyaltyAdjustment::class)->where('source_id', (string) $negative->id)->sole();
        self::assertSame(5, (int) LoyaltyPointAllocation::query()->where('debit_ledger_id', $negativeLedger->id)->sum('points'));
        self::assertSame(15, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
        self::assertGreaterThanOrEqual(2, AuditLog::query()->where('event', 'approval_requested')->count());

        $this->actingAs($this->manager);
        $rejected = app(RequestLoyaltyAdjustmentAction::class)->execute($this->manager, $customer, $this->store, 7, 'Duplicate request.', 'ADJUST-'.Str::uuid(), 'CASE-003');
        $this->actingAs($this->reviewer);
        app(DecideApprovalSource::class)->reject($rejected->approvalRecord, 'Duplicate request confirmed.');
        self::assertSame('rejected', $rejected->fresh()->status);
        self::assertSame(ApprovalState::Rejected, $rejected->approvalRecord()->firstOrFail()->approval_state);
        self::assertSame(15, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
        self::assertSame(1, AuditLog::query()->where('event', 'loyalty_adjustment_rejected')->where('source_id', (string) $rejected->id)->count());
    }

    public function test_failed_negative_adjustment_rolls_back_approval_transition_and_ledger(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $this->actingAs($this->manager);
        $adjustment = app(RequestLoyaltyAdjustmentAction::class)->execute($this->manager, $customer, $this->store, -5, 'No balance.', 'ADJUST-'.Str::uuid());
        $approval = $adjustment->approvalRecord;

        try {
            $this->actingAs($this->reviewer);
            app(ApproveLoyaltyAdjustmentAction::class)->execute($this->reviewer, $approval, $this->store);
            self::fail('A negative adjustment without balance must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('negative', strtolower($exception->getMessage()));
        }

        self::assertSame('pending', $adjustment->fresh()->status);
        self::assertSame(ApprovalState::Pending, $approval->fresh()->approval_state);
        self::assertDatabaseMissing('loyalty_ledger', ['source_type' => LoyaltyAdjustment::class, 'source_id' => (string) $adjustment->id]);
    }

    public function test_ledger_is_immutable_and_customer_scope_blocks_idor(): void
    {
        $customer = $this->createTestCustomer($this->cashier, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->cashier, 10);
        $this->actingAs($this->cashier);
        $entry = app(EarnLoyaltyAction::class)->executeForSale($this->cashier, $sale);
        self::assertNotNull($entry);
        try {
            $entry->update(['points' => 999]);
            self::fail('A loyalty ledger entry must be append-only.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $foreignBranch = $this->branch('CUS-FOREIGN-BR');
        $foreignStore = $this->store($foreignBranch, 'CUS-FOREIGN-ST');
        $foreignUser = $this->userWith('tsk027-foreign', ['cashier'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignUser);
        $this->get(route('customers.show', $customer))->assertNotFound();
        $this->get(route('customers.loyalty', $customer))->assertNotFound();

        $noAccess = $this->userWith('tsk027-no-access');
        $this->actingAs($noAccess);
        $this->get(route('customers.index'))->assertForbidden();
    }
}
