<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use Tests\TestCase;

final class LaravelHarnessSmokeTest extends TestCase
{
    public function test_application_boots_from_this_worktree_with_the_named_qa_database_and_storage(): void
    {
        $root = dirname(__DIR__, 3);
        $database = getenv('DB_DATABASE');
        $storagePath = getenv('QA_STORAGE_PATH');

        self::assertSame(realpath($root), realpath(app()->basePath()));
        self::assertSame('mysql', config('database.default'));
        self::assertSame($database, config('database.connections.mysql.database'));
        self::assertSame(realpath($storagePath), realpath(app()->storagePath()));
    }
}
