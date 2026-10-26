<?php

namespace Database\Factories;

use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashDrawer> */
class CashDrawerFactory extends Factory
{
    protected $model = CashDrawer::class;

    public function definition(): array
    {
        $store = Store::factory();
        $code = 'DR-'.$this->faker->unique()->bothify('###');

        return [
            'company_id' => null,
            'branch_id' => null,
            'store_id' => $store,
            'code' => $code,
            'name_ar' => 'درج '.$code,
            'name_en' => 'Drawer '.$code,
            'status' => 'active',
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CashDrawer $drawer): void {
            if ($drawer->store_id !== null) {
                $store = Store::query()->findOrFail($drawer->store_id);
                $drawer->company_id ??= $store->company_id;
                $drawer->branch_id ??= $store->branch_id;
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
