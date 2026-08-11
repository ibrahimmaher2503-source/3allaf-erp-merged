<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Models\PartyBooking;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;

/** @group party @group us-027 @group concurrency */
final class PartyAssetReservationConcurrencyTest extends ConcurrencyTestCase
{
    use CustomerLoyaltyFixtures;

    public function test_overlapping_party_bookings_racing_for_one_asset_have_exactly_one_winner(): void
    {
        $fixture = $this->fixture();
        $results = $this->race([
            ['party_asset_confirm', ['user_id' => $fixture['manager']->id, 'booking_id' => $fixture['first']->id]],
            ['party_asset_confirm', ['user_id' => $fixture['manager']->id, 'booking_id' => $fixture['second']->id]],
        ]);

        $successful = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
        $failed = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === false));

        self::assertCount(1, $successful, json_encode($results, JSON_THROW_ON_ERROR));
        self::assertCount(1, $failed, json_encode($results, JSON_THROW_ON_ERROR));
        self::assertNotSame('', trim((string) ($failed[0]['message'] ?? '')));
        self::assertSame(1, AssetReservation::query()->where('asset_id', $fixture['asset']->id)->where('source_type', 'party_booking')->count());

        $confirmed = PartyBooking::query()->where('status', 'confirmed')->whereIn('id', [$fixture['first']->id, $fixture['second']->id])->get();
        self::assertCount(1, $confirmed);
        self::assertSame((string) $confirmed->sole()->id, (string) AssetReservation::query()->where('asset_id', $fixture['asset']->id)->where('source_type', 'party_booking')->value('source_id'));
    }

    /** @return array{manager: \App\Models\User, asset: \App\Modules\Assets\Models\RentalAsset, first: PartyBooking, second: PartyBooking} */
    private function fixture(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('party_booking', 'PB-');
        $this->documentSequence('party_invoice', 'PI-');

        $branch = $this->branch('PARTY-ASSET-RACE-BR-'.Str::random(6));
        $store = $this->store($branch, 'PARTY-ASSET-RACE-ST-'.Str::random(6), 'party');
        $manager = $this->userWith('party-asset-race-manager-'.Str::random(8), ['party-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $administrator = $this->administrator('party-asset-race-admin-'.Str::random(8));
        $this->configureCustomerLoyaltyPolicies($administrator);
        $this->configureWalletPolicies($administrator);
        $customer = $this->createTestCustomer($manager, $store, '010'.random_int(10000000, 99999999));
        $asset = app(CreateAssetAction::class)->execute($manager, [
            'code' => 'PARTY-ASSET-RACE-'.Str::random(6),
            'name_ar' => 'Asset',
            'name_en' => 'Race asset',
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'condition' => 'good',
        ]);

        $start = now()->addDays(20)->setTime(14, 0);
        $makeBooking = function (string $suffix) use ($manager, $store, $customer, $asset, $start): PartyBooking {
            return app(CreatePartyBookingAction::class)->execute($manager, $store, [
                'customer_id' => $customer->id,
                'party_date' => $start->toDateString(),
                'start_time' => $start->format('H:i'),
                'end_time' => $start->copy()->addHours(3)->format('H:i'),
                'timezone' => 'UTC',
                'location' => 'Race room '.$suffix,
                'primary_contact' => '01011111111',
                'idempotency_key' => 'PARTY-ASSET-RACE-BOOK-'.$suffix.'-'.Str::uuid(),
                'lines' => [[
                    'line_type' => 'rental_asset',
                    'asset_id' => $asset->id,
                    'description' => 'Race asset',
                    'quantity' => '1',
                    'unit_price' => '0.0000',
                ]],
            ]);
        };

        return [
            'manager' => $manager,
            'asset' => $asset,
            'first' => $makeBooking('one'),
            'second' => $makeBooking('two'),
        ];
    }
}
