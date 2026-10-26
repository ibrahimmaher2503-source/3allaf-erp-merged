<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductOptionGroup> */
class ProductOptionGroupFactory extends Factory
{
    protected $model = ProductOptionGroup::class;

    public function definition(): array
    {
        return [
            'code' => 'QA-OPT-'.$this->faker->unique()->numerify('#####'),
            'name_ar' => fake('ar_EG')->word(),
            'name_en' => $this->faker->word(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
