<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Models\SupplierPayableEntry;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

final class PurchasingSupplierReturnsV2Test extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    private Supplier $supplier;

    private Product $product;

    private SupplierReturnReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
        request()->setLaravelSession(app('session')->driver());
        $this->supplier = Supplier::query()->create(['code' => 'V2-SUP', 'name_ar' => 'مورد الاختبار', 'name_en' => 'V2 supplier', 'status' => 'active']);
        $this->product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'average_cost' => '115', 'sale_price' => '150']);
        $this->product->barcodes()->create(['barcode' => 'V2-CODE-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $this->product->id]);
        $this->reason = SupplierReturnReason::query()->create(['code' => 'V2-DAMAGE', 'label_ar' => 'تالف', 'label_en' => 'Damaged', 'is_active' => true]);
    }

    public function test_distribution_keeps_destinations_independent_and_preserves_price(): void
    {
        $second = Store::factory()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => $this->fixture['branch']->id, 'type' => 'warehouse', 'status' => 'active']);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $this->fixture['receiving']->id, 'invoice_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => '4', 'unit_cost' => '100', 'base_consumer_price' => '150']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $lineId = $invoice->lines->firstOrFail()->id;
        $sellingId = $this->fixture['store']->id;
        $priceListCount = PriceList::query()->count();
        $component = Livewire::test('purchasing::invoices')->call('openDistribution', $invoice->id)
            ->set('selectedDestinationIds', [(string) $sellingId, (string) $second->id])
            ->set('distributionMatrix.'.$lineId.'.'.$sellingId, '2')
            ->assertSet('distributionMatrix.'.$lineId.'.'.$sellingId, '2')
            ->assertSet('distributionMatrix.'.$lineId.'.'.$second->id, null);
        $component->set('distributionMatrix.'.$lineId.'.'.$second->id, '1')
            ->assertSet('distributionMatrix.'.$lineId.'.'.$sellingId, '2')
            ->assertSet('distributionMatrix.'.$lineId.'.'.$second->id, '1');
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$lineId => [$sellingId => '2', $second->id => '']], $invoice->lock_version);
        self::assertSame('2.000000', $invoice->distributions->first()->quantity);
        self::assertCount(1, $invoice->distributions);
        $savedPrice = $invoice->distributions->first()->effective_selling_price;
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$lineId => [$sellingId => '2', $second->id => '1']], $invoice->lock_version);
        self::assertSame('2.000000', $invoice->distributions->firstWhere('destination_store_id', $sellingId)->quantity);
        self::assertSame('1.000000', $invoice->distributions->firstWhere('destination_store_id', $second->id)->quantity);
        self::assertSame($savedPrice, $invoice->distributions->firstWhere('destination_store_id', $sellingId)->effective_selling_price);
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$lineId => [$sellingId => '2', $second->id => '2']], $invoice->lock_version);
        self::assertCount(2, $invoice->distributions);
        try {
            app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$lineId => [$sellingId => '3', $second->id => '2']], $invoice->lock_version);
            self::fail('Over-allocation was accepted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('distributionMatrix.'.$lineId, $exception->errors());
        }
        self::assertSame('150.00', $this->product->fresh()->sale_price);
        self::assertSame($priceListCount, PriceList::query()->count());
    }

    public function test_invoice_free_return_uses_current_cost_and_allows_negative_payable(): void
    {
        StockBalance::query()->create(['product_id' => $this->product->id, 'store_id' => $this->fixture['receiving']->id, 'on_hand' => 5, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 115, 'total_value' => 575, 'version' => 0]);
        $return = app(CreatePurchaseReturnDraftAction::class)->executeWithoutInvoice($this->supplier->id, $this->fixture['receiving']->id, $this->reason->id, [['product_id' => $this->product->id, 'quantity' => '3']], 'v2-no-invoice');
        self::assertNull($return->purchase_invoice_id);
        self::assertSame('115.0000', $return->lines->first()->unit_cost);
        self::assertSame('product_current_cost', $return->lines->first()->cost_source);
        self::assertSame('345.0000', $return->total_amount);
        $this->product->update(['average_cost' => 120]);
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        $return = app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        self::assertSame('2.000000', StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $return->store_id)->firstOrFail()->on_hand);
        self::assertSame('-345.0000', (string) SupplierPayableEntry::query()->where('supplier_id', $this->supplier->id)->sum('amount'));
        self::assertSame(1, StockMovement::query()->where('source_id', $return->id)->where('movement_type', 'purchase_return')->count());
        self::assertSame(1, SupplierPayableEntry::query()->where('source_type', 'supplier_return')->where('source_id', $return->id)->count());
        self::assertTrue(AuditLog::query()->where('event', 'approve_supplier_return')->where('source_id', (string) $return->id)->exists());
        app(ApprovePurchaseReturnAction::class)->execute($return->id);
        self::assertSame(1, SupplierPayableEntry::query()->where('source_type', 'supplier_return')->where('source_id', $return->id)->count());
    }

    public function test_invoice_linked_return_uses_historical_invoice_cost_and_posts_one_debit(): void
    {
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $this->fixture['receiving']->id, 'invoice_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => '4', 'unit_cost' => '100', 'base_consumer_price' => '150']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        self::assertSame('400.0000', SupplierPayableEntry::query()->where('source_type', 'purchase_invoice')->where('source_id', $invoice->id)->firstOrFail()->amount);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame(1, SupplierPayableEntry::query()->where('source_type', 'purchase_invoice')->where('source_id', $invoice->id)->count());
        $line = $invoice->lines->firstOrFail();
        $this->product->update(['average_cost' => 115]);
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $this->reason->id, [['purchase_invoice_line_id' => $line->id, 'quantity' => '2']], 'v2-linked');
        self::assertSame('100.0000', $return->lines->first()->unit_cost);
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        $return = app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        self::assertSame('200.0000', $return->total_amount);
        self::assertSame('200.0000', (string) SupplierPayableEntry::query()->where('supplier_id', $this->supplier->id)->sum('amount'));
        self::assertSame('2.000000', StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $return->store_id)->firstOrFail()->on_hand);
        app(ApprovePurchaseReturnAction::class)->execute($return->id);
        self::assertSame(1, SupplierPayableEntry::query()->where('source_type', 'supplier_return')->where('source_id', $return->id)->count());
    }

    public function test_no_invoice_validation_rejects_missing_relation_and_inactive_warehouse(): void
    {
        StockBalance::query()->create(['product_id' => $this->product->id, 'store_id' => $this->fixture['receiving']->id, 'on_hand' => 2, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 115, 'total_value' => 230, 'version' => 0]);
        $create = app(CreatePurchaseReturnDraftAction::class);
        $rejects = function (array $lines) use ($create): void {
            try {
                $create->executeWithoutInvoice($this->supplier->id, $this->fixture['receiving']->id, $this->reason->id, $lines);
                self::fail('Invalid supplier return was accepted.');
            } catch (InvalidArgumentException|ValidationException) {
                self::assertTrue(true);
            }
        };
        $rejects([['product_id' => $this->product->id, 'quantity' => '0']]);
        $rejects([['product_id' => $this->product->id, 'quantity' => '3']]);
        ProductSupplier::query()->where('supplier_id', $this->supplier->id)->where('product_id', $this->product->id)->delete();
        $rejects([['product_id' => $this->product->id, 'quantity' => '1']]);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $this->product->id]);
        $this->reason->update(['is_active' => false]);
        $rejects([['product_id' => $this->product->id, 'quantity' => '1']]);
        $this->reason->update(['is_active' => true]);
        $this->fixture['receiving']->update(['status' => 'inactive']);
        $rejects([['product_id' => $this->product->id, 'quantity' => '1']]);
    }

    public function test_invoice_linked_return_can_post_from_another_active_store_in_the_same_company(): void
    {
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $this->fixture['receiving']->id, 'invoice_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => '4', 'unit_cost' => '100', 'base_consumer_price' => '150']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $sourceStore = Store::factory()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => $this->fixture['branch']->id, 'type' => 'warehouse', 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $this->product->id, 'store_id' => $sourceStore->id, 'on_hand' => 2, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 100, 'total_value' => 200, 'version' => 0]);
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $this->reason->id, [['purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id, 'quantity' => '2']], 'v2-other-source', null, $sourceStore->id);
        self::assertSame($sourceStore->id, $return->store_id);
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        $return = app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        self::assertSame('0.000000', StockBalance::query()->where('store_id', $sourceStore->id)->where('product_id', $this->product->id)->firstOrFail()->on_hand);
        self::assertSame('4.000000', StockBalance::query()->where('store_id', $invoice->store_id)->where('product_id', $this->product->id)->firstOrFail()->on_hand);
        self::assertSame('-200.0000', SupplierPayableEntry::query()->where('source_type', 'supplier_return')->where('source_id', $return->id)->firstOrFail()->amount);
    }

    public function test_product_first_requires_supplier_choice_when_multiple_relationships_exist(): void
    {
        StockBalance::query()->create(['product_id' => $this->product->id, 'store_id' => $this->fixture['receiving']->id, 'on_hand' => 2, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 115, 'total_value' => 230, 'version' => 0]);
        $otherSupplier = Supplier::query()->create(['code' => 'V2-SECOND', 'name_ar' => 'مورد ثانٍ', 'name_en' => 'Second supplier', 'status' => 'active']);
        ProductSupplier::query()->create(['supplier_id' => $otherSupplier->id, 'product_id' => $this->product->id]);
        Livewire::test('purchasing::returns')->set('selectedStoreId', $this->fixture['receiving']->id)
            ->call('selectReturnProduct', $this->product->id)->assertHasErrors(['selectedSupplierId']);
        ProductSupplier::query()->where('supplier_id', $otherSupplier->id)->delete();
        Livewire::test('purchasing::returns')->set('selectedStoreId', $this->fixture['receiving']->id)
            ->call('selectReturnProduct', $this->product->id)
            ->assertSet('selectedSupplierId', $this->supplier->id)
            ->assertCount('returnLines', 1);
    }
}
