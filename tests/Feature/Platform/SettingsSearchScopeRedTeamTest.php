<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

final class SettingsSearchScopeRedTeamTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_scoped_settings_search_rejects_a_forged_company_id(): void
    {
        $authorizedCompany = Company::factory()->create();
        $authorizedBranch = Branch::factory()->create(['company_id' => $authorizedCompany->id]);
        Store::factory()->create([
            'company_id' => $authorizedCompany->id,
            'branch_id' => $authorizedBranch->id,
        ]);
        TaxSetting::query()->create([
            'company_id' => $authorizedCompany->id,
            'code' => 'QA-IN-SCOPE-'.strtoupper(bin2hex(random_bytes(4))),
            'name_ar' => 'ضريبة ضمن النطاق',
            'name_en' => 'IN-SCOPE-TAX',
            'rate' => '5.00',
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
        ]);

        $otherCompany = Company::factory()->create();
        TaxSetting::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'QA-CROSS-COMPANY-'.strtoupper(bin2hex(random_bytes(4))),
            'name_ar' => 'بيانات شركة أخرى سرية',
            'name_en' => 'OUT-OF-SCOPE-TAX-SECRET',
            'rate' => '17.00',
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
        ]);

        $actor = $this->userWithPermission('company_settings.edit');
        $actor->branchScopes()->create([
            'branch_id' => $authorizedBranch->id,
            'status' => 'active',
        ]);
        $this->assertSame($authorizedCompany->id, app(AuthorizedCompanyContext::class)->resolve($actor)->id);
        $this->actingAs($actor);

        $component = Livewire::test('platform::admin.settings')
            ->set('activeTab', 'tax')
            ->assertSee('IN-SCOPE-TAX');

        try {
            $component->set('companyId', $otherCompany->id);
        } catch (CannotUpdateLockedPropertyException) {
            return;
        }

        $this->assertFalse(
            str_contains($component->html(), 'OUT-OF-SCOPE-TAX-SECRET'),
            'A branch-scoped settings user must not use a forged company ID to render another company tax rule.',
        );
    }

    private function userWithPermission(string $permissionCode): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'module' => str($permissionCode)->beforeLast('.')->toString(),
                'action' => str($permissionCode)->afterLast('.')->toString(),
                'sensitivity' => 'high',
                'status' => 'active',
            ],
        );
        $role = Role::query()->create([
            'code' => 'qa-scope-'.strtolower(bin2hex(random_bytes(6))),
            'name_ar' => 'مستخدم إعدادات QA',
            'name_en' => 'QA scoped settings user',
            'status' => 'active',
        ]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }
}
