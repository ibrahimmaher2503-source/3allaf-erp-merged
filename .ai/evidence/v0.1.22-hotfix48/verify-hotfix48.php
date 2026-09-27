<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\UpdateStockTransferDraftAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\ProductPriceOverride;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Queries\SupplierProductPrice;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Purchasing\Support\DefaultReceivingWarehouse;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$product = static function (Category $category, string $token, string $cost = '2', string $selling = '5'): Product {
    $record = Product::query()->create([
        'item_code' => 'HF48-'.$token,
        'name_ar' => 'تحقق '.$token,
        'name_en' => 'HF48 '.$token,
        'model_number' => 'MODEL-'.$token,
        'product_type' => 'standard',
        'category_id' => $category->id,
        'status' => 'active',
        'barcode_mode' => 'manual',
        'average_cost' => $cost,
        'sale_price' => $selling,
        'lock_version' => 0,
    ]);
    Barcode::query()->create(['product_id' => $record->id, 'barcode' => 'HF48BC-'.$token, 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);

    return $record;
};
$gtin13 = static function (string $twelveDigits): string {
    $sum = 0;
    for ($index = 0; $index < 12; $index++) {
        $sum += ((int) $twelveDigits[$index]) * ($index % 2 === 0 ? 1 : 3);
    }

    return $twelveDigits.(string) ((10 - ($sum % 10)) % 10);
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix48', 'Version mismatch.');
    $assert(app()->environment() !== 'production' || str_contains((string) DB::connection()->getDatabaseName(), 'hf48'), 'Focused verification refuses the production database.');

    DB::beginTransaction();
    try {
        $token = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $company = Company::query()->where('status', 'active')->whereNotNull('currency_code')->firstOrFail();
        $role = Role::query()->where('code', 'system-administrator')->where('status', 'active')->firstOrFail();
        $actor = User::query()->create(['name' => 'HF48 scenario actor', 'username' => 'hf48-'.$token, 'email' => 'hf48-'.$token.'@example.test', 'password' => Hash::make(Str::password(24)), 'status' => 'active']);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->roles()->attach($role->id);
        $approver = User::query()->create(['name' => 'HF48 scenario approver', 'username' => 'hf48a-'.$token, 'email' => 'hf48a-'.$token.'@example.test', 'password' => Hash::make(Str::password(24)), 'status' => 'active']);
        $approver->forceFill(['email_verified_at' => now()])->save();
        $approver->roles()->attach($role->id);
        Auth::login($actor);
        request()->merge(['company_id' => $company->id]);

        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => 'HF48B'.$token, 'name_ar' => 'فرع تحقق', 'name_en' => 'HF48 Branch', 'timezone' => $company->timezone ?: 'Africa/Cairo', 'status' => 'active']);
        $source = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48S'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن مصدر', 'name_en' => 'HF48 Source', 'status' => 'active']);
        $assert(app(DefaultReceivingWarehouse::class)->resolve($actor, $company->id, $branch->id)?->is($source), 'Unique branch warehouse was not selected as the safe default.');
        $destination = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48D'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن وجهة', 'name_en' => 'HF48 Destination', 'status' => 'active']);
        $sourceTwo = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48S2'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن مصدر ثان', 'name_en' => 'HF48 Source Two', 'status' => 'active']);
        $otherBranch = Branch::query()->create(['company_id' => $company->id, 'code' => 'HF48X'.$token, 'name_ar' => 'فرع آخر', 'name_en' => 'HF48 Other Branch', 'timezone' => $company->timezone ?: 'Africa/Cairo', 'status' => 'active']);
        $otherSource = Store::query()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'code' => 'HF48X'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن آخر', 'name_en' => 'HF48 Other Source', 'status' => 'active']);
        $assert(Store::query()->visibleTo($actor)->count() === Store::query()->count(), 'System Administrator does not see every store.');
        $assert(Branch::query()->visibleTo($actor)->count() === Branch::query()->count(), 'System Administrator does not see every branch.');
        $assert(Gate::forUser($actor)->allows('purchase_invoices.draft'), 'System Administrator lacks invoice draft permission.');

        $category = Category::query()->create(['code' => 'HF48C'.$token, 'name_ar' => 'فئة تحقق', 'name_en' => 'HF48 Category', 'status' => 'active', 'sort_order' => 999, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $supplier = Supplier::query()->create(['code' => 'HF48SUP'.$token, 'name_ar' => 'مورد تحقق', 'name_en' => 'Alpha Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $otherSupplier = Supplier::query()->create(['code' => 'HF48OTH'.$token, 'name_ar' => 'مورد آخر', 'name_en' => 'Beta Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        $poProduct = $product($category, 'PO'.$token);
        $noHistoryProduct = $product($category, 'NOH'.$token);
        foreach ([[$otherSupplier, '99'], [$supplier, '8']] as [$historySupplier, $historyCost]) {
            $history = PurchaseInvoice::query()->create(['supplier_id' => $historySupplier->id, 'store_id' => $source->id, 'invoice_number' => null, 'supplier_reference' => 'HF48-H-'.$historySupplier->id, 'invoice_date' => today(), 'currency_code' => $company->currency_code, 'status' => 'approved', 'subtotal' => $historyCost, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $historyCost, 'idempotency_key' => (string) Str::uuid(), 'approved_at' => now(), 'approved_by' => $approver->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $history->lines()->create(['product_id' => $poProduct->id, 'quantity' => 1, 'unit_cost' => $historyCost, 'discount_type' => null, 'discount_value' => 0, 'discount_amount' => 0, 'tax_rate' => 0, 'tax_code' => null, 'tax_amount' => 0, 'subtotal' => $historyCost, 'line_total' => $historyCost, 'quantity_received' => 1]);
        }
        $prices = app(SupplierProductPrice::class);
        $assert($prices->resolve($poProduct, $supplier->id, $company->id)['unit_cost'] === '8.0000', 'Same-supplier latest cost was not selected.');
        $assert($prices->resolve($noHistoryProduct, $supplier->id, $company->id)['unit_cost'] === '0.0000', 'No-history cost did not default to zero.');
        $order = app(SavePurchaseOrderAction::class)->execute(['supplier_id' => $supplier->id, 'branch_id' => $branch->id, 'store_id' => $source->id, 'order_date' => today()->toDateString()], [['product_id' => $poProduct->id, 'quantity_ordered' => 3, 'unit_cost' => 8, 'discount_type' => 'percentage', 'discount_value' => 90, 'tax_rate' => 14]]);
        $order = $order->fresh('lines');
        $assert((string) $order->subtotal === '24.0000' && (string) $order->tax_amount === '0.0000' && (string) $order->total_amount === '24.0000', 'New PO total is not quantity multiplied by purchase cost.');
        $assert($order->lines->first()->discount_type === null && (string) $order->lines->first()->tax_amount === '0.0000', 'New PO retained discount or tax inputs.');

        $invoiceProduct = $product($category, 'INV'.$token, '2', '5');
        $beforeProduct = $invoiceProduct->only(['average_cost', 'sale_price']);
        $calculator = app(PurchaseInvoiceCalculator::class)->calculateLine(['quantity' => 2, 'unit_cost' => 10, 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 14, 'tax_inclusive' => true]);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id' => $supplier->id, 'store_id' => $source->id, 'supplier_reference' => 'HF48-I-'.$token, 'invoice_date' => today()->toDateString(), 'currency_code' => 'USD'], [['product_id' => $invoiceProduct->id, 'quantity' => 2, 'unit_cost' => 10, 'base_consumer_price' => 25, 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 14, 'tax_code' => 'VAT14', 'tax_inclusive' => true]]);
        $invoice = $invoice->fresh('lines');
        $assert($invoice->currency_code === strtoupper((string) $company->currency_code), 'Single configured base currency was not applied implicitly.');
        $assert((string) $invoice->total_amount === $calculator['line_total'] && (string) $invoice->lines->first()->tax_amount === $calculator['tax_amount'], 'Invoice tax/discount totals changed after save and reload.');
        $assert($invoiceProduct->fresh()->only(['average_cost', 'sale_price']) === $beforeProduct, 'Draft invoice changed the existing product card.');
        $assert(! ProductSupplier::query()->where('product_id', $invoiceProduct->id)->where('supplier_id', $supplier->id)->exists(), 'Draft invoice created a supplier association.');

        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$invoice->lines->first()->id => [$destination->id => '2']], $invoice->lock_version);
        $customPriceCount = ProductPriceOverride::query()->where('product_id', $invoiceProduct->id)->count();
        Auth::login($approver);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $approvedProduct = $invoiceProduct->fresh();
        $assert((string) $approvedProduct->average_cost === '10.00' && (string) $approvedProduct->sale_price === '25.00', 'Approval did not update last purchase cost and Base selling price.');
        $association = ProductSupplier::query()->where('product_id', $invoiceProduct->id)->where('supplier_id', $supplier->id)->first();
        $assert((string) $association?->last_purchase_price === '10.0000', 'Approval did not update supplier purchase history.');
        $assert(ProductPriceOverride::query()->where('product_id', $invoiceProduct->id)->count() === $customPriceCount, 'Approval changed custom branch prices.');
        $assert((string) StockBalance::query()->where('product_id', $invoiceProduct->id)->where('store_id', $source->id)->value('on_hand') === '0.000000', 'Receiving warehouse did not finish at zero.');
        $assert((string) $invoiceProduct->fresh()->average_cost === '10.00', 'Last purchase cost disappeared at zero stock.');
        $again = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $assert($again->status === 'approved', 'Duplicate approval was not idempotent.');

        Auth::login($actor);
        request()->merge(['company_id' => $company->id]);
        $preview = app(ReserveProductIdentityAction::class)->preview($supplier->id);
        $localProduct = app(SaveProductAction::class)->execute(['item_code' => '', 'name_ar' => 'منتج محلي', 'name_en' => 'HF48 Local Product', 'model_number' => 'HF48-LOCAL-'.$token, 'product_type' => 'standard', 'category_id' => $category->id, 'preferred_supplier_id' => $supplier->id, 'barcode_registration_type' => 'local', 'status' => 'active', 'average_cost' => 31, 'sale_price' => 47]);
        $assert($localProduct->item_code === $preview && preg_match('/^ALP\d{7}$/', (string) $localProduct->item_code) === 1, 'Local generated identity does not use supplier letters plus seven digits.');
        $assert($localProduct->barcodes()->where('barcode', $localProduct->item_code)->where('source', 'local')->exists(), 'Generated local identity was not stored as a barcode.');
        $international = $gtin13('622123456789');
        $internationalProduct = app(SaveProductAction::class)->execute(['item_code' => '', 'name_ar' => 'منتج دولي', 'name_en' => 'HF48 International Product', 'model_number' => 'HF48-INT-'.$token, 'product_type' => 'standard', 'category_id' => $category->id, 'preferred_supplier_id' => $supplier->id, 'barcode_registration_type' => 'international', 'status' => 'active', 'average_cost' => 31, 'sale_price' => 47]);
        app(AddBarcodeAction::class)->addSupplierBarcode($internationalProduct->id, $international);
        $assert($internationalProduct->barcodes()->where('barcode', $international)->where('source', 'international')->exists(), 'International barcode was not preserved.');
        $inlineInvoice = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id' => $supplier->id, 'store_id' => $source->id, 'supplier_reference' => 'HF48-INLINE-'.$token, 'invoice_date' => today()->toDateString()], [['product_id' => $internationalProduct->id, 'quantity' => 1, 'unit_cost' => 31, 'base_consumer_price' => 47, 'discount_type' => null, 'discount_value' => 0, 'tax_rate' => 0, 'tax_inclusive' => false]]);
        $inlineLine = $inlineInvoice->lines->firstOrFail();
        $assert(bccomp((string) $inlineLine->unit_cost, '31', 4) === 0, 'Inline product purchase cost was replaced by a no-history default.');
        $assert(bccomp((string) $inlineLine->base_consumer_price, '47', 3) === 0, 'Inline product selling price was not preserved.');

        $transferProduct = $product($category, 'TR'.$token, '90', '11');
        foreach ([[$source, '7'], [$sourceTwo, '9']] as [$transferSource, $cost]) {
            StockBalance::query()->create(['product_id' => $transferProduct->id, 'store_id' => $transferSource->id, 'on_hand' => 10, 'reserved' => 2, 'in_transit' => 0, 'average_cost' => $cost, 'total_value' => bcmul('10', $cost, 4), 'version' => 1]);
        }
        $transferOne = app(CreateStockTransferDraftAction::class)->execute($source->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '3']], 'restock');
        $transferTwo = app(CreateStockTransferDraftAction::class)->execute($sourceTwo->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '1']], 'restock');
        $assert(preg_match('/^'.preg_quote($branch->code, '/').'-\d{6}$/', $transferOne->transfer_number) === 1, 'Transfer number does not use branch code plus six digits.');
        $sequenceOne = (int) substr($transferOne->transfer_number, -6);
        $sequenceTwo = (int) substr($transferTwo->transfer_number, -6);
        $assert($sequenceTwo === $sequenceOne + 1 && $transferOne->transfer_number !== $transferTwo->transfer_number, 'Warehouses in one branch do not share one unique sequence.');
        $assert((string) $transferOne->lines->first()->unit_cost === '7.0000', 'Transfer used product last purchase cost instead of source WAC.');
        $assert(bccomp((string) app(EffectivePriceResolver::class)->resolve($transferProduct->id, $source->id)?->amount, '11', 3) === 0, 'Applicable source selling price was not resolved.');
        try {
            app(CreateStockTransferDraftAction::class)->execute($source->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '9']], 'over-available');
            throw new RuntimeException('Reservation-aware transfer availability was not enforced.');
        } catch (InvalidArgumentException $expected) {
            $assert(str_contains($expected->getMessage(), 'enough available stock'), 'Unexpected availability rejection.');
        }
        try {
            app(UpdateStockTransferDraftAction::class)->execute($transferOne->id, $otherSource->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '1']], 'move-branch', null, $transferOne->lock_version);
            throw new RuntimeException('Saved transfer source branch was allowed to change.');
        } catch (InvalidArgumentException $expected) {
            $assert(str_contains($expected->getMessage(), 'numbered transfer'), 'Unexpected source-branch rejection.');
        }

        $invoiceView = file_get_contents($root.'/resources/views/purchasing/invoices.blade.php');
        $returnView = file_get_contents($root.'/resources/views/purchasing/returns.blade.php');
        $transferView = file_get_contents($root.'/resources/views/inventory/index.blade.php');
        $assert(str_contains($invoiceView, 'result-presentation="name-code"'), 'Invoice search presentation is not name plus item code.');
        $assert(str_contains($returnView, 'wire:click="openCreateModal" variant="primary" icon="plus">') && ! str_contains($returnView, 'wire:click="openCreateModal" variant="primary" icon="plus" :disabled'), 'Supplier return creation is still blocked at the entry button.');
        foreach (['data-line-available', 'data-line-cost', 'data-line-selling-price', 'quantity_requested'] as $needle) {
            $assert(str_contains($transferView, $needle), 'Transfer editor omitted '.$needle.'.');
        }
        $transferEditor = substr($transferView, (int) strpos($transferView, 'transfer-editor-heading'));
        $positions = array_map(fn (string $needle): int|false => strpos($transferEditor, $needle), ['x-product-line-lookup', 'data-line-available', 'data-line-cost', 'data-line-selling-price', 'quantity_requested']);
        $assert(! in_array(false, $positions, true) && $positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3] && $positions[3] < $positions[4], 'Transfer editor field order is incorrect.');

        echo "HOTFIX48_FOCUSED_VERIFICATION=PASS po=pass invoice=pass approval=pass inline_product=pass supplier_return=pass transfer=pass system_administrator=pass production_records=none\n";
    } finally {
        DB::rollBack();
        Auth::logout();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX48_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
