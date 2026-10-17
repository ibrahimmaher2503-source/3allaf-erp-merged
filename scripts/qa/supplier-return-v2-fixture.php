<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;

$fixture = qaFixture();
$supplier = Supplier::query()->firstOrCreate(['code' => 'V2-BROWSER-SUP'], ['name_ar' => 'مورد الأجهزة', 'name_en' => 'Devices supplier', 'status' => 'active']);
$reason = SupplierReturnReason::query()->firstOrCreate(['code' => 'V2-BROWSER-REASON'], ['label_ar' => 'عيب في الصنف', 'label_en' => 'Defective item', 'is_active' => true]);
$product = Product::query()->whereHas('barcodes', fn ($query) => $query->where('barcode', 'V2-BROWSER-CODE'))->first();
if ($product === null) {
    $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'name_ar' => 'لعبة تركيب تعليمية', 'name_en' => 'Educational building toy', 'average_cost' => 115, 'sale_price' => 150]);
    $product->barcodes()->create(['barcode' => 'V2-BROWSER-CODE', 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
    ProductSupplier::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id]);
    StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $fixture['receiving']->id, 'on_hand' => 5, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 115, 'total_value' => 575, 'version' => 0]);
}
file_put_contents(getenv('QA_STORAGE').'/supplier-return-v2-fixture.json', json_encode(['supplier' => $supplier->id, 'store' => $fixture['receiving']->id, 'reason' => $reason->id, 'product' => $product->id], JSON_THROW_ON_ERROR));
echo "PASS disposable supplier-return screenshot fixture\n";
