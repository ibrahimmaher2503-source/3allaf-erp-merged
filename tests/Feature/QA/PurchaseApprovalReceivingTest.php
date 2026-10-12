<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ProductPricingReadiness;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseReceivingAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Support\ProductQuantity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

final class PurchaseApprovalReceivingTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
        $this->supplier = Supplier::query()->create(['code' => 'SUP-9123', 'name_ar' => 'مورد اختبار', 'name_en' => 'QA supplier', 'status' => 'active']);
        $this->product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'name_ar' => 'منتج الاستلام', 'sale_price' => 25, 'average_cost' => 10, 'model_number' => null]);
        $this->product->barcodes()->create(['barcode' => 'QA-RECEIVING-CODE', 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        request()->setLaravelSession(app('session')->driver());
    }

    private function draft(string $quantity = '1000', string $cost = '12', string $price = '30'): PurchaseInvoice
    {
        return app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $this->fixture['receiving']->id, 'invoice_date' => '2026-10-12'],
            [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'base_consumer_price' => $price]],
        );
    }

    private function submit(PurchaseInvoice $invoice): PurchaseInvoice
    {
        return app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
    }

    public function test_direct_approval_posts_only_accepted_stock_and_current_prices_once(): void
    {
        $invoice = $this->draft();
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        self::assertSame('25.00', $this->product->fresh()->sale_price);
        self::assertSame(0, StockMovement::query()->count());
        $invoice = $this->submit($invoice);
        $line = $invoice->lines->first();
        $invoice = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '900'], $invoice->lock_version);
        self::assertSame('1000.000000', $line->fresh()->original_quantity);
        self::assertSame('900.000000', $line->fresh()->quantity);
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        $version = $invoice->lock_version;
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $version);
        self::assertSame('approved', $invoice->status);
        self::assertSame('900.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame('12.00', $this->product->fresh()->average_cost);
        self::assertSame('30.00', $this->product->fresh()->sale_price);
        self::assertSame('100.000000', $line->fresh()->shortageQuantity());
        self::assertSame(1, PurchaseInvoiceLine::query()->withShortage()->count());
        self::assertSame(0, StockTransfer::query()->count());
        self::assertSame(0, DB::table('purchase_returns')->count());
        $auditCount = AuditLog::query()->count();
        $snapshot = $line->fresh()->getAttributes();
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $version);
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame($auditCount, AuditLog::query()->count());
        self::assertSame($snapshot, $line->fresh()->getAttributes());
        $this->get(route('purchasing.quantity-differences'))->assertOk()->assertSee('100')->assertSee($invoice->invoice_number);
        $export = $this->get(route('purchasing.quantity-differences', ['export' => 'csv']))->assertOk()->streamedContent();
        self::assertStringContainsString('1000.000000,900.000000,100.000000', $export);
        $this->get(route('purchasing.invoices.print', $invoice))->assertOk()->assertSee('Original quantity')->assertSee('Accepted quantity');
    }

    public function test_partial_optional_distribution_and_blank_cells_preserve_receiving_stock(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $blankStore = Store::factory()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => $this->fixture['branch']->id, 'type' => 'warehouse']);
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$line->id => [$this->fixture['store']->id => '3', $blankStore->id => '']], $invoice->lock_version);
        self::assertCount(1, $invoice->distributions);
        self::assertSame('30.000', $invoice->distributions->first()->effective_selling_price);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        self::assertSame('7.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame(1, StockTransfer::query()->count());
        self::assertSame('3.000000', StockBalance::query()->where('store_id', $this->fixture['store']->id)->firstOrFail()->in_transit);
        self::assertSame(0, PurchaseInvoiceLine::query()->withShortage()->count());
    }

    public function test_invalid_distribution_is_visible_in_ui_and_rolls_back(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $component = Livewire::test('purchasing::invoices')->call('openDistribution', $invoice->id)
            ->set('selectedDestinationIds', [(string) $this->fixture['store']->id])
            ->set('distributionMatrix', [$line->id => [$this->fixture['store']->id => '11']])
            ->call('saveDistribution')->assertHasErrors(['distributionMatrix.'.$line->id]);
        self::assertStringContainsString('Distribution cannot exceed the accepted quantity.', $component->html());
        self::assertSame(0, $invoice->distributions()->count());
        self::assertSame($invoice->lock_version, $invoice->fresh()->lock_version);
        $component->set('distributionMatrix', [$line->id => [$this->fixture['store']->id => '']])->call('saveDistribution')->assertHasNoErrors();
    }

    public function test_draft_edit_preserves_original_and_later_approval_preserves_history(): void
    {
        $invoice = $this->draft();
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $invoice->store_id],
            [['product_id' => $this->product->id, 'quantity' => '900', 'unit_cost' => '12', 'base_consumer_price' => '30', 'original_quantity' => '900']], $invoice->id, $invoice->lock_version);
        self::assertSame('1000.000000', $invoice->lines->first()->original_quantity);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($this->submit($invoice)->id);
        $lineSnapshot = $invoice->lines->first()->getAttributes();
        $movementSnapshot = StockMovement::query()->firstOrFail()->getAttributes();
        $later = $this->submit($this->draft('5', '18', '45'));
        app(ApprovePurchaseInvoiceAction::class)->execute($later->id);
        self::assertSame('18.00', $this->product->fresh()->average_cost);
        self::assertSame('45.00', $this->product->fresh()->sale_price);
        self::assertSame($lineSnapshot, $invoice->lines->first()->fresh()->getAttributes());
        self::assertSame($movementSnapshot, StockMovement::query()->findOrFail($movementSnapshot['id'])->getAttributes());
        self::assertSame(1, PurchaseInvoiceLine::query()->withShortage()->count());
        $this->expectException(\LogicException::class);
        $invoice->lines->first()->update(['original_quantity' => '900']);
    }

    public function test_livewire_received_quantity_is_saved_atomically_with_approval(): void
    {
        $invoice = $this->submit($this->draft());
        $line = $invoice->lines->first();
        Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id)
            ->set('receivedQuantities.'.$line->id, '900')->call('approveInvoice', $invoice->id)->assertHasNoErrors()->assertSet('showReceiving', false);
        self::assertSame('approved', $invoice->fresh()->status);
        self::assertSame('900.000000', $line->fresh()->quantity_received);
    }

    public function test_unauthorized_approval_and_report_are_denied(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $this->actingAs($this->fixture['user']);
        $this->get(route('purchasing.quantity-differences'))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
    }

    public function test_zero_received_has_shortage_without_inventory_movement(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $invoice = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '0'], $invoice->lock_version);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame('10.000000', $line->fresh()->shortageQuantity());
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        self::assertSame('25.00', $this->product->fresh()->sale_price);
    }

    public function test_model_optional_in_final_draft_edit_readiness_and_product_form(): void
    {
        $data = ['name_ar' => 'منتج بدون موديل', 'name_en' => 'No model', 'category_id' => $this->fixture['category']->id,
            'preferred_supplier_id' => $this->supplier->id, 'barcode_registration_type' => 'local', 'product_type' => 'service', 'sale_price' => '25', 'status' => 'active'];
        $save = app(SaveProductAction::class);
        $product = $save->execute($data);
        self::assertNull($product->model_number);
        self::assertStringStartsWith('9123-', $product->item_code);
        self::assertFalse($product->hasIncompleteCard());
        self::assertTrue(Product::query()->completeCard()->whereKey($product->id)->exists());
        self::assertFalse(Product::query()->incompleteCard()->whereKey($product->id)->exists());
        self::assertTrue(app(ProductPricingReadiness::class)->assessProducts([$product])['product_cards_complete']);
        $product = $save->execute([...$data, 'model_number' => 'KEPT-123'], $product->id, $product->lock_version);
        $product = $save->execute($data, $product->id, $product->lock_version);
        self::assertSame('KEPT-123', $product->model_number);
        $draft = $save->execute([...$data, '_save_as_draft' => true]);
        self::assertNull($draft->model_number);
        $final = $save->execute($data, $draft->id, $draft->lock_version);
        self::assertNotNull($final->item_code);
        Livewire::test('catalog::product-form', ['product' => $final])->call('save')->assertHasNoErrors();
        self::assertNull($final->fresh()->model_number);
    }

    public function test_workbook_import_allows_multiple_blank_models_without_fabricating_identity(): void
    {
        $path = storage_path('app/model-optional.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Instructions');
        foreach ([['Template'], ['schema', 'toyjoy.product-card.v2'], ['version', '2'], ['company', $this->fixture['company']->code.' QA']] as $metadata) {
            $writer->addRow(Row::fromValues($metadata));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('Data Entry');
        $headers = ImportProductCardsWorkbookAction::HEADERS;
        $writer->addRow(Row::fromValues($headers));
        foreach (['مستورد أول', 'مستورد ثان'] as $name) {
            $data = ['barcode_registration_type' => 'local', 'supplier_code' => $this->supplier->code, 'name_ar' => $name,
                'base_consumer_price' => '25', 'category_code' => $this->fixture['category']->code, 'product_type' => 'standard', 'status' => 'active'];
            $writer->addRow(Row::fromValues(array_map(fn ($key) => $data[$key] ?? '', $headers)));
        }
        $writer->close();
        $result = app(ImportProductCardsWorkbookAction::class)->execute($path);
        self::assertSame(2, $result['added'], json_encode($result['rejections']));
        self::assertSame(0, $result['rejected']);
        $products = Product::query()->whereIn('name_ar', ['مستورد أول', 'مستورد ثان'])->get();
        self::assertCount(2, $products);
        foreach ($products as $product) {
            self::assertNull($product->model_number);
            self::assertStringStartsWith('9123-', $product->item_code);
        }
        self::assertNotSame($products[0]->item_code, $products[1]->item_code);
    }

    public function test_stale_review_and_cross_company_distribution_cannot_write(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        try {
            app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '9'], $invoice->lock_version - 1);
            self::fail('Stale review was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('another session', $exception->getMessage());
        }
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->create(['company_id' => $otherCompany->id]);
        $other = Store::factory()->create(['type' => 'warehouse', 'company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id]);
        try {
            app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$line->id => [$other->id => '1']], $invoice->lock_version);
            self::fail('Cross-company distribution was accepted.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('company and store scope', $exception->getMessage());
        }
        self::assertSame('10.000000', $line->fresh()->quantity);
        self::assertSame(0, $invoice->distributions()->count());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_baseline_blank_distribution_cell_failure_is_meaningful_validation(): void
    {
        try {
            ProductQuantity::normalize('', 'distribution.1.1', true);
            self::fail('Baseline normalization unexpectedly accepted a blank cell.');
        } catch (ValidationException $exception) {
            self::assertSame('Product quantities must be whole numbers.', $exception->errors()['distribution.1.1'][0]);
        }
        // The old action sent blank cells here and UserSafeError masked this exception.
        self::assertStringContainsString('Unable to complete the request', file_get_contents(app_path('Support/UserSafeError.php')));
    }
}
