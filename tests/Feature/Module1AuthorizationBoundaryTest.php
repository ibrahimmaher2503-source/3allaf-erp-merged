<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\ExportAuditLogs;
use App\Modules\Platform\Actions\SaveRoleAction;
use App\Modules\Platform\Actions\SaveUserAction;
use App\Modules\Platform\Actions\SaveUserAuthorizationAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1AuthorizationBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_view_only_user_cannot_forge_user_or_role_mutation_actions(): void
    {
        $viewer = $this->userWithRole('authorization-viewer', ['users_roles_permissions.view']);

        $this->actingAs($viewer)
            ->get(route('admin.authorization-baseline'))
            ->assertOk();

        $this->get(route('admin.roles'))
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test('platform::admin.authorization-baseline')
            ->call('openCreateUserModal')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test('platform::admin.roles')
            ->call('openCreateRole')
            ->assertForbidden();
    }

    public function test_user_creation_ignores_forged_super_admin_mass_assignment(): void
    {
        $administrator = $this->userWithRole('system-administrator', [
            'users_roles_permissions.create',
            'users_roles_permissions.edit',
        ]);

        $this->actingAs($administrator);

        $created = app(SaveUserAction::class)->execute([
            'name' => 'Forged administrator',
            'username' => 'forged-administrator',
            'email' => 'forged-administrator@example.test',
            'password' => 'fixture-password-2026',
            'status' => 'active',
            'is_super_admin' => true,
        ], [], [], []);

        self::assertFalse($created->is_super_admin);
        self::assertFalse($created->roles()->where('roles.code', 'system-administrator')->exists());
        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'is_super_admin' => false,
        ]);
    }

    public function test_store_only_scope_denies_other_stores_and_known_record_mutation(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
        $assignedStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $sameBranchStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $otherBranchStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id]);

        $editorRole = $this->role('location-editor', [
            'users_roles_permissions.view',
            'users_roles_permissions.edit',
        ]);
        $actor = $this->userWithRoleModel($editorRole);
        UserStoreScope::query()->create([
            'user_id' => $actor->id,
            'store_id' => $assignedStore->id,
            'status' => 'active',
        ]);

        $targetRole = $this->role('location-target');
        $sameBranchTarget = $this->userWithRoleModel($targetRole);
        UserStoreScope::query()->create([
            'user_id' => $sameBranchTarget->id,
            'store_id' => $sameBranchStore->id,
            'status' => 'active',
        ]);
        $otherBranchTarget = $this->userWithRoleModel($targetRole);
        UserStoreScope::query()->create([
            'user_id' => $otherBranchTarget->id,
            'store_id' => $otherBranchStore->id,
            'status' => 'active',
        ]);

        self::assertTrue($actor->canAccessStore((int) $assignedStore->id));
        self::assertFalse($actor->canAccessStore((int) $sameBranchStore->id));
        self::assertFalse($actor->canAccessStore((int) $otherBranchStore->id));

        $this->actingAs($actor)
            ->get(route('admin.authorization-baseline'))
            ->assertOk()
            ->assertDontSee($sameBranchTarget->email)
            ->assertDontSee($otherBranchTarget->email);

        try {
            Livewire::actingAs($actor)
                ->test('platform::admin.authorization-baseline')
                ->call('editAuthorization', $otherBranchTarget->id);
            self::fail('A known user outside the assigned store must not resolve through the component action.');
        } catch (ModelNotFoundException) {
            self::assertFalse(app(UserManagementScope::class)->visibleUsers($actor)->whereKey($otherBranchTarget->id)->exists());
        }

        try {
            app(SaveUserAuthorizationAction::class)->execute(
                $otherBranchTarget,
                [$targetRole->id],
                [],
                [$assignedStore->id],
                'inactive',
            );
            self::fail('An actor must not mutate a known user outside its assigned store.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('editingUserId', $exception->errors());
        }

        self::assertSame('active', $otherBranchTarget->fresh()->status);

        try {
            app(SaveUserAction::class)->execute([
                'name' => 'Forged out-of-scope update',
                'username' => 'forged-out-of-scope',
                'email' => 'forged-out-of-scope@example.test',
                'status' => 'inactive',
            ], [$targetRole->id], [], [$otherBranchStore->id], $otherBranchTarget);
            self::fail('A direct user-save action must not mutate a known user outside the actor scope.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('editingUserId', $exception->errors());
        }

        self::assertSame('active', $otherBranchTarget->fresh()->status);
        self::assertNotSame('Forged out-of-scope update', $otherBranchTarget->fresh()->name);

        $rootRole = $this->role('system-administrator', ['users_roles_permissions.edit']);
        $inScopeTarget = $this->userWithRoleModel($targetRole);
        UserStoreScope::query()->create([
            'user_id' => $inScopeTarget->id,
            'store_id' => $assignedStore->id,
            'status' => 'active',
        ]);

        try {
            app(SaveUserAuthorizationAction::class)->execute(
                $inScopeTarget,
                [$rootRole->id],
                [],
                [$assignedStore->id],
            );
            self::fail('A store-scoped actor must not assign the root-managed role.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('roleIds', $exception->errors());
        }

        self::assertFalse($inScopeTarget->fresh()->roles()->where('roles.code', 'system-administrator')->exists());
    }

    public function test_store_scoped_editor_cannot_call_global_role_actions_directly(): void
    {
        $editor = $this->userWithRole('role-action-editor', ['users_roles_permissions.edit']);
        $targetRole = $this->role('existing-custom-role');
        $permission = $this->permission('products.view');
        $this->actingAs($editor);

        try {
            app(SaveRoleAction::class)->execute([
                'code' => 'forged-global-role',
                'name_ar' => 'دور مزور',
                'name_en' => 'Forged role',
                'status' => 'active',
            ]);
            self::fail('A store-scoped editor must not create a global role through the direct action.');
        } catch (AuthorizationException) {
            self::assertDatabaseMissing('roles', ['code' => 'forged-global-role']);
        }

        try {
            app(SaveRoleAction::class)->syncPermissions($targetRole, [$permission->id]);
            self::fail('A store-scoped editor must not change global role permissions through the direct action.');
        } catch (AuthorizationException) {
            self::assertFalse($targetRole->fresh()->permissions()->whereKey($permission->id)->exists());
        }
    }

    public function test_branch_scope_includes_same_branch_stores_but_not_other_branches(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
        $firstStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $secondStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $otherBranchStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id]);
        $actor = $this->userWithRole('branch-scoped-viewer', ['users_roles_permissions.view']);
        UserBranchScope::query()->create([
            'user_id' => $actor->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        self::assertTrue($actor->canAccessStore((int) $firstStore->id));
        self::assertTrue($actor->canAccessStore((int) $secondStore->id));
        self::assertFalse($actor->canAccessStore((int) $otherBranchStore->id));
    }

    public function test_audit_export_is_denied_without_export_permission(): void
    {
        $viewer = $this->userWithRole('audit-viewer', ['audit_logs.view']);
        $this->actingAs($viewer);

        $this->expectException(AuthorizationException::class);
        app(ExportAuditLogs::class)->execute([]);
    }

    public function test_audit_export_keeps_only_records_in_the_assigned_store_scope(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
        $assignedStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $otherStore = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id]);
        $exporter = $this->userWithRole('audit-exporter', ['audit_logs.export']);
        UserStoreScope::query()->create([
            'user_id' => $exporter->id,
            'store_id' => $assignedStore->id,
            'status' => 'active',
        ]);

        AuditLog::query()->create([
            'event_id' => (string) Str::uuid(),
            'category' => 'authorization',
            'event' => 'visible-store-event',
            'branch_id' => $branch->id,
            'store_id' => $assignedStore->id,
            'created_at' => now(),
        ]);
        AuditLog::query()->create([
            'event_id' => (string) Str::uuid(),
            'category' => 'authorization',
            'event' => 'hidden-store-event',
            'branch_id' => $otherBranch->id,
            'store_id' => $otherStore->id,
            'created_at' => now(),
        ]);

        Config::set('audit.export_max_rows', 100);
        $this->actingAs($exporter)
            ->get(route('admin.audit.export'))
            ->assertForbidden();

        $response = app(ExportAuditLogs::class)->execute([]);
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        self::assertStringContainsString('visible-store-event', $csv);
        self::assertStringNotContainsString('hidden-store-event', $csv);
    }

    public function test_last_active_system_administrator_cannot_be_removed_or_deactivated(): void
    {
        $administratorRole = $this->role('system-administrator', [
            'users_roles_permissions.create',
            'users_roles_permissions.edit',
        ]);
        $firstAdministrator = $this->userWithRoleModel($administratorRole);
        $this->actingAs($firstAdministrator);
        $lastAdministrator = app(SaveUserAction::class)->execute([
            'name' => 'Second system administrator',
            'username' => 'second-system-administrator',
            'email' => 'second-system-administrator@example.test',
            'password' => 'fixture-password-2026',
            'status' => 'active',
        ], [$administratorRole->id], [], []);
        $action = app(SaveUserAuthorizationAction::class);

        self::assertFalse($lastAdministrator->is_super_admin);
        self::assertSame(2, User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.code', 'system-administrator'))
            ->count());

        $this->actingAs($firstAdministrator);
        $action->execute($firstAdministrator, [], [], []);

        self::assertFalse($firstAdministrator->fresh()->roles()->whereKey($administratorRole->id)->exists());
        self::assertTrue($lastAdministrator->fresh()->roles()->whereKey($administratorRole->id)->exists());

        $this->actingAs($lastAdministrator);
        try {
            $action->execute($lastAdministrator, [], [], []);
            self::fail('The final active system administrator role must not be removed.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('roleIds', $exception->errors());
        }

        self::assertSame('active', $lastAdministrator->fresh()->status);
        self::assertTrue($lastAdministrator->fresh()->roles()->whereKey($administratorRole->id)->exists());

        try {
            $action->execute($lastAdministrator, [$administratorRole->id], [], [], 'inactive');
            self::fail('The final active system administrator must not be deactivated.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('roleIds', $exception->errors());
        }

        self::assertSame('active', $lastAdministrator->fresh()->status);
        self::assertTrue($lastAdministrator->fresh()->roles()->whereKey($administratorRole->id)->exists());
    }

    public function test_global_administrator_cannot_manually_create_a_duplicate_canonical_role(): void
    {
        $administrator = $this->userWithRole('system-administrator', ['users_roles_permissions.create']);
        $this->actingAs($administrator);

        try {
            app(SaveRoleAction::class)->execute([
                'code' => 'cashier',
                'name_ar' => 'أمين الصندوق',
                'name_en' => 'Cashier',
                'status' => 'active',
            ]);
            self::fail('A canonical role code must not be created through the custom-role action.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('roleForm.code', $exception->errors());
        }

        $this->assertDatabaseMissing('roles', ['code' => 'cashier']);
    }

    /** @param array<int, string> $permissionCodes */
    private function role(string $code, array $permissionCodes = []): Role
    {
        $role = Role::query()->create([
            'code' => $code,
            'name_ar' => $code,
            'name_en' => $code,
            'status' => 'active',
        ]);

        $permissionIds = array_map(
            fn (string $permissionCode): int => $this->permission($permissionCode)->id,
            $permissionCodes,
        );

        $role->permissions()->sync($permissionIds);

        return $role;
    }

    /** @param array<int, string> $permissionCodes */
    private function userWithRole(string $code, array $permissionCodes = []): User
    {
        return $this->userWithRoleModel($this->role($code, $permissionCodes));
    }

    private function userWithRoleModel(Role $role): User
    {
        $user = User::factory()->create([
            'username' => 'qa-'.Str::lower(Str::random(12)),
            'status' => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function permission(string $code): Permission
    {
        [$module, $action] = array_pad(explode('.', $code, 2), 2, 'view');

        return Permission::query()->firstOrCreate(
            ['code' => $code],
            [
                'module' => $module,
                'action' => $action,
                'sensitivity' => 'normal',
                'status' => 'active',
            ],
        );
    }
}
