<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves `php artisan migrate:rollback` can reverse every migration in this
 * project cleanly, end to end, against a dedicated MySQL/MariaDB schema — not
 * the shared test connection. It never interacts with RefreshDatabase or any
 * other test's state.
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

    private const SERVER_CONNECTION = 'migration_rollback_server';

    private const DATABASE = 'toyjoy_migration_rollback';

    public function test_the_full_migration_set_rolls_back_cleanly_end_to_end(): void
    {
        $this->createDedicatedDatabase();
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => self::DATABASE,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]]);
        DB::purge(self::CONNECTION);

        try {
            $migrateExitCode = Artisan::call('migrate', [
                '--database' => self::CONNECTION,
                '--force' => true,
            ]);

            self::assertSame(0, $migrateExitCode, 'Fresh migration against the dedicated MySQL/MariaDB schema failed: '.Artisan::output());
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
            $this->dropDedicatedDatabase();
        }
    }

    private function createDedicatedDatabase(): void
    {
        config(['database.connections.'.self::SERVER_CONNECTION => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => null,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]]);
        DB::purge(self::SERVER_CONNECTION);
        DB::connection(self::SERVER_CONNECTION)->statement('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function dropDedicatedDatabase(): void
    {
        DB::purge(self::SERVER_CONNECTION);
        DB::connection(self::SERVER_CONNECTION)->statement('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
        DB::purge(self::SERVER_CONNECTION);
    }
}
