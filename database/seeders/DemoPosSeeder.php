<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\SavePosFinancialSettingAction;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use App\Modules\Retail\Support\PosOpenOrderManager;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoPosSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $cashier = User::query()->where('username', 'qa-admin')->firstOrFail();
            $drawer = CashDrawer::query()->where('code', 'QA-SAHIL-DRAWER')->firstOrFail();
            $outlet = Store::query()->where('code', 'QA-SAHIL-OUTLET')->firstOrFail();
            $product = Product::query()->where('item_code', 'QA-POS-64')->firstOrFail();
            $customer = Customer::query()->where('email', 'qa-pos-customer@example.test')->firstOrFail();
            $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
            $card = PaymentMethod::query()->where('code', 'CARD')->firstOrFail();

            Auth::login($cashier);
            try {
                app(SavePosFinancialSettingAction::class)->execute(
                    PosFinancialSettingRegistry::CASH_ROUNDING_ENABLED,
                    '0',
                    'QA split payment scenario uses exact tender portions.',
                );
                $shift = app(OpenShiftAction::class)->execute($cashier, $drawer, '100.00', 'qa-demo-shift');
                $sale = app(RetailSaleAction::class)->create(
                    $cashier, $outlet,
                    [['product_id' => $product->id, 'quantity' => '1.000000']],
                    'qa-demo-split-sale', false,
                    [
                        ['method' => $cash, 'amount' => '50.00', 'tendered' => '50.00'],
                        ['method' => $card, 'amount' => '14.00', 'evidence_reference' => 'QA-DEMO-CARD'],
                    ],
                    ['tax_applicable' => false], $customer,
                );
                app(RecordCashMovementAction::class)->execute($cashier, $shift, CashMovement::TYPE_CASH_IN, '20.00', fake()->sentence(), 'qa-demo-cash-in');
                $line = $sale->fresh('lines')->lines->firstOrFail();
                app(RetailReturnAction::class)->create($cashier, [
                    'source_sale_id' => $sale->id,
                    'settlement_type' => 'original_tender',
                    'reason' => fake()->sentence(),
                    'lines' => [[
                        'sale_line_id' => $line->id,
                        'quantity' => '1.000000',
                        'condition' => 'sellable',
                        'disposition' => 'restock',
                        'return_store_id' => $outlet->id,
                    ]],
                ], 'qa-demo-return-draft');
                app(PosOpenOrderManager::class)->create($cashier, app(PosContextResolver::class)->resolve($cashier));
            } finally {
                Auth::logout();
            }
        });
    }
}
