<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseInvoice> */
class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'QA-PIN-'.$this->faker->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'store_id' => Store::factory()->warehouse(),
            'supplier_reference' => 'QA-REF-'.$this->faker->unique()->numerify('######'),
            'invoice_date' => $this->faker->dateTimeBetween('-14 days', 'now'),
            'currency_code' => 'EGP',
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
            'notes' => $this->faker->sentence(),
            'lock_version' => 1,
        ];
    }
}
