<?php

namespace App\Console\Commands;

use Database\Seeders\TestDataSeeder;
use Illuminate\Console\Command;
use LogicException;

final class GenerateTestData extends Command
{
    protected $signature = 'testing:data {--size=tiny : tiny, small, medium, large, or race}';

    protected $description = 'Generate deterministic test data in an isolated non-production database.';

    public function handle(): int
    {
        if (app()->environment('production', 'staging')) {
            throw new LogicException('testing:data is blocked in production-like environments.');
        }

        $size = strtolower((string) $this->option('size'));
        if (! in_array($size, ['tiny', 'small', 'medium', 'large', 'race'], true)) {
            $this->error('Invalid size. Choose tiny, small, medium, large, or race.');

            return self::FAILURE;
        }

        putenv('ALLOW_TEST_DATA=true');
        putenv('TEST_DATA_SIZE='.$size);
        $this->call('db:seed', ['--class' => TestDataSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
