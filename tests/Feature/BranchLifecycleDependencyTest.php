<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

class BranchLifecycleDependencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_active_store_blocks_branch_deactivation_with_a_specific_message(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['branch_id' => $branch->id, 'company_id' => $company->id]);
        $user = User::factory()->create();
        $this->grantPermissions($user, ['branches_stores.edit', 'branches_stores.view']);
        UserBranchScope::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $this->actingAs($user);
        $this->assertTrue($user->hasPermission('branches_stores.edit'));
        $this->assertSame('active', $user->status);
        $this->assertSame($user->id, auth()->id());
        $this->assertTrue(Gate::allows('branches_stores.edit'));

        $exception = null;
        try {
            app(SaveBranchAction::class)->toggleStatus($branch->id);
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception, (string) $exception);
        $this->assertSame(
            __('Cannot deactivate branch while it has active stores or an active selling store mapping.'),
            $exception->errors()['branch'][0],
        );
        $this->assertSame('active', $branch->fresh()->status);

        Livewire::test('platform::admin.branches')
            ->call('toggleBranchStatus', $branch->id)
            ->assertDispatched('toast-show', fn (string $event, array $parameters): bool => ($parameters['slots']['text'] ?? null) === __('Cannot deactivate branch while it has active stores or an active selling store mapping.')
            );
    }

    public function test_active_selling_mapping_alone_blocks_branch_deactivation(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create([
            'branch_id' => $branch->id,
            'company_id' => $company->id,
            'type' => 'selling',
            'status' => 'inactive',
        ]);
        BranchSellingStore::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'effective_from' => now(),
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $this->grantPermissions($user, ['branches_stores.edit']);
        UserBranchScope::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $this->actingAs($user);

        $exception = null;
        try {
            app(SaveBranchAction::class)->toggleStatus($branch->id);
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception, (string) $exception);
        $this->assertSame(
            __('Cannot deactivate branch while it has active stores or an active selling store mapping.'),
            $exception->errors()['branch'][0],
        );
        $this->assertSame('active', $branch->fresh()->status);
    }

    public function test_active_store_and_selling_mapping_block_status_edits_as_well_as_toggles(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['branch_id' => $branch->id, 'company_id' => $company->id]);
        BranchSellingStore::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'effective_from' => now(),
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $this->grantPermissions($user, ['branches_stores.edit']);
        UserBranchScope::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $this->actingAs($user);

        $exception = null;
        try {
            app(SaveBranchAction::class)->execute([
                'code' => $branch->code,
                'name_ar' => $branch->name_ar,
                'name_en' => $branch->name_en,
                'status' => 'inactive',
            ], $branch->id);
            $this->fail('A branch with active stores and an active selling mapping must not deactivate.');
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception, (string) $exception);
        $this->assertSame(
            __('Cannot deactivate branch while it has active stores or an active selling store mapping.'),
            $exception->errors()['branch'][0],
        );
        $this->assertSame('active', $branch->fresh()->status);
    }

    public function test_active_user_scopes_are_not_new_deactivation_blockers(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create();
        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->grantPermissions($user, ['branches_stores.edit']);
        $this->actingAs($user);

        $result = app(SaveBranchAction::class)->toggleStatus($branch->id);

        $this->assertSame('inactive', $result->status);
        $this->assertSame('inactive', $branch->fresh()->status);
    }

    public function test_branch_delete_request_captures_context_and_approval_keeps_active_dependencies_blocked(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['branch_id' => $branch->id, 'company_id' => $company->id]);
        BranchSellingStore::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'effective_from' => now(),
            'status' => 'active',
        ]);
        CashDrawer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
        ]);

        $user = User::factory()->create();
        UserBranchScope::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        UserStoreScope::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => 'active',
        ]);
        $this->grantPermissions($user, ['branches_stores.view', 'branches_stores.logical_delete']);

        $this->actingAs($user);

        Livewire::test('platform::admin.branches')->call('deleteBranch', $branch->id);

        $approval = ApprovalRecord::query()
            ->where('requested_action', 'branch_delete')
            ->where('source_id', 'branch_delete:'.$branch->id)
            ->firstOrFail();

        $this->assertSame('pending', $approval->approval_state->value);
        $this->assertSame([
            'deleted' => true,
            'store_count' => 1,
            'active_store_count' => 1,
            'active_selling_mapping_count' => 1,
            'active_cash_drawer_count' => 1,
            'active_user_scope_count' => 2,
        ], $approval->limit_context['proposed']);
        $this->assertSame('active', $branch->fresh()->status);

        $approver = User::factory()->create();
        $this->grantPermissions($approver, ['company_settings.approve']);
        UserBranchScope::query()->create([
            'user_id' => $approver->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->actingAs($approver);

        $exception = null;
        try {
            app(PlatformSettingsApprovalAction::class)->approve($approval);
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(ValidationException::class, $exception, (string) $exception);
        $this->assertSame(
            __('Cannot deactivate branch while it has active stores or an active selling store mapping.'),
            $exception->errors()['approval'][0],
        );
        $this->assertSame('pending', $approval->fresh()->approval_state->value);
        $this->assertSame('active', $branch->fresh()->status);
        $this->assertSame('active', $store->fresh()->status);
        $this->assertSame('active', $store->branch->activeSellingStoreMapping->status);
    }

    /** @param array<int, string> $permissionCodes */
    private function grantPermissions(User $user, array $permissionCodes): void
    {
        $role = Role::query()->create([
            'code' => 'qa-branch-'.Str::lower(Str::random(12)),
            'name_ar' => 'اختبار دورة الفرع',
            'name_en' => 'Branch lifecycle test',
            'status' => 'active',
        ]);
        $permissionIds = [];

        foreach ($permissionCodes as $permissionCode) {
            $permissionIds[] = Permission::query()->create([
                'code' => $permissionCode,
                'module' => 'branches_stores',
                'action' => Str::afterLast($permissionCode, '.'),
                'status' => 'active',
            ])->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id);
        $user->refresh();
    }
}
