<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;

final class CatalogDemoModeSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        $this->call([CoreSystemDemoSeeder::class, DemoCatalogSeeder::class]);
    }
}
