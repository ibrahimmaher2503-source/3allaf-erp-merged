<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;

final class FullDemoSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        fake()->seed(20261026);
        $this->call([
            CoreSystemDemoSeeder::class,
            DemoCatalogSeeder::class,
            DemoPricingSeeder::class,
            DemoSuppliersSeeder::class,
            DemoCustomersSeeder::class,
            DemoInventorySeeder::class,
            DemoPurchasingSeeder::class,
            DemoPosSeeder::class,
            DemoStocktakeSeeder::class,
        ]);
    }
}
