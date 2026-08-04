<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveUserAuthorizationAction
{
    /** @param array<int, int> $roleIds @param array<int, int> $branchIds @param array<int, int> $storeIds */
    public function execute(User $user, array $roleIds, array $branchIds, array $storeIds): void
    {
        Gate::authorize('users_roles_permissions.edit');

        DB::transaction(function () use ($user, $roleIds, $branchIds, $storeIds): void {
            $systemAdministratorId = Role::query()->where('code', 'system-administrator')->value('id');

            if ($systemAdministratorId !== null
                && $user->roles()->whereKey($systemAdministratorId)->exists()
                && ! in_array((int) $systemAdministratorId, $roleIds, true)
                && ! User::query()->whereKeyNot($user->id)->whereHas('roles', fn ($query) => $query->whereKey($systemAdministratorId))->exists()) {
                throw ValidationException::withMessages(['roleIds' => __('At least one system administrator must remain assigned.')]);
            }

            $before = ['roles' => $user->roles()->pluck('roles.id')->all(), 'branches' => $user->branchScopes()->pluck('branch_id')->all(), 'stores' => $user->storeScopes()->pluck('store_id')->all()];
            $user->roles()->sync($roleIds);
            $user->branchScopes()->delete();
            $user->storeScopes()->delete();
            $user->branchScopes()->createMany(array_map(fn ($id) => ['branch_id' => $id, 'status' => 'active'], $branchIds));
            $user->storeScopes()->createMany(array_map(fn ($id) => ['store_id' => $id, 'status' => 'active'], $storeIds));
            app(RecordAuditEvent::class)->execute(
                category: 'authorization',
                event: 'update_user_authorization',
                source: $user,
                before: $before,
                after: ['roles' => $roleIds, 'branches' => $branchIds, 'stores' => $storeIds],
            );
        });
    }
}
