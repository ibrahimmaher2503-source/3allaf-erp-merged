<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class AccessMasterManagementTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_authorized_access_managers_can_reach_roles_and_permission_matrix_while_viewers_cannot_mutate(): void
    {
        $administrator = $this->userWith('access-master-admin', ['system-administrator']);
        $role = Role::query()->where('code', 'cashier')->sole();

        $this->actingAs($administrator);
        $this->get('/admin/roles')->assertOk()->assertSee('Roles');
        $this->get("/admin/roles/{$role->id}/permissions")->assertOk()->assertSee('Permissions');

        $viewerRole = Role::query()->create([
            'code' => 'access-viewer',
            'name_ar' => 'مراجع الوصول',
            'name_en' => 'Access Viewer',
            'status' => 'active',
        ]);
        $viewerRole->permissions()->sync([Permission::query()->where('code', 'users_roles_permissions.view')->sole()->id]);
        $viewer = $this->userWith('access-master-viewer', [$viewerRole->code]);

        $this->actingAs($viewer);
        $this->get('/admin/roles')->assertOk();
        $this->get("/admin/roles/{$role->id}/permissions")->assertOk();
    }

    public function test_access_pages_make_role_management_and_supplier_group_setup_discoverable(): void
    {
        $this->actingAs($this->userWith('access-master-discovery', ['system-administrator']));

        Livewire::test('platform::admin.authorization-baseline')
            ->assertSee('Manage roles');

        Livewire::test('catalog::suppliers')
            ->assertSee('Manage supplier groups')
            ->assertSee(route('catalog.suppliers', ['section' => 'supplier-groups']));
    }

    public function test_branch_selling_store_changes_are_direct_edits_with_optional_change_notes_not_approval_requests(): void
    {
        $this->actingAs($this->userWith('access-master-branch-editor', ['system-administrator'], true));
        $branch = $this->branch('ACCESS-MASTER-BRANCH');
        $this->store($branch, 'ACCESS-MASTER-STORE');

        Livewire::withQueryParams(['section' => 'selling-store-mapping'])
            ->test('platform::admin.branches')
            ->call('openMappingModal', $branch->id)
            ->assertSee('Change notes (optional)')
            ->assertDontSee('Approval / Reason Notes');
    }

    public function test_authorized_administrator_edits_branch_and_store_directly_without_creating_approval_records(): void
    {
        $this->actingAs($this->userWith('access-master-direct-editor', ['system-administrator'], true));
        $branch = $this->branch('ACCESS-DIRECT-BRANCH');
        $store = $this->store($branch, 'ACCESS-DIRECT-STORE');

        Livewire::test('platform::admin.branches')
            ->call('openEditBranchModal', $branch->id)
            ->set('branchForm.name_en', 'Updated direct branch')
            ->call('saveBranch')
            ->assertHasNoErrors();

        Livewire::test('platform::admin.stores')
            ->call('openEditStoreModal', $store->id)
            ->set('storeForm.name_en', 'Updated direct store')
            ->call('saveStore')
            ->assertHasNoErrors();

        Livewire::withQueryParams(['section' => 'selling-store-mapping'])
            ->test('platform::admin.branches')
            ->call('openMappingModal', $branch->id)
            ->set('selectedStoreId', $store->id)
            ->set('mappingApprovalNotes', 'QA direct mapping context')
            ->call('saveSellingStoreMapping')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name_en' => 'Updated direct branch']);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name_en' => 'Updated direct store']);
        $this->assertDatabaseHas('branch_selling_stores', ['branch_id' => $branch->id, 'store_id' => $store->id, 'approval_notes' => 'QA direct mapping context', 'status' => 'active']);
        $this->assertDatabaseMissing('approval_records', ['source_type' => 'platform_settings', 'source_id' => 'branch_delete:'.$branch->id]);
        $this->assertDatabaseMissing('approval_records', ['source_type' => 'platform_settings', 'source_id' => 'store_archive:'.$store->id]);
    }

    public function test_branch_store_viewer_cannot_open_direct_edit_actions(): void
    {
        $branch = $this->branch('ACCESS-VIEW-BRANCH');
        $store = $this->store($branch, 'ACCESS-VIEW-STORE');
        $viewerRole = Role::query()->create(['code' => 'branch-store-viewer', 'name_ar' => 'مراجع الفروع', 'name_en' => 'Branch Store Viewer', 'status' => 'active']);
        $viewerRole->permissions()->sync([Permission::query()->where('code', 'branches_stores.view')->sole()->id]);
        $this->actingAs($this->userWith('access-master-branch-viewer', [$viewerRole->code], false, [$branch->id], [$store->id]));

        Livewire::test('platform::admin.branches')
            ->call('openEditBranchModal', $branch->id)
            ->assertForbidden();

        Livewire::test('platform::admin.stores')
            ->call('openEditStoreModal', $store->id)
            ->assertForbidden();
    }

    public function test_access_manager_creates_a_local_role_and_persists_its_standard_permission_mapping_with_audit(): void
    {
        $this->actingAs($this->userWith('access-master-role-editor', ['system-administrator']));

        Livewire::test('platform::admin.roles')
            ->call('openCreateRole')
            ->set('roleForm', [
                'code' => 'supplier-observer',
                'name_ar' => 'مراقب الموردين',
                'name_en' => 'Supplier Observer',
                'description_ar' => '',
                'description_en' => '',
                'status' => 'active',
            ])
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = Role::query()->where('code', 'supplier-observer')->sole();
        $permission = Permission::query()->where('code', 'suppliers.view')->sole();

        Livewire::test('platform::admin.role-permissions', ['role' => $role])
            ->set('permissionIds', [$permission->id])
            ->call('savePermissions')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'create_role', 'source_id' => (string) $role->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'update_role_permissions', 'source_id' => (string) $role->id]);
    }

    public function test_role_permission_screen_rejects_sensitive_grants_and_canonical_role_mutation(): void
    {
        $this->actingAs($this->userWith('access-master-role-guard', ['system-administrator']));
        $role = Role::query()->create(['code' => 'guarded-local-role', 'name_ar' => 'دور محمي', 'name_en' => 'Guarded local role', 'status' => 'active']);
        $sensitive = Permission::query()->where('sensitivity', 'sensitive')->orderBy('id')->firstOrFail();
        $canonical = Role::query()->where('code', 'cashier')->sole();

        Livewire::test('platform::admin.role-permissions', ['role' => $role])
            ->set('permissionIds', [$sensitive->id])
            ->call('savePermissions')
            ->assertHasErrors('permissionIds');

        Livewire::test('platform::admin.role-permissions', ['role' => $canonical])
            ->call('savePermissions')
            ->assertHasErrors('permissionIds');

        $viewerRole = Role::query()->create(['code' => 'matrix-viewer', 'name_ar' => 'مراجع المصفوفة', 'name_en' => 'Matrix Viewer', 'status' => 'active']);
        $viewerRole->permissions()->sync([Permission::query()->where('code', 'users_roles_permissions.view')->sole()->id]);
        $this->actingAs($this->userWith('access-master-matrix-viewer', [$viewerRole->code]));

        Livewire::test('platform::admin.role-permissions', ['role' => $role])
            ->call('savePermissions')
            ->assertForbidden();
    }

    public function test_supplier_group_setup_is_visible_and_validates_then_persists_a_company_scoped_group(): void
    {
        $this->actingAs($this->userWith('access-master-supplier-editor', ['system-administrator']));

        Livewire::withQueryParams(['section' => 'supplier-groups'])
            ->test('catalog::suppliers')
            ->call('openCreateSupplierGroupModal')
            ->set('supplierGroupForm.name_ar', '')
            ->call('saveSupplierGroup')
            ->assertHasErrors('supplierGroupForm.name_ar')
            ->set('supplierGroupForm.name_ar', 'مجموعة الاختبار')
            ->set('supplierGroupForm.name_en', 'Test group')
            ->call('saveSupplierGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_groups', ['name_ar' => 'مجموعة الاختبار', 'name_en' => 'Test group', 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier_group_created']);
    }
}
