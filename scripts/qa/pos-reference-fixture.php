<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$fixture = qaFixture();
$admin = $fixture['admin'];
$store = $fixture['store'];

if (! PosShift::query()->where('cashier_id', $admin->id)->where('status', 'open')->exists()) {
    $shift = PosShift::query()->create([
        'branch_id' => $fixture['branch']->id, 'store_id' => $store->id,
        'cash_drawer_id' => $fixture['drawer']->id, 'cashier_id' => $admin->id,
        'opened_by' => $admin->id, 'status' => 'open', 'opening_cash' => '0',
        'currency_code' => 'EGP', 'idempotency_key' => (string) Str::uuid(),
        'opened_at' => now(),
    ]);
    DB::table('active_pos_shift_assignments')->insert([
        'shift_id' => $shift->id, 'cashier_id' => $admin->id,
        'cash_drawer_id' => $fixture['drawer']->id,
    ]);
}

$names = array_map(
    fn (int $number): string => 'فئة اختبار نقطة البيع '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
    range(1, 30),
);
foreach ($names as $index => $name) {
    $category = Category::query()->firstOrCreate(['code' => sprintf('QA-POS-%02d', $index + 1)], [
        'name_ar' => $name, 'name_en' => 'QA POS category '.($index + 1),
        'status' => 'active', 'sort_order' => $index,
    ]);
    $product = Product::query()->where('model_number', sprintf('QA-POS-%02d', $index + 1))->first();
    if ($product === null) {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'model_number' => sprintf('QA-POS-%02d', $index + 1),
            'name_ar' => $index === 0 ? 'لعبة تركيب عربية' : 'لعبة اختبار '.($index + 1),
            'name_en' => 'QA Toy '.($index + 1),
            'sale_price' => '45.00',
        ]);
    }
    $product->barcodes()->firstOrCreate(['barcode' => sprintf('QA-POS-BAR-%02d', $index + 1)], [
        'source' => 'local', 'status' => 'active', 'is_primary' => true,
    ]);
    if (! PriceLine::query()->where('product_id', $product->id)->where('store_id', $store->id)->whereNotNull('active_key')->exists()) {
        $proposal = app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'PL-0000', 'قائمة اختبار', 'QA Base', '45.00',
            'product_card', 'QA-POS', null, null, 'POS visual acceptance',
        );
        $proposal = app(SubmitPriceProposalAction::class)->execute($proposal);
        app(ApprovePriceProposalAction::class)->execute($proposal);
    }
    if (! StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->exists()) {
        app(PostInventoryMovement::class)->execute(
            $product->id, $store->id, '150', 'qa_pos_fixture', '10',
            'qa-pos-reference-stock-'.$product->id,
        );
    }
}

CustomerPolicySettingVersion::query()->firstOrCreate(
    ['key' => 'customer.consent.purpose', 'version' => 1],
    ['value' => '["retail"]', 'value_type' => 'json', 'created_by' => $admin->id],
);
foreach ([
    'customer.phone_normalization' => ['digits_only', 'string'],
    'customer.consent.wording' => ['{"version":"qa-1","text":"موافقة اختبارية"}', 'json'],
    'customer.consent.retention' => ['{"days":365}', 'json'],
] as $key => [$value, $type]) {
    CustomerPolicySettingVersion::query()->firstOrCreate(
        ['key' => $key, 'version' => 1],
        ['value' => $value, 'value_type' => $type, 'created_by' => $admin->id],
    );
}
Governorate::query()->firstOrCreate(['code' => 'QA-POS-GOV'], [
    'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'active', 'sort_order' => 0,
]);
$customer = Customer::query()->firstOrCreate(['phone_normalized' => '201011223399'], [
    'idempotency_key' => (string) Str::uuid(), 'name_ar' => 'عميل اختبار نقطة البيع',
    'name_en' => 'QA POS Customer', 'phone_display' => '01011223399',
    'status' => 'active', 'created_by' => $admin->id,
    'created_branch_id' => $fixture['branch']->id,
    'created_store_id' => $store->id,
]);
for ($index = 2; $index <= 6; $index++) {
    Customer::query()->firstOrCreate(['phone_normalized' => '2010112233'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)], [
        'idempotency_key' => (string) Str::uuid(),
        'name_ar' => 'عميل اختبار '.$index,
        'name_en' => 'QA POS Customer '.$index,
        'phone_display' => '010112233'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'status' => 'active',
        'created_by' => $admin->id,
        'created_branch_id' => $fixture['branch']->id,
        'created_store_id' => $store->id,
    ]);
}
$firstProduct = Product::query()->where('model_number', 'QA-POS-01')->firstOrFail();
if (! Sale::query()->where('document_number', 'QA-POS-LOOKUP-001')->exists()) {
    $sale = Sale::query()->create([
        'branch_id' => $fixture['branch']->id, 'store_id' => $store->id,
        'cashier_id' => $admin->id, 'customer_id' => $customer->id,
        'document_number' => 'QA-POS-LOOKUP-001', 'status' => 'draft',
        'idempotency_key' => (string) Str::uuid(), 'subtotal' => '45.00',
        'total' => '45.00', 'currency_code' => 'EGP',
    ]);
    $sale->lines()->create([
        'product_id' => $firstProduct->id, 'line_number' => 1,
        'item_code' => $firstProduct->item_code, 'name_ar' => $firstProduct->name_ar,
        'name_en' => $firstProduct->name_en, 'quantity' => '1',
        'unit_price' => '45.00', 'gross_amount' => '45.00',
        'net_amount' => '45.00',
    ]);
    $sale->update(['status' => 'approved', 'approved_at' => now()]);
}

echo 'PASS disposable POS browser fixture: 30 catalog categories, priced stock, open shift, customer, approved invoice'.PHP_EOL;
