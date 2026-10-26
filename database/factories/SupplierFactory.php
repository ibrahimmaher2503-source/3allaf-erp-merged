<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => 'QA-SUP-'.$this->faker->unique()->numerify('#####'),
            'name_ar' => fake('ar_EG')->company(),
            'name_en' => $this->faker->company(),
            'contact_name' => fake('ar_EG')->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('01#########'),
            'address' => fake('ar_EG')->address(),
            'payment_terms' => $this->faker->sentence(),
            'status' => 'active',
            'lock_version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
