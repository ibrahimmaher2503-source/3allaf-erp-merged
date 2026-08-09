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

    public function test_the_test_database_is_an_isolated_in_memory_sqlite_database(): void
    {
        if (config('database.default') !== 'sqlite') {
            // Non-SQLite phpunit configs (phpunit.concurrency.xml, phpunit.prodlike.xml,
            // phpunit.staging.xml) intentionally target a real MySQL/MariaDB engine for
            // production-like regression passes. This guard's job there is not "must be
            // SQLite" but "must still be a dedicated, isolated test database" — see the
            // sibling assertion below.
            $this->markTestSkipped('Non-SQLite run: isolation is verified by test_a_non_sqlite_connection_still_targets_a_dedicated_test_database() instead.');
        }

        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_a_non_sqlite_connection_still_targets_a_dedicated_test_database(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('SQLite run: isolation is verified by test_the_test_database_is_an_isolated_in_memory_sqlite_database() instead.');
        }

        $databaseName = mb_strtolower((string) DB::connection()->getDatabaseName());

        $this->assertMatchesRegularExpression(
            '/^toyjoy_(concurrency|prodlike|tsk_env)_\d{8}$/',
            $databaseName,
            'A non-SQLite test run must target one of this project\'s dedicated, dated test databases — never a shared dev/production database.',
        );
    }

    public function test_the_local_development_sqlite_file_is_not_the_test_database(): void
    {
        $developmentDatabase = database_path('database.sqlite');

        $this->assertNotSame($developmentDatabase, DB::connection()->getDatabaseName());

        if (file_exists($developmentDatabase)) {
            // Prove the running suite is not attached to the developer database.
            $this->assertStringNotContainsString(
                'database.sqlite',
                (string) DB::connection()->getDatabaseName(),
            );
        }
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
