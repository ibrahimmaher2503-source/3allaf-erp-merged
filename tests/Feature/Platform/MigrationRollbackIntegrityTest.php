<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves `php artisan migrate:rollback` can reverse every migration in this
 * project cleanly, end to end, against a real database file — not the shared
 * in-memory test connection. Runs on its own dedicated SQLite connection so it
 * never interacts with RefreshDatabase or any other test's state.
 *
 * Regression coverage for a real deployment-rollback defect found 2026-08-09
 * (QA-042/QA-043): several `down()` migrations dropped a foreign-key- or
 * unique-index-backed column without first dropping that index/constraint,
 * which crashes `migrate:rollback` outright instead of cleanly reversing the
 * migration — see DEFECTS.md.
 */
class MigrationRollbackIntegrityTest extends TestCase
{
    private const CONNECTION = 'migration_rollback_test';

    public function test_the_full_migration_set_rolls_back_cleanly_end_to_end(): void
    {
        $databasePath = storage_path('framework/testing/migration-rollback-integrity.sqlite');

        if (! is_dir(dirname($databasePath))) {
            mkdir(dirname($databasePath), 0777, true);
        }
        file_put_contents($databasePath, '');

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge(self::CONNECTION);

        try {
            $migrateExitCode = Artisan::call('migrate', [
                '--database' => self::CONNECTION,
                '--force' => true,
            ]);

            self::assertSame(0, $migrateExitCode, 'Fresh migration against a real SQLite file failed: '.Artisan::output());
            self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('users'), 'Migration did not create the expected users table.');
            self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('purchase_invoices'), 'Migration did not create the expected purchase_invoices table.');

            $rollbackExitCode = Artisan::call('migrate:rollback', [
                '--database' => self::CONNECTION,
                '--force' => true,
            ]);

            self::assertSame(0, $rollbackExitCode, 'migrate:rollback did not exit cleanly: '.Artisan::output());

            $remainingMigrationRows = DB::connection(self::CONNECTION)->table('migrations')->count();
            self::assertSame(0, $remainingMigrationRows, 'Not every migration reported itself rolled back.');

            self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('users'), 'users table survived a full rollback.');
            self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('purchase_invoices'), 'purchase_invoices table survived a full rollback.');
            self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('products'), 'products table survived a full rollback.');
        } finally {
            DB::purge(self::CONNECTION);
            if (file_exists($databasePath)) {
                unlink($databasePath);
            }
        }
    }
}
