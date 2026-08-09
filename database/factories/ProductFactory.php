<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $code = 'SKU-'.$this->faker->unique()->bothify('#####');

        return [
            'item_code' => $code,
            'category_id' => Category::factory(),
            'name_ar' => 'منتج '.$code,
            'name_en' => 'Product '.$code,
            'product_type' => 'standard',
            'unit_of_measure' => 'piece',
            'status' => 'active',
            'barcode_mode' => 'single',
            'average_cost' => 10.00,
            'reorder_threshold' => 1.000,
            'fractional_quantity' => false,
            'lock_version' => 1,
        ];
    }

    public function fractional(): static
    {
        return $this->state(['fractional_quantity' => true, 'unit_of_measure' => 'kg']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
