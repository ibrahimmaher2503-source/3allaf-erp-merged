<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

$admin = User::query()->where('username', 'qa-admin')->firstOrFail();
$role = Role::query()->where('code', 'system-administrator')->firstOrFail();
$admin->roles()->syncWithoutDetaching([$role->id]);
Auth::login($admin);
$store = Store::query()->where('code', 'QA-SELL')->firstOrFail();
$product = Product::query()->where('item_code', 'QA-POS-SKU-01')->firstOrFail();
$cash = PaymentMethod::query()->where('code', 'QA-CASH')->firstOrFail();
$customer = Customer::query()->where('phone_normalized', '201011223399')->firstOrFail();
$canonicalSale = app(RetailSaleAction::class)->create($admin, $store, [
    ['product_id' => $product->id, 'quantity' => '2'],
], (string) Str::uuid(), false, [['method' => $cash, 'amount' => '90.00', 'tendered' => '90.00']], [], $customer);
$lookupSale = Sale::query()->where('document_number', 'QA-POS-LOOKUP-001')->firstOrFail();
file_put_contents($argv[1], json_encode([
    'sale_id' => $lookupSale->id,
    'receipt_reference' => $lookupSale->document_number,
    'return_sale_id' => $canonicalSale->id,
    'return_reference' => $canonicalSale->document_number,
], JSON_THROW_ON_ERROR));
echo 'PASS disposable Admin and canonical paid POS sale ready for Returns QA'.PHP_EOL;
