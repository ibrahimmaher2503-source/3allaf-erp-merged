<?php

declare(strict_types=1);

namespace Tests\Feature\Party;

use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Customer\Models\Customer;
use App\Modules\Party\Actions\ConfirmPartyBookingAction;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Actions\CreatePartyOperatingOrderAction;
use App\Modules\Party\Actions\ReleasePartyOperatingOrderAction;
use App\Modules\Party\Actions\CompletePartyOperatingOrderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;
use InvalidArgumentException;

/** @group party @group us-027 @group assets */
final class PartyAssetIntegrationTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    protected function beforeRefreshingDatabase(): void
    {
        config(['database.connections.mysql.strict' => false]);
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::connection('mysql')->statement("SET SESSION sql_mode = ''");
    }

    public function test_confirming_a_party_booking_reserves_its_selected_asset_through_us028(): void
    {
        $fixture = $this->fixture();
        $asset = app(CreateAssetAction::class)->execute($fixture['manager'], [
            'code' => 'PARTY-ASSET-001',
            'name_ar' => 'Asset',
            'name_en' => 'Party asset',
            'branch_id' => $fixture['branch']->id,
            'store_id' => $fixture['partyStore']->id,
            'condition' => 'good',
        ]);

        $start = now()->addDays(10)->setTime(14, 0);
        $booking = app(CreatePartyBookingAction::class)->execute($fixture['manager'], $fixture['partyStore'], [
            'customer_id' => $fixture['customer']->id,
            'party_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHours(3)->format('H:i'),
            'timezone' => 'UTC',
            'location' => 'Party room',
            'primary_contact' => '01012345678',
            'idempotency_key' => 'PARTY-ASSET-BOOKING-'.Str::uuid(),
            'lines' => [[
                'line_type' => 'rental_asset',
                'asset_id' => $asset->id,
                'description' => 'Party asset',
                'quantity' => '1',
                'unit_price' => '0.0000',
            ]],
        ]);

        app(ConfirmPartyBookingAction::class)->execute($fixture['manager'], $booking);

        $reservation = AssetReservation::query()->where('source_type', 'party_booking')->where('source_id', (string) $booking->id)->first();
        self::assertNotNull($reservation, 'Confirming a Party booking must create the authoritative US-028 reservation.');
        self::assertSame($asset->id, $reservation->asset_id);
        self::assertSame($fixture['partyStore']->id, $reservation->store_id);
    }

    public function test_asset_workspace_modes_are_addressable_and_semantically_focused(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['manager']);

        foreach ([
            'workspace' => 'Rental assets & calendar',
            'reservations' => 'Asset reservations & checkout',
            'returns' => 'Return, condition & damages',
            'history' => 'Depreciation & asset history',
        ] as $mode => $heading) {
            $this->get(route('party.assets.index', ['mode' => $mode]))->assertOk()->assertSee($heading);
        }
    }

    public function test_party_operating_order_checks_out_returns_inspects_and_completes_the_reserved_asset(): void
    {
        $fixture = $this->fixture();
        $asset = app(CreateAssetAction::class)->execute($fixture['manager'], [
            'code' => 'PARTY-ASSET-002', 'name_ar' => 'Asset', 'name_en' => 'Party asset',
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['partyStore']->id, 'condition' => 'good',
        ]);
        $start = now()->addDays(11)->setTime(14, 0);
        $booking = app(CreatePartyBookingAction::class)->execute($fixture['manager'], $fixture['partyStore'], [
            'customer_id' => $fixture['customer']->id, 'party_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'), 'end_time' => $start->copy()->addHours(3)->format('H:i'),
            'timezone' => 'UTC', 'location' => 'Party room', 'primary_contact' => '01012345678',
            'idempotency_key' => 'PARTY-ASSET-OP-BOOKING-'.Str::uuid(),
            'lines' => [['line_type' => 'rental_asset', 'asset_id' => $asset->id, 'description' => 'Party asset', 'quantity' => '1', 'unit_price' => '0.0000']],
        ]);
        app(ConfirmPartyBookingAction::class)->execute($fixture['manager'], $booking);
        $order = app(CreatePartyOperatingOrderAction::class)->execute($fixture['manager'], $booking->fresh(), $booking->fresh()->invoice, 'PARTY-ASSET-ORDER-'.Str::uuid());
        app(ReleasePartyOperatingOrderAction::class)->execute($fixture['manager'], $order);
        $line = $order->fresh('lines')->lines->sole();

        $checkout = app(\App\Modules\Party\Actions\CheckoutPartyRentalAssetAction::class)->execute($fixture['manager'], $order->fresh(), $line, 'PARTY-ASSET-CHECKOUT-'.Str::uuid());
        self::assertInstanceOf(AssetCheckout::class, $checkout);
        $return = app(\App\Modules\Party\Actions\ReturnPartyRentalAssetAction::class)->execute($fixture['manager'], $order->fresh(), $line->fresh(), 'good', 'PARTY-ASSET-RETURN-'.Str::uuid());
        self::assertInstanceOf(AssetReturn::class, $return);
        $inspection = app(\App\Modules\Party\Actions\InspectPartyRentalAssetAction::class)->execute($fixture['manager'], $order->fresh(), $line->fresh(), 'available', 'Returned in good condition.');
        self::assertInstanceOf(AssetEvent::class, $inspection);
        $completed = app(CompletePartyOperatingOrderAction::class)->execute($fixture['manager'], $order->fresh('lines'));

        self::assertSame('completed', $completed->status);
        self::assertSame('available', $asset->fresh()->status);
        self::assertSame('fulfilled', AssetReservation::query()->where('asset_id', $asset->id)->firstOrFail()->status);
        self::assertSame($checkout->id, $line->fresh()->asset_checkout_id);
        self::assertSame($return->id, $line->fresh()->asset_return_id);
        self::assertSame($inspection->id, $line->fresh()->asset_inspection_event_id);
    }

    public function test_free_text_asset_codes_cannot_bypass_the_authoritative_reservation_selection(): void
    {
        $fixture = $this->fixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('actual rental asset');

        app(CreatePartyBookingAction::class)->execute($fixture['manager'], $fixture['partyStore'], [
            'customer_id' => $fixture['customer']->id,
            'party_date' => now()->addDays(12)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'timezone' => 'UTC',
            'location' => 'Party room',
            'primary_contact' => '01012345678',
            'idempotency_key' => 'PARTY-ASSET-FREE-TEXT-'.Str::uuid(),
            'lines' => [[
                'line_type' => 'rental_asset',
                'resource_key' => 'FREE-TEXT-ASSET-CODE',
                'description' => 'Unresolved asset',
                'quantity' => '1',
                'unit_price' => '0.0000',
            ]],
        ]);
    }

    /** @return array{manager: AppModelsUser, branch: AppModulesPlatformModelsBranch, partyStore: AppModulesPlatformModelsStore, customer: Customer} */
    private function fixture(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('party_booking', 'PB-');
        $this->documentSequence('party_invoice', 'PI-');
        $this->documentSequence('party_operating_order', 'POO-');
        $branch = $this->branch('PARTY-ASSET-BR-'.Str::random(6));
        $partyStore = $this->store($branch, 'PARTY-ASSET-ST-'.Str::random(6), 'party');
        $manager = $this->userWith('party-asset-manager-'.Str::random(8), ['party-manager'], branchIds: [$branch->id], storeIds: [$partyStore->id]);
        $administrator = $this->administrator('party-asset-admin-'.Str::random(8));
        $this->configureCustomerLoyaltyPolicies($administrator);
        $this->configureWalletPolicies($administrator);
        $customer = $this->createTestCustomer($manager, $partyStore, '010'.random_int(10000000, 99999999));

        return compact('manager', 'branch', 'partyStore', 'customer');
    }
}
