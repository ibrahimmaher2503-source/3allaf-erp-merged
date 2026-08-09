<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Base class for the real-MariaDB concurrency proof suite.
 *
 * Deliberately does NOT use RefreshDatabase/DatabaseTransactions: those wrap
 * each test in an open, uncommitted transaction on the test's own
 * connection, which (a) would be invisible to the separate OS processes
 * spawned below until rolled back, and (b) would make this connection's own
 * lockForUpdate() calls block against work this same test set up. All
 * fixture writes here are plain, auto-committed statements against a
 * disposable, session-owned database (toyjoy_concurrency_20260809) —
 * migrated once, canonically seeded once, never RefreshDatabase'd.
 *
 * This suite only runs under phpunit.concurrency.xml, which points DB_*
 * at real MariaDB. Running it under another database profile would either
 * fail to connect or (worse) silently "pass" without proving anything —
 * the intended row locks. Guard accordingly.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Concurrency proof requires the real MariaDB connection (run via phpunit.concurrency.xml).');
        }
    }

    /**
     * Launch N race workers as real, independent OS processes against the
     * same MariaDB database, all started before any is awaited, so their
     * transactions genuinely overlap. Returns each worker's decoded JSON
     * result in the same order the calls were given.
     *
     * @param  array<int, array{0: string, 1: array<string, mixed>}>  $calls  [scenario, params] pairs
     * @return array<int, array{ok: bool, result?: array<string, mixed>, exception?: string, message?: string}>
     */
    protected function race(array $calls): array
    {
        $workerScript = __DIR__.'/support/race_worker.php';
        $env = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];

        $processes = [];
        foreach ($calls as $index => [$scenario, $params]) {
            $process = new Process(['php', $workerScript, $scenario, json_encode($params, JSON_THROW_ON_ERROR)], base_path(), $env);
            $process->setTimeout(30);
            $process->start();
            $processes[$index] = $process;
        }

        $results = [];
        foreach ($processes as $index => $process) {
            $process->wait();
            $line = trim($process->getOutput());
            self::assertNotSame('', $line, "Race worker #{$index} produced no output. STDERR: ".$process->getErrorOutput());
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded, "Race worker #{$index} produced non-JSON output: {$line}");
            $results[$index] = $decoded;
        }

        return $results;
    }
}
