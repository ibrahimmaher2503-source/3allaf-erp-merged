<?php

declare(strict_types=1);

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Purchasing\Services\PurchaseOrderCalculator;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix42', 'Version mismatch.');

    $migration115 = file_get_contents($root.'/database/migrations/2026_09_16_000115_add_purchase_order_line_financial_snapshots.php');
    foreach ([
        "string('discount_type', 20)->nullable()",
        "decimal('discount_value', 19, 4)->default(0)",
        "decimal('discount_amount', 19, 4)->default(0)",
        "decimal('tax_rate', 9, 4)->default(0)",
        "string('tax_code', 50)->nullable()",
        "boolean('tax_inclusive')->default(false)",
        "decimal('tax_amount', 19, 4)->default(0)",
        "decimal('line_total', 19, 4)->default(0)",
        "'line_total' => DB::raw('subtotal')",
    ] as $needle) {
        $assert(str_contains($migration115, $needle), "Migration 000115 omitted {$needle}.");
    }
    $assert(! preg_match('/(?:index|foreign|constrained)\s*\(/', $migration115), 'Migration 000115 adds an index or foreign key.');
    $assert(str_contains($migration115, 'throw new \\RuntimeException'), 'Migration 000115 down is not fail closed.');

    $migration116 = file_get_contents($root.'/database/migrations/2026_09_16_000116_allow_null_item_code_for_product_drafts.php');
    foreach (["string('item_code', 50)->nullable()->change()", 'throw new \\RuntimeException'] as $needle) {
        $assert(str_contains($migration116, $needle), "Migration 000116 omitted {$needle}.");
    }
    $assert(! preg_match('/(?:index|foreign|drop|update|insert)\s*\(/i', $migration116), 'Migration 000116 changes data or indexes.');

    $orderCalculator = app(PurchaseOrderCalculator::class);
    $exclusive = $orderCalculator->calculateLine(['quantity_ordered' => 2, 'unit_cost' => 100, 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 14, 'tax_code' => 'VAT14', 'tax_inclusive' => false]);
    $inclusive = $orderCalculator->calculateLine(['quantity_ordered' => 2, 'unit_cost' => 100, 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 14, 'tax_code' => 'VAT14', 'tax_inclusive' => true]);
    $assert($exclusive['discount_amount'] === '20.0000' && $exclusive['tax_amount'] === '25.2000' && $exclusive['line_total'] === '205.2000', 'Exclusive PO calculation mismatch.');
    $assert($inclusive['discount_amount'] === '20.0000' && $inclusive['tax_amount'] === '22.1052' && $inclusive['line_total'] === '180.0000', 'Inclusive PO calculation mismatch.');
    $invoice = app(PurchaseInvoiceCalculator::class)->calculateLine(['quantity' => 2, 'unit_cost' => 100, 'discount_type' => 'percentage', 'discount_value' => 10, 'tax_rate' => 14, 'tax_inclusive' => true]);
    $assert($invoice['tax_amount'] === $inclusive['tax_amount'] && $invoice['line_total'] === $inclusive['line_total'], 'PO/invoice inclusive conversion mismatch.');

    $saveOrder = file_get_contents($root.'/app/Modules/Purchasing/Actions/SavePurchaseOrderAction.php');
    foreach (['legacyHeaderTax', 'ResolveProductTaxSnapshot', "status !== 'draft'", 'branch_id', 'lockForUpdate()'] as $needle) {
        $assert(str_contains($saveOrder, $needle), "PO integrity omitted {$needle}.");
    }
    $identity = file_get_contents($root.'/app/Modules/Catalog/Actions/ReserveProductIdentityAction.php');
    foreach (['supplierPrefix', 'name_en', 'str_pad((string) $number, 7', 'lockForUpdate()', 'preview('] as $needle) {
        $assert(str_contains($identity, $needle), "Product identity omitted {$needle}.");
    }
    $product = file_get_contents($root.'/app/Modules/Catalog/Models/Product.php');
    foreach (['blank($this->item_code)', "whereNotNull('products.item_code')", "products.item_code', ''", 'inactive draft'] as $needle) {
        $assert(str_contains($product, $needle), "Draft isolation omitted {$needle}.");
    }
    $lookup = file_get_contents($root.'/resources/views/components/product-line-lookup.blade.php');
    $assert(! str_contains($lookup, 'data-camera-'), 'Purchase lookup still renders camera controls.');
    $storeAction = file_get_contents($root.'/app/Modules/Platform/Actions/SaveStoreAction.php');
    $assert(! preg_match('/warehouse.{0,120}(?:prohibit|prevent|cannot).{0,120}(?:selling|outlet)|(?:selling|outlet).{0,120}(?:prohibit|prevent|cannot).{0,120}warehouse/is', $storeAction), 'A branch warehouse/outlet prohibition remains.');

    $changedMigrations = trim((string) shell_exec('git -C '.escapeshellarg($root).' diff --name-only e4ac8a0a98d788eabcb750c797307fee0aa2efef -- database/migrations 2>/dev/null'));
    $assert(explode("\n", $changedMigrations) === [
        'database/migrations/2026_09_16_000115_add_purchase_order_line_financial_snapshots.php',
        'database/migrations/2026_09_16_000116_allow_null_item_code_for_product_drafts.php',
    ], 'Migration scope is not exactly 000115 and 000116.');

    foreach (['ar', 'en', 'ar-EG'] as $locale) {
        $translations = json_decode(file_get_contents($root."/lang/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach (['Pending code', 'Save draft', 'Creating Branch', 'Tax and discounts are calculated and stored by the server.', 'Reports and Export Center'] as $key) {
            $assert(filled($translations[$key] ?? null), "{$locale} missing {$key}.");
        }
    }

    echo "HOTFIX42_FOCUSED_VERIFICATION=PASS database_mutation=none migrations_executed=none\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX42_FOCUSED_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
