<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\CachedState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use Tests\Support\DisposableMariaDbRuntime;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $usesDisposableRuntime = getenv('QA_DISPOSABLE_RUNTIME') !== false;
        if ($usesDisposableRuntime) {
            DisposableMariaDbRuntime::assertSafeEnvironment();
        }

        $application = require dirname(__DIR__).'/bootstrap/app.php';
        $cachePathPrefix = DIRECTORY_SEPARATOR === '\\'
            ? substr((string) getenv('QA_STORAGE_PATH'), 0, 3)
            : DIRECTORY_SEPARATOR;
        $application->addAbsoluteCachePathPrefix($cachePathPrefix);
        $application->loadEnvironmentFrom('.env.module1-qa-unused');
        $application->useStoragePath((string) getenv('QA_STORAGE_PATH'));

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        if (isset(CachedState::$cachedConfig, $this->traitsUsedByTest[WithCachedConfig::class])) {
            $this->markConfigCached($application);
        }

        if (isset(CachedState::$cachedRoutes, $this->traitsUsedByTest[WithCachedRoutes::class])) {
            $application->booting(fn () => $this->markRoutesCached($application));
        }

        $application->make(Kernel::class)->bootstrap();

        $connection = $application['config']->get('database.connections.mysql');

        if (
            $application->environment() !== 'testing'
            || ! $application->runningUnitTests()
            || $application['config']->get('database.default') !== 'mysql'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || (string) ($connection['port'] ?? '') !== ($usesDisposableRuntime ? '1' : '3306')
            || ($connection['database'] ?? null) !== getenv('DB_DATABASE')
            || ! empty($connection['url'] ?? null)
            || ($usesDisposableRuntime
                ? ($connection['unix_socket'] ?? null) !== getenv('DB_SOCKET')
                : ! empty($connection['unix_socket'] ?? null))
        ) {
            throw new \RuntimeException('Unsafe test database configuration; refusing to run this Laravel test.');
        }

        $expectedCachePath = realpath(rtrim((string) getenv('QA_STORAGE_PATH'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');
        foreach ([$application->getCachedConfigPath(), $application->getCachedRoutesPath(), $application->getCachedEventsPath(), $application->getCachedServicesPath(), $application->getCachedPackagesPath()] as $cachedPath) {
            if (strtolower((string) realpath(dirname($cachedPath))) !== strtolower((string) $expectedCachePath)) {
                throw new \RuntimeException('Unsafe Laravel bootstrap cache path; refusing to run this test.');
            }
        }

        return $application;
    }
}
