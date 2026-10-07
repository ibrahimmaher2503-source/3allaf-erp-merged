<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class DisposableMariaDbRuntime
{
    public static function assertSafeEnvironment(): void
    {
        $runtime = (string) getenv('QA_DISPOSABLE_RUNTIME');
        $database = (string) getenv('DB_DATABASE');
        $socket = (string) getenv('DB_SOCKET');
        $storage = (string) getenv('QA_STORAGE_PATH');

        if (
            preg_match('/\A\/tmp\/rajeh-qa-mariadb\.[a-zA-Z0-9]{6}\z/', $runtime) !== 1
            || realpath($runtime) !== $runtime
            || (fileperms($runtime) & 0777) !== 0700
            || preg_match('/\Aqa_rajh_[a-zA-Z0-9_]{1,50}\z/', $database) !== 1
            || ! is_file($runtime.'/schema-ready')
            || ! is_file($runtime.'/database-name')
            || trim((string) file_get_contents($runtime.'/database-name')) !== $database
            || $socket !== $runtime.'/mariadb.sock'
            || ! file_exists($socket)
            || filetype($socket) !== 'socket'
            || realpath(dirname($socket)) !== $runtime
            || $storage !== $runtime.'/storage/'.$database
            || realpath($storage) !== $storage
        ) {
            throw new RuntimeException('Unsafe disposable MariaDB runtime, database, socket or storage; refusing PHPUnit.');
        }

        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '1',
            'DB_URL' => '',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
        ] as $name => $expected) {
            if (getenv($name) !== $expected) {
                throw new RuntimeException("Unsafe disposable MariaDB environment: {$name} must be '{$expected}'.");
            }
        }

        $root = dirname(__DIR__, 2);
        foreach (['.env', '.env.testing', '.env.module1-qa-unused', '.env.module1-qa-unused.testing', 'bootstrap/cache/config.php'] as $path) {
            if (file_exists($root.'/'.$path)) {
                throw new RuntimeException('Disposable PHPUnit refuses worktree environment files or cached runtime configuration.');
            }
        }
    }
}
