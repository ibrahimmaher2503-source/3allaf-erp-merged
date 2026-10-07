<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class RolePermissionPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        app(ProductionSeeder::class)->seedAuthorizationOnly();
    }

    public function test_permission_group_selection_persists_and_reloads_from_the_livewire_screen(): void
    {
        $administrator = $this->systemAdministrator();
        $role = $this->customRole();
        $modules = ['company_settings', 'pricing_labels', 'pricing_lists', 'products_categories_brands'];

        $expectedIds = Permission::query()
            ->whereIn('module', $modules)
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        self::assertNotEmpty($expectedIds);
        self::assertSame(
            count($modules),
            Permission::query()->whereIn('module', $modules)->distinct()->count('module'),
            'The canonical catalog must contain each reported permission group.',
        );

        $this->actingAs($administrator)
            ->get(route('admin.role-permissions', $role))
            ->assertOk();

        $component = Livewire::actingAs($administrator)
            ->test('platform::admin.role-permissions', ['role' => $role]);

        foreach ($modules as $module) {
            $component->call('toggleSection', $module);
        }

        $selectedIds = $component->get('permissionIds');
        sort($selectedIds);
        self::assertSame($expectedIds, $selectedIds);

        $component->call('savePermissions')
            ->assertHasNoErrors();

        self::assertSame(
            $expectedIds,
            $role->fresh()->permissions()->orderBy('permissions.id')->pluck('permissions.id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $reloaded = Livewire::actingAs($administrator)
            ->test('platform::admin.role-permissions', ['role' => $role->fresh()]);
        $reloadedIds = $reloaded->get('permissionIds');
        sort($reloadedIds);

        self::assertSame($expectedIds, $reloadedIds);
    }

    public function test_invalid_permission_ids_do_not_change_existing_role_grants(): void
    {
        $administrator = $this->systemAdministrator();
        $role = $this->customRole();
        $existing = Permission::query()->where('code', 'products_categories_brands.view')->firstOrFail();
        $role->permissions()->sync([$existing->id]);

        Livewire::actingAs($administrator)
            ->test('platform::admin.role-permissions', ['role' => $role])
            ->set('permissionIds', [$existing->id, PHP_INT_MAX])
            ->call('savePermissions')
            ->assertHasErrors('permissionIds.1');

        self::assertSame(
            [(int) $existing->id],
            $role->fresh()->permissions()->pluck('permissions.id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
    }

    private function systemAdministrator(): User
    {
        $role = Role::query()->where('code', 'system-administrator')->firstOrFail();
        $user = User::factory()->create([
            'username' => 'qa-role-admin-'.Str::lower(Str::random(12)),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function customRole(): Role
    {
        $code = 'qa-role-'.Str::lower(Str::random(16));

        return Role::query()->create([
            'code' => $code,
            'name_ar' => 'دور اختبار',
            'name_en' => 'QA Role',
            'status' => 'active',
        ]);
    }
}
