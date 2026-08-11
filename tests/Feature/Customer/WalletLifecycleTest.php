<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\User;
use App\Modules\Customer\Actions\ApproveProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Actions\PostProductWalletEntryAction;
use App\Modules\Customer\Actions\RejectProductWalletAdjustmentAction;
use App\Modules\Customer\Actions\RequestProductWalletAdjustmentAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\ProductWalletBalance;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class WalletLifecycleTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    private Store $store;

    private User $administrator;

    private User $operator;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('TSK028-BR');
        $this->store = $this->store($branch, 'TSK028-ST');
        $this->administrator = $this->administrator('tsk028-admin');
        $this->operator = $this->walletUser('tsk028-operator', ['customers.view', 'customers.create', 'product_wallet.view', 'product_wallet.settle', 'product_wallet.adjust'], $branch->id, $this->store->id);
        $this->reviewer = $this->walletUser('tsk028-reviewer', ['customers.view', 'product_wallet.view', 'product_wallet.approve', 'party_wallet.view', 'party_wallet.approve'], $branch->id, $this->store->id);
        $this->configureCustomerLoyaltyPolicies($this->administrator);
    }

    public function test_unset_owner_financial_policy_fails_closed_without_a_ledger_row(): void
    {
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->operator, 25);
        $this->actingAs($this->operator);

        $this->expectException(InvalidArgumentException::class);
        app(PostProductWalletEntryAction::class)->credit($this->operator, $customer, $this->store, '10.00', $sale::class, (string) $sale->id, 'TSK028-POLICY-MISSING');
        self::assertDatabaseCount('product_wallet_ledger', 0);
    }

    public function test_product_and_party_wallets_credit_debit_and_derive_separate_balances(): void
    {
        $this->configureWalletPolicies($this->administrator);
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->operator, 100);
        $this->actingAs($this->operator);

        $productCredit = app(PostProductWalletEntryAction::class)->credit($this->operator, $customer, $this->store, '25.0000', $sale::class, (string) $sale->id, 'TSK028-PRODUCT-CREDIT');
        app(PostProductWalletEntryAction::class)->debit($this->operator, $customer, $this->store, '5.0000', $sale::class, (string) $sale->id, 'TSK028-PRODUCT-DEBIT');
        $this->actingAs($this->administrator);
        app(PostPartyWalletEntryAction::class)->credit($this->administrator, $customer, $this->store, '12.5000', 'party_invoice', 'PTY-INV-1', 'TSK028-PARTY-CREDIT');

        self::assertSame('20.0000', app(ProductWalletBalance::class)->forCustomer($customer, $this->operator));
        self::assertSame('12.5000', bcadd((string) \App\Modules\Customer\Models\PartyWalletLedger::query()->where('customer_id', $customer->id)->sum('amount'), '0', 4));
        self::assertSame(1, $productCredit->balance_before === '0.0000' ? 1 : 0);
        self::assertSame(2, ProductWalletLedger::query()->where('customer_id', $customer->id)->count());
        self::assertSame(1, \App\Modules\Customer\Models\PartyWalletLedger::query()->where('customer_id', $customer->id)->count());

        $this->expectException(InvalidArgumentException::class);
        app(PostProductWalletEntryAction::class)->debit($this->operator, $customer, $this->store, '2000.0000', $sale::class, (string) $sale->id, 'TSK028-INSUFFICIENT');
    }

    public function test_idempotency_replay_mismatch_and_append_only_ledger_are_enforced(): void
    {
        $this->configureWalletPolicies($this->administrator);
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->operator, 25);
        $this->actingAs($this->operator);
        $action = app(PostProductWalletEntryAction::class);
        $entry = $action->credit($this->operator, $customer, $this->store, '10.0000', $sale::class, (string) $sale->id, 'TSK028-IDEM');
        $replay = $action->credit($this->operator, $customer, $this->store, '10.0000', $sale::class, (string) $sale->id, 'TSK028-IDEM');
        self::assertSame($entry->id, $replay->id);

        try {
            $action->credit($this->operator, $customer, $this->store, '11.0000', $sale::class, (string) $sale->id, 'TSK028-IDEM');
            self::fail('A mismatched wallet replay must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('different payload', $exception->getMessage());
        }

        try {
            ProductWalletLedger::query()->create(['customer_id' => $customer->id]);
            self::fail('Direct ledger creation must fail closed.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
        try {
            $entry->update(['amount' => '99.0000']);
            self::fail('Ledger history must be immutable.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_adjustment_approval_correction_reversal_rejection_and_sod_use_canonical_history(): void
    {
        $this->configureWalletPolicies($this->administrator);
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->operator, 100);
        $this->actingAs($this->operator);
        $initial = app(PostProductWalletEntryAction::class)->credit($this->operator, $customer, $this->store, '100.0000', $sale::class, (string) $sale->id, 'TSK028-INITIAL');

        $requester = $this->walletUser('tsk028-maker', ['customers.view', 'product_wallet.adjust'], $this->store->branch_id, $this->store->id);
        $this->actingAs($requester);
        $adjustment = app(RequestProductWalletAdjustmentAction::class)->execute($requester, $customer, $this->store, 'adjustment', '25.0000', 'manual_adjustment', 'CASE-ADJUST-1', 'Approved recovery.', 'TSK028-ADJUST-1');
        self::assertSame(ApprovalState::Pending, $adjustment->approvalRecord?->approval_state);

        $this->actingAs($this->reviewer);
        $posted = app(ApproveProductWalletAdjustmentAction::class)->execute($this->reviewer, $adjustment->approvalRecord, $this->store);
        self::assertSame('adjustment', $posted->entry_type);
        self::assertSame('approved', $adjustment->fresh()->status);
        self::assertSame(ApprovalState::Approved, $adjustment->approvalRecord()->firstOrFail()->approval_state);

        $this->actingAs($requester);
        $correction = app(RequestProductWalletAdjustmentAction::class)->execute($requester, $customer, $this->store, 'correction', '110.0000', 'manual_correction', 'CASE-CORRECT-1', 'Correct the original source amount.', 'TSK028-CORRECT-1', $initial->id);
        $this->actingAs($this->reviewer);
        app(ApproveProductWalletAdjustmentAction::class)->execute($this->reviewer, $correction->approvalRecord, $this->store);
        self::assertSame('135.0000', app(ProductWalletBalance::class)->forCustomer($customer, $this->reviewer));
        self::assertSame(1, ProductWalletLedger::query()->where('reversal_of_id', $initial->id)->count());
        self::assertSame(1, ProductWalletLedger::query()->where('correction_of_id', $initial->id)->count());

        $this->actingAs($requester);
        $rejected = app(RequestProductWalletAdjustmentAction::class)->execute($requester, $customer, $this->store, 'adjustment', '7.0000', 'manual_adjustment', 'CASE-REJECT-1', 'Reject this request.', 'TSK028-REJECT-1');
        $beforeRejected = app(ProductWalletBalance::class)->forCustomer($customer, $this->reviewer);
        $this->actingAs($this->reviewer);
        app(RejectProductWalletAdjustmentAction::class)->execute($this->reviewer, $rejected->approvalRecord, 'Not approved.');
        self::assertSame($beforeRejected, app(ProductWalletBalance::class)->forCustomer($customer, $this->reviewer));
        self::assertSame('rejected', $rejected->fresh()->status);

        $makerApprover = $this->walletUser('tsk028-maker-approver', ['customers.view', 'product_wallet.adjust', 'product_wallet.approve'], $this->store->branch_id, $this->store->id);
        $this->actingAs($makerApprover);
        $sod = app(RequestProductWalletAdjustmentAction::class)->execute($makerApprover, $customer, $this->store, 'adjustment', '1.0000', 'manual_adjustment', 'CASE-SOD-1', 'SoD check.', 'TSK028-SOD-1');
        $this->expectException(AuthorizationException::class);
        app(ApproveProductWalletAdjustmentAction::class)->execute($makerApprover, $sod->approvalRecord, $this->store);
    }

    public function test_approval_posting_rolls_back_when_the_result_breaks_the_configured_limit(): void
    {
        $this->configureWalletPolicies($this->administrator);
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $sale = $this->approvedCustomerSale($customer, $this->store, $this->operator, 100);
        $this->actingAs($this->operator);
        app(PostProductWalletEntryAction::class)->credit($this->operator, $customer, $this->store, '100.0000', $sale::class, (string) $sale->id, 'TSK028-ROLLBACK-INITIAL');
        $this->actingAs($this->administrator);
        app(\App\Modules\Customer\Actions\SaveCustomerPolicySettingAction::class)->execute('wallet.product.credit_limit', '100.0000', 'Rollback test only.');
        $requester = $this->walletUser('tsk028-rollback-maker', ['customers.view', 'product_wallet.adjust'], $this->store->branch_id, $this->store->id);
        $this->actingAs($requester);
        $adjustment = app(RequestProductWalletAdjustmentAction::class)->execute($requester, $customer, $this->store, 'adjustment', '10.0000', 'manual_adjustment', 'CASE-ROLLBACK', 'Over the configured limit.', 'TSK028-ROLLBACK-ADJUST');
        $this->actingAs($this->reviewer);
        try {
            app(ApproveProductWalletAdjustmentAction::class)->execute($this->reviewer, $adjustment->approvalRecord, $this->store);
            self::fail('The limit violation must fail and roll back.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('credit limit', strtolower($exception->getMessage()));
        }
        self::assertSame('pending', $adjustment->fresh()->status);
        self::assertSame(ApprovalState::Pending, $adjustment->approvalRecord()->firstOrFail()->approval_state);
        self::assertSame(1, ProductWalletLedger::query()->count());
        self::assertSame('100.0000', app(ProductWalletBalance::class)->forCustomer($customer, $this->reviewer));
    }

    public function test_direct_http_wallet_routes_enforce_wallet_and_scope_isolation(): void
    {
        $this->configureWalletPolicies($this->administrator);
        $customer = $this->createTestCustomer($this->operator, $this->store);
        $this->actingAs($this->operator);
        $this->get(route('customers.product-wallet', $customer))->assertOk()->assertSee('Derived balance');
        $this->get(route('customers.party-wallet', $customer))->assertForbidden();
        $partyManager = $this->walletUser('tsk028-party', ['customers.view', 'party_wallet.view'], $this->store->branch_id, $this->store->id);
        $this->actingAs($partyManager);
        $this->get(route('customers.party-wallet', $customer))->assertOk();
        $this->get(route('customers.product-wallet', $customer))->assertForbidden();

        $foreignBranch = $this->branch('TSK028-FOREIGN-BR');
        $foreignStore = $this->store($foreignBranch, 'TSK028-FOREIGN-ST');
        $foreignUser = $this->walletUser('tsk028-foreign', ['customers.view', 'product_wallet.view'], $foreignBranch->id, $foreignStore->id);
        $this->actingAs($foreignUser);
        $this->get(route('customers.product-wallet', $customer))->assertNotFound();
        $this->get(route('wallets.party'))->assertForbidden();
        self::assertFalse($foreignUser->can('product_wallet.approve'));
    }

    private function walletUser(string $username, array $permissions, int $branchId, int $storeId): User
    {
        $role = Role::query()->updateOrCreate(['code' => 'tsk028-'.$username], [
            'name_ar' => 'TSK-028', 'name_en' => 'TSK-028 '.Str::headline($username), 'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id')->all());

        return $this->userWith($username, [$role->code], false, [$branchId], [$storeId]);
    }
}
