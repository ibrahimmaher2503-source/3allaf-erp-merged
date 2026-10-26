<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductOptionValue> */
class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    public function definition(): array
    {
        return [
            'product_option_group_id' => ProductOptionGroup::factory(),
            'code' => 'QA-VALUE-'.$this->faker->unique()->numerify('#####'),
            'name_ar' => fake('ar_EG')->word(),
            'name_en' => $this->faker->word(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
