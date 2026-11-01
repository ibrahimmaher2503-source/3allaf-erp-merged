<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Support\Str;

$fixturePath = getenv('QA_STORAGE').'/price-discount-browser.json';
if (is_file($fixturePath)) {
    echo "PASS bounded pricing browser fixture reused\n";
    exit(0);
}
$fixture = qaFixture();
$product = Product::query()->where('item_code', 'QA-DISCOUNT-LABEL')->first() ?? Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => 'QA-DISCOUNT-LABEL', 'model_number' => 'QA-DISCOUNT-LABEL', 'sale_price' => '300.00']);
$product->barcodes()->firstOrCreate(['barcode' => 'QA-DISCOUNT-LOCAL-001'], ['source' => 'local', 'status' => 'active', 'is_primary' => true]);
$product->barcodes()->firstOrCreate(['barcode' => '6224008875185'], ['source' => 'international', 'status' => 'active', 'is_primary' => false]);
$local = Product::query()->where('item_code', 'QA-LOCAL-LABEL')->first() ?? Product::factory()->create(['category_id' => $fixture['category']->id, 'item_code' => 'QA-LOCAL-LABEL', 'model_number' => 'QA-LOCAL-LABEL', 'sale_price' => '300.00']);
$local->barcodes()->firstOrCreate(['barcode' => '0002-000001'], ['source' => 'local', 'status' => 'active', 'is_primary' => true]);
$outlets = ['base' => $fixture['store']->id];
foreach (['up15' => '15', 'up10' => '10', 'down10' => '-10', 'fixed' => '0'] as $key => $percent) {
    $list = PriceList::query()->create(['company_id' => $fixture['company']->id, 'list_number' => (int) PriceList::query()->where('company_id', $fixture['company']->id)->max('list_number') + 1, 'code' => 'QA-'.$key.'-'.Str::random(5), 'name_ar' => $key, 'name_en' => $key, 'percentage_increase' => $percent, 'status' => 'active']);
    $store = Store::query()->create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id, 'code' => 'QA-'.strtoupper($key).'-'.Str::random(4), 'name_ar' => $key, 'name_en' => $key, 'type' => 'selling', 'status' => 'active', 'price_list_id' => $list->id]);
    $outlets[$key] = $store->id;
    if ($key === 'fixed') {
        $version = $list->versions()->create(['version' => 1, 'state' => 'approved', 'source_type' => 'qa_fixture', 'approved_by' => $fixture['admin']->id, 'approved_at' => now()]);
        $version->lines()->create(['product_id' => $product->id, 'store_id' => $store->id, 'branch_id' => $store->branch_id, 'amount' => '420.00', 'reference_amount' => '300.00']);
    }
}
file_put_contents($fixturePath, json_encode(['outlets' => $outlets, 'product' => $product->item_code, 'local' => $local->item_code], JSON_THROW_ON_ERROR));
echo "PASS bounded pricing browser fixture\n";
