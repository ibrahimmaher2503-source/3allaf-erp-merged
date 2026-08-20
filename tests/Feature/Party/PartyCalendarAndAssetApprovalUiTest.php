<?php

declare(strict_types=1);

namespace Tests\Feature\Party;

use App\Models\User;
use App\Modules\Assets\Actions\CreateAssetAction;
use App\Modules\Assets\Actions\CreateAssetEventAction;
use App\Modules\Assets\Actions\ReserveAssetAction;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** @group party @group us-025 @group us-028 @group us-029 */
final class PartyCalendarAndAssetApprovalUiTest extends TestCase
{
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
        DB::purge('mysql');
        DB::connection('mysql')->statement("SET SESSION sql_mode = ''");
    }

    public function test_scoped_party_calendar_renders_only_the_authorized_bounded_schedule_and_asset_conflict_data(): void
    {
        $fixture = $this->calendarFixture();

        $this->actingAs($fixture['requester'])
            ->get('/parties/calendar?from='.$fixture['from']->toDateString().'&to='.$fixture['to']->toDateString())
            ->assertOk()
            ->assertSee('Party calendar')
            ->assertSee($fixture['inScopeReservation']->source_reference)
            ->assertDontSee($fixture['outOfRangeReservation']->source_reference)
            ->assertDontSee($fixture['foreignReservation']->source_reference);

        $this->actingAs($fixture['deniedUser'])
            ->get('/parties/calendar')
            ->assertForbidden();
    }

    public function test_pending_asset_event_approval_control_is_visible_only_to_a_separate_scoped_approver(): void
    {
        $fixture = $this->assetEventFixture();
        $approvalUrl = route('party.asset-events.approve', $fixture['event']);

        self::assertNotSame($fixture['requester']->id, $fixture['approver']->id);

        $this->actingAs($fixture['requester'])
            ->get(route('party.assets.index', ['mode' => 'history']))
            ->assertOk()
            ->assertDontSee('action="'.$approvalUrl.'"', false)
            ->assertDontSee('Approve asset event');

        $this->actingAs($fixture['approver'])
            ->get(route('party.assets.index', ['mode' => 'history']))
            ->assertOk()
            ->assertSee('action="'.$approvalUrl.'"', false)
            ->assertSee('Approve asset event');
    }

    public function test_requester_and_unscoped_user_cannot_post_pending_asset_event_approval(): void
    {
        $fixture = $this->assetEventFixture();
        $approvalUrl = route('party.asset-events.approve', $fixture['event']);

        $this->actingAs($fixture['requester'])->post($approvalUrl)->assertForbidden();
        self::assertSame('submitted', $fixture['event']->fresh()->status);

        $this->actingAs($fixture['deniedUser'])->post($approvalUrl)->assertForbidden();
        self::assertSame('submitted', $fixture['event']->fresh()->status);
    }

    /** @return array{requester: User, deniedUser: User, from: CarbonImmutable, to: CarbonImmutable, inScopeReservation: AssetReservation, outOfRangeReservation: AssetReservation, foreignReservation: AssetReservation} */
    private function calendarFixture(): array
    {
        [$requester, $deniedUser, $branch, $partyStore] = $this->actors();
        $from = now()->addDay()->startOfDay()->toImmutable();
        $to = $from->addDays(6)->endOfDay();

        $inScopeReservation = $this->reserveAsset($requester, $branch, $partyStore, $from->addHours(14), $from->addHours(17), 'CALENDAR-IN-SCOPE');
        $outOfRangeReservation = $this->reserveAsset($requester, $branch, $partyStore, $to->addDay()->addHours(14), $to->addDay()->addHours(17), 'CALENDAR-OUT-OF-RANGE');

        $foreignBranch = $this->branch('PARTY-CAL-FOREIGN-BR-'.Str::random(6));
        $foreignStore = $this->store($foreignBranch, 'PARTY-CAL-FOREIGN-ST-'.Str::random(6), 'party');
        $foreignReservation = $this->reserveAsset($this->administrator('party-calendar-foreign-admin-'.Str::random(6)), $foreignBranch, $foreignStore, $from->addHours(14), $from->addHours(17), 'CALENDAR-FOREIGN');

        return compact('requester', 'deniedUser', 'from', 'to', 'inScopeReservation', 'outOfRangeReservation', 'foreignReservation');
    }

    /** @return array{requester: User, approver: User, deniedUser: User, event: AssetEvent} */
    private function assetEventFixture(): array
    {
        [$requester, $deniedUser, $branch, $partyStore] = $this->actors();
        $this->actingAs($requester);
        $asset = app(CreateAssetAction::class)->execute($requester, [
            'code' => 'PARTY-APPROVAL-ASSET-'.Str::random(6),
            'name_ar' => 'أصل اعتماد',
            'name_en' => 'Approval asset',
            'branch_id' => $branch->id,
            'store_id' => $partyStore->id,
            'condition' => 'good',
        ]);
        $event = app(CreateAssetEventAction::class)->execute($requester, $asset, [
            'event_type' => 'maintenance',
            'assessment' => 'A separately authorized reviewer must approve this pending maintenance event.',
            'resulting_status' => 'under_maintenance',
            'idempotency_key' => 'party-asset-approval-'.Str::uuid(),
        ]);
        $approverRole = Role::query()->create([
            'code' => 'party-asset-approver-'.Str::lower(Str::random(6)),
            'name_ar' => 'مراجع أصول الحفلات',
            'name_en' => 'Party asset reviewer',
            'status' => 'active',
        ]);
        $approverRole->permissions()->sync(Permission::query()
            ->whereIn('code', ['rental_assets.view', 'rental_assets.approve'])
            ->pluck('id')
            ->all());
        $approver = $this->userWith(
            'party-asset-approver-'.Str::random(6),
            [$approverRole->code],
            branchIds: [$branch->id],
            storeIds: [$partyStore->id],
        );

        return compact('requester', 'approver', 'deniedUser', 'event');
    }

    /** @return array{User, User, Branch, Store} */
    private function actors(): array
    {
        $branch = $this->branch('PARTY-CAL-BR-'.Str::random(6));
        $partyStore = $this->store($branch, 'PARTY-CAL-ST-'.Str::random(6), 'party');
        Role::query()->where('code', 'party-manager')->firstOrFail()->permissions()->syncWithoutDetaching(
            Permission::query()
                ->whereIn('code', ['rental_assets.view', 'rental_assets.create', 'rental_assets.reserve'])
                ->pluck('id')
                ->all(),
        );
        $requester = $this->userWith('party-calendar-requester-'.Str::random(6), ['party-manager'], branchIds: [$branch->id], storeIds: [$partyStore->id]);
        $deniedUser = $this->userWith('party-calendar-denied-'.Str::random(6), ['cashier'], branchIds: [$branch->id], storeIds: [$partyStore->id]);

        return [$requester, $deniedUser, $branch, $partyStore];
    }

    private function reserveAsset(User $user, Branch $branch, Store $store, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $reference): AssetReservation
    {
        $asset = app(CreateAssetAction::class)->execute($user, [
            'code' => 'PARTY-CALENDAR-ASSET-'.Str::random(6),
            'name_ar' => 'أصل تقويم',
            'name_en' => 'Calendar asset',
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'condition' => 'good',
        ]);

        return app(ReserveAssetAction::class)->execute($user, $asset, [
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'timezone' => 'UTC',
            'source_reference' => $reference,
            'idempotency_key' => 'party-calendar-reservation-'.Str::uuid(),
        ]);
    }
}
