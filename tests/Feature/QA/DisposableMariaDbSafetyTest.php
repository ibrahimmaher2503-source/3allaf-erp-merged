<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DisposableMariaDbRuntime;

final class DisposableMariaDbSafetyTest extends TestCase
{
    #[DataProvider('unsafeEnvironment')]
    public function test_disposable_profile_rejects_unsafe_environment_before_application_boot(string $name, string $value): void
    {
        if (getenv('QA_DISPOSABLE_RUNTIME') === false) {
            self::markTestSkipped('Only the disposable Fast QA profile uses this guard.');
        }

        $original = getenv($name);
        putenv($name.'='.$value);

        try {
            $this->expectException(RuntimeException::class);
            DisposableMariaDbRuntime::assertSafeEnvironment();
        } finally {
            putenv($original === false ? $name : $name.'='.$original);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeEnvironment(): array
    {
        return [
            'production environment' => ['APP_ENV', 'production'],
            'wrong database driver' => ['DB_CONNECTION', 'sqlite'],
            'remote host' => ['DB_HOST', 'production.invalid'],
            'TCP fallback' => ['DB_PORT', '3306'],
            'URL override' => ['DB_URL', 'mysql://root@production.invalid/production'],
            'production database' => ['DB_DATABASE', 'production'],
            'unrecorded QA database' => ['DB_DATABASE', 'qa_rajh_unrecorded'],
            'missing socket' => ['DB_SOCKET', ''],
            'external socket' => ['DB_SOCKET', '/var/run/mysqld/mysqld.sock'],
            'external storage' => ['QA_STORAGE_PATH', '/tmp/shared-storage'],
            'external runtime' => ['QA_DISPOSABLE_RUNTIME', '/tmp/other-runtime'],
            'wrong user' => ['DB_USERNAME', 'production'],
            'credential override' => ['DB_PASSWORD', 'unexpected'],
        ];
    }
}
