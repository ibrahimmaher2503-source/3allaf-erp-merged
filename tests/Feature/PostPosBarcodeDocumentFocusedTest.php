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
use App\Modules\Pricing\Services\BarcodeLabelService;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\PriceListResolver;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
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
        PriceList::query()->create(['company_id' => $company->id, 'list_number' => 0, 'code' => 'QA-BASE-'.Str::random(8), 'name_ar' => 'السعر الأساسي', 'name_en' => 'Base price', 'percentage_increase' => '0', 'status' => 'active']);
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

    public function test_invoice_label_mode_infers_receiving_warehouse_and_selling_outlet(): void
    {
        $this->destinationPrice('175');
        $supplier = Supplier::query()->create(['code' => 'QA-'.Str::random(8), 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'QA-PI-'.Str::random(8), 'supplier_id' => $supplier->id,
            'store_id' => $this->destination->id, 'status' => 'approved', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $invoice->lines()->create(['product_id' => $this->product->id, 'quantity' => '3', 'unit_cost' => '12']);

        $prepared = app(BarcodeDocumentSource::class)->prepare($this->actor, 'purchase_invoice', $invoice->invoice_number);
        self::assertSame($this->destination->id, $prepared['destination']->id);
        self::assertSame($this->selling->id, $prepared['selling_store_id']);
        self::assertSame([$this->product->id => 3], $prepared['quantities']);
        self::assertSame('175.000', $prepared['prices'][$this->product->id]);
        $this->get(route('pricing.labels', ['source' => 'purchase_invoice', 'number' => $invoice->invoice_number]))
            ->assertOk()->assertSee('Purchase Invoice items')->assertSee('3');
    }

    public function test_label_preview_and_print_resolve_the_selected_store_price_each_time(): void
    {
        Gate::before(static fn (): bool => true);
        $otherSelling = Store::factory()->create([
            'company_id' => $this->selling->company_id,
            'branch_id' => $this->otherDestination->branch_id,
            'type' => 'selling',
        ]);
        $this->product->update(['sale_price' => '400']);
        $this->destinationPrice('420', $this->selling);
        $this->destinationPrice('520', $otherSelling);
        $service = app(BarcodeLabelService::class);
        $rows = [['product_id' => $this->product->id, 'barcode_id' => $this->product->barcodes->first()->id, 'copies' => 1]];
        $profile = $service->profile($this->actor);

        self::assertSame('420.000', (string) $service->compose($this->actor, $rows, $profile, $this->selling->id, [$this->product->id => '999'])['labels'][0]['price']);
        self::assertSame('520.000', (string) $service->compose($this->actor, $rows, $profile, $otherSelling->id)['labels'][0]['price']);
        $this->actingAs($this->actor)->get(route('pricing.labels.prices', ['store_id' => $otherSelling->id, 'product_ids' => [$this->product->id]]))
            ->assertOk()->assertJsonPath('prices.'.$this->product->id, '520.000');
        $fallbackSelling = Store::factory()->create([
            'company_id' => $this->selling->company_id,
            'branch_id' => $this->destination->branch_id,
            'type' => 'selling',
        ]);
        self::assertSame('400.00', (string) $service->compose($this->actor, $rows, $profile, $fallbackSelling->id)['labels'][0]['price']);
    }

    public function test_label_uses_manufacturer_barcode_before_internal_barcode(): void
    {
        Gate::before(static fn (): bool => true);
        $local = $this->product->barcodes->first();
        $local->update(['source' => 'local']);
        $manufacturer = $this->product->barcodes()->create([
            'barcode' => '6224008875178', 'source' => 'international', 'status' => 'active', 'is_primary' => false,
        ]);
        $service = app(BarcodeLabelService::class);
        $rows = [['product_id' => $this->product->id, 'barcode_id' => $local->id, 'copies' => 1]];
        self::assertSame($manufacturer->id, $service->compose($this->actor, $rows, $service->profile($this->actor), $this->selling->id)['labels'][0]['barcode']->id);
        $manufacturer->update(['status' => 'inactive']);
        self::assertSame($local->id, $service->compose($this->actor, $rows, $service->profile($this->actor), $this->selling->id)['labels'][0]['barcode']->id);
    }

    public function test_label_percentage_prices_follow_the_selected_list_in_both_directions(): void
    {
        Gate::before(static fn (): bool => true);
        $this->product->update(['sale_price' => '300.00']);
        $base = PriceList::query()->where('company_id', $this->selling->company_id)->where('list_number', 0)->firstOrFail();
        $markup = PriceList::query()->create([
            'company_id' => $this->selling->company_id, 'code' => 'QA-UP-'.Str::random(8),
            'list_number' => 1, 'name_ar' => 'زيادة', 'name_en' => 'Markup',
            'percentage_increase' => '10', 'status' => 'active',
        ]);
        $markup15 = PriceList::query()->create([
            'company_id' => $this->selling->company_id, 'code' => 'QA-UP15-'.Str::random(8),
            'list_number' => 3, 'name_ar' => 'زيادة 15', 'name_en' => 'Markup 15',
            'percentage_increase' => '15', 'status' => 'active',
        ]);
        $discount = PriceList::query()->create([
            'company_id' => $this->selling->company_id, 'code' => 'QA-DOWN-'.Str::random(8),
            'list_number' => 2, 'name_ar' => 'خصم', 'name_en' => 'Discount',
            'percentage_increase' => '-10', 'status' => 'active',
        ]);
        $service = app(BarcodeLabelService::class);
        $rows = [['product_id' => $this->product->id, 'barcode_id' => $this->product->barcodes->first()->id, 'copies' => 1]];
        $profile = $service->profile($this->actor);
        $this->selling->update(['price_list_id' => $markup15->id]);
        self::assertSame('345.000', (string) $service->compose($this->actor, $rows, $profile, $this->selling->id)['labels'][0]['price']);
        $this->selling->update(['price_list_id' => $markup->id]);
        self::assertSame('330.000', (string) $service->compose($this->actor, $rows, $profile, $this->selling->id)['labels'][0]['price']);
        $this->selling->update(['price_list_id' => $discount->id]);
        self::assertSame('270.000', (string) $service->compose($this->actor, $rows, $profile, $this->selling->id)['labels'][0]['price']);
        $this->selling->update(['price_list_id' => $base->id]);
        self::assertSame('300.00', (string) $service->compose($this->actor, $rows, $profile, $this->selling->id)['labels'][0]['price']);
    }

    public function test_percentage_bounds_reject_zero_or_negative_final_price(): void
    {
        $resolver = app(PriceListResolver::class);
        self::assertSame('270.000', $resolver->calculate('300', '-10')['rounded']);
        self::assertSame('345.000', $resolver->calculate('300', '15')['rounded']);
        foreach (['-100', '-101', '10000', 'abc'] as $invalid) {
            try {
                $resolver->calculate('300', $invalid);
                self::fail('Invalid percentage accepted: '.$invalid);
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
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
