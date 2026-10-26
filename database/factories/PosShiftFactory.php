<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PosShift> */
class PosShiftFactory extends Factory
{
    protected $model = PosShift::class;

    public function definition(): array
    {
        return [
            'cash_drawer_id' => CashDrawer::factory(),
            'branch_id' => null,
            'store_id' => null,
            'cashier_id' => User::factory(),
            'status' => 'open',
            'opening_cash' => '0.00',
            'currency_code' => 'EGP',
            'idempotency_key' => (string) Str::uuid(),
            'opened_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
            'lock_version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PosShift $shift): void {
            $drawer = CashDrawer::query()->findOrFail($shift->cash_drawer_id);
            $shift->branch_id ??= $drawer->branch_id;
            $shift->store_id ??= $drawer->store_id;
        });
    }
}
