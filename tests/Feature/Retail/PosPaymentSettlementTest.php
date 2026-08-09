<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\CapturePaymentAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: POS-03, POS-05, NFR-01, NFR-06. Policy: docs/48 §6 (DEC-066).
 * Test cases: TC-POS-030..039.
 *
 * Before TSK-024 a sale was approved with `paid_total = subtotal` and no tender
 * ever recorded. These tests exist to keep that fiction from returning.
 */
final class PosPaymentSettlementTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_a_sale_cannot_be_approved_without_any_tender(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                [['product_id' => $scenario['product']->id, 'quantity' => '2']],
                'PAY-NONE-1',
            );
            self::fail('An untendered sale must not be approved.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('not fully settled', $e->getMessage());
        }

        self::assertSame(0, Sale::query()->where('status', 'approved')->count());
        self::assertSame(0, StockMovement::query()->count(), 'No stock may move for an unsettled sale.');
        self::assertSame('5.000000', (string) StockBalance::query()->where('store_id', $scenario['store']->id)->value('on_hand'));
    }

    public function test_underpayment_blocks_approval_and_moves_no_stock(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                [['product_id' => $scenario['product']->id, 'quantity' => '2']],
                'PAY-UNDER-1',
                false,
                [['method' => $scenario['card'], 'amount' => '20.00', 'evidence_attachment_id' => $this->evidence($scenario)]],
            );
            self::fail('A partially settled sale must not be approved.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('not fully settled', $e->getMessage());
        }

        self::assertSame(0, Sale::query()->where('status', 'approved')->count());
        self::assertSame(0, SalePayment::query()->count(), 'The partial tender must roll back with the sale.');
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_a_split_payment_settles_the_sale_exactly(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        // 2 x 15.00 = 30.00, settled as 20.00 card + 10.00 cash.
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-SPLIT-1',
            false,
            [
                ['method' => $scenario['card'], 'amount' => '20.00', 'evidence_reference' => 'TERM-99321', 'evidence_attachment_id' => $this->evidence($scenario)],
                ['method' => $scenario['cash'], 'amount' => '10.00'],
            ],
        );

        self::assertSame('approved', $sale->status);
        self::assertSame('30.00', (string) $sale->total);
        self::assertSame('30.00', (string) $sale->paid_total);
        self::assertSame(2, SalePayment::query()->where('sale_id', $sale->id)->count());

        $paid = '0.00';
        foreach (SalePayment::query()->where('sale_id', $sale->id)->get() as $payment) {
            $paid = bcadd($paid, (string) $payment->amount, 2);
        }
        self::assertSame((string) $sale->payable_total, $paid, 'Payment rows must sum to the payable amount (docs/48 §6).');
    }

    public function test_cash_overpayment_produces_change_without_inflating_the_paid_total(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        // 30.00 payable, customer hands over 50.00.
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-CHANGE-1',
            false,
            [['method' => $scenario['cash'], 'amount' => '30.00', 'tendered' => '50.00']],
        );

        self::assertSame('approved', $sale->status);
        self::assertSame('30.00', (string) $sale->paid_total, 'Only the applied amount settles the sale.');
        self::assertSame('20.00', (string) $sale->change_total);

        $payment = SalePayment::query()->where('sale_id', $sale->id)->sole();
        self::assertSame('50.00', (string) $payment->tendered_amount);
        self::assertSame('20.00', (string) $payment->change_amount);
    }

    /**
     * The `amount > residual` branch caps the applied amount at the residual
     * and turns the excess into change. Distinct from the case above, where the
     * applied amount is exact and only the tendered cash is higher.
     */
    public function test_cash_amount_input_is_ignored_because_cash_settles_the_residual(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        // POSF-03: cash is always the residual; an amount field cannot turn
        // into implicit tendered cash or inflate payment/change.
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-CAP-1',
            false,
            [['method' => $scenario['cash'], 'amount' => '50.00']],
        );

        self::assertSame('approved', $sale->status);
        self::assertSame('30.00', (string) $sale->paid_total, 'Only the residual may settle the sale.');
        self::assertSame('0.00', (string) $sale->change_total);

        $payment = SalePayment::query()->where('sale_id', $sale->id)->sole();
        self::assertSame('30.00', (string) $payment->amount);
        self::assertSame('0.00', (string) $payment->change_amount);
    }

    /**
     * With a split tender, change on one row must not inflate `paid_total`.
     */
    public function test_change_on_one_row_of_a_split_tender_does_not_inflate_the_paid_total(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        // 30.00 payable: 20.00 card exact, then 10.00 cash from a 20.00 note.
        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-SPLIT-CHANGE-1',
            false,
            [
                ['method' => $scenario['card'], 'amount' => '20.00', 'evidence_reference' => 'TERM-5', 'evidence_attachment_id' => $this->evidence($scenario)],
                ['method' => $scenario['cash'], 'amount' => '10.00', 'tendered' => '20.00'],
            ],
        );

        self::assertSame('approved', $sale->status);
        self::assertSame('30.00', (string) $sale->paid_total, 'paid_total must exclude change.');
        self::assertSame('10.00', (string) $sale->change_total);
        self::assertSame((string) $sale->payable_total, (string) $sale->paid_total);
    }

    public function test_electronic_overpayment_is_rejected(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                [['product_id' => $scenario['product']->id, 'quantity' => '2']],
                'PAY-OVER-CARD-1',
                false,
                [['method' => $scenario['card'], 'amount' => '40.00', 'evidence_reference' => 'TERM-1', 'evidence_attachment_id' => $this->evidence($scenario)]],
            );
            self::fail('Electronic overpayment must be rejected (docs/48 §6).');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('cannot exceed', $e->getMessage());
        }

        self::assertSame(0, SalePayment::query()->count());
    }

    public function test_a_method_requiring_evidence_cannot_be_captured_without_it(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        try {
            app(RetailSaleAction::class)->create(
                $scenario['cashier'],
                $scenario['store'],
                [['product_id' => $scenario['product']->id, 'quantity' => '2']],
                'PAY-NOEVID-1',
                false,
                [['method' => $scenario['card'], 'amount' => '30.00']],
            );
            self::fail('An evidence-requiring method must be blocked without evidence.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('evidence', $e->getMessage());
        }

        self::assertSame(0, SalePayment::query()->count());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_cash_tender_fails_explicitly_when_the_denomination_is_unset(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        DB::table('pos_financial_setting_versions')->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('denomination');

        app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-NO-DENOMINATION-1',
            false,
            [['method' => $scenario['cash'], 'amount' => '0.00']],
        );
    }

    public function test_capturing_the_same_tender_twice_is_idempotent(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        $sale = $this->draftSale($scenario);
        $capture = app(CapturePaymentAction::class);

        $first = $capture->execute($scenario['cashier'], $sale, $scenario['cash'], '10.00', 'TENDER-IDEM-1');
        $replay = $capture->execute($scenario['cashier'], $sale, $scenario['cash'], '10.00', 'TENDER-IDEM-1');

        self::assertTrue($first->is($replay));
        self::assertSame(1, SalePayment::query()->where('sale_id', $sale->id)->count());
        self::assertSame('30.00', $capture->paidSoFar($sale->fresh()), 'Cash settles the full residual regardless of an amount input.');
    }

    public function test_a_captured_payment_is_immutable(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);
        $sale = $this->draftSale($scenario);

        $payment = app(CapturePaymentAction::class)->execute($scenario['cashier'], $sale, $scenario['cash'], '10.00', 'TENDER-IMMUTABLE-1');

        $this->expectException(LogicException::class);
        $payment->update(['amount' => '999.00']);
    }

    public function test_a_payment_cannot_be_added_to_an_already_approved_sale(): void
    {
        $scenario = $this->scenario();
        $this->actingAs($scenario['cashier']);

        $sale = app(RetailSaleAction::class)->create(
            $scenario['cashier'],
            $scenario['store'],
            [['product_id' => $scenario['product']->id, 'quantity' => '2']],
            'PAY-SETTLED-1',
            false,
            [['method' => $scenario['cash'], 'amount' => '30.00']],
        );

        $this->expectException(InvalidArgumentException::class);
        app(CapturePaymentAction::class)->execute($scenario['cashier'], $sale, $scenario['cash'], '5.00', 'TENDER-AFTER-APPROVAL-1');
    }

    /** @param array{cashier: User, store: Store, product: Product, cash: PaymentMethod, card: PaymentMethod} $scenario */
    private function draftSale(array $scenario): Sale
    {
        $shift = PosShift::query()->where('cashier_id', $scenario['cashier']->id)->sole();

        return Sale::query()->create([
            'branch_id' => $scenario['store']->branch_id,
            'store_id' => $scenario['store']->id,
            'cash_drawer_id' => $shift->cash_drawer_id,
            'shift_id' => $shift->id,
            'cashier_id' => $scenario['cashier']->id,
            'status' => 'draft',
            'idempotency_key' => 'DRAFT-'.uniqid(),
            'currency_code' => 'EGP',
            'subtotal' => '30.00',
            'total' => '30.00',
            'payable_total' => '30.00',
            'paid_total' => '0.00',
        ]);
    }

    /** @return array{cashier: User, store: Store, product: Product, cash: PaymentMethod, card: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('PAY-BR');
        $store = $this->store($branch, 'PAY-ST');
        $cashier = $this->userWith('pay-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'PAY-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        $category = Category::query()->create(['code' => 'PAY-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'PAY-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'PAY-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual',
            'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'branch_id' => $branch->id, 'amount' => '15.000', 'active_key' => $product->id.':'.$store->id,
        ]);

        $cash = PaymentMethod::query()->create([
            'code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash',
            'requires_evidence' => false, 'status' => 'active',
        ]);
        $card = PaymentMethod::query()->create([
            'code' => 'card', 'name_ar' => 'بطاقة', 'name_en' => 'Card', 'type' => 'manual',
            'requires_evidence' => true, 'status' => 'active',
        ]);
        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '0.05',
            'value_type' => 'decimal',
            'version' => 1,
            'created_by' => $cashier->id,
        ]);

        return compact('cashier', 'store', 'product', 'cash', 'card');
    }

    /** @param array{cashier: User, store: Store} $scenario */
    private function evidence(array $scenario): string
    {
        return app(StoreAttachment::class)->execute(
            UploadedFile::fake()->image('terminal-receipt.jpg'),
            'payment_evidence',
            new AttachmentSourceReference(
                branchId: (int) $scenario['store']->branch_id,
                storeId: (int) $scenario['store']->id,
                visibility: 'private',
            ),
        )->id;
    }
}
