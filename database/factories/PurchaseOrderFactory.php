<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => 'QA-PO-'.$this->faker->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'store_id' => Store::factory()->warehouse(),
            'status' => 'draft',
            'receiving_status' => 'not_received',
            'order_date' => $this->faker->dateTimeBetween('-14 days', 'now'),
            'expected_delivery_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'notes' => $this->faker->sentence(),
            'lock_version' => 1,
        ];
    }
}
