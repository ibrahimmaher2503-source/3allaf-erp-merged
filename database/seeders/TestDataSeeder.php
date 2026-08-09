<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;
use Tests\Support\Datasets\DatasetSize;
use Tests\Support\Scenarios\PosScenario;

final class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $environment = (string) app()->environment();
        if (in_array($environment, ['production', 'staging'], true)) {
            throw new LogicException('TestDataSeeder is blocked in production-like environments. Use an isolated testing database.');
        }

        if (! app()->runningUnitTests() && ! (bool) env('ALLOW_TEST_DATA', false)) {
            throw new LogicException('TestDataSeeder requires ALLOW_TEST_DATA=true outside PHPUnit.');
        }

        $size = DatasetSize::tryFrom((string) env('TEST_DATA_SIZE', DatasetSize::TINY->value)) ?? DatasetSize::TINY;
        PosScenario::ready($size);
    }
}
