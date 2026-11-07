<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AuthenticationRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_posts_are_throttled_after_five_invalid_attempts_for_the_same_username_and_ip(): void
    {
        $username = 'throttle_zz';

        User::factory()->create([
            'username' => $username,
            'status' => 'active',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->from('/login')
                ->post('/login', [
                    'username' => $username,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('username');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->from('/login')
            ->post('/login', [
                'username' => $username,
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_inactive_authenticated_user_is_logged_out_without_closing_the_open_shift(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive_shift_cashier',
            'status' => 'active',
        ]);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);
        $drawer = CashDrawer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => $user->id,
        ]);
        $openedAt = now();
        $shiftId = DB::table('pos_shifts')->insertGetId([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cash_drawer_id' => $drawer->id,
            'cashier_id' => $user->id,
            'status' => 'open',
            'opening_cash' => 100,
            'opened_at' => $openedAt,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);
        DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shiftId,
            'cashier_id' => $user->id,
            'cash_drawer_id' => $drawer->id,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);

        $this->post('/login', [
            'username' => 'inactive_shift_cashier',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);
        Auth::forgetGuards();

        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest('web');
        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shiftId,
            'cashier_id' => $user->id,
            'status' => 'open',
            'closed_at' => null,
        ]);
        $this->assertDatabaseHas('cash_drawers', [
            'id' => $drawer->id,
            'assigned_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('active_pos_shift_assignments', [
            'shift_id' => $shiftId,
            'cashier_id' => $user->id,
            'cash_drawer_id' => $drawer->id,
        ]);
    }

    public function test_password_reset_sends_notification_and_accepts_its_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'password_reset_user',
            'status' => 'active',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        self::assertIsString($token);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Strong-password-123!',
            'password_confirmation' => 'Strong-password-123!',
        ])->assertSessionHas('status');

        $updatedUser = $user->fresh();
        self::assertInstanceOf(User::class, $updatedUser);
        self::assertTrue(Hash::check('Strong-password-123!', $updatedUser->password));
    }
}
