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
                'name_ar' => 'ألعاب تجريبية',
                'name_en' => 'Demo Toys',
                'status' => 'active',
                'sort_order' => 1,
            ],
        );

        $brand = Brand::query()->updateOrCreate(
            ['code' => 'DEMO-BRAND-01'],
            [
                'name_ar' => 'علامة تجريبية',
                'name_en' => 'Demo Brand',
                'status' => 'active',
            ],
        );

        foreach ([
            [
                'item_code' => 'DEMO-PROD-001',
                'name_ar' => 'سيارة سباق تجريبية',
                'name_en' => 'Demo Racing Car',
                'description_ar' => 'منتج تجريبي للتحقق المحلي من دورة الشراء.',
                'description_en' => 'Demo product for local purchase-cycle verification.',
                'product_type' => 'standard',
                'unit_of_measure' => 'unit',
                'status' => 'active',
                'barcode_mode' => 'internal',
            ],
            [
                'item_code' => 'DEMO-PROD-002',
                'name_ar' => 'مجموعة بناء تجريبية',
                'name_en' => 'Demo Building Set',
                'description_ar' => 'منتج تجريبي ثانٍ للتحقق من سطور أمر الشراء.',
                'description_en' => 'Second demo product for purchase-order line verification.',
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
