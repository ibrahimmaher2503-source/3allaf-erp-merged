<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoCustomersSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $outlet = Store::query()->where('code', 'QA-SAHIL-OUTLET')->firstOrFail();
            Customer::factory()->count(5)->create([
                'created_by' => $administrator->id,
                'created_branch_id' => $outlet->branch_id,
                'created_store_id' => $outlet->id,
            ]);
            Customer::factory()->create([
                'email' => 'qa-pos-customer@example.test',
                'created_by' => $administrator->id,
                'created_branch_id' => $outlet->branch_id,
                'created_store_id' => $outlet->id,
            ]);
        });
    }
}
