<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Store;

final class AuthenticatedLanding
{
    public function destination(User $user): string
    {
        if (($scopeSetup = $this->scopeSetupDestination($user)) !== null) {
            return $scopeSetup;
        }

        if (! $user->can('dashboard_reports.view') && $user->can('pos_sales.view')) {
            return route('pos');
        }

        return route('dashboard');
    }

    public function scopeSetupDestination(User $user): ?string
    {
        if (! app(UserManagementScope::class)->canManageGlobalRoles($user)
            || ! $user->can('users_roles_permissions.view')
            || Store::query()->visibleTo($user)->where('status', 'active')->exists()) {
            return null;
        }

        return route('admin.authorization-baseline', [
            'setup' => 'true',
            'setup_step' => 'users-scopes',
        ]);
    }
}
