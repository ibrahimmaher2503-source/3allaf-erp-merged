<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Support\ProductBarcodePolicy;
use PHPUnit\Framework\TestCase;

final class V021PurchasingSearchBarcodesHotfixTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_scanner_remains_exact_immediate_and_focus_preserving(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        $scanner = substr($view, strpos($view, 'public function addItemEntry'), strpos($view, 'public function selectSearchProduct') - strpos($view, 'public function addItemEntry'));
        self::assertStringContainsString("where('item_code', \$lookup)", $scanner);
        self::assertStringContainsString("where('barcode', \$lookup)", $scanner);
        self::assertStringNotContainsString("'like'", $scanner);
        self::assertStringContainsString('wire:keydown.enter.prevent="addItemEntry"', $view);
        self::assertStringContainsString('openQuickProduct($lookup)', $scanner);
        self::assertGreaterThanOrEqual(3, substr_count($view, "dispatch('invoice-scanner-focus')"));
        self::assertStringContainsString("dispatch('invoice-line-focus'", $view);
    }

    public function test_search_is_ranked_bounded_partial_and_company_context_scoped(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('mb_strlen($term) < 3', $view);
        self::assertStringContainsString("visibleTo(\$actor)", $view);
        self::assertStringContainsString("value('company_id')", $view);
        self::assertStringContainsString("\$contained = '%'.\$escaped.'%'", $view);
        self::assertStringContainsString("item_code', 'like', \$contained", $view);
        self::assertStringContainsString("barcode', 'like', \$contained", $view);
        self::assertStringContainsString('CASE WHEN item_code = ?', $view);
        self::assertStringContainsString('THEN 0 WHEN item_code LIKE ?', $view);
        self::assertStringContainsString('ELSE 2 END', $view);
        self::assertStringContainsString('->limit(20)->get()', $view);
        self::assertStringNotContainsString('limit(1000)', $view);
    }

    public function test_debounce_enter_loading_and_stale_response_contract(): void
    {
        $view = $this->source('resources/views/purchasing/invoices.blade.php');
        self::assertStringContainsString('setTimeout(()=>$wire.searchProductsNow(value),175)', $view);
        self::assertStringContainsString('now(value){clearTimeout(this.timer);$wire.searchProductsNow(value)}', $view);
        self::assertStringContainsString('wire:loading.flex wire:target="searchProductsNow"', $view);
        self::assertStringContainsString('wire:loading.remove wire:target="searchProductsNow"', $view);
        self::assertStringContainsString('wire:key="product-search-{{$productSearchRequest}}"', $view);
    }

    public function test_uat_uses_two_products_and_real_barcode_services_only(): void
    {
        $service = $this->source('app/Modules/Platform/Services/UatDataset.php');
        foreach (['TEST-ITEM-INT-','TEST-ITEM-LOC-',"'barcode_registration_type'=>'international'","'barcode_registration_type'=>'local'",'AddBarcodeAction::class','addSupplierBarcode','allocateLocalBarcode',"'TEST-SUP-1234-'", "mark(\$batch,'barcode_sequences'"] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('TESTLOCAL', $service);
        self::assertStringContainsString("getenv('RAJEH_UAT_ALLOW_PRODUCTION')!=='CONFIRMED'", $service);
        self::assertStringContainsString("firstOrCreate(['uat_batch_id'=>\$batch->id", $service);
    }

    public function test_gtin_and_local_barcode_formats_are_valid(): void
    {
        $policy = new ProductBarcodePolicy();
        self::assertSame('6220000000013', $policy->international('6220000000013'));
        self::assertSame('01234000001', $policy->local('1234', 1));
    }

    public function test_product_card_does_not_expose_internal_blade_state(): void
    {
        $view = $this->source('resources/views/catalog/product-form.blade.php');
        self::assertStringNotContainsString("                    (\$isEditing &&", $view);
        self::assertStringContainsString("@if (\$isEditing && \$productForm['product_type'] === 'digital')", $view);
        self::assertStringContainsString("__('Digital product (legacy)')", $view);
    }
}
