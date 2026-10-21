<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestPermissionLookup
{
    /** @var array<int, array<string, true>> */
    private array $lookups = [];

    /** @return array<string, true> */
    public function for(User $user): array
    {
        $requestKey = 'permission_lookup.'.$user->id;
        if (app()->bound('request') && request()->attributes->has($requestKey)) {
            return request()->attributes->get($requestKey);
        }

        $permissions = DB::table('permissions')->where('permissions.status', 'active');
        if (! $user->hasGlobalApplicationAccess() && ! $user->isCompanyOwner()) {
            $permissions->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
                ->join('role_user', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $user->id)
                ->where('roles.status', 'active')
                ->distinct();
        }

        $lookup = $this->lookups[$user->id] ??= $permissions->get(['permissions.code', 'permissions.module'])
            ->filter(static fn (object $permission): bool => $user->hasGlobalApplicationAccess() || ! $user->isCompanyOwner()
                || CompanyOwnerPermissionProfile::allows($permission->code, $permission->module))
            ->mapWithKeys(static fn (object $permission): array => [$permission->code => true])
            ->all();

        if (app()->bound('request')) {
            request()->attributes->set($requestKey, $lookup);
        }

        return $lookup;
    }
}
