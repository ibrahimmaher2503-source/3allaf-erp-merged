<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1SettingsAuthorizationRedteamTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_revoked_settings_permission_forbids_editing_a_print_template_from_an_existing_component(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        Store::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(['code' => 'company_settings.edit'], [
            'module' => 'company_settings',
            'action' => 'edit',
            'sensitivity' => 'normal',
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'code' => 'qa-settings-redteam-'.Str::lower(Str::random(8)),
            'name_ar' => 'محرر الإعدادات للاختبار',
            'name_en' => 'QA settings editor',
            'status' => 'active',
        ]);
        $role->permissions()->sync([$permission->id]);
        $editor = User::factory()->create([
            'username' => 'qa-settings-redteam-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $editor->roles()->sync([$role->id]);
        $editor->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $template = PrintTemplate::query()->create([
            'company_id' => $company->id,
            'code' => 'qa-redteam-template',
            'name_ar' => 'قالب اختبار',
            'name_en' => 'QA template',
            'document_type' => 'sales_invoice',
            'paper_size' => '80mm',
            'language' => 'bilingual',
            'header_text' => 'private template header',
            'footer_text' => 'private template footer',
            'layout_settings' => ['show_price' => true],
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($editor)
            ->test('platform::admin.settings')
            ->set('activeTab', 'printers');

        $role->permissions()->detach($permission->id);
        request()->attributes->remove('permission_lookup.'.$editor->id);
        app()->forgetScopedInstances();

        $component->call('editPrintTemplate', $template->id)->assertForbidden();
    }
}
