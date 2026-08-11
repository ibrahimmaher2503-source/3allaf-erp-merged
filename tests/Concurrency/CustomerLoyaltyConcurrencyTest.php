<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Customer\Actions\EarnLoyaltyAction;
use App\Modules\Customer\Models\LoyaltyLedger;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;

/** @group tsk-027 */
final class CustomerLoyaltyConcurrencyTest extends ConcurrencyTestCase
{
    use CustomerLoyaltyFixtures;

    public function test_concurrent_customer_create_with_same_phone_and_idempotency_collapses_to_one_profile(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('TSK027-CONC-CUS-'.Str::random(6));
        $store = $this->store($branch, 'TSK027-CONC-CUS-'.Str::random(6));
        $administrator = $this->administrator('tsk027-conc-admin-'.Str::random(6));
        $cashier = $this->userWith('tsk027-conc-cashier-'.Str::random(6), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->configureCustomerLoyaltyPolicies($administrator);
        $idempotencyKey = 'TSK027-CONC-CUSTOMER-'.Str::uuid();
        $params = [
            'user_id' => $cashier->id,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
            'phone' => '010-'.random_int(10000000, 99999999),
            'name_ar' => 'عميل تزامن',
            'name_en' => 'Concurrent Customer',
            'consent_purpose' => 'loyalty',
        ];

        $results = $this->race([
            ['customer_create', $params],
            ['customer_create', $params],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results[1]));
        self::assertSame($results[0]['result']['customer_id'], $results[1]['result']['customer_id']);
        self::assertSame(1, \App\Modules\Customer\Models\Customer::query()->where('idempotency_key', $idempotencyKey)->count());
        self::assertSame(1, \App\Modules\Customer\Models\CustomerConsent::query()->where('idempotency_key', $idempotencyKey.':CONSENT:0')->count());
    }

    public function test_concurrent_same_redemption_key_posts_one_ledger_entry(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('TSK027-CONC-LOY-'.Str::random(6));
        $store = $this->store($branch, 'TSK027-CONC-LOY-'.Str::random(6));
        $administrator = $this->administrator('tsk027-conc-loy-admin-'.Str::random(6));
        $cashier = $this->userWith('tsk027-conc-loy-cashier-'.Str::random(6), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->configureCustomerLoyaltyPolicies($administrator);
        $customer = $this->createTestCustomer($cashier, $store, '010'.random_int(10000000, 99999999));
        $sale = $this->approvedCustomerSale($customer, $store, $cashier, 100);
        $this->actingAs($cashier);
        app(EarnLoyaltyAction::class)->executeForSale($cashier, $sale);
        $idempotencyKey = 'TSK027-CONC-REDEEM-'.Str::uuid();

        $results = $this->race([
            ['loyalty_redeem', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'points' => 30, 'idempotency_key' => $idempotencyKey]],
            ['loyalty_redeem', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'points' => 30, 'idempotency_key' => $idempotencyKey]],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results[1]));
        self::assertSame($results[0]['result']['ledger_id'], $results[1]['result']['ledger_id']);
        self::assertSame(1, LoyaltyLedger::query()->where('idempotency_key', $idempotencyKey)->count());
        self::assertSame(70, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
    }

    public function test_concurrent_different_redemption_keys_cannot_redeem_the_same_sale_twice(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('TSK027-CONC-LOY2-'.Str::random(6));
        $store = $this->store($branch, 'TSK027-CONC-LOY2-'.Str::random(6));
        $administrator = $this->administrator('tsk027-conc-loy2-admin-'.Str::random(6));
        $cashier = $this->userWith('tsk027-conc-loy2-cashier-'.Str::random(6), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->configureCustomerLoyaltyPolicies($administrator);
        $customer = $this->createTestCustomer($cashier, $store, '010'.random_int(10000000, 99999999));
        $sale = $this->approvedCustomerSale($customer, $store, $cashier, 100);
        $this->actingAs($cashier);
        app(EarnLoyaltyAction::class)->executeForSale($cashier, $sale);

        $results = $this->race([
            ['loyalty_redeem', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'points' => 30, 'idempotency_key' => 'TSK027-CONC-REDEEM-A-'.Str::uuid()]],
            ['loyalty_redeem', ['user_id' => $cashier->id, 'customer_id' => $customer->id, 'store_id' => $store->id, 'sale_id' => $sale->id, 'points' => 30, 'idempotency_key' => 'TSK027-CONC-REDEEM-B-'.Str::uuid()]],
        ]);

        self::assertCount(1, array_filter($results, static fn (array $result): bool => $result['ok'] ?? false), json_encode($results));
        self::assertCount(1, array_filter($results, static fn (array $result): bool => ! ($result['ok'] ?? false)), json_encode($results));
        self::assertSame('InvalidArgumentException', array_values(array_filter($results, static fn (array $result): bool => ! ($result['ok'] ?? false)))[0]['exception']);
        self::assertSame(1, LoyaltyLedger::query()->where('source_type', \App\Modules\Retail\Models\Sale::class)->where('event_type', 'redeem')->where('source_id', (string) $sale->id)->count());
        self::assertSame(70, (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points'));
    }
}
