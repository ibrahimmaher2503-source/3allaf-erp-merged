<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test-environment isolation guard for the TSK-001..TSK-010 regression suite.
 *
 * These assertions must pass before any other suite result is trusted.
 */
class EnvironmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_suite_runs_in_the_testing_environment(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertFalse(app()->isProduction());
    }

    public function test_the_suite_uses_the_xampp_mysql_database(): void
    {
        $connection = (string) config('database.default');

        $this->assertContains($connection, ['mysql', 'mariadb']);
        $this->assertSame('127.0.0.1', (string) config("database.connections.{$connection}.host"));
        $this->assertSame('3306', (string) config("database.connections.{$connection}.port"));
    }

    public function test_the_test_database_is_dedicated_in_phpmyadmin(): void
    {
        $databaseName = mb_strtolower((string) DB::connection()->getDatabaseName());

        $this->assertMatchesRegularExpression(
            '/^toyjoy_(testing|concurrency|prodlike|tsk_env)(?:_\d{8})?$/',
            $databaseName,
            'The test run must target a dedicated MySQL/MariaDB schema — never a shared dev/production database.',
        );
    }

    public function test_the_local_development_database_is_not_the_test_database(): void
    {
        $this->assertNotSame('toyjoy_local', mb_strtolower((string) DB::connection()->getDatabaseName()));
    }

    public function test_external_side_effects_are_disabled(): void
    {
        $this->assertSame('array', config('mail.default'), 'Tests must not send real email.');
        $this->assertSame('array', config('cache.default'), 'Tests must not share the development cache.');
        $this->assertSame('array', config('session.driver'), 'Tests must not share the development session store.');
        $this->assertSame('sync', config('queue.default'), 'Tests must not push jobs onto a shared queue.');
        $this->assertSame('log', config('broadcasting.default'), 'Tests must not broadcast to an external service.');
    }

    public function test_no_external_service_credentials_are_configured(): void
    {
        $this->assertEmpty(config('filesystems.disks.s3.key'));
        $this->assertEmpty(config('filesystems.disks.s3.secret'));
        $this->assertEmpty(config('filesystems.disks.s3.bucket'));
    }

    public function test_migrations_run_cleanly_against_the_isolated_database(): void
    {
        foreach ([
            'users', 'companies', 'branches', 'stores', 'branch_selling_stores', 'cash_drawers',
            'payment_methods', 'tax_settings', 'document_sequences', 'printer_configurations',
            'roles', 'permissions', 'user_branch_scopes', 'user_store_scopes',
            'audit_logs', 'approval_records',
        ] as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Expected migrated table [{$table}] to exist in the isolated test database.",
            );
        }
    }
}
