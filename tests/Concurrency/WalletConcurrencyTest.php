<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;

/** @group tsk-028 */
final class WalletConcurrencyTest extends ConcurrencyTestCase
{
    use CustomerLoyaltyFixtures;

    public function test_concurrent_identical_product_wallet_settlement_is_idempotent(): void
    {
        [$cashier, $store, $customer, $sale] = $this->walletFixture('IDEM');
        $key = 'TSK028-RACE-IDEM-'.Str::uuid();
        $params = ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'amount' => '10.0000', 'direction' => 'credit', 'source_type' => Sale::class, 'source_id' => (string) $sale->id, 'idempotency_key' => $key];
        $results = $this->race([
            ['product_wallet_entry', $params],
            ['product_wallet_entry', $params],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results));
        self::assertSame($results[0]['result']['ledger_id'], $results[1]['result']['ledger_id']);
        self::assertSame(1, ProductWalletLedger::query()->where('idempotency_key', $key)->count());
        self::assertSame('10.0000', bcadd((string) ProductWalletLedger::query()->where('customer_id', $customer->id)->sum('amount'), '0', 4));
    }

    public function test_concurrent_different_wallet_keys_serialize_without_lost_balance(): void
    {
        [$cashier, $store, $customer, $sale] = $this->walletFixture('SERIAL');
        $results = $this->race([
            ['product_wallet_entry', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'amount' => '10.0000', 'direction' => 'credit', 'source_type' => Sale::class, 'source_id' => (string) $sale->id, 'idempotency_key' => 'TSK028-RACE-A-'.Str::uuid()]],
            ['product_wallet_entry', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'amount' => '15.0000', 'direction' => 'credit', 'source_type' => Sale::class, 'source_id' => (string) $sale->id, 'idempotency_key' => 'TSK028-RACE-B-'.Str::uuid()]],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results));
        self::assertSame(2, ProductWalletLedger::query()->where('customer_id', $customer->id)->count());
        self::assertSame('25.0000', bcadd((string) ProductWalletLedger::query()->where('customer_id', $customer->id)->sum('amount'), '0', 4));
    }

    public function test_concurrent_debits_respect_the_locked_balance_and_do_not_overspend(): void
    {
        [$cashier, $store, $customer, $sale] = $this->walletFixture('DEBIT');
        $this->actingAs($this->administratorForWallet($store));
        app(SaveCustomerPolicySettingAction::class)->execute('wallet.product.debt_limit', '0.0000', 'TSK-028 concurrency no-debt test.');
        $this->actingAs($cashier);
        app(\App\Modules\Customer\Actions\PostProductWalletEntryAction::class)->credit($cashier, $customer, $store, '10.0000', Sale::class, (string) $sale->id, 'TSK028-RACE-SEED-'.Str::uuid());

        $results = $this->race([
            ['product_wallet_entry', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'amount' => '7.0000', 'direction' => 'debit', 'source_type' => Sale::class, 'source_id' => (string) $sale->id, 'idempotency_key' => 'TSK028-RACE-DEBIT-A-'.Str::uuid()]],
            ['product_wallet_entry', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'amount' => '7.0000', 'direction' => 'debit', 'source_type' => Sale::class, 'source_id' => (string) $sale->id, 'idempotency_key' => 'TSK028-RACE-DEBIT-B-'.Str::uuid()]],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok'] ?? false), json_encode($results));
        self::assertCount(1, array_filter($results, static fn (array $result): bool => ! ($result['ok'] ?? false)), json_encode($results));
        self::assertSame('3.0000', bcadd((string) ProductWalletLedger::query()->where('customer_id', $customer->id)->sum('amount'), '0', 4));
    }

    /** @return array{0: \App\Models\User, 1: \App\Modules\Platform\Models\Store, 2: \App\Modules\Customer\Models\Customer, 3: \App\Modules\Retail\Models\Sale} */
    private function walletFixture(string $tag): array
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('TSK028-CONC-'.$tag.'-'.Str::random(5));
        $store = $this->store($branch, 'TSK028-CONC-'.$tag.'-'.Str::random(5));
        $administrator = $this->administrator('tsk028-conc-admin-'.$tag.'-'.Str::random(5));
        $cashier = $this->userWith('tsk028-conc-cashier-'.$tag.'-'.Str::random(5), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->configureCustomerLoyaltyPolicies($administrator);
        $this->configureWalletPolicies($administrator);
        $customer = $this->createTestCustomer($cashier, $store, '010'.random_int(10000000, 99999999));
        $sale = $this->approvedCustomerSale($customer, $store, $cashier, 100);
        return [$cashier, $store, $customer, $sale];
    }

    private function administratorForWallet($store): \App\Models\User
    {
        $admin = $this->administrator('tsk028-conc-limit-'.Str::random(5));
        $this->actingAs($admin);

        return $admin;
    }
}
