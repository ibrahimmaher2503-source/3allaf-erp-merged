<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use Tests\TestCase;

final class AuthenticationRedTeamReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_inactive_session_revocation_rotates_session_and_csrf_and_stays_guest(): void
    {
        $user = User::factory()->create([
            'username' => 'redteam_inactive_'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $this->logIn($user);
        $oldSessionId = session()->getId();
        $oldToken = session()->token();

        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);
        Auth::forgetGuards();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_every_non_active_status_value_is_revoked_when_encountered(): void
    {
        foreach (['inactive', 'suspended', 'deleted'] as $status) {
            $user = User::factory()->create([
                'username' => 'redteam_status_'.Str::lower(Str::random(8)),
                'status' => 'active',
            ]);

            $this->logIn($user);
            DB::table('users')->where('id', $user->id)->update(['status' => $status]);
            Auth::forgetGuards();

            $this->get('/dashboard')->assertRedirect(route('login'));
            $this->assertGuest('web');
            $this->get('/dashboard')->assertRedirect(route('login'));
            $this->assertGuest('web');
        }
    }

    public function test_inactive_session_is_revoked_before_pos_shift_post_side_effects(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();

        $user = User::factory()->create([
            'username' => 'redteam_post_'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::query()->where('code', 'cashier')->firstOrFail());

        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'selling',
            'status' => 'active',
        ]);
        $user->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'status' => 'active',
        ]);

        $this->logIn($user);
        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);
        Auth::forgetGuards();

        $this->post(route('pos.shift.store'), [
            'cash_drawer_id' => $drawer->id,
            'opening_float' => '100.00',
            'idempotency_key' => (string) Str::uuid(),
            'confirm_outlet' => '1',
        ])->assertRedirect(route('login'));

        $this->assertGuest('web');
        $this->assertDatabaseMissing('pos_shifts', ['cashier_id' => $user->id]);
        $this->assertDatabaseMissing('active_pos_shift_assignments', ['cashier_id' => $user->id]);
    }

    public function test_inactive_session_is_revoked_before_livewire_component_updates(): void
    {
        $user = User::factory()->create([
            'username' => 'redteam_livewire_'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $user->forceFill(['is_super_admin' => true])->save();

        $this->logIn($user);

        $component = Livewire::test('platform::system.approval-inbox')
            ->assertSee(__('Approval requests'));

        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);
        Auth::forgetGuards();

        $snapshot = Utils::extractAttributeDataFromHtml($component->html(), 'wire:snapshot');
        self::assertIsArray($snapshot);

        $response = $this->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => ['search' => 'probe'],
                'calls' => [],
            ]],
        ], ['X-Livewire' => 'true']);

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_missing_inactive_and_wrong_password_responses_use_equal_hash_work(): void
    {
        $inactive = User::factory()->create([
            'username' => 'redteam_inactive_login_'.Str::lower(Str::random(8)),
            'status' => 'inactive',
        ]);
        $active = User::factory()->create([
            'username' => 'redteam_active_login_'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        Hash::spy();

        $missingResponse = $this->from('/login')->post('/login', [
            'username' => 'redteam_missing_'.Str::lower(Str::random(8)),
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $missingMessage = $missingResponse->getSession()->get('errors')->getBag('default')->first('username');

        $inactiveResponse = $this->from('/login')->post('/login', [
            'username' => $inactive->username,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $inactiveMessage = $inactiveResponse->getSession()->get('errors')->getBag('default')->first('username');

        $activeResponse = $this->from('/login')->post('/login', [
            'username' => $active->username,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $activeMessage = $activeResponse->getSession()->get('errors')->getBag('default')->first('username');

        self::assertSame($missingMessage, $inactiveMessage);
        self::assertSame($inactiveMessage, $activeMessage);
        Hash::shouldHaveReceived('check')->times(3);
    }

    public function test_missing_username_with_dummy_digest_returns_generic_login_failure(): void
    {
        $this->from('/login')->post('/login', [
            'username' => 'redteam_missing_'.Str::lower(Str::random(8)),
            'password' => 'wrong-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
    }

    private function logIn(User $user): void
    {
        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect();
    }
}
