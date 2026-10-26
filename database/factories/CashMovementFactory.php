<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CashMovement> */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        return [
            'shift_id' => PosShift::factory(),
            'cash_drawer_id' => null,
            'branch_id' => null,
            'store_id' => null,
            'created_by' => null,
            'movement_type' => CashMovement::TYPE_CASH_IN,
            'amount' => (string) $this->faker->numberBetween(1, 100),
            'reason' => $this->faker->sentence(),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CashMovement $movement): void {
            $shift = PosShift::query()->findOrFail($movement->shift_id);
            $movement->cash_drawer_id ??= $shift->cash_drawer_id;
            $movement->branch_id ??= $shift->branch_id;
            $movement->store_id ??= $shift->store_id;
            $movement->created_by ??= $shift->cashier_id;
        });
    }
}
