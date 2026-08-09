<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Customer\Models\PartyWalletLedger;
use App\Modules\Customer\Models\ProductWalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CUS-02, NFR-02, NFR-03. Test cases: TC-CUS-003, TC-CUS-004, TC-CUS-006.
 */
final class WalletIsolationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_cross_activity_wallet_routes_are_denied_in_both_directions(): void
    {
        $this->seedCanonicalAuthorization();
        $cashier = $this->userWith('wallet-cashier', ['cashier']);
        $partyManager = $this->userWith('wallet-party-manager', ['party-manager']);

        $this->actingAs($cashier)->get(route('wallets.party'))->assertForbidden();
        $this->actingAs($partyManager)->get(route('wallets.product'))->assertForbidden();
    }

    public function test_super_administrator_can_view_each_separate_empty_ledger(): void
    {
        $this->seedCanonicalAuthorization();
        $administrator = $this->administrator('wallet-admin');

        $this->actingAs($administrator)->get(route('wallets.product'))
            ->assertOk()
            ->assertSee('Product Wallet');
        $this->actingAs($administrator)->get(route('wallets.party'))
            ->assertOk()
            ->assertSee('Party Wallet');
    }

    public function test_product_and_party_entries_are_physically_separate_and_append_only(): void
    {
        $product = ProductWalletLedger::query()->create([
            'entry_type' => 'test_credit', 'amount' => '10.0000', 'currency_code' => 'EGP',
            'idempotency_key' => 'PRODUCT-WALLET-1', 'created_at' => now(),
        ]);
        $party = PartyWalletLedger::query()->create([
            'entry_type' => 'test_credit', 'amount' => '20.0000', 'currency_code' => 'EGP',
            'idempotency_key' => 'PARTY-WALLET-1', 'created_at' => now(),
        ]);

        self::assertSame(1, ProductWalletLedger::query()->count());
        self::assertSame(1, PartyWalletLedger::query()->count());
        self::assertSame('10.0000', $product->amount);
        self::assertSame('20.0000', $party->amount);

        $this->expectException(LogicException::class);
        $product->update(['amount' => '999.0000']);
    }

    public function test_party_wallet_entries_cannot_be_deleted(): void
    {
        $entry = PartyWalletLedger::query()->create([
            'entry_type' => 'test_credit', 'amount' => '20.0000', 'currency_code' => 'EGP',
            'idempotency_key' => 'PARTY-WALLET-DELETE-1', 'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
