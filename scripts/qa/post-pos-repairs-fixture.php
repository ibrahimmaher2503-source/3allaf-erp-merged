<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Str;

$fixture = qaFixture();
$suffix = strtoupper(Str::random(6));
$product = Product::factory()->create([
    'category_id' => $fixture['category']->id, 'item_code' => 'QA-R1-R7-SKU-'.$suffix,
    'name_ar' => 'لعبة إصلاحات التشغيل', 'name_en' => 'Operational repair toy',
    'sale_price' => '140', 'average_cost' => '10',
]);
$product->barcodes()->create(['barcode' => 'QA-R1-R7-BAR-'.$suffix, 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
$supplier = Supplier::query()->create(['code' => 'QA-R1-R7-'.$suffix, 'name_ar' => 'مورد إصلاحات التشغيل', 'name_en' => 'QA repair supplier', 'status' => 'active']);
$invoice = app(SavePurchaseInvoiceAction::class)->execute(
    ['supplier_id' => $supplier->id, 'store_id' => $fixture['receiving']->id, 'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP'],
    [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '20', 'base_consumer_price' => '140', 'discount_value' => '0']],
);
$approvedInvoice = PurchaseInvoice::query()->create([
    'invoice_number' => 'QA-LABEL-'.Str::random(8), 'supplier_id' => $supplier->id,
    'store_id' => $fixture['receiving']->id, 'status' => 'approved', 'idempotency_key' => (string) Str::uuid(),
]);
$approvedInvoice->lines()->create(['product_id' => $product->id, 'quantity' => '20', 'unit_cost' => '20']);

$destination = Store::query()->create([
    'company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id,
    'code' => 'QA-R3-DST-'.$suffix, 'name_ar' => 'مخزن استلام الاختبار', 'name_en' => 'QA Receiving Destination',
    'type' => 'warehouse', 'status' => 'active', 'allows_negative_stock' => false,
]);
$otherBranch = Branch::query()->create([
    'company_id' => $fixture['company']->id, 'code' => 'QA-R3-BR-'.$suffix,
    'name_ar' => 'فرع ثان للاختبار', 'name_en' => 'QA Second Branch', 'status' => 'active', 'timezone' => 'Africa/Cairo',
]);
$otherDestination = Store::query()->create([
    'company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id,
    'code' => 'QA-R3-OTHER-'.$suffix, 'name_ar' => 'مخزن الفرع الثاني', 'name_en' => 'QA Second Warehouse',
    'type' => 'warehouse', 'status' => 'active', 'allows_negative_stock' => false,
]);
$otherSelling = Store::query()->create([
    'company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id,
    'code' => 'QA-R6-SELL-'.$suffix, 'name_ar' => 'بيع الفرع الثاني', 'name_en' => 'QA Second Selling',
    'type' => 'selling', 'status' => 'active', 'allows_negative_stock' => false,
]);
CashDrawer::query()->create([
    'company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id, 'store_id' => $otherSelling->id,
    'code' => 'QA-R6-TILL-'.$suffix, 'name_ar' => 'خزينة الفرع الثاني', 'name_en' => 'QA Second Till', 'status' => 'active',
]);
app(PostInventoryMovement::class)->execute($product->id, $fixture['receiving']->id, '50', 'qa_r1_r7_stock', '10', 'qa-r1-r7-stock-'.Str::uuid());
$line = ['product_id' => $product->id, 'quantity_requested' => '10', 'allocations' => [$destination->id => '8', $otherDestination->id => '2']];
$approval = app(CreateStockTransferDraftAction::class)->execute($fixture['receiving']->id, $destination->id, [$line], idempotencyKey: 'qa-r3-approval-'.Str::uuid());
$approval = app(SubmitStockTransferAction::class)->execute($approval->id);
$receiving = app(CreateStockTransferDraftAction::class)->execute($fixture['receiving']->id, $destination->id, [$line], idempotencyKey: 'qa-r3-receiving-'.Str::uuid());
$receiving->update(['status' => 'approved', 'approved_by' => $fixture['admin']->id, 'approved_at' => now()]);
$receiving = app(DispatchStockTransferAction::class)->execute($receiving->id);
$count = StockCount::query()->create([
    'count_number' => 'QA-R7-'.Str::random(8), 'count_type' => 'full', 'scope_type' => 'full',
    'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['receiving']->id,
    'status' => 'in_progress', 'reference_at' => now()->subMinute(), 'opened_at' => now()->subMinute(),
    'created_by' => $fixture['admin']->id, 'manager_id' => $fixture['admin']->id,
    'idempotency_key' => (string) Str::uuid(), 'lock_version' => 2,
]);
$count->locations()->create(['store_id' => $fixture['receiving']->id, 'snapshot_at' => now()->subMinute()]);
foreach ([['SHORT', true, '8', '-2'], ['PLUS', true, '12', '2'], ['MISS', false, null, '0'], ['MATCH', true, '10', '0']] as [$direction, $isCounted, $quantity, $variance]) {
    $countProduct = Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => 'QA-R7-'.$direction.'-'.$suffix]);
    $count->lines()->create([
        'store_id' => $fixture['receiving']->id, 'product_id' => $countProduct->id,
        'reference_on_hand' => '10', 'counted_quantity' => $quantity,
        'variance_quantity' => $variance, 'is_counted' => $isCounted,
        'counted_at' => $isCounted ? now() : null,
    ]);
}

file_put_contents($argv[1], json_encode([
    'invoice' => $invoice->id, 'invoiceNumber' => $invoice->invoice_number,
    'approvedInvoiceNumber' => $approvedInvoice->invoice_number,
    'product' => $product->id, 'productCode' => $product->item_code,
    'approvalTransfer' => $approval->id, 'receivingTransfer' => $receiving->id,
    'receivingTransferNumber' => $receiving->transfer_number,
    'receivingDestination' => $destination->id, 'otherDestination' => $otherDestination->id,
    'sellingStore' => $fixture['store']->id, 'receivingStore' => $fixture['receiving']->id,
    'workStore' => $fixture['store']->id,
    'otherSellingCode' => $otherSelling->code, 'count' => $count->id,
], JSON_THROW_ON_ERROR));
chmod($argv[1], 0600);
echo "PASS disposable R1–R7 browser documents and contexts\n";
