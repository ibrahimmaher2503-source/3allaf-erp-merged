<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DisposableMariaDbRuntime;

final class NativeLaravelBootstrapSmokeTest extends TestCase
{
    public function createApplication(): Application
    {
        if (getenv('QA_DISPOSABLE_RUNTIME') !== false) {
            DisposableMariaDbRuntime::assertSafeEnvironment();
        }

        $application = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();

        return $application;
    }

    public function test_native_framework_boot_uses_the_guarded_database_and_temp_paths(): void
    {
        $application = app();
        $expectedStoragePath = realpath((string) getenv('QA_STORAGE_PATH'));
        $expectedCachePath = realpath((string) getenv('QA_STORAGE_PATH').DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');
        $connection = config('database.connections.mysql');

        self::assertSame('testing', $application->environment());
        self::assertTrue($application->runningUnitTests());
        self::assertSame($expectedStoragePath, realpath($application->storagePath()));
        self::assertSame($expectedCachePath, realpath(dirname($application->getCachedConfigPath())));
        self::assertSame($expectedCachePath, realpath(dirname($application->getCachedRoutesPath())));
        self::assertSame('mysql', config('database.default'));
        self::assertSame('mysql', $connection['driver'] ?? null);
        self::assertSame('127.0.0.1', $connection['host'] ?? null);
        $usesDisposableRuntime = getenv('QA_DISPOSABLE_RUNTIME') !== false;
        self::assertSame($usesDisposableRuntime ? '1' : '3306', (string) ($connection['port'] ?? ''));
        self::assertSame(getenv('DB_DATABASE'), $connection['database'] ?? null);
        self::assertEmpty($connection['url'] ?? null);
        if ($usesDisposableRuntime) {
            self::assertSame(getenv('DB_SOCKET'), $connection['unix_socket'] ?? null);
            $server = DB::selectOne('SELECT @@socket AS socket, @@skip_networking AS skip_networking, DATABASE() AS db');
            self::assertSame(getenv('DB_SOCKET'), $server->socket);
            self::assertSame(1, (int) $server->skip_networking);
            self::assertSame(getenv('DB_DATABASE'), $server->db);
        } else {
            self::assertEmpty($connection['unix_socket'] ?? null);
        }
    }
}
