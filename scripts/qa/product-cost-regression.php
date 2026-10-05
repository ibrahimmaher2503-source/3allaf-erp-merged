<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\SaveOpeningInventoryDraftAction;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$f = qaFixture();
$save = app(SaveProductAction::class);
$supplier = Supplier::where('code', 'SUP-0002')->first() ?? app(SaveSupplierAction::class)->execute(['code'=>'SUP-0002', 'name_ar'=>'مورد اختبار', 'name_en'=>'Cost QA supplier', 'status'=>'active', 'settlement_method'=>'cash']);
$data = ['name_ar'=>'منتج اختبار التكلفة', 'name_en'=>'Cost QA', 'model_number'=>'COST-QA', 'category_id'=>$f['category']->id, 'preferred_supplier_id'=>$supplier->id, 'barcode_registration_type'=>'local', 'sale_price'=>'25', 'status'=>'active'];

if (($argv[1] ?? '') === 'verify-browser') {
    $fixture = json_decode(file_get_contents(getenv('QA_STORAGE').'/cost-browser.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (['A'=>'12.34', 'B'=>null, 'D'=>'45.67', 'E'=>'87.65'] as $scenario=>$expected) {
        qaAssert(Product::findOrFail($fixture['products'][$scenario])->average_cost === $expected, $scenario.' browser persisted cost confirmed directly in MariaDB');
    }
    foreach (['Admin', 'Editor'] as $role) {
        $p = Product::where('model_number', 'BROWSER-'.strtoupper($role))->firstOrFail();
        qaAssert($p->average_cost === null && ! $p->hasIncompleteCard(), $role.' browser new product finalized with genuine null cost');
    }
    $pending = Product::findOrFail($fixture['products']['B']);
    $openingCount = DB::table('opening_inventory_documents')->count();
    qaReject(fn () => app(SaveOpeningInventoryDraftAction::class)->execute([['product_id'=>$pending->id, 'store_id'=>$f['store']->id, 'quantity'=>'1']]), 'F opening inventory without explicit/master cost remains safely rejected', InvalidArgumentException::class);
    qaAssert(DB::table('opening_inventory_documents')->count() === $openingCount, 'F pending-cost opening rejection creates no accounting document');
    $path = getenv('QA_STORAGE').'/optional-cost-import.xlsx';
    $writer = new OpenSpout\Writer\XLSX\Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Instructions');
    foreach ([['Product Card'], ['Schema', 'toyjoy.product-card.v2'], ['Version', '2'], ['Company', $f['company']->code.' — QA']] as $row) $writer->addRow(OpenSpout\Common\Entity\Row::fromValues($row));
    $writer->addNewSheetAndMakeItCurrent()->setName('Data Entry');
    $headers = App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction::HEADERS;
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues($headers));
    foreach (['EMPTY'=>'', 'VALID'=>'12.34', 'INVALID'=>'-1'] as $tag=>$cost) {
        $row = ['barcode_registration_type'=>'local', 'supplier_code'=>$supplier->code, 'model_number'=>'COST-IMPORT-'.$tag, 'name_ar'=>'منتج استيراد اختبار', 'unit_cost'=>$cost, 'base_consumer_price'=>'25', 'category_code'=>$f['category']->code, 'product_type'=>'standard', 'status'=>'active'];
        $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(array_map(fn ($key) => $row[$key] ?? '', $headers)));
    }
    $writer->close();
    $result = app(App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction::class)->execute($path);
    qaAssert($result['added'] === 2 && $result['rejected'] === 1, 'Product Card workbook uses optional cost contract and rejects invalid entered cost');
    qaAssert(Product::where('model_number', 'COST-IMPORT-EMPTY')->firstOrFail()->average_cost === null, 'import creates genuine null cost');
    qaAssert(Product::where('model_number', 'COST-IMPORT-VALID')->firstOrFail()->average_cost === '12.34', 'import preserves exact entered cost');
    echo "PASS browser database readback, pending-cost accounting safety and affected import path\n";
    exit;
}

$withCost = $save->execute([...$data, 'model_number'=>'COST-A', 'average_cost'=>'12.34']);
foreach (['omitted', 'blank', 'null'] as $input) {
    $edit = [...$data, 'name_en'=>'Cost QA A '.$input];
    if ($input !== 'omitted') $edit['average_cost'] = $input === 'blank' ? '' : null;
    $withCost = $save->execute($edit, $withCost->id, $withCost->lock_version);
    qaAssert($withCost->average_cost === '12.34', 'A '.$input.' cost preserves stored value on final edit');
}
$noCost = $save->execute([...$data, 'model_number'=>'COST-B']);
$noCost = $save->execute([...$data, 'name_en'=>'Cost QA B edited'], $noCost->id, $noCost->lock_version);
qaAssert($noCost->average_cost === null && $noCost->status === 'active' && ! $noCost->hasIncompleteCard(), 'B no-cost product final edit');
$new = $save->execute([...$data, 'model_number'=>'COST-C', 'average_cost'=>'']);
qaAssert($new->average_cost === null && ! $new->hasIncompleteCard(), 'C new no-cost product finalized');
$entered = $save->execute([...$data, 'model_number'=>'COST-D', 'average_cost'=>'87.65']);
qaAssert($entered->fresh()->average_cost === '87.65', 'D exact entered cost persists on reload');
$draft = $save->execute([...$data, 'model_number'=>'COST-DRAFT', 'average_cost'=>'', 'sale_price'=>'', '_save_as_draft'=>true]);
$draft = $save->execute([...$data, 'model_number'=>'COST-DRAFT'], $draft->id, $draft->lock_version);
qaAssert($draft->average_cost === null && ! $draft->hasIncompleteCard(), 'draft finalization does not require cost');
$zero = $save->execute([...$data, 'model_number'=>'COST-ZERO', 'average_cost'=>'0']);
qaAssert($zero->average_cost === '0.00', 'explicit zero is preserved as an intentional input');
$version = $withCost->lock_version;
qaReject(fn () => $save->execute([...$data, 'average_cost'=>'bad'], $withCost->id, $version), 'invalid entered cost rejected', InvalidArgumentException::class);
qaAssert($withCost->fresh()->average_cost === '12.34' && $withCost->fresh()->lock_version === $version, 'invalid cost leaves saved product unchanged');
qaReject(fn () => $save->execute($data, $withCost->id, -1), 'stale edit remains rejected', App\Modules\Catalog\Exceptions\StaleCatalogRecordException::class);

// Grants apply only to the fresh disposable QA identity/database.
$role = Role::create(['code'=>'qa-cost-editor', 'name_ar'=>'محرر اختبار', 'name_en'=>'QA costless editor', 'status'=>'active']);
$role->permissions()->sync(Permission::whereIn('code', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'])->pluck('id'));
$f['user']->roles()->sync([$role->id]);
DB::table('user_branch_scopes')->insert(['user_id'=>$f['user']->id, 'branch_id'=>$f['branch']->id, 'status'=>'active', 'created_at'=>now(), 'updated_at'=>now()]);
Auth::login($f['user']);
qaAssert(! $f['user']->hasPermission('products_categories_brands.cost_view'), 'E editor has no cost permission');
$hidden = $save->execute([...$data, 'name_en'=>'Cost QA E edited', 'average_cost'=>'forged-invalid'], $withCost->id, $withCost->lock_version);
qaAssert($hidden->average_cost === '12.34', 'E hidden forged cost ignored and existing cost preserved');
$hiddenNew = $save->execute([...$data, 'model_number'=>'COST-E', 'average_cost'=>'999']);
qaAssert($hiddenNew->average_cost === null && ! $hiddenNew->hasIncompleteCard(), 'E editor can create without supplying cost');
Auth::login($f['admin']);

// Receiving uses explicit document cost even when the master cost is null.
$invoice = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id'=>$supplier->id, 'store_id'=>$f['receiving']->id, 'invoice_date'=>today()->toDateString(), 'currency_code'=>'EGP', 'supplier_reference'=>'COST-F'], [['product_id'=>$noCost->id, 'quantity'=>'2', 'unit_cost'=>'12.1234', 'base_consumer_price'=>'30', 'tax_rate'=>'0']]);
$invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
$invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$invoice->lines->first()->id=>[$f['store']->id=>'2']], $invoice->lock_version);
$invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
$line = $invoice->lines->first();
qaAssert($line->previous_product_cost === null, 'F previous null master cost remains null in purchase snapshot');
qaMoney($line->unit_cost, '12.1234', 'F historical purchase unit cost');
qaMoney($line->line_total, '24.2468', 'F purchase financial total');
$transfer = StockTransfer::where('purchase_invoice_id', $invoice->id)->firstOrFail()->load('lines');
app(ReceiveStockTransferAction::class)->execute($transfer->id, $transfer->lines->mapWithKeys(fn ($row) => [$row->id=>(string) $row->quantity_dispatched])->all(), null, null);
$tables = ['purchase_invoices', 'purchase_invoice_lines', 'stock_movements', 'stock_balances', 'stock_transfers', 'stock_transfer_lines', 'approval_records'];
$snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table=>DB::table($table)->orderBy('id')->get()->toJson()])->all();
$before = $snapshot();
$received = $noCost->fresh();
$received = $save->execute([...$data, 'name_en'=>'Cost QA received edited', 'sale_price'=>$received->sale_price, 'average_cost'=>''], $received->id, $received->lock_version);
qaAssert($received->average_cost === '12.12', 'F Product Card blank edit preserves approved purchase cost');
qaAssert($snapshot() === $before, 'F Product Card edit leaves every historical purchase/receipt/valuation/approval record unchanged');
$balance = DB::table('stock_balances')->where('product_id', $noCost->id)->where('store_id', $f['store']->id)->first();
qaMoney($balance->on_hand, '2', 'F destination quantity', 6);
qaMoney($balance->average_cost, '12.1234', 'F exact destination WAC');
qaMoney($balance->total_value, '24.2468', 'F exact destination valuation');

// Dedicated browser targets: keep B and D null until their UI checks run.
$browser = ['A'=>$withCost->id, 'B'=>$hiddenNew->id, 'D'=>$new->id, 'E'=>$entered->id];
$accounts = [];
foreach (['Admin'=>$f['admin'], 'Editor'=>$f['user']] as $label=>$user) {
    $password = bin2hex(random_bytes(20)); $user->password = $password; $user->save();
    $accounts[$label] = ['username'=>$user->username, 'password'=>$password];
}
$private = getenv('QA_STORAGE').'/cost-browser.json';
file_put_contents($private, json_encode(['accounts'=>$accounts, 'products'=>$browser, 'category'=>$f['category']->id, 'supplier'=>$supplier->id], JSON_THROW_ON_ERROR));
chmod($private, 0600);
echo "PASS isolated backend A–F, draft, invalid, stale and forged-input cases\n";
