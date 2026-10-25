<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$fixture = qaFixture();
$product = Product::query()->where('model_number', 'QA-POS-01')->firstOrFail();
$product->update(['sale_price' => '400']);
DB::table('pos_financial_setting_versions')->where('key', 'pos.cash_rounding_denomination')->update(['value' => '1']);
DB::table((new PriceLine)->getTable())->where('product_id', $product->id)->where('store_id', $fixture['store']->id)->whereNotNull('active_key')->update(['amount' => '64.000']);
$list = PriceList::query()->firstOrCreate(['company_id' => $fixture['company']->id, 'list_number' => 2], [
    'code' => 'QA-COM-2', 'name_ar' => 'قائمة الساحل', 'name_en' => 'Sahil List 2', 'percentage_increase' => '15', 'status' => 'active',
]);
$fixture['store']->update(['price_list_id' => $list->id]);
$supplier = Supplier::query()->firstOrCreate(['code' => 'QA-COM-SUP'], ['name_ar' => 'مورد ساحل', 'name_en' => 'Sahil Supplier', 'status' => 'active']);
$invoice = app(SavePurchaseInvoiceAction::class)->execute(
    ['supplier_id' => $supplier->id, 'store_id' => $fixture['receiving']->id, 'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP'],
    [['product_id' => $product->id, 'quantity' => '20', 'unit_cost' => '100', 'base_consumer_price' => '400', 'discount_type' => null, 'discount_value' => '0']],
);
$cashier = User::query()->where('username', 'qa-cashier')->firstOrFail();
$cashierDrawer = CashDrawer::query()->firstOrCreate(['code' => 'QA-COM-TILL'], [
    'company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
    'name_ar' => 'خزينة كاشير', 'name_en' => 'Cashier drawer', 'status' => 'active', 'assigned_user_id' => $cashier->id,
]);
$shift = PosShift::query()->firstOrCreate(['cashier_id' => $cashier->id, 'cash_drawer_id' => $cashierDrawer->id, 'status' => 'open'], [
    'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id, 'cash_drawer_id' => $cashierDrawer->id,
    'cashier_id' => $cashier->id, 'opened_by' => $cashier->id, 'status' => 'open', 'opening_cash' => '125.00',
    'currency_code' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'opened_at' => now(),
]);
DB::table('active_pos_shift_assignments')->updateOrInsert(['shift_id' => $shift->id], ['cashier_id' => $cashier->id, 'cash_drawer_id' => $cashierDrawer->id]);
file_put_contents($argv[1], json_encode([
    'branch' => $fixture['branch']->id, 'warehouse' => $fixture['receiving']->id,
    'invoice' => $invoice->id, 'cashierShift' => $shift->id,
], JSON_THROW_ON_ERROR));
echo 'PASS disposable commercial browser fixture: List 0 400, branch List 2 460, POS 64, draft 20, cashier shift'.PHP_EOL;
