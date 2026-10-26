<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockCount> */
class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    public function definition(): array
    {
        return [
            'count_number' => 'QA-CNT-'.$this->faker->unique()->numerify('######'),
            'store_id' => Store::factory()->warehouse(),
            'count_type' => 'partial',
            'scope_type' => 'partial',
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 1,
            'notes' => $this->faker->sentence(),
        ];
    }
}
