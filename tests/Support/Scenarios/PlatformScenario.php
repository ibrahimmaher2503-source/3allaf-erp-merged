<?php

namespace Tests\Support\Scenarios;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Factories\UserFactory;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Datasets\DatasetSize;

final class PlatformScenario
{
    /** @return array{company: Company, branch: Branch, store: Store, user: User} */
    public static function ready(DatasetSize $size = DatasetSize::TINY): array
    {
        app('db')->transaction(function (): void {
            if (! Role::query()->exists()) {
                app(CanonicalAuthorizationSeeder::class)->run();
            }
        });

        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $store = Store::factory()->for($branch)->create(['company_id' => $company->id]);
        $user = UserFactory::new()->create(['username' => 'scenario-cashier-'.(User::query()->count() + 1), 'name' => 'Scenario Cashier']);
        $user->forceFill(['password' => Hash::make('TestOnly!2026')])->save();
        $role = Role::query()->where('code', 'cashier')->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }
        $user->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $user->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        return compact('company', 'branch', 'store', 'user') + ['size' => $size];
    }
}
