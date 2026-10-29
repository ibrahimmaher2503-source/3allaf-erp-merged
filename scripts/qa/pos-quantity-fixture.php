<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;

$fixture = qaFixture();
$product = Product::query()->where('item_code', 'QA-POS-SKU-02')->firstOrFail();
StockBalance::query()->where('store_id', $fixture['store']->id)->where('product_id', $product->id)->update(['on_hand' => '2', 'reserved' => '0', 'total_value' => '20']);
