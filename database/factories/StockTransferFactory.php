<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockTransfer> */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'transfer_number' => 'QA-TRF-'.$this->faker->unique()->numerify('######'),
            'source_store_id' => Store::factory()->warehouse(),
            'destination_store_id' => Store::factory()->warehouse(),
            'status' => 'draft',
            'reason_code' => 'qa_demo',
            'reason_notes' => $this->faker->sentence(),
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 1,
        ];
    }
}
