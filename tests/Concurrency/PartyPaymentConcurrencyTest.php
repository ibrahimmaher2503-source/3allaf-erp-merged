<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Models\User;
use App\Modules\Party\Actions\ConfirmPartyBookingAction;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyPayment;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;

/** @group party @group us-026 @group concurrency */
final class PartyPaymentConcurrencyTest extends ConcurrencyTestCase
{
    use CustomerLoyaltyFixtures;

    public function test_concurrent_duplicate_party_payment_creates_one_receipt_and_one_balance_change(): void
    {
        $fixture = $this->fixture();
        $key = 'PARTY-RACE-PAY-'.Str::uuid();
        $params = ['user_id' => $fixture['manager']->id, 'invoice_id' => $fixture['invoice']->id, 'payment_method_id' => $fixture['cash']->id, 'amount' => '1000.0000', 'idempotency_key' => $key];
        $results = $this->race([
            ['party_payment', $params],
            ['party_payment', $params],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, json_encode($results));
        self::assertTrue($results[1]['ok'] ?? false, json_encode($results));
        self::assertSame($results[0]['result']['payment_id'], $results[1]['result']['payment_id']);
        self::assertSame(1, PartyPayment::query()->where('idempotency_key', $key)->count());
        self::assertSame('1000.0000', (string) PartyInvoice::query()->findOrFail($fixture['invoice']->id)->paid_amount);
    }

    /** @return array{manager: User, invoice: PartyInvoice, cash: PaymentMethod} */
    private function fixture(): array
    {
        $this->seedCanonicalAuthorization();
        foreach ([['party_booking', 'PB-'], ['party_invoice', 'PI-'], ['party_payment_receipt', 'PPR-']] as [$type, $prefix]) {
            $this->documentSequence($type, $prefix);
        }
        $branch = $this->branch('PARTY-RACE-BR-'.Str::random(6));
        $store = $this->store($branch, 'PARTY-RACE-ST-'.Str::random(6), 'party');
        $manager = $this->userWith('party-race-manager-'.Str::random(8), ['party-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $administrator = $this->administrator('party-race-admin-'.Str::random(8));
        $this->configureCustomerLoyaltyPolicies($administrator);
        $this->configureWalletPolicies($administrator);
        $customer = $this->createTestCustomer($manager, $store, '010'.random_int(10000000, 99999999));
        $this->actingAs($manager);
        $booking = app(CreatePartyBookingAction::class)->execute($manager, $store, ['customer_id' => $customer->id, 'party_date' => now()->addDays(12)->toDateString(), 'start_time' => '12:00', 'end_time' => '15:00', 'timezone' => 'UTC', 'location' => 'Race room '.Str::random(5), 'primary_contact' => '01011111111', 'idempotency_key' => 'PARTY-RACE-BOOK-'.Str::uuid(), 'lines' => [['line_type' => 'service', 'description' => 'Race package', 'quantity' => '1', 'unit_price' => '1000.00']]]);
        app(ConfirmPartyBookingAction::class)->execute($manager, $booking);
        $cash = PaymentMethod::query()->create(['code' => 'party-race-cash-'.Str::random(8), 'name_ar' => 'نقد', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);

        return ['manager' => $manager, 'invoice' => $booking->fresh()->invoice, 'cash' => $cash];
    }
}
