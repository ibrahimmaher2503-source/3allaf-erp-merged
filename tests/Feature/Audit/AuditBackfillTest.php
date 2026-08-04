<?php

namespace Tests\Feature\Audit;

use App\Modules\Platform\Actions\BackfillLegacySettingsAuditLogs;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009 — Audit foundation: one-time legacy backfill and single-writer rule.
 *
 * @group tsk-009
 */
class AuditBackfillTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    private function seedLegacyRows(int $count = 2): void
    {
        for ($index = 1; $index <= $count; $index++) {
            DB::table('settings_audit_logs')->insert([
                'correlation_id' => 'LEGACY-REQ-'.$index,
                'user_id' => null,
                'user_name' => 'Legacy Administrator',
                'action' => 'update_local_settings',
                'setting_type' => $index === 1 ? 'company' : 'user_authorization',
                'setting_id' => $index,
                'changes' => json_encode([
                    'before' => ['currency_code' => 'TBD', 'password' => 'legacy-secret'],
                    'after' => ['currency_code' => 'EGP', 'api_key' => 'AK-legacy'],
                ]),
                'created_at' => now()->subDays($index),
            ]);
        }
    }

    public function test_legacy_rows_are_inserted_once_with_a_stable_key(): void
    {
        $this->seedLegacyRows();

        $inserted = app(BackfillLegacySettingsAuditLogs::class)->execute();

        $this->assertSame(2, $inserted);
        $this->assertSame(2, AuditLog::query()->count());
        $this->assertDatabaseHas('audit_logs', ['legacy_source_key' => 'settings_audit_logs:1']);
        $this->assertDatabaseHas('audit_logs', ['legacy_source_key' => 'settings_audit_logs:2']);

        $first = AuditLog::query()->where('legacy_source_key', 'settings_audit_logs:1')->sole();
        $this->assertSame('master_data', $first->category);
        $this->assertSame('LEGACY-REQ-1', $first->request_id);
        $this->assertSame('Legacy Administrator', $first->actor_name);
        $this->assertSame('legacy_settings:company', $first->source_type);

        $authorizationRow = AuditLog::query()->where('legacy_source_key', 'settings_audit_logs:2')->sole();
        $this->assertSame('authorization', $authorizationRow->category);
    }

    public function test_rerunning_the_backfill_inserts_zero_duplicates(): void
    {
        $this->seedLegacyRows();

        app(BackfillLegacySettingsAuditLogs::class)->execute();
        $afterFirstRun = AuditLog::query()->pluck('event_id')->sort()->values()->all();

        $secondRun = app(BackfillLegacySettingsAuditLogs::class)->execute();
        $thirdRun = app(BackfillLegacySettingsAuditLogs::class)->execute();

        $this->assertSame(0, $secondRun);
        $this->assertSame(0, $thirdRun);
        $this->assertSame($afterFirstRun, AuditLog::query()->pluck('event_id')->sort()->values()->all());
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_the_backfill_console_command_is_idempotent(): void
    {
        $this->seedLegacyRows();

        $this->artisan('platform:backfill-legacy-settings-audit')
            ->expectsOutputToContain('2 row(s) inserted')
            ->assertSuccessful();

        $this->artisan('platform:backfill-legacy-settings-audit')
            ->expectsOutputToContain('0 row(s) inserted')
            ->assertSuccessful();

        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_the_legacy_source_key_is_unique(): void
    {
        $this->seedLegacyRows(1);
        app(BackfillLegacySettingsAuditLogs::class)->execute();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('audit_logs')->insert([
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'legacy_source_key' => 'settings_audit_logs:1',
            'category' => 'master_data',
            'event' => 'duplicate_attempt',
            'created_at' => now(),
        ]);
    }

    public function test_legacy_values_are_redacted_during_the_backfill(): void
    {
        $this->seedLegacyRows(1);
        app(BackfillLegacySettingsAuditLogs::class)->execute();

        $row = DB::table('audit_logs')->where('legacy_source_key', 'settings_audit_logs:1')->first();

        $this->assertStringNotContainsString('legacy-secret', (string) $row->before_values);
        $this->assertStringNotContainsString('AK-legacy', (string) $row->after_values);
        $this->assertStringContainsString('[redacted]', (string) $row->before_values);
    }

    public function test_new_platform_mutations_are_written_only_to_audit_logs(): void
    {
        $this->actingAs($this->administrator('tsk009-single-writer'));

        $legacyBefore = DB::table('settings_audit_logs')->count();

        app(SaveBranchAction::class)->execute(['code' => 'SW-01', 'name_ar' => 'فرع', 'name_en' => 'Branch']);

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(
            $legacyBefore,
            DB::table('settings_audit_logs')->count(),
            'No permanent dual write to the retired settings audit table may remain.',
        );
    }

    public function test_no_application_code_still_writes_to_the_retired_table(): void
    {
        $sources = array_merge(
            glob(app_path('Modules/Platform/Actions/*.php')) ?: [],
            glob(resource_path('views/platform/admin/*.blade.php')) ?: [],
            glob(resource_path('views/platform/system/*.blade.php')) ?: [],
        );

        foreach ($sources as $file) {
            $contents = (string) file_get_contents($file);

            $this->assertStringNotContainsString('SettingsAuditLog::create', $contents, "Legacy audit write found in [{$file}].");
            $this->assertStringNotContainsString("table('settings_audit_logs')->insert", $contents, "Legacy audit write found in [{$file}].");
        }
    }
}
