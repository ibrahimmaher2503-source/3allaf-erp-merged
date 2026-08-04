<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-002 — Authentication, sessions, and account recovery.
 *
 * @group tsk-002
 */
class AuthenticationLifecycleTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    private const PASSWORD = 'TestOnly!2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        RateLimiter::clear('');
    }

    public function test_the_login_screen_renders_for_a_guest(): void
    {
        $this->get('/login')->assertOk()->assertSee('login', false);
    }

    public function test_a_valid_credential_authenticates_and_reaches_the_configured_home(): void
    {
        $user = $this->userWith('tsk002-admin', ['system-administrator'], true);

        $response = $this->post('/login', [
            'username' => 'tsk002-admin',
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(config('fortify.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invalid_password_is_rejected_and_leaves_the_visitor_a_guest(): void
    {
        $this->userWith('tsk002-user', ['cashier']);

        $response = $this->from('/login')->post('/login', [
            'username' => 'tsk002-user',
            'password' => 'WrongPassword!2026',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_the_credential_error_is_generic_and_does_not_disclose_account_existence(): void
    {
        $this->userWith('tsk002-known', ['cashier']);

        $wrongPassword = $this->from('/login')->post('/login', [
            'username' => 'tsk002-known',
            'password' => 'WrongPassword!2026',
        ])->assertSessionHasErrors('username');

        $unknownAccount = $this->from('/login')->post('/login', [
            'username' => 'tsk002-does-not-exist',
            'password' => 'WrongPassword!2026',
        ])->assertSessionHasErrors('username');

        $this->assertSame(
            $wrongPassword->getSession()->get('errors')->first('username'),
            $unknownAccount->getSession()->get('errors')->first('username'),
            'An unknown account and a wrong password must return the same generic message.',
        );

        $this->assertGuest();
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $this->userWith('tsk002-throttled', ['cashier']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'username' => 'tsk002-throttled',
                'password' => 'WrongPassword!2026',
            ]);
        }

        $blocked = $this->from('/login')->post('/login', [
            'username' => 'tsk002-throttled',
            'password' => self::PASSWORD,
        ]);

        $blocked->assertStatus(429);
        $blocked->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_the_throttled_response_is_the_framework_default_page(): void
    {
        // Recorded coverage fact: no bilingual `errors/429` view exists, so the
        // throttled response falls back to the framework page.
        $this->assertFalse(view()->exists('errors.429'));
        $this->assertFalse(view()->exists('errors.419'));
    }

    public function test_the_session_identifier_is_regenerated_on_login(): void
    {
        $this->userWith('tsk002-session', ['system-administrator'], true);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', [
            'username' => 'tsk002-session',
            'password' => self::PASSWORD,
        ])->assertRedirect(config('fortify.home'));

        $this->assertNotSame($before, session()->getId(), 'Session fixation protection requires a regenerated session ID.');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->userWith('tsk002-logout', ['system-administrator'], true);

        $this->actingAs($user);
        $this->post('/logout')->assertRedirect();

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_only_routes_redirect_a_guest_to_login(): void
    {
        foreach (['/dashboard', '/admin/settings', '/admin/branches', '/admin/audit', '/pos'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_guest_only_routes_redirect_an_authenticated_user(): void
    {
        $this->actingAs($this->userWith('tsk002-guest-check', ['system-administrator'], true));

        $this->get('/login')->assertRedirect(config('fortify.home'));
        $this->get('/forgot-password')->assertRedirect(config('fortify.home'));
    }

    public function test_the_verified_middleware_is_currently_inert_because_the_user_model_is_not_verifiable(): void
    {
        // DEFECT-002 (reported, not fixed): Fortify enables `emailVerification`
        // and every authenticated route carries the `verified` middleware, but
        // `App\Models\User` does not implement `MustVerifyEmail`, so the guard
        // never applies. This test pins the actual behavior so the regression is
        // visible if the model changes.
        $user = $this->userWith('tsk002-unverified', ['system-administrator'], true);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->assertNotInstanceOf(\Illuminate\Contracts\Auth\MustVerifyEmail::class, $user);

        $this->actingAs($user->fresh());
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_password_reset_link_is_issued_for_a_known_account(): void
    {
        Notification::fake();
        $user = $this->userWith('tsk002-reset', ['cashier']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_reset_request_returns_the_same_generic_response(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'not-registered@toyjoy.test',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_a_valid_reset_token_changes_the_password_and_cannot_be_reused(): void
    {
        $user = $this->userWith('tsk002-single-use', ['cashier']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewTestOnly!2026',
            'password_confirmation' => 'NewTestOnly!2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewTestOnly!2026', $user->fresh()->password));

        $replay = $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ReplayedOnly!2026',
            'password_confirmation' => 'ReplayedOnly!2026',
        ]);

        $replay->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('ReplayedOnly!2026', $user->fresh()->password));
    }

    public function test_an_invalid_reset_token_is_rejected(): void
    {
        $user = $this->userWith('tsk002-bad-token', ['cashier']);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => 'this-token-was-never-issued',
            'email' => $user->email,
            'password' => 'NewTestOnly!2026',
            'password_confirmation' => 'NewTestOnly!2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $user = $this->userWith('tsk002-mismatch', ['cashier']);
        $token = Password::broker()->createToken($user);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewTestOnly!2026',
            'password_confirmation' => 'DifferentOnly!2026',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_locale_switch_accepts_supported_locales_only(): void
    {
        $this->actingAs($this->userWith('tsk002-locale', ['system-administrator'], true));

        $this->from('/dashboard')->post('/locale', ['locale' => 'ar'])->assertSessionHasNoErrors();
        $this->assertSame('ar', session('locale'));

        $this->from('/dashboard')->post('/locale', ['locale' => 'zz'])->assertSessionHasErrors('locale');
        $this->assertSame('ar', session('locale'));
    }

    public function test_a_deactivated_role_removes_effective_access_without_a_lockout_feature(): void
    {
        // There is no account lock/disable field on `users`; role status is the
        // only implemented deactivation lever. Recorded as implemented scope.
        $user = $this->userWith('tsk002-role-status', ['system-administrator'], false);
        $this->actingAs($user);
        $this->get('/admin/branches')->assertOk();

        $user->roles()->update(['status' => 'inactive']);

        $this->assertFalse($user->fresh()->hasPermission('branches_stores.view'));
        $this->get('/admin/branches')->assertForbidden();
        $this->assertArrayNotHasKey('is_active', User::query()->first()->getAttributes());
    }
}
