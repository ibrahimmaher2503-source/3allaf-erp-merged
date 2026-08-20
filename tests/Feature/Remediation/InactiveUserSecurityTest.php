<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use App\Modules\Platform\Actions\SaveUserAction;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class InactiveUserSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inactive_super_administrator_cannot_authenticate_or_bypass_a_gate(): void
    {
        $this->seed(ProductionSeeder::class);
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $user->forceFill([
            'status' => 'inactive',
            'is_super_admin' => true,
            'password' => Hash::make('InactiveOnly!2026'),
        ])->save();

        self::assertFalse(Gate::forUser($user->fresh())->allows('company_settings.edit'));

        $this->from('/login')->post('/login', [
            'username' => 'admin',
            'password' => 'InactiveOnly!2026',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_administrator_created_user_has_a_unique_valid_username_for_login(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->actingAs(User::query()->where('username', 'admin')->firstOrFail());

        $created = app(SaveUserAction::class)->execute([
            'name' => 'Remediation cashier',
            'username' => 'remediation-cashier',
            'email' => 'remediation-cashier@toyjoy.test',
            'password' => 'CashierOnly!2026',
            'status' => 'active',
        ], [], [], []);

        self::assertSame('remediation-cashier', $created->username);
        $this->post('/logout');
        $this->post('/login', ['username' => 'remediation-cashier', 'password' => 'CashierOnly!2026'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($created);
    }
}
