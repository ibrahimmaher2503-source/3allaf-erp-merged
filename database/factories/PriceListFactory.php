<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceList> */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        $code = 'QA-PL-'.$this->faker->unique()->numerify('#####');

        return [
            'company_id' => Company::factory(),
            'code' => $code,
            'name_ar' => fake('ar_EG')->words(3, true),
            'name_en' => $this->faker->words(3, true),
            'percentage_increase' => '0.0000',
            'status' => 'active',
            'notes' => $this->faker->sentence(),
        ];
    }

    public function base(): static
    {
        return $this->state(['list_number' => 0, 'code' => 'PL-0000']);
    }
}
