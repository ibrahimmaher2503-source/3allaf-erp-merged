<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\PreviewPurchaseOrderWorkbook;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Support\DataExchange\MasterDataDocument;

$fixture = qaFixture();
$company = $fixture['company'];
$branch = $fixture['branch'];
$source = $fixture['store'];
$destinationA = $fixture['receiving'];
$destinationB = Store::query()->firstOrCreate(['code' => 'QA-E2E-B'], [
    'company_id' => $company->id, 'branch_id' => $branch->id, 'name_ar' => 'مخزن اختبار الوجهة ب',
    'name_en' => 'QA E2E Destination B', 'type' => 'warehouse', 'status' => 'active', 'allows_negative_stock' => false,
]);
$zeroDestination = Store::query()->firstOrCreate(['code' => 'QA-E2E-ZERO'], [
    'company_id' => $company->id, 'branch_id' => $branch->id, 'name_ar' => 'مخزن اختبار الوجهة صفر',
    'name_en' => 'QA E2E Zero Destination', 'type' => 'warehouse', 'status' => 'active', 'allows_negative_stock' => false,
]);
$main = Store::query()->firstOrCreate(['code' => 'QA-E2E-MAIN'], [
    'company_id' => $company->id, 'branch_id' => null, 'name_ar' => 'المخزن الرئيسي للاختبار',
    'name_en' => 'QA E2E Main Warehouse', 'type' => 'warehouse', 'status' => 'active', 'allows_negative_stock' => false,
]);
$inactive = Store::query()->firstOrCreate(['code' => 'QA-E2E-INACTIVE'], [
    'company_id' => $company->id, 'branch_id' => null, 'name_ar' => 'مخزن اختبار غير نشط',
    'name_en' => 'QA E2E Inactive Warehouse', 'type' => 'warehouse', 'status' => 'inactive', 'allows_negative_stock' => false,
]);
$supplier = Supplier::query()->firstOrCreate(['code' => 'SUP-9001'], [
    'name_ar' => 'مورد اختبار المتصفح', 'name_en' => 'QA E2E Supplier', 'status' => 'active',
]);
$reason = SupplierReturnReason::query()->firstOrCreate(['code' => 'QA-E2E-DEFECT'], [
    'label_ar' => 'عيب اختبار', 'label_en' => 'QA defect', 'is_active' => true,
]);
$products = [];
foreach ([1, 2] as $number) {
    $barcode = $number === 1 ? '0009001001' : '0009001002';
    $product = Product::query()->whereHas('barcodes', fn ($query) => $query->where('barcode', $barcode))->first();
    if ($product === null) {
        $product = Product::factory()->create([
            'category_id' => $fixture['category']->id,
            'name_ar' => 'منتج اختبار المتصفح '.$number,
            'name_en' => 'QA E2E Product '.$number,
            'average_cost' => '10.00',
            'sale_price' => '25.00',
        ]);
        $product->barcodes()->create(['barcode' => $barcode, 'source' => 'supplier', 'status' => 'active', 'is_primary' => true]);
    }
    ProductSupplier::query()->firstOrCreate(['product_id' => $product->id, 'supplier_id' => $supplier->id]);
    foreach ([$source, $main] as $store) {
        StockBalance::query()->firstOrCreate(['product_id' => $product->id, 'store_id' => $store->id], [
            'on_hand' => 30, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10,
            'total_value' => 300, 'version' => 0,
        ]);
    }
    $products[] = ['id' => $product->id, 'code' => $product->item_code, 'barcode' => $barcode];
}

foreach (['valid' => [[$products[0]['code'], '', '2', '11.50', 'QA normal'], [$products[1]['barcode'], '', '3', '12.00', 'QA leading zero']], 'invalid' => [['QA-UNKNOWN-CODE', '', '1', '10.00', 'QA unknown']]] as $kind => $rows) {
    $document = app(MasterDataDocument::class)->xlsx('qa-po-'.$kind.'.xlsx', PreviewPurchaseOrderWorkbook::HEADERS, $rows);
    copy($document->getFile()->getPathname(), getenv('QA_STORAGE').'/qa-po-'.$kind.'.xlsx');
}

file_put_contents(getenv('QA_STORAGE').'/v2-current-batch-e2e.json', json_encode([
    'branch' => $branch->id, 'source' => $source->id, 'destination_a' => $destinationA->id,
    'destination_b' => $destinationB->id, 'zero_destination' => $zeroDestination->id,
    'main' => $main->id, 'inactive' => $inactive->id, 'supplier' => $supplier->id,
    'reason' => $reason->id, 'category' => $fixture['category']->id, 'products' => $products,
    'workbook_valid' => getenv('QA_STORAGE').'/qa-po-valid.xlsx',
    'workbook_invalid' => getenv('QA_STORAGE').'/qa-po-invalid.xlsx',
], JSON_THROW_ON_ERROR));
echo "PASS disposable v2 E2E stores, products, and supplier fixture\n";
