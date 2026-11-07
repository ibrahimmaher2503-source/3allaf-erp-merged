<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;

$f = qaFixture();
$f['branch']->update(['name_ar' => 'الدقي', 'name_en' => 'Dokki']);
$f['receiving']->update(['name_ar' => 'مخزن الدقي A', 'name_en' => 'Dokki Store A']);
$supplier = Supplier::query()->create(['code' => 'SUP-9134', 'name_ar' => 'مورد فحص الاستلام', 'name_en' => 'Receiving QA supplier', 'status' => 'active']);
$product = app(SaveProductAction::class)->execute(['preferred_supplier_id' => $supplier->id, 'name_ar' => 'منتج فحص الاستلام', 'name_en' => 'Receiving QA product', 'category_id' => $f['category']->id, 'sale_price' => '25', 'average_cost' => '10', 'product_type' => 'service', 'barcode_registration_type' => 'local', 'status' => 'active']);
$invoice = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id' => $supplier->id, 'store_id' => $f['receiving']->id, 'invoice_date' => '2026-10-12'], [['product_id' => $product->id, 'quantity' => '1000', 'unit_cost' => '12', 'base_consumer_price' => '30']]);
$invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id);
$englishInvoice = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id' => $supplier->id, 'store_id' => $f['receiving']->id, 'invoice_date' => '2026-10-12'], [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '12', 'base_consumer_price' => '30']]);
$englishInvoice = app(SubmitPurchaseInvoiceAction::class)->execute($englishInvoice->id);
$password = bin2hex(random_bytes(20));
$f['admin']->update(['password' => $password]);
$f['admin']->forceFill(['is_super_admin' => false])->save();
$f['admin']->roles()->attach(Role::query()->where('code', 'system-administrator')->firstOrFail());
Permission::query()->where('code', 'products_categories_brands.cost_view')->update(['status' => 'inactive']);
$editorRole = Role::query()->firstOrCreate(['code' => 'qa-card-browser-editor'], ['name_ar' => 'محرر بطاقة الاختبار', 'name_en' => 'QA card browser editor', 'status' => 'active']);
$editorRole->permissions()->sync(Permission::query()->whereIn('code', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'])->pluck('id'));
$f['user']->roles()->sync([$editorRole->id]);
$f['user']->storeScopes()->firstOrCreate(['store_id' => $f['receiving']->id], ['status' => 'active']);
$editorPassword = bin2hex(random_bytes(20));
$f['user']->update(['password' => $editorPassword]);
$path = getenv('QA_STORAGE').'/purchase-browser.json';
file_put_contents($path, json_encode(['username' => $f['admin']->username, 'password' => $password, 'editorUsername' => $f['user']->username, 'editorPassword' => $editorPassword, 'invoice' => $invoice->id, 'line' => $invoice->lines->first()->id, 'englishInvoice' => $englishInvoice->id, 'product' => $product->id], JSON_THROW_ON_ERROR));
chmod($path, 0600);
echo "PASS disposable Dokki invoice and browser account prepared\n";
