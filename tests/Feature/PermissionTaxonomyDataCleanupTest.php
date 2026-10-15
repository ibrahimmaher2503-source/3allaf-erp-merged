<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PermissionTaxonomyDataCleanupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cleanup_removes_only_legacy_audit_log_permissions_and_pivots(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();

        $administrator = Role::query()->where('code', 'system-administrator')->firstOrFail();
        $legacy = Permission::query()->create([
            'code' => 'audit_logs.edit',
            'module' => 'audit_logs',
            'action' => 'edit',
            'sensitivity' => 'normal',
            'status' => 'active',
        ]);
        $administrator->permissions()->attach($legacy);

        $unrelated = Permission::query()->where('code', 'suppliers.edit')->firstOrFail();
        $unrelatedRole = Role::query()->create([
            'code' => 'qa-unrelated-'.Str::lower(Str::random(8)),
            'name_ar' => 'اختبار صلاحية أخرى',
            'name_en' => 'Unrelated permission test',
            'status' => 'active',
        ]);
        $unrelatedRole->permissions()->attach($unrelated);

        $migration = require database_path('migrations/2026_10_15_000123_clean_audit_log_permissions.php');
        $migration->up();

        self::assertSame(
            ['export', 'print', 'view'],
            Permission::query()->where('module', 'audit_logs')->orderBy('action')->pluck('action')->all(),
        );
        self::assertFalse($administrator->fresh()->permissions()->whereKey($legacy->id)->exists());
        self::assertTrue($unrelatedRole->fresh()->permissions()->whereKey($unrelated->id)->exists());
        self::assertSame('edit', $unrelated->fresh()->action);
    }
}
