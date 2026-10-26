<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoSuppliersSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $supplier = Supplier::factory()->create(['code' => 'QA-SUPPLIER']);
            Supplier::factory()->count(3)->create();
            $product = Product::query()->where('item_code', 'QA-PURCHASE')->firstOrFail();
            Auth::login($administrator);
            try {
                app(SaveProductSupplierAction::class)->execute([
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'supplier_item_code' => fake()->bothify('QA-SKU-#####'),
                    'is_preferred' => true,
                    'notes' => fake()->sentence(),
                ]);
            } finally {
                Auth::logout();
            }
        });
    }
}
