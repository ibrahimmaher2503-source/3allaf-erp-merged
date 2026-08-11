<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

/**
 * Deterministic browser actors for local Demo Auth walkthroughs only.
 *
 * This seeder is called only by DemoSeeder. It never runs as part of the
 * normal deployment seed path and it cannot run outside local Demo Auth.
 */
class PlaywrightLocalActorsSeeder extends Seeder
{
    private const PASSWORD = 'LocalDemoOnly!2026';

    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('Playwright local actors require local Demo Auth.');
        }

        $branchId = Branch::query()->where('code', 'DEMO-CAI')->value('id');
        $storeId = Store::query()->where('code', 'DEMO-SELL')->value('id');

        if ($branchId === null || $storeId === null) {
            throw new LogicException('Playwright local actors require the Demo branch and selling store.');
        }

        $supportRole = Role::query()->updateOrCreate(
            ['code' => 'local-support'],
            [
                'name_ar' => 'دعم محلي',
                'name_en' => 'Local Support',
                'description_ar' => 'دور دعم محلي للعرض المتصفح فقط.',
                'description_en' => 'Local browser-only support role.',
                'status' => 'active',
            ],
        );

        $auditViewPermissionId = Permission::query()->where('code', 'audit_logs.view')->value('id');

        if ($auditViewPermissionId === null) {
            throw new LogicException('The canonical audit view permission is required for the local support fixture.');
        }

        $supportRole->permissions()->sync([$auditViewPermissionId]);

        $actors = [
            'demo-admin' => ['System Administrator', 'demo.admin@toyjoy.local', 'system-administrator', true, 'none'],
            'demo-support' => ['Support User', 'demo.support@toyjoy.local', 'local-support', false, 'branch'],
            'demo-reviewer' => ['Accountant Reviewer', 'demo.reviewer@toyjoy.local', 'accountant-reviewer', false, 'branch'],
            'demo-branch-manager' => ['Branch Manager', 'demo.branch.manager@toyjoy.local', 'branch-manager', false, 'branch'],
            'demo-cashier' => ['Cashier', 'demo.cashier@toyjoy.local', 'cashier', false, 'store'],
            'demo-no-access' => ['User without access', 'demo.no.access@toyjoy.local', null, false, 'none'],
        ];

        foreach ($actors as $username => [$name, $email, $roleCode, $isSuperAdmin, $scopeType]) {
            $user = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::PASSWORD),
                    'is_super_admin' => $isSuperAdmin,
                ],
            );

            $roleId = $roleCode === null ? null : Role::query()->where('code', $roleCode)->value('id');

            if ($roleCode !== null && $roleId === null) {
                throw new LogicException("The local actor role [{$roleCode}] is missing.");
            }

            $user->roles()->sync($roleId === null ? [] : [$roleId]);
            $user->branchScopes()->delete();
            $user->storeScopes()->delete();

            if ($scopeType === 'branch') {
                $user->branchScopes()->create(['branch_id' => $branchId, 'status' => 'active']);
            }

            if ($scopeType === 'store') {
                $user->storeScopes()->create(['store_id' => $storeId, 'status' => 'active']);
            }
        }
    }
}
