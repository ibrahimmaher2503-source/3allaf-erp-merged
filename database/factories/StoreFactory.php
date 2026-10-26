<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $code = 'ST-'.$this->faker->unique()->bothify('###');

        return [
            'company_id' => null,
            'branch_id' => Branch::factory(),
            'code' => $code,
            'type' => 'selling',
            'name_ar' => 'متجر '.$code,
            'name_en' => 'Store '.$code,
            'status' => 'active',
            'allows_negative_stock' => false,
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Store $store): void {
            if ($store->branch_id !== null && $store->company_id === null) {
                $store->company_id = Branch::query()->findOrFail($store->branch_id)->company_id;
            }
        });
    }

    public function warehouse(): static
    {
        return $this->state(['type' => 'warehouse']);
    }

    public function standaloneWarehouse(Company $company): static
    {
        return $this->state(['company_id' => $company->id, 'branch_id' => null, 'type' => 'warehouse']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
