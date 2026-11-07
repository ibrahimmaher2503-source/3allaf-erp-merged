<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\PermissionPresentation;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PermissionTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_log_catalog_uses_only_canonical_actions(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();

        self::assertSame(['export', 'print', 'view'], Permission::query()
            ->where('module', 'audit_logs')
            ->orderBy('action')
            ->pluck('action')
            ->all());
        self::assertSame(['view', 'export', 'print'], PermissionPresentation::canonicalActionsForModule('audit_logs', ['view', 'create', 'edit']));
        self::assertSame('update', PermissionPresentation::canonicalAction('edit'));
        self::assertSame('delete', PermissionPresentation::canonicalAction('logical_delete'));
    }

    public function test_existing_legacy_permission_assignments_are_preserved(): void
    {
        $legacy = Permission::query()->create([
            'code' => 'audit_logs.edit',
            'module' => 'audit_logs',
            'action' => 'edit',
            'sensitivity' => 'normal',
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'code' => 'legacy-audit-role-'.Str::lower(Str::random(8)),
            'name_ar' => 'دور تدقيق قديم',
            'name_en' => 'Legacy audit role',
            'status' => 'active',
        ]);
        $role->permissions()->attach($legacy);

        app(ProductionSeeder::class)->seedAuthorizationOnly();

        self::assertTrue($role->fresh()->permissions()->whereKey($legacy->id)->exists());
        self::assertSame('Update audit logs', PermissionPresentation::permission($legacy, 'en')['label']);
    }

    public function test_bilingual_permission_labels_and_checkbox_accessibility_are_present(): void
    {
        $permission = new Permission(['module' => 'suppliers', 'action' => 'edit']);

        self::assertSame('Update supplier records', PermissionPresentation::permission($permission, 'en')['label']);
        self::assertSame('تحديث سجلات الموردين', PermissionPresentation::permission($permission, 'ar-EG')['label']);
        self::assertStringContainsString('aria-label="{{ $permissionPresentation[\'label\'] }}"', file_get_contents(resource_path('views/platform/admin/role-permissions.blade.php')) ?: '');
    }
}
