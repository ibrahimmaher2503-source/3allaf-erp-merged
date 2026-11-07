<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = $this->faker->randomElement(['010', '011', '012', '015']).$this->faker->unique()->numerify('########');
        $first = fake('ar_EG')->firstName();
        $last = fake('ar_EG')->lastName();

        return [
            'public_id' => (string) Str::uuid(),
            'phone_normalized' => $phone,
            'phone_display' => $phone,
            'first_name_ar' => $first,
            'last_name_ar' => $last,
            'name_ar' => $first.' '.$last,
            'name_en' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'address_ar' => fake('ar_EG')->address(),
            'address_en' => $this->faker->address(),
            'invoice_delivery_method' => 'PRINT',
            'status' => 'active',
            'customer_type' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 1,
        ];
    }

    public function cash(): static
    {
        return $this->state(['customer_type' => 'cash', 'credit_limit' => null]);
    }

    public function credit(): static
    {
        return $this->state(['customer_type' => 'credit', 'credit_limit' => '25000.0000']);
    }
}
