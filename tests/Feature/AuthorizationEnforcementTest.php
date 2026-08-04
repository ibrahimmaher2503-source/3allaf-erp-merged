<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Actions\SaveUserAuthorizationAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed();
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_super_administrator_reaches_every_current_sensitive_route(): void
    {
        $this->actingAs($this->user('demo-admin'));

        foreach (['/dashboard', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/system/health', '/admin/system/ui-showcase', '/system/app', '/pos'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_current_role_routes_are_limited_to_their_canonical_permissions(): void
    {
        $this->actingAs($this->user('demo-branch-manager'));
        $this->get('/admin/branches')->assertOk();
        $this->get('/pos')->assertOk();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/cash-drawers')->assertForbidden();

        $this->actingAs($this->user('demo-cashier'));
        $this->get('/pos')->assertOk();
        $this->get('/dashboard')->assertForbidden();
        $this->get('/admin/stores')->assertForbidden();

        $this->actingAs($this->user('demo-reviewer'));
        $this->get('/dashboard')->assertOk();
        $this->get('/admin/system/health')->assertOk();
        $this->get('/admin/branches')->assertForbidden();
    }

    public function test_user_without_permissions_is_denied_direct_urls(): void
    {
        $this->actingAs($this->user('demo-no-access'));

        foreach (['/dashboard', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/system/health', '/pos'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_forged_livewire_management_action_is_denied(): void
    {
        $this->actingAs($this->user('demo-branch-manager'));

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->assertForbidden();
    }

    public function test_current_master_write_services_reject_a_user_without_the_action_permission(): void
    {
        $this->actingAs($this->user('demo-branch-manager'));
        $branch = Branch::query()->where('code', 'DEMO-CAI')->firstOrFail();
        $store = Store::query()->where('code', 'DEMO-SELL')->firstOrFail();

        $attempts = [
            fn () => app(SaveBranchAction::class)->execute([]),
            fn () => app(SaveStoreAction::class)->execute([]),
            fn () => app(SaveCashDrawerAction::class)->execute([]),
            fn () => app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $store->id),
            fn () => app(SaveLocalSettingsAction::class)->execute([]),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('A protected write service accepted an unauthorized caller.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_branch_and_store_scopes_isolate_master_queries(): void
    {
        $company = Company::query()->firstOrFail();
        $otherBranch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'OTHER',
            'name_ar' => 'Other',
            'name_en' => 'Other',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);
        $otherStore = Store::query()->create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'code' => 'OTHER-SELL',
            'type' => 'selling',
            'name_ar' => 'Other',
            'name_en' => 'Other',
            'status' => 'active',
        ]);

        $manager = $this->user('demo-branch-manager');
        $cashier = $this->user('demo-cashier');

        $this->assertFalse(Branch::visibleTo($manager)->whereKey($otherBranch)->exists());
        $this->assertFalse(Store::visibleTo($manager)->whereKey($otherStore)->exists());
        $this->assertFalse($cashier->canAccessStore($otherStore->id));
        $this->assertTrue($cashier->canAccessStore(Store::query()->where('code', 'DEMO-SELL')->value('id')));
    }

    public function test_role_assignment_is_audited_and_protects_the_final_system_administrator(): void
    {
        $administrator = $this->user('demo-admin');
        $target = $this->user('demo-no-access');
        $role = Role::query()->where('code', 'cashier')->firstOrFail();
        $this->actingAs($administrator);

        app(SaveUserAuthorizationAction::class)->execute($target, [$role->id], [], []);

        $this->assertTrue($target->fresh()->roles()->whereKey($role)->exists());
        // TSK-009 replaced the retired `settings_audit_logs` writer with the
        // shared append-only `audit_logs` table; there is no dual write.
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'authorization',
            'event' => 'update_user_authorization',
            'source_id' => (string) $target->id,
        ]);
        $this->assertDatabaseCount('settings_audit_logs', 0);

        $this->expectException(ValidationException::class);
        app(SaveUserAuthorizationAction::class)->execute($administrator, [], [], []);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
