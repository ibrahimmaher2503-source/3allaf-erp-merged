<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Queries\PhaseOneReports;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PhaseOneReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_three_reports_aggregate_posted_sales_and_current_stock(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'sale_price' => '25.00', 'average_cost' => '10.00']);
        $product->barcodes()->create(['barcode' => '0001234567', 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $second = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id, 'status' => 'active']);
        foreach ([$fixture['store']->id => 2, $second->id => 3] as $storeId => $quantity) {
            StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $storeId, 'on_hand' => $quantity, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => $quantity * 10, 'version' => 0]);
        }
        $sale = Sale::query()->create(['branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id, 'cashier_id' => $fixture['admin']->id, 'status' => 'approved', 'document_number' => 'QA-RPT-1', 'idempotency_key' => (string) str()->uuid(), 'subtotal' => '50.00', 'discount_total' => '5.00', 'total' => '45.00', 'paid_total' => '45.00', 'payable_total' => '45.00', 'currency_code' => 'EGP', 'approved_at' => now()]);
        $sale->lines()->create(['product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => '2', 'unit_price' => '25.00', 'gross_amount' => '50.00', 'discount_amount' => '5.00', 'net_amount' => '45.00', 'consumed_cost' => '20.00']);
        $sale->payments()->create(['payment_method_id' => $fixture['method']->id, 'method_code' => $fixture['method']->code, 'method_type' => 'cash', 'amount' => '45.00', 'idempotency_key' => (string) str()->uuid(), 'created_by' => $fixture['admin']->id]);
        $filters = ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()];
        $reports = app(PhaseOneReports::class);
        $sales = $reports->productSales($fixture['admin'], $filters);
        self::assertSame('2.000000', (string) $sales['rows']->first()->sold_quantity);
        self::assertEquals(45, $sales['totals']->sales);
        self::assertEquals(45, $reports->productSales($fixture['admin'], $filters + ['store_ids' => [$fixture['store']->id]])['totals']->sales);
        self::assertEquals(0, $reports->productSales($fixture['admin'], $filters + ['store_ids' => [$second->id]])['totals']->sales);
        $supplier = Supplier::query()->create(['code' => 'QA-RPT-SUP', 'name_ar' => 'مورد تقرير', 'name_en' => 'Report supplier', 'status' => 'active']);
        ProductSupplier::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id]);
        self::assertCount(1, $reports->productSales($fixture['admin'], $filters + ['supplier_ids' => [$supplier->id], 'category_ids' => [$fixture['category']->id]])['rows']);
        self::assertCount(0, $reports->productSales($fixture['admin'], $filters + ['supplier_ids' => [$supplier->id + 1000]])['rows']);
        $inventory = $reports->inventory($fixture['admin'], []);
        self::assertSame(1, $inventory['rows']->where('id', $product->id)->count());
        self::assertEquals(5, $inventory['rows']->firstWhere('id', $product->id)->quantity);
        self::assertEquals(50, $inventory['totals']->cost);
        self::assertEquals(2, $reports->inventory($fixture['admin'], ['store_id' => $fixture['store']->id])['rows']->firstWhere('id', $product->id)->quantity);
        self::assertCount(1, $reports->inventory($fixture['admin'], ['supplier_ids' => [$supplier->id], 'category_ids' => [$fixture['category']->id]])['rows']);
        $restricted = User::factory()->create(['status' => 'active']);
        $restricted->storeScopes()->create(['store_id' => $fixture['store']->id, 'status' => 'active']);
        self::assertEquals(2, $reports->inventory($restricted, [])['rows']->firstWhere('id', $product->id)->quantity);
        self::assertEquals(45, $reports->productSales($restricted, $filters)['totals']->sales);
        try {
            $reports->inventory($restricted, ['store_id' => $second->id]);
            self::fail('Unauthorized store filter was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $daily = $reports->daily($fixture['admin'], $filters + ['branch_id' => $fixture['branch']->id]);
        self::assertCount(1, $daily['rows']);
        self::assertSame(45.0, $daily['totals']['cash']);
        self::assertSame(5.0, $daily['totals']['discounts']);
        self::assertSame(45.0, $daily['totals']['net']);
        $return = RetailReturn::query()->create(['branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id, 'cashier_id' => $fixture['admin']->id, 'source_sale_id' => $sale->id, 'status' => 'completed', 'settlement_type' => 'refund', 'reason' => 'QA refund', 'eligible_value' => '22.50', 'subtotal_refund' => '25.00', 'discount_refund' => '2.50', 'settlement_value' => '22.50', 'idempotency_key' => (string) str()->uuid(), 'completed_at' => now()]);
        $return->lines()->create(['sale_line_id' => $sale->lines()->firstOrFail()->id, 'product_id' => $product->id, 'return_store_id' => $fixture['store']->id, 'line_number' => 1, 'quantity' => '1', 'unit_value' => '22.50', 'gross_value' => '25.00', 'discount_value' => '2.50', 'eligible_value' => '22.50', 'tax_value' => '0.00', 'condition' => 'sellable', 'disposition' => 'restock']);
        $return->settlements()->create(['direction' => 'outbound', 'amount' => '22.50', 'settlement_type' => 'cash', 'method_type_snapshot' => 'cash', 'idempotency_key' => (string) str()->uuid(), 'created_by' => $fixture['admin']->id]);
        self::assertEquals(22.5, $reports->productSales($fixture['admin'], $filters)['totals']->sales);
        $afterReturn = $reports->daily($fixture['admin'], $filters + ['branch_id' => $fixture['branch']->id]);
        self::assertSame(22.5, $afterReturn['totals']['net']);
        self::assertSame(22.5, $afterReturn['totals']['cash']);
        self::assertSame(2.5, $afterReturn['totals']['discounts']);
        $olderSale = $sale->replicate(['document_number', 'idempotency_key', 'approved_at']);
        $olderSale->document_number = 'QA-RPT-OLDER';
        $olderSale->idempotency_key = (string) str()->uuid();
        $olderSale->approved_at = now()->subDay();
        $olderSale->subtotal = '25.00';
        $olderSale->discount_total = '0.00';
        $olderSale->total = '25.00';
        $olderSale->paid_total = '25.00';
        $olderSale->payable_total = '25.00';
        $olderSale->save();
        $olderSale->lines()->create(['product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => '1', 'unit_price' => '25.00', 'gross_amount' => '25.00', 'discount_amount' => '0.00', 'net_amount' => '25.00', 'consumed_cost' => '10.00']);
        $olderSale->payments()->create(['payment_method_id' => $fixture['method']->id, 'method_code' => $fixture['method']->code, 'method_type' => 'card', 'amount' => '25.00', 'idempotency_key' => (string) str()->uuid(), 'created_by' => $fixture['admin']->id]);
        $twoDays = $reports->daily($fixture['admin'], ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString(), 'branch_id' => $fixture['branch']->id]);
        self::assertCount(2, $twoDays['rows']);
        self::assertSame(47.5, $twoDays['totals']['net']);
        self::assertSame(25.0, $twoDays['totals']['electronic']);
        self::assertSame(22.5, $twoDays['totals']['cash']);
        $this->get(route('reports.phase-one.sales', $filters))->assertOk();
        $this->get(route('reports.phase-one.inventory'))->assertOk();
        $this->get(route('reports.phase-one.daily', $filters + ['branch_id' => $fixture['branch']->id]))->assertOk();
    }
}
