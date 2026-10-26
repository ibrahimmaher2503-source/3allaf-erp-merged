<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CoreSystemDemoSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        QaDemoSafety::assertDemoPassword();

        app(ProductionSeeder::class)->seedAuthorizationOnly();

        DB::transaction(function (): void {
            $company = Company::factory()->create([
                'code' => 'QA-DEMO',
                'timezone' => 'Africa/Cairo',
                'locale_default' => 'ar',
            ]);
            $administrator = $this->createUser('qa-admin', 'qa-admin@example.test', true);
            $approver = $this->createUser('qa-approver', 'qa-approver@example.test', false);
            $role = Role::query()->where('code', 'system-administrator')->firstOrFail();
            $administrator->roles()->attach($role);
            $approver->roles()->attach($role);

            $sahil = Branch::factory()->for($company)->create([
                'code' => 'QA-SAHIL',
                'name_ar' => 'فرع الساحل التجريبي',
                'name_en' => 'Sahil Demo Branch',
                'timezone' => 'Africa/Cairo',
                'address' => fake('ar_EG')->address(),
            ]);
            $cairo = Branch::factory()->for($company)->create([
                'code' => 'QA-CAIRO',
                'timezone' => 'Africa/Cairo',
                'address' => fake('ar_EG')->address(),
            ]);
            $outlet = Store::factory()->for($sahil, 'branch')->create(['company_id' => $company->id, 'code' => 'QA-SAHIL-OUTLET', 'type' => 'selling']);
            $source = Store::factory()->for($sahil, 'branch')->warehouse()->create(['company_id' => $company->id, 'code' => 'QA-SAHIL-WH']);
            $destination = Store::factory()->for($cairo, 'branch')->warehouse()->create(['company_id' => $company->id, 'code' => 'QA-CAIRO-WH']);
            $standalone = Store::factory()->standaloneWarehouse($company)->create(['code' => 'QA-STANDALONE-WH']);

            foreach ([$administrator, $approver] as $user) {
                foreach ([$sahil, $cairo] as $branch) {
                    UserBranchScope::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'status' => 'active']);
                }
                foreach ([$outlet, $source, $destination, $standalone] as $store) {
                    UserStoreScope::query()->create(['user_id' => $user->id, 'store_id' => $store->id, 'status' => 'active']);
                }
            }

            Auth::login($administrator);
            try {
                app(SaveBranchSellingStoreMappingAction::class)->execute($sahil->id, $outlet->id, 'QA demo outlet mapping.');
            } finally {
                Auth::logout();
            }

            CashDrawer::factory()->create([
                'company_id' => $company->id,
                'branch_id' => $sahil->id,
                'store_id' => $outlet->id,
                'assigned_user_id' => $administrator->id,
                'code' => 'QA-SAHIL-DRAWER',
            ]);
            PaymentMethod::factory()->create(['code' => 'CASH', 'type' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash']);
            PaymentMethod::factory()->create(['code' => 'CARD', 'type' => 'card', 'name_ar' => 'بطاقة', 'name_en' => 'Card']);
            TaxSetting::query()->create([
                'company_id' => $company->id,
                'code' => 'QA-ZERO',
                'name_ar' => 'ضريبة صفرية تجريبية',
                'name_en' => 'QA Zero Tax',
                'rate' => '0.00',
                'treatment' => 'zero_rated',
                'is_default' => true,
                'is_tax_inclusive' => false,
                'status' => 'active',
            ]);
        });
    }

    private function createUser(string $username, string $email, bool $superAdministrator): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $email,
            'name' => fake('ar_EG')->name(),
            'password' => config('qa-demo.password'),
            'status' => 'active',
        ]);
        $user->forceFill(['is_super_admin' => $superAdministrator])->save();

        return $user;
    }
}
