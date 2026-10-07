<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class Module1MasterLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $administrator;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Gate::before(static fn (User $user, string $ability): bool => true);

        Company::factory()->create();

        $this->administrator = User::factory()->create([
            'username' => 'qa-master-localization-admin',
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        $this->role = Role::query()->create([
            'code' => 'qa-master-localization-role',
            'name_ar' => 'دور اختبار الترجمة',
            'name_en' => 'Localization QA role',
            'status' => 'active',
        ]);
    }

    public function test_all_module_one_master_pages_render_the_expected_bilingual_titles_and_direction(): void
    {
        $pages = [
            ['id' => 'UI-ADM-002', 'route' => 'admin.settings', 'parameters' => ['tab' => 'company'], 'panel' => 'panel-company', 'titles' => ['en' => 'System Settings', 'ar' => 'إعدادات النظام', 'ar-EG' => 'إعدادات النظام']],
            ['id' => 'UI-ADM-003', 'route' => 'admin.branches', 'parameters' => [], 'panel' => null, 'titles' => ['en' => 'Branch Management', 'ar' => 'إدارة الفروع', 'ar-EG' => 'إدارة الفروع']],
            ['id' => 'UI-ADM-004', 'route' => 'admin.stores', 'parameters' => [], 'panel' => null, 'titles' => ['en' => 'Store & Inventory Mapping Masters', 'ar' => 'البيانات الأساسية للمواقع وربط المخزون', 'ar-EG' => 'البيانات الأساسية للمواقع وربط المخزون']],
            ['id' => 'UI-ADM-005', 'route' => 'admin.cash-drawers', 'parameters' => [], 'panel' => null, 'titles' => ['en' => 'Cashier Tills', 'ar' => 'خزائن الكاشير', 'ar-EG' => 'خزائن الكاشير']],
            ['id' => 'UI-ADM-006', 'route' => 'admin.settings', 'parameters' => ['tab' => 'payments'], 'panel' => 'panel-payments', 'titles' => ['en' => 'System Settings', 'ar' => 'إعدادات النظام', 'ar-EG' => 'إعدادات النظام']],
            ['id' => 'UI-ADM-007', 'route' => 'admin.settings', 'parameters' => ['tab' => 'tax'], 'panel' => 'panel-tax', 'titles' => ['en' => 'System Settings', 'ar' => 'إعدادات النظام', 'ar-EG' => 'إعدادات النظام']],
            ['id' => 'UI-ADM-008', 'route' => 'admin.settings', 'parameters' => ['tab' => 'sequences'], 'panel' => 'panel-sequences', 'titles' => ['en' => 'System Settings', 'ar' => 'إعدادات النظام', 'ar-EG' => 'إعدادات النظام']],
            ['id' => 'UI-ADM-009', 'route' => 'admin.settings', 'parameters' => ['tab' => 'printers'], 'panel' => 'panel-printers', 'titles' => ['en' => 'System Settings', 'ar' => 'إعدادات النظام', 'ar-EG' => 'إعدادات النظام']],
            ['id' => 'UI-ADM-010', 'route' => 'admin.authorization-baseline', 'parameters' => [], 'panel' => null, 'titles' => ['en' => 'Authorization Baseline', 'ar' => 'أساس الصلاحيات والأدوار', 'ar-EG' => 'أساس الصلاحيات والأدوار']],
            ['id' => 'UI-ADM-011', 'route' => 'admin.roles', 'parameters' => [], 'panel' => null, 'titles' => ['en' => 'Roles', 'ar' => 'الأدوار', 'ar-EG' => 'الأدوار']],
            ['id' => 'UI-ADM-012', 'route' => 'admin.role-permissions', 'parameters' => ['role' => 'test-role'], 'panel' => null, 'titles' => ['en' => 'Role Permissions', 'ar' => 'صلاحيات الدور', 'ar-EG' => 'صلاحيات الدور']],
        ];

        foreach ($pages as $page) {
            foreach (['en' => 'ltr', 'ar' => 'rtl', 'ar-EG' => 'rtl'] as $locale => $direction) {
                $parameters = $page['parameters'];
                if ($page['route'] === 'admin.role-permissions') {
                    $parameters['role'] = $this->role->id;
                }

                $response = $this->withSession(['locale' => $locale])
                    ->actingAs($this->administrator)
                    ->get(route($page['route'], $parameters));

                $response->assertOk()
                    ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);

                $hasTitle = preg_match('/<title>\s*(.*?)\s*<\/title>/si', (string) $response->getContent(), $titleMatch);

                self::assertSame(1, $hasTitle, $page['id'].' '.$locale.' title element');

                $renderedTitle = html_entity_decode(trim(strip_tags($titleMatch[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                self::assertSame(
                    $page['titles'][$locale].' - '.config('app.name'),
                    $renderedTitle,
                    $page['id'].' '.$locale.' page title',
                );

                if ($page['panel'] !== null) {
                    $response->assertSee('id="'.$page['panel'].'"', false);
                }
            }
        }
    }
}
