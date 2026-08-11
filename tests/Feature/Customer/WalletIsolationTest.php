<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Actions\PostProductWalletEntryAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CUS-02, NFR-02, NFR-03. Test cases: TC-CUS-003, TC-CUS-004, TC-CUS-006.
 */
final class WalletIsolationTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    private Branch $branch;

    private User $administrator;

    private Store $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->branch = $this->branch('WALLET-BR');
        $this->store = $this->store($this->branch, 'WALLET-ST');
        $this->administrator = $this->administrator('wallet-admin');
        $this->configureCustomerLoyaltyPolicies($this->administrator);
        $this->configureWalletPolicies($this->administrator);
        $this->customer = $this->createTestCustomer($this->administrator, $this->store, '01090000001');
    }

    public function test_cross_activity_wallet_routes_are_denied_in_both_directions(): void
    {
        $cashier = $this->userWith('wallet-cashier', ['cashier'], branchIds: [$this->branch->id]);
        $partyManager = $this->userWith('wallet-party-manager', ['party-manager'], branchIds: [$this->branch->id]);

        $this->actingAs($cashier)->get(route('wallets.party'))->assertForbidden();
        $this->actingAs($partyManager)->get(route('wallets.product'))->assertForbidden();
        $this->actingAs($cashier)->get(route('customers.party-wallet', $this->customer))->assertForbidden();
        $this->actingAs($partyManager)->get(route('customers.product-wallet', $this->customer))->assertForbidden();
    }

    public function test_super_administrator_can_view_each_separate_ledger(): void
    {
        $this->actingAs($this->administrator)->get(route('wallets.product'))->assertOk()->assertSee('Product Wallet');
        $this->actingAs($this->administrator)->get(route('wallets.party'))->assertOk()->assertSee('Party Wallet');
    }

    public function test_product_and_party_entries_are_physically_separate_and_append_only(): void
    {
        $sale = $this->approvedCustomerSale($this->customer, $this->store, $this->administrator, 10);
        $this->actingAs($this->administrator);
        $product = app(PostProductWalletEntryAction::class)->credit($this->administrator, $this->customer, $this->store, '10.0000', $sale::class, (string) $sale->id, 'PRODUCT-WALLET-1');
        $party = app(PostPartyWalletEntryAction::class)->credit($this->administrator, $this->customer, $this->store, '20.0000', 'party_invoice', 'PARTY-1', 'PARTY-WALLET-1');

        self::assertSame(1, ProductWalletLedger::query()->count());
        self::assertSame(1, PartyWalletLedger::query()->count());
        self::assertSame('10.0000', $product->amount);
        self::assertSame('20.0000', $party->amount);

        $this->expectException(LogicException::class);
        $product->update(['amount' => '999.0000']);
    }

    public function test_party_wallet_entries_cannot_be_deleted(): void
    {
        $this->actingAs($this->administrator);
        $entry = app(PostPartyWalletEntryAction::class)->credit($this->administrator, $this->customer, $this->store, '20.0000', 'party_invoice', 'PARTY-DELETE-1', 'PARTY-WALLET-DELETE-1');

        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
