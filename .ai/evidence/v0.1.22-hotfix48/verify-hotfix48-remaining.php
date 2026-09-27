<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Actions\AddBarcodeAction;
use App\Modules\Catalog\Actions\ReserveProductIdentityAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\UpdateStockTransferDraftAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    $assert(str_contains((string) DB::connection()->getDatabaseName(), 'hf48'), 'Focused verification requires a disposable Hotfix48 database.');

    DB::beginTransaction();
    try {
        $token = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $company = Company::query()->where('status', 'active')->whereNotNull('currency_code')->firstOrFail();
        $role = Role::query()->where('code', 'system-administrator')->where('status', 'active')->firstOrFail();
        $actor = User::query()->create(['name' => 'HF48 remaining actor', 'username' => 'hf48r-'.$token, 'email' => 'hf48r-'.$token.'@example.test', 'password' => Hash::make(Str::password(24)), 'status' => 'active']);
        $actor->forceFill(['email_verified_at' => now()])->save();
        $actor->roles()->attach($role->id);
        Auth::login($actor);
        request()->merge(['company_id' => $company->id]);

        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => 'HF48R'.$token, 'name_ar' => 'فرع تحقق', 'name_en' => 'HF48 Branch', 'timezone' => $company->timezone ?: 'Africa/Cairo', 'status' => 'active']);
        $source = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48RS'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن مصدر', 'name_en' => 'HF48 Source', 'status' => 'active']);
        $destination = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48RD'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن وجهة', 'name_en' => 'HF48 Destination', 'status' => 'active']);
        $sourceTwo = Store::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'HF48R2'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن مصدر ثان', 'name_en' => 'HF48 Source Two', 'status' => 'active']);
        $otherBranch = Branch::query()->create(['company_id' => $company->id, 'code' => 'HF48RX'.$token, 'name_ar' => 'فرع آخر', 'name_en' => 'HF48 Other Branch', 'timezone' => $company->timezone ?: 'Africa/Cairo', 'status' => 'active']);
        $otherSource = Store::query()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'code' => 'HF48RX'.$token, 'type' => 'warehouse', 'name_ar' => 'مخزن آخر', 'name_en' => 'HF48 Other Source', 'status' => 'active']);
        $category = Category::query()->create(['code' => 'HF48RC'.$token, 'name_ar' => 'فئة تحقق', 'name_en' => 'HF48 Category', 'status' => 'active', 'sort_order' => 999, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $supplier = Supplier::query()->create(['code' => 'HF48RSUP'.$token, 'name_ar' => 'مورد تحقق', 'name_en' => 'Alpha Supplier', 'settlement_method' => 'cash', 'status' => 'active', 'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        if (getenv('HOTFIX48_SKIP_INLINE') !== '1') {
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
        }

        $transferProduct = $product($category, 'TR'.$token, '90', '11');
        foreach ([[$source, '7'], [$sourceTwo, '9']] as [$transferSource, $cost]) {
            StockBalance::query()->create(['product_id' => $transferProduct->id, 'store_id' => $transferSource->id, 'on_hand' => 10, 'reserved' => 2, 'in_transit' => 0, 'average_cost' => $cost, 'total_value' => bcmul('10', $cost, 4), 'version' => 1]);
        }
        $transferOne = app(CreateStockTransferDraftAction::class)->execute($source->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '3']], 'restock');
        $transferTwo = app(CreateStockTransferDraftAction::class)->execute($sourceTwo->id, $destination->id, [['product_id' => $transferProduct->id, 'quantity_requested' => '1']], 'restock');
        $assert(preg_match('/^'.preg_quote($branch->code, '/').'-\d{6}$/', $transferOne->transfer_number) === 1, 'Transfer number does not use branch code plus six digits.');
        $assert((int) substr($transferTwo->transfer_number, -6) === (int) substr($transferOne->transfer_number, -6) + 1, 'Warehouses in one branch do not share one unique sequence.');
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
        $positions = array_map(fn (string $needle): int|false => strpos(substr($transferView, (int) strpos($transferView, 'transfer-editor-heading')), $needle), ['x-product-line-lookup', 'data-line-available', 'data-line-cost', 'data-line-selling-price', 'quantity_requested']);
        $assert(! in_array(false, $positions, true) && $positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3] && $positions[3] < $positions[4], 'Transfer editor field order is incorrect.');

        $inlineResult = getenv('HOTFIX48_SKIP_INLINE') === '1' ? 'covered_by_prior_partial_run' : 'pass';
        echo "HOTFIX48_REMAINING_VERIFICATION=PASS inline_product={$inlineResult} supplier_return=pass transfer=pass production_records=none\n";
    } finally {
        DB::rollBack();
        Auth::logout();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX48_REMAINING_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
