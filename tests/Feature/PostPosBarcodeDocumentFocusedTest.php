<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\BarcodeDocumentSource;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PostPosBarcodeDocumentFocusedTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private Store $source;

    private Store $destination;

    private Store $otherDestination;

    private Store $selling;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
        $this->source = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $this->destination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $this->selling = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'selling']);
        $this->otherDestination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'type' => 'warehouse']);
        $this->product = Product::factory()->create(['status' => 'active', 'sale_price' => '140']);
        $this->product->barcodes()->create(['barcode' => 'QA-'.Str::random(16), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $this->actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actor->storeScopes()->create(['store_id' => $this->selling->id, 'status' => 'active']);
        $this->actingAs($this->actor)->withoutVite();
    }

    public function test_purchase_invoice_import_uses_receiving_quantity_and_destination_selling_price(): void
    {
        $this->destinationPrice('175');
        $supplier = Supplier::query()->create(['code' => 'QA-'.Str::random(8), 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'QA-PI-'.Str::random(8), 'supplier_id' => $supplier->id,
            'store_id' => $this->destination->id, 'status' => 'approved',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $invoice->lines()->create(['product_id' => $this->product->id, 'quantity' => '20', 'unit_cost' => '12']);

        $prepared = app(BarcodeDocumentSource::class)->prepare($this->actor, 'purchase_invoice', $invoice->invoice_number, $this->destination->id, $this->selling->id);
        self::assertSame([$this->product->id => 20], $prepared['quantities']);
        self::assertSame((string) app(EffectivePriceResolver::class)->resolve($this->product->id, $this->selling->id)?->amount, $prepared['prices'][$this->product->id]);
        self::assertSame('175.000', $prepared['prices'][$this->product->id]);
        self::assertNotSame('12', $prepared['prices'][$this->product->id]);
    }

    public function test_multi_destination_transfer_import_is_isolated_to_selected_allocation(): void
    {
        $this->destinationPrice('185');
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'QA-TR-'.Str::random(8), 'source_store_id' => $this->source->id,
            'destination_store_id' => $this->destination->id, 'status' => 'in_transit',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $line = $transfer->lines()->create(['product_id' => $this->product->id, 'quantity_requested' => 13, 'quantity_dispatched' => 13]);
        $line->allocations()->create(['destination_store_id' => $this->destination->id, 'quantity' => 8]);
        $line->allocations()->create(['destination_store_id' => $this->otherDestination->id, 'quantity' => 5]);

        $prepared = app(BarcodeDocumentSource::class)->prepare($this->actor, 'stock_transfer', $transfer->transfer_number, $this->destination->id, $this->selling->id);
        self::assertSame([$this->product->id => 8], $prepared['quantities']);
        self::assertSame((string) app(EffectivePriceResolver::class)->resolve($this->product->id, $this->selling->id)?->amount, $prepared['prices'][$this->product->id]);
        self::assertSame('185.000', $prepared['prices'][$this->product->id]);
        self::assertSame(2, $line->allocations()->count());
        self::assertSame('in_transit', $transfer->fresh()->status);

        $otherSelling = Store::factory()->create([
            'company_id' => $this->otherDestination->company_id,
            'branch_id' => $this->otherDestination->branch_id,
            'type' => 'selling',
        ]);
        $this->destinationPrice('205', $otherSelling);
        $otherPrepared = app(BarcodeDocumentSource::class)->prepare($this->actor, 'stock_transfer', $transfer->transfer_number, $this->otherDestination->id, $otherSelling->id);
        self::assertSame([$this->product->id => 5], $otherPrepared['quantities']);
        self::assertSame('205.000', $otherPrepared['prices'][$this->product->id]);
    }

    public function test_missing_selling_price_is_a_product_level_error(): void
    {
        $this->product->update(['sale_price' => null]);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'QA-TR-'.Str::random(8), 'source_store_id' => $this->source->id,
            'destination_store_id' => $this->destination->id, 'status' => 'in_transit',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $transfer->lines()->create(['product_id' => $this->product->id, 'quantity_requested' => 2, 'quantity_dispatched' => 2]);

        try {
            app(BarcodeDocumentSource::class)->prepare($this->actor, 'stock_transfer', $transfer->transfer_number, $this->destination->id, $this->selling->id);
            self::fail('A missing destination selling price must reject label preparation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('number', $exception->errors());
            self::assertStringContainsString($this->product->item_code, $exception->errors()['number'][0]);
        }
    }

    private function destinationPrice(string $amount, ?Store $store = null): void
    {
        $store ??= $this->selling;
        $list = PriceList::query()->create([
            'company_id' => $store->company_id, 'code' => 'QA-'.Str::random(8),
            'list_number' => 100000 + $store->id, 'name_ar' => 'سعر الفرع',
            'name_en' => 'Destination price', 'percentage_increase' => '0', 'status' => 'active',
        ]);
        $version = $list->versions()->create([
            'version' => 1, 'state' => 'approved', 'source_type' => 'qa_fixture',
            'approved_by' => $this->actor->id, 'approved_at' => now(),
        ]);
        $version->lines()->create([
            'product_id' => $this->product->id, 'store_id' => $store->id,
            'branch_id' => $store->branch_id, 'amount' => $amount,
            'reference_amount' => '140',
        ]);
    }
}
