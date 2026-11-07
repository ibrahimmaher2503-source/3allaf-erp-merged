<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\GenerateProductVariationsAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
        Auth::login($administrator);

        try {
            DB::transaction(function (): void {
                $category = Category::factory()->create(['code' => 'QA-TOYS']);
                Category::factory()->count(2)->create();

                $scenarioProducts = [
                    ['QA-PRICE-400', '400.00', '100.0000'],
                    ['QA-POS-64', '64.00', '20.0000'],
                    ['QA-PURCHASE', '180.00', '45.0000'],
                    ['QA-ZERO-STOCK', '120.00', '30.0000'],
                ];
                foreach ($scenarioProducts as [$code, $price, $cost]) {
                    $product = Product::factory()->for($category)->create([
                        'item_code' => $code,
                        'sale_price' => $price,
                        'average_cost' => $cost,
                        'name_ar' => fake('ar_EG')->words(2, true),
                        'name_en' => fake()->words(2, true),
                    ]);
                    app(AddBarcodeAction::class)->addManualCode128($product->id, 'QA-BAR-'.$code);
                }

                Product::factory()->count(6)->for($category)->state(fn (): array => [
                    'sale_price' => (string) fake()->numberBetween(70, 350),
                    'average_cost' => (string) fake()->numberBetween(10, 60),
                    'name_ar' => fake('ar_EG')->words(2, true),
                    'name_en' => fake()->words(2, true),
                ])->create()->each(fn (Product $product) => app(AddBarcodeAction::class)
                    ->addManualCode128($product->id, 'QA-BAR-'.fake()->unique()->bothify('########')));

                $family = Product::factory()->for($category)->unpricedFamilyCandidate()->create();
                $group = ProductOptionGroup::factory()->create();
                $values = ProductOptionValue::factory()->count(2)->for($group, 'group')->sequence(
                    ['sort_order' => 1],
                    ['sort_order' => 2],
                )->create();
                $generator = app(GenerateProductVariationsAction::class);
                $variantInput = [];
                foreach ($values as $value) {
                    $variantInput[$generator->signature([$value->id])] = [
                        'sku' => strtoupper(fake()->unique()->bothify('QA-VAR-#####')),
                        'status' => 'inactive',
                    ];
                }
                $generator->execute($family, [$group->id => $values->pluck('id')->all()], $variantInput);
            });
        } finally {
            Auth::logout();
        }
    }
}
