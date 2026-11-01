<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Str;

$fixture = qaFixture();
$customer = Customer::query()->where('phone_normalized', '201011223399')->firstOrFail();
$customer->scopes()->firstOrCreate(['branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id], ['created_by' => $fixture['admin']->id]);
$product = Product::query()->where('model_number', 'QA-POS-01')->firstOrFail();
$product->barcodes()->firstOrCreate(['barcode' => '6224008875178'], ['source' => 'international', 'status' => 'active', 'is_primary' => false]);
$shift = PosShift::query()->where('cashier_id', $fixture['admin']->id)->where('status', 'open')->firstOrFail();
$sale = Sale::query()->create([
    'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
    'cash_drawer_id' => $fixture['drawer']->id, 'shift_id' => $shift->id,
    'cashier_id' => $fixture['admin']->id, 'customer_id' => $customer->id,
    'document_number' => 'QA-SHIFT-RECEIPT-001', 'status' => 'draft',
    'idempotency_key' => (string) Str::uuid(), 'subtotal' => '45.00', 'total' => '45.00', 'currency_code' => 'EGP',
]);
$sale->lines()->create([
    'product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code,
    'name_ar' => $product->name_ar, 'name_en' => $product->name_en,
    'quantity' => '1', 'unit_price' => '45.00', 'gross_amount' => '45.00', 'net_amount' => '45.00',
]);
$sale->update(['status' => 'approved', 'approved_at' => now()]);
app(RecordAuditEvent::class)->execute('retail', 'sale_completed', $sale, branchId: $fixture['branch']->id, storeId: $fixture['store']->id, metadata: ['shift_id' => $shift->id, 'non_posting' => false]);
echo "PASS disposable quotation customer scope and manufacturer barcode fixture\n";
