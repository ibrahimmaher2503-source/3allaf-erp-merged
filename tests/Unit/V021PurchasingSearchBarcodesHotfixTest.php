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
        $lookup = $this->source('app/Http/Controllers/TransactionProductSearchController.php');
        $component = $this->source('resources/views/components/product-line-lookup.blade.php');
        $keyboard = $this->source('resources/js/keyboard-product-lines.js');

        self::assertStringContainsString('<x-product-line-lookup wire:model="itemEntry"', $view);
        self::assertStringContainsString('x-on:product-selected="$wire.selectSearchProduct($event.detail.id)"', $view);
        self::assertStringContainsString('x-on:product-lookup-empty="$wire.openQuickProduct($event.detail.term)"', $view);
        self::assertStringContainsString("barcodes.barcode = ?", $lookup);
        self::assertStringContainsString("'exact' => \$identifiers->contains(mb_strtolower(\$term))", $lookup);
        self::assertStringContainsString('data-search-url="{{ route(\'transaction-products.search\') }}"', $component);
        self::assertStringContainsString('if (exact.length === 1', $keyboard);
        self::assertStringContainsString('this.select(exact[0]);', $keyboard);
        self::assertStringContainsString("if (event.key !== 'Enter') return;", $keyboard);
        self::assertStringContainsString('event.preventDefault();', $keyboard);
        self::assertStringContainsString('event.stopPropagation();', $keyboard);
        self::assertStringContainsString("requestAnimationFrame(() => this.root.closest('[data-product-line]')?.querySelector('[data-line-quantity]')?.focus())", $keyboard);
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
        $component = $this->source('resources/views/components/product-line-lookup.blade.php');
        $keyboard = $this->source('resources/js/keyboard-product-lines.js');
        $app = $this->source('resources/js/app.js');

        self::assertStringContainsString("import './keyboard-product-lines';", $app);
        self::assertStringContainsString('<x-product-line-lookup wire:model="itemEntry"', $view);
        self::assertStringContainsString('role="combobox"', $component);
        self::assertStringContainsString('data-product-results', $component);
        self::assertStringContainsString('clearTimeout(this.timer);', $keyboard);
        self::assertStringContainsString('this.timer = setTimeout(() => this.search(term), 140);', $keyboard);
        self::assertStringContainsString('async search(term, commitExact = false)', $keyboard);
        self::assertStringContainsString('this.controller?.abort();', $keyboard);
        self::assertStringContainsString('this.controller = new AbortController();', $keyboard);
        self::assertStringContainsString('signal: this.controller.signal', $keyboard);
        self::assertStringContainsString('const response = await fetch(', $keyboard);
        self::assertStringContainsString("if (error.name !== 'AbortError') this.close();", $keyboard);
        self::assertStringContainsString("if (event.key !== 'Enter' || event.target.matches('[data-product-search], textarea, button')) return;", $keyboard);
        self::assertStringContainsString('event.preventDefault();', $keyboard);
        self::assertStringContainsString('event.stopPropagation();', $keyboard);
        self::assertStringContainsString("document.querySelectorAll('[data-product-lookup]').forEach((root) => new ProductLookup(root));", $keyboard);
        self::assertStringContainsString("document.querySelectorAll('[data-product-line-editor]').forEach(initializeEditor);", $keyboard);
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
