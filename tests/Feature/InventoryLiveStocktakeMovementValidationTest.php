<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\AddAllStockCountProductsAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SetStockCountQuantityAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\StockCountReconciliation;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseReceivingAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryLiveStocktakeMovementValidationTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{actor: User, store: Store, product: Product, count: StockCount, method: PaymentMethod} */
    private function activeCount(): array
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $actor = $fixture['admin'];
        $store = $fixture['store'];
        $this->actingAs($actor)->withoutVite();
        request()->setLaravelSession(app('session')->driver());
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'sale_price' => '30', 'average_cost' => '10']);
        $product->barcodes()->create(['barcode' => 'QA-STF-'.Str::upper(Str::random(12)), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '100', 'opening_inventory', '10', 'qa-stocktake-opening-'.Str::uuid());
        $count = app(SaveStockCountAction::class)->execute([
            'branch_id' => $store->branch_id,
            'location_ids' => [$store->id],
            'member_ids' => [$actor->id],
            'manager_id' => $actor->id,
            'count_type' => 'partial',
            'scope_type' => 'partial',
        ]);
        app(OpenStockCountSessionAction::class)->execute($count->id);
        self::assertSame('in_progress', $count->fresh()->status);
        app(AddAllStockCountProductsAction::class)->execute($count->id, $store->id);
        self::assertSame('100.000000', (string) $count->lines()->where('product_id', $product->id)->sole()->reference_on_hand);
        $method = PaymentMethod::factory()->create(['type' => 'card', 'requires_evidence' => false]);
        $drawer = $fixture['drawer'];
        $shift = PosShift::query()->create([
            'branch_id' => $store->branch_id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $actor->id, 'opened_by' => $actor->id, 'status' => 'open',
            'opening_cash' => '0', 'currency_code' => 'EGP', 'idempotency_key' => (string) Str::uuid(), 'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id, 'cashier_id' => $actor->id, 'cash_drawer_id' => $drawer->id,
        ]);

        return compact('actor', 'store', 'product', 'count', 'method');
    }

    /** @param array{actor: User, store: Store, product: Product, count: StockCount, method: PaymentMethod} $fixture */
    private function sale(array $fixture, string $quantity, string $key): Sale
    {
        return app(RetailSaleAction::class)->create(
            $fixture['actor'], $fixture['store'],
            [['product_id' => $fixture['product']->id, 'quantity' => $quantity]],
            $key, false, [['method' => $fixture['method'], 'amount' => bcmul($quantity, '30', 2)]],
        );
    }

    /** @param array{actor: User, store: Store, product: Product, count: StockCount, method: PaymentMethod} $fixture */
    private function returnSale(array $fixture, Sale $sale, string $quantity, string $key): RetailReturn
    {
        $action = app(RetailReturnAction::class);
        $created = $action->create($fixture['actor'], [
            'source_sale_id' => $sale->id, 'settlement_type' => 'original_tender',
            'reason' => 'Focused stocktake QA return',
            'lines' => [[
                'sale_line_id' => $sale->lines()->firstOrFail()->id,
                'quantity' => $quantity, 'condition' => 'sellable', 'disposition' => 'restock',
                'return_store_id' => $fixture['store']->id, 'inspection_notes' => 'Sellable QA return',
            ]],
        ], 'qa-stocktake-return-create-'.Str::uuid());
        $submitted = $action->submit($fixture['actor'], $created);
        $approved = $action->approve($fixture['actor'], $submitted);

        return $action->complete($fixture['actor'], $approved, $key);
    }

    /** @param array{actor: User, store: Store, product: Product, count: StockCount, method: PaymentMethod} $fixture */
    private function receivePurchase(array $fixture, string $quantity): PurchaseInvoice
    {
        $supplier = Supplier::query()->create([
            'code' => 'QA-STF-'.Str::upper(Str::random(8)), 'name_ar' => 'مورد اختبار الجرد',
            'name_en' => 'Stocktake QA supplier', 'status' => 'active',
        ]);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $fixture['store']->id, 'invoice_date' => now()->toDateString()],
            [['product_id' => $fixture['product']->id, 'quantity' => $quantity, 'unit_cost' => '12', 'base_consumer_price' => '30']],
        );
        $submitted = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $reviewed = app(SavePurchaseReceivingAction::class)->execute(
            $submitted->id, [$submitted->lines()->firstOrFail()->id => $quantity], $submitted->lock_version,
        );

        return app(ApprovePurchaseInvoiceAction::class)->execute($reviewed->id, $reviewed->lock_version);
    }

    /** @param array{actor: User, store: Store, product: Product, count: StockCount, method: PaymentMethod} $fixture */
    private function countQuantity(array $fixture, string $quantity): array
    {
        $line = app(SetStockCountQuantityAction::class)->execute(
            $fixture['count']->id, $fixture['store']->id, $fixture['product']->item_code,
            $quantity, (string) Str::uuid(), 'qa-followup', null,
        );

        return app(StockCountReconciliation::class)->calculate($fixture['count'], $line);
    }

    public function test_pos_sale_during_active_count_posts_once_and_reconciles(): void
    {
        $fixture = $this->activeCount();
        $key = 'qa-stocktake-sale-'.Str::uuid();
        $sale = $this->sale($fixture, '10', $key);
        self::assertSame('approved', $sale->status);
        self::assertSame('in_progress', $fixture['count']->fresh()->status);
        self::assertSame('-10.000000', (string) StockMovement::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->sole()->quantity);
        self::assertSame('90.000000', (string) StockBalance::query()->where('store_id', $fixture['store']->id)->where('product_id', $fixture['product']->id)->value('on_hand'));
        self::assertSame($sale->id, $this->sale($fixture, '10', $key)->id);
        self::assertSame(1, StockMovement::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->count());
        $values = $this->countQuantity($fixture, '90');
        self::assertSame('90.000000', $values['expected_quantity']);
        self::assertSame('0.000000', $values['variance_quantity']);
    }

    public function test_customer_return_during_active_count_posts_once_and_reconciles(): void
    {
        $fixture = $this->activeCount();
        $sale = $this->sale($fixture, '10', 'qa-stocktake-sale-'.Str::uuid());
        $key = 'qa-stocktake-return-complete-'.Str::uuid();
        $return = $this->returnSale($fixture, $sale, '2', $key);
        self::assertSame('completed', $return->status);
        self::assertSame('in_progress', $fixture['count']->fresh()->status);
        self::assertSame('2.000000', (string) StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $return->id)->sole()->quantity);
        self::assertSame('92.000000', (string) StockBalance::query()->where('store_id', $fixture['store']->id)->where('product_id', $fixture['product']->id)->value('on_hand'));
        app(RetailReturnAction::class)->complete($fixture['actor'], $return, $key);
        self::assertSame(1, StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $return->id)->count());
        $values = $this->countQuantity($fixture, '92');
        self::assertSame('92.000000', $values['expected_quantity']);
        self::assertSame('0.000000', $values['variance_quantity']);
    }

    public function test_purchase_receipt_and_combined_count_boundary_use_canonical_movements(): void
    {
        $fixture = $this->activeCount();
        $sale = $this->sale($fixture, '10', 'qa-stocktake-sale-'.Str::uuid());
        $this->returnSale($fixture, $sale, '2', 'qa-stocktake-return-complete-'.Str::uuid());
        $invoice = $this->receivePurchase($fixture, '5');
        self::assertSame('approved', $invoice->status);
        self::assertSame('in_progress', $fixture['count']->fresh()->status);
        $receipt = StockMovement::query()->where('source_type', PurchaseInvoice::class)->where('source_id', $invoice->id)->sole();
        self::assertSame('purchase_receipt', $receipt->movement_type);
        self::assertSame('5.000000', (string) $receipt->quantity);
        self::assertSame('97.000000', (string) StockBalance::query()->where('store_id', $fixture['store']->id)->where('product_id', $fixture['product']->id)->value('on_hand'));
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        self::assertSame(1, StockMovement::query()->where('source_type', PurchaseInvoice::class)->where('source_id', $invoice->id)->count());
        $values = $this->countQuantity($fixture, '95');
        $line = $fixture['count']->lines()->where('product_id', $fixture['product']->id)->sole();
        self::assertSame(['sale', 'retail_return', 'purchase_receipt'], StockMovement::query()
            ->where('product_id', $fixture['product']->id)->where('store_id', $fixture['store']->id)
            ->where('id', '>', $line->reference_movement_id)->where('id', '<=', $line->counted_movement_id)
            ->orderBy('id')->pluck('movement_type')->all());
        self::assertSame('-3.000000', bcadd((string) StockMovement::query()
            ->where('product_id', $fixture['product']->id)->where('store_id', $fixture['store']->id)
            ->where('id', '>', $line->reference_movement_id)->where('id', '<=', $line->counted_movement_id)
            ->sum('quantity'), '0', 6));
        self::assertSame('-3.000000', $values['movement_quantity_after_reference']);
        self::assertSame('97.000000', $values['expected_quantity']);
        self::assertSame('95.000000', $values['normalized_closing_quantity']);
        self::assertSame('-2.000000', $values['variance_quantity']);
        $after = $this->sale($fixture, '1', 'qa-stocktake-after-count-'.Str::uuid());
        self::assertSame(1, StockMovement::query()->where('source_type', Sale::class)->where('source_id', $after->id)->count());
        $values = app(StockCountReconciliation::class)->calculate($fixture['count'], $fixture['count']->lines()->sole());
        self::assertSame('-1.000000', $values['movement_after_completion']);
        self::assertSame('96.000000', $values['expected_quantity']);
        self::assertSame('94.000000', $values['normalized_closing_quantity']);
        self::assertSame('-2.000000', $values['variance_quantity']);
    }
}
