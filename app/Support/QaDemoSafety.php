<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class QaDemoSafety
{
    public static function assertDemoPassword(): void
    {
        $password = config('qa-demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('RAJEH_QA_DEMO_PASSWORD must be an explicit QA-only password of at least 12 characters.');
        }
    }

    public static function assertAllowed(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('QA/Demo database reset is disabled in production.');
        }

        if (! app()->environment(['testing', 'qa'])) {
            throw new RuntimeException('QA/Demo data is available only in testing or a dedicated QA environment.');
        }

        if (config('qa-demo.enabled') !== true) {
            throw new RuntimeException('Set RAJEH_QA_DEMO_ENABLED=true explicitly for a disposable QA database.');
        }

        if (config('database.default') !== 'mysql' || filled(config('database.connections.mysql.url'))) {
            throw new RuntimeException('QA/Demo reset requires a direct MySQL/MariaDB QA connection.');
        }

        $database = DB::connection('mysql')->getDatabaseName();
        if (! is_string($database) || ! preg_match('/^qa_rajh_[A-Za-z0-9_]{1,50}$/', $database) || $database === 'rajeh_toyjoy') {
            throw new RuntimeException('QA/Demo reset requires an explicitly named disposable qa_rajh_* database.');
        }
    }
}
