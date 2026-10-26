<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosOpenOrder;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PosOpenOrder> */
class PosOpenOrderFactory extends Factory
{
    protected $model = PosOpenOrder::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'shift_id' => PosShift::factory(),
            'company_id' => null,
            'branch_id' => null,
            'store_id' => null,
            'cash_drawer_id' => null,
            'cashier_id' => null,
            'sequence_number' => 1,
            'status' => 'open',
            'checkout_token' => (string) Str::uuid(),
            'tax_applicable' => false,
            'subtotal' => '0.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '0.00',
            'revision' => 1,
            'last_activity_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'notes' => $this->faker->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PosOpenOrder $order): void {
            $shift = PosShift::query()->findOrFail($order->shift_id);
            $order->company_id ??= Store::query()->findOrFail($shift->store_id)->company_id;
            $order->branch_id ??= $shift->branch_id;
            $order->store_id ??= $shift->store_id;
            $order->cash_drawer_id ??= $shift->cash_drawer_id;
            $order->cashier_id ??= $shift->cashier_id;
        });
    }
}
