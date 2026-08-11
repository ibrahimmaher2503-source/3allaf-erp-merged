<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;

final class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::query()->updateOrCreate(
            ['code' => 'DEMO-CAT-TOYS'],
            [
                'name_ar' => 'ألعاب',
                'name_en' => 'Toys',
                'status' => 'active',
                'sort_order' => 1,
            ],
        );

        $brand = Brand::query()->updateOrCreate(
            ['code' => 'DEMO-BRAND-01'],
            [
                'name_ar' => 'علامة الألعاب',
                'name_en' => 'Toy brand',
                'status' => 'active',
            ],
        );

        foreach ([
            [
                'item_code' => 'DEMO-PROD-001',
                'name_ar' => 'سيارة سباق',
                'name_en' => 'Racing car',
                'description_ar' => 'سيارة سباق للأطفال.',
                'description_en' => 'A racing car for children.',
                'product_type' => 'standard',
                'unit_of_measure' => 'unit',
                'status' => 'active',
                'barcode_mode' => 'internal',
            ],
            [
                'item_code' => 'DEMO-PROD-002',
                'name_ar' => 'مجموعة بناء',
                'name_en' => 'Building set',
                'description_ar' => 'مجموعة بناء للأطفال.',
                'description_en' => 'A building set for children.',
                'product_type' => 'standard',
                'unit_of_measure' => 'unit',
                'status' => 'active',
                'barcode_mode' => 'internal',
            ],
        ] as $attributes) {
            Product::query()->updateOrCreate(
                ['item_code' => $attributes['item_code']],
                array_merge($attributes, [
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'lock_version' => 1,
                ]),
            );
        }
    }
}
