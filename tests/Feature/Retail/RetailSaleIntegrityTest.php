<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SuspendedSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: INV-02, POS-01, POS-02, PRC-07, NFR-06. Test cases: TC-POS-002, TC-POS-003, TC-PRC-010.
 */
final class RetailSaleIntegrityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_approved_sale_rejects_a_conflicting_idempotency_replay_without_duplicate_effect(): void
    {
        $scenario = $this->saleScenario();
        $this->actingAs($scenario['cashier']);
        $action = app(RetailSaleAction::class);

        $first = $action->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '2']], 'SALE-REPLAY-1');

        try {
            $action->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '999']], 'SALE-REPLAY-1');
            self::fail('A conflicting idempotency replay must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue($first->is(Sale::query()->sole()));
        }
        self::assertSame('approved', $first->status);
        self::assertNotNull($first->document_number);
        self::assertSame(1, Sale::query()->count());
        self::assertSame(1, StockMovement::query()->where('movement_type', 'sale')->count());
        self::assertSame('3.000000', StockBalance::query()->firstOrFail()->on_hand);
    }

    public function test_unpriced_product_is_rejected_without_sale_or_stock_effect(): void
    {
        $scenario = $this->saleScenario(priced: false);
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'SALE-UNPRICED-1');
            self::fail('An unpriced product must not be sellable.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, Sale::query()->count());
            self::assertSame(0, StockMovement::query()->count());
            self::assertSame('5.000000', StockBalance::query()->firstOrFail()->on_hand);
        }
    }

    public function test_insufficient_stock_rolls_back_sale_number_and_movement(): void
    {
        $scenario = $this->saleScenario();
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create($scenario['cashier'], $scenario['store'], [['product_id' => $scenario['product']->id, 'quantity' => '6']], 'SALE-INSUFFICIENT-1');
            self::fail('Insufficient stock must reject the sale.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, Sale::query()->count());
            self::assertSame(0, StockMovement::query()->count());
            self::assertDatabaseMissing('document_sequences', ['document_type' => 'retail_sale']);
        }
    }

    public function test_cashier_cannot_post_against_an_out_of_scope_store(): void
    {
        $scenario = $this->saleScenario();
        $otherBranch = $this->branch('POS-OTHER-BR');
        $otherStore = $this->store($otherBranch, 'POS-OTHER-ST');
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create($scenario['cashier'], $otherStore, [['product_id' => $scenario['product']->id, 'quantity' => '1']], 'SALE-OUT-SCOPE-1');
            self::fail('The out-of-scope store should have been denied.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame(0, Sale::query()->count());
        }
    }

    /**
     * FAIL-POS-003 (testing/results/FAILURE-RECOVERY-SCENARIOS.md) — crash
     * during suspended-sale resume. `finalizeSuspended()` -> `finalize()`
     * posts one stock movement per sale line inside a single
     * `DB::transaction()`; this proves that if a LATER line fails, the
     * FIRST line's already-applied movement — and the sale/suspension
     * status flip — are rolled back too, not left half-committed. The fault
     * is a real `PostInventoryMovement` rejection (fractional quantity on a
     * non-fractional product), not a mock — the same technique used by
     * `InventoryFaultInjectionAtomicityTest` for FAIL-INV-003/004.
     */
    public function test_a_failure_on_the_second_line_during_suspended_sale_resume_rolls_back_the_first_lines_movement(): void
    {
        $scenario = $this->saleScenario();
        $branch = $scenario['store']->branch;
        $productB = Product::query()->create([
            'item_code' => 'POS-PROD-B', 'name_ar' => 'ب', 'name_en' => 'Product B',
            'category_id' => $scenario['product']->category_id, 'status' => 'active', 'fractional_quantity' => false,
        ]);
        StockBalance::query()->create([
            'product_id' => $productB->id, 'store_id' => $scenario['store']->id, 'on_hand' => '10', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '100', 'version' => 1,
        ]);

        $this->actingAs($scenario['cashier']);
        $shift = PosShift::query()->where('cashier_id', $scenario['cashier']->id)->sole();
        $sale = Sale::query()->create([
            'branch_id' => $branch->id, 'store_id' => $scenario['store']->id, 'cash_drawer_id' => $shift->cash_drawer_id,
            'shift_id' => $shift->id, 'cashier_id' => $scenario['cashier']->id, 'status' => 'suspended',
            'idempotency_key' => 'SALE-RESUME-FAULT-'.Str::random(10), 'currency_code' => 'EGP',
            'subtotal' => '25.00', 'total' => '25.00', 'paid_total' => '0.00', 'suspended_at' => now(),
        ]);
        SaleLine::query()->create([
            'sale_id' => $sale->id, 'product_id' => $scenario['product']->id, 'line_number' => 1,
            'item_code' => $scenario['product']->item_code, 'name_ar' => 'Test', 'name_en' => 'Test',
            'quantity' => '1', 'unit_price' => '15.0000', 'gross_amount' => '15.00', 'net_amount' => '15.00',
        ]);
        SaleLine::query()->create([
            'sale_id' => $sale->id, 'product_id' => $productB->id, 'line_number' => 2,
            'item_code' => $productB->item_code, 'name_ar' => 'Test', 'name_en' => 'Test',
            'quantity' => '0.5', 'unit_price' => '10.0000', 'gross_amount' => '5.00', 'net_amount' => '5.00',
        ]);
        $suspendedSale = SuspendedSale::query()->create([
            'sale_id' => $sale->id, 'resume_code' => 'S-'.strtoupper(Str::random(10)), 'created_by' => $scenario['cashier']->id, 'status' => 'suspended',
        ]);

        try {
            app(RetailSaleAction::class)->finalizeSuspended($scenario['cashier'], $sale);
            self::fail('A fractional quantity against a non-fractional product must be rejected.');
        } catch (InvalidArgumentException) {
            // expected — the assertions below are the actual proof.
        }

        self::assertSame('suspended', $sale->fresh()->status, 'The sale must remain suspended; resume never actually committed.');
        self::assertNull($sale->fresh()->document_number);
        self::assertSame('suspended', $suspendedSale->fresh()->status, 'The suspension record must not flip to resumed for a failed resume.');
        self::assertSame(0, StockMovement::query()->count(), 'Zero stock movements — including line 1\'s, which "succeeded" before line 2 failed inside the same transaction.');
        self::assertSame('5.000000', (string) StockBalance::query()->where('product_id', $scenario['product']->id)->where('store_id', $scenario['store']->id)->value('on_hand'), 'Line 1\'s product balance must be unchanged: a real partial commit would show 4 here (5 - 1).');
        self::assertSame(0, AuditLog::query()->where('event', 'finalize_sale')->where('source_id', (string) $sale->id)->count(), 'No audit event for a resume that never actually committed.');
        // Order-independent rollback proof: allocateNumber() runs BEFORE the
        // per-line posting loop and unconditionally creates the
        // `document_sequences` row on first use (this fixture never seeds
        // one). Its absence proves a real rollback regardless of which line
        // the eager-loaded `lines` relation happens to iterate first —
        // unlike the movement/balance assertions above, which a
        // line-iteration-order fluke could satisfy even without an actual
        // rollback if line 2 were (for some future reason) iterated first.
        self::assertDatabaseMissing('document_sequences', ['document_type' => 'retail_sale']);
    }

    /** @return array{cashier: User, store: Store, product: Product} */
    private function saleScenario(bool $priced = true): array
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('POS-BR');
        $store = $this->store($branch, 'POS-ST');
        $cashier = $this->userWith('pos-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'POS-DR', 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active',
        ]);
        PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        $category = Category::query()->create(['code' => 'POS-CAT', 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'POS-PROD', 'name_ar' => 'Test', 'name_en' => 'Test', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);

        if ($priced) {
            $priceList = PriceList::query()->create([
                'company_id' => $this->company()->id, 'code' => 'POS-PRICE', 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active',
            ]);
            $version = PriceVersion::query()->create([
                'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual',
                'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
            ]);
            PriceLine::query()->create([
                'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id,
                'branch_id' => $branch->id, 'amount' => '15.000', 'active_key' => $product->id.':'.$store->id,
            ]);
        }

        return compact('cashier', 'store', 'product');
    }
}
