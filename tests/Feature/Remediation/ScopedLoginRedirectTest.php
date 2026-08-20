<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ScopedLoginRedirectTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    private const PASSWORD = 'TestOnly!2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_a_scoped_party_manager_logs_in_to_an_authorized_party_home(): void
    {
        $branch = $this->branch('LOGIN-PARTY');
        $partyStore = $this->store($branch, 'LOGIN-PARTY', 'party');
        Role::query()->where('code', 'party-manager')->firstOrFail()->permissions()->syncWithoutDetaching([
            Permission::query()->where('code', 'party_bookings_invoices.view')->firstOrFail()->id,
        ]);
        $user = $this->userWith('scoped-party-manager', ['party-manager'], false, [$branch->id], [$partyStore->id]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('parties.bookings.index'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('parties.bookings.index'))->assertOk();
    }

    public function test_a_scoped_cashier_keeps_the_existing_authorized_pos_home(): void
    {
        $branch = $this->branch('LOGIN-POS');
        $salesStore = $this->store($branch, 'LOGIN-POS', 'selling');
        $user = $this->userWith('scoped-cashier', ['cashier'], false, [$branch->id], [$salesStore->id]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('pos'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('pos'))->assertOk();
    }
}
