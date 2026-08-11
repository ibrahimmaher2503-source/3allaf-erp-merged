<?php

declare(strict_types=1);

namespace Tests\Feature\ReturnsGifts;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Actions\GiftReceiptAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\Exchange;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftCardLedger;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnLine;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SalePayment;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ReturnsGiftsLifecycleTest extends TestCase
{
    use PlatformFixtures;
    use WithoutMiddleware;

    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$schemaReady) {
            if (! filter_var(env('CONFIDENCE_SCHEMA_READY', false), FILTER_VALIDATE_BOOL)) {
                $this->artisan('migrate:fresh', ['--force' => true]);
            } else {
                $this->truncateSchema();
            }
            self::$schemaReady = true;
        } else {
            $this->truncateSchema();
        }
        $this->seed(CanonicalAuthorizationSeeder::class);
    }

    private function truncateSchema(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (DB::select('SHOW TABLES') as $table) {
            $name = (array) $table;
            $tableName = (string) array_values($name)[0];
            if ($tableName !== 'migrations') DB::table($tableName)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_gift_receipt_is_price_free_reprintable_and_can_be_consumed_once_by_a_return(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);

        $this->get(route('gift.receipts.index', ['sale_id' => $scenario['sale']->id]))
            ->assertOk()
            ->assertSee('sale_line_ids[]', escape: false)
            ->assertSee(__('Select eligible lines'));

        $receipt = app(GiftReceiptAction::class)->issue($scenario['admin'], $scenario['sale'], 'gr-issue-1');
        self::assertSame('active', $receipt->status);
        self::assertSame(1, $receipt->lines->count());

        $print = $this->actingAs($scenario['admin'])->get(route('gift.receipts.print', $receipt));
        $print->assertOk()->assertDontSee('20.00')->assertDontSee('unit_price')->assertDontSee('gross_amount');
        self::assertSame(1, $receipt->fresh()->printEvents()->count());
        $reprint = $this->actingAs($scenario['admin'])->get(route('gift.receipts.print', ['giftReceipt' => $receipt->id, 'reprint' => 1, 'reason' => 'Customer reprint']));
        $reprint->assertOk()->assertDontSee('20.00')->assertDontSee('unit_price');
        self::assertSame(2, $receipt->fresh()->printEvents()->count());

        $return = app(RetailReturnAction::class)->create($scenario['admin'], [
            'source_gift_receipt_id' => $receipt->id,
            'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock']],
            'settlement_type' => 'cash_refund',
            'reason' => 'Gift return',
        ], 'return-gr-1');
        app(RetailReturnAction::class)->submit($scenario['admin'], $return);
        app(RetailReturnAction::class)->approve($scenario['admin'], $return);
        app(RetailReturnAction::class)->complete($scenario['admin'], $return, 'return-gr-1-complete', $scenario['cashMethod']->id);

        self::assertSame('used', $receipt->fresh()->status);
        self::assertSame('completed', $return->fresh()->status);
        self::assertSame('approved', $scenario['sale']->fresh()->status);
        self::assertSame(0, GiftCard::query()->count());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(GiftReceiptAction::class)->validate($scenario['admin'], $receipt->reference);
    }

    public function test_gift_receipt_reprint_requires_the_distinct_reprint_permission(): void
    {
        $scenario = $this->scenario();
        $receipt = app(GiftReceiptAction::class)->issue($scenario['admin'], $scenario['sale'], 'gr-reprint-permission-issue');
        app(GiftReceiptAction::class)->print($scenario['admin'], $receipt, 'gr-reprint-permission-first');

        $printOnly = $this->userWith('gift-print-only', [], false, [$scenario['branch']->id], [$scenario['store']->id]);
        $role = Role::query()->create([
            'code' => 'gift-print-only', 'name_ar' => 'طباعة فقط', 'name_en' => 'Gift print only', 'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->where('code', 'gift_receipts.print')->value('id'));
        $printOnly->roles()->sync([$role->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(GiftReceiptAction::class)->print($printOnly, $receipt->fresh(), 'gr-reprint-without-permission', 'thermal', 'Customer reprint', true);
    }

    public function test_return_is_source_linked_and_idempotent_and_original_sale_is_unchanged(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $saleBefore = $scenario['sale']->fresh()->only(['status', 'total', 'paid_total', 'lock_version']);
        $returnAction = app(RetailReturnAction::class);
        $payload = ['source_sale_id' => $scenario['sale']->id, 'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock', 'inspection_notes' => 'Seal intact; photographed at counter.']], 'settlement_type' => 'cash_refund', 'reason' => 'Customer return'];
        $return = $returnAction->create($scenario['admin'], $payload, 'return-idempotent-1');
        self::assertSame($return->id, $returnAction->create($scenario['admin'], $payload, 'return-idempotent-1')->id);
        self::assertSame('Seal intact; photographed at counter.', $return->fresh()->lines->first()->inspection_notes);
        $returnAction->submit($scenario['admin'], $return);
        $returnAction->approve($scenario['admin'], $return);
        $returnAction->complete($scenario['admin'], $return, 'return-idempotent-1-complete', $scenario['cashMethod']->id);
        $returnAction->complete($scenario['admin'], $return->fresh(), 'return-idempotent-1-complete', $scenario['cashMethod']->id);

        self::assertSame($saleBefore, $scenario['sale']->fresh()->only(['status', 'total', 'paid_total', 'lock_version']));
        self::assertSame(1, RetailReturn::query()->where('status', 'completed')->count());
        self::assertSame(1, DB::table('stock_movements')->where('source_type', RetailReturn::class)->count());
        self::assertSame('1.000000', (string) DB::table('stock_balances')->where('product_id', $scenario['product']->id)->where('store_id', $scenario['store']->id)->value('on_hand'));
    }

    public function test_gift_card_ledger_is_append_only_and_retry_does_not_double_redeem(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $cards = app(GiftCardAction::class);
        $card = $cards->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'test', 'card-issue-1');
        $first = $cards->redeem($scenario['admin'], $card, '20.00', 'card-redeem-1', 'test', '1');
        $retry = $cards->redeem($scenario['admin'], $card, '20.00', 'card-redeem-1', 'test', '1');
        $print = $cards->print($scenario['admin'], $card->fresh(), 'card-print-1');
        $reprint = $cards->print($scenario['admin'], $card->fresh(), 'card-print-2', 'a4', 'Customer reprint');

        self::assertSame($first->id, $retry->id);
        self::assertFalse($print->is_reprint);
        self::assertTrue($reprint->is_reprint);
        self::assertSame('30.00', (string) $card->fresh()->balance);
        self::assertSame(2, GiftCardLedger::query()->where('gift_card_id', $card->id)->count());
        self::assertSame(2, $card->fresh()->printEvents()->count());
        $this->expectException(LogicException::class);
        $card->fresh()->ledger()->first()->update(['amount' => '999.00']);
    }

    public function test_gift_card_redemption_rejects_an_idempotency_key_reused_with_a_different_payload(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $cards = app(GiftCardAction::class);
        $card = $cards->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'test', 'card-conflict-issue');
        $cards->redeem($scenario['admin'], $card, '10.00', 'card-conflict-redeem', Sale::class, (string) $scenario['sale']->id, $scenario['sale']->document_number);

        try {
            $cards->redeem($scenario['admin'], $card, '20.00', 'card-conflict-redeem', Sale::class, (string) $scenario['sale']->id, $scenario['sale']->document_number);
            self::fail('A Gift Card redemption token must not be reusable with another amount.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('idempotency_key', $exception->errors());
        }

        self::assertSame('40.00', (string) $card->fresh()->balance);
        self::assertSame(2, GiftCardLedger::query()->where('gift_card_id', $card->id)->count());
    }

    public function test_gift_card_history_is_bounded_and_shows_the_source_linked_ledger(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $cards = app(GiftCardAction::class);
        $card = $cards->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'return', '42', 'card-history-issue', 'RET-000042');
        $cards->redeem($scenario['admin'], $card, '10.00', 'card-history-redeem', Sale::class, (string) $scenario['sale']->id, $scenario['sale']->document_number);

        $this->get(route('gift.cards.show', $card))
            ->assertOk()
            ->assertSee('Gift Card History')
            ->assertViewHas('ledger', static fn ($ledger): bool => $ledger->total() === 2 && $ledger->perPage() === 20);
    }

    public function test_direct_manual_gift_card_redemption_route_is_not_available(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $card = app(GiftCardAction::class)->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'return', '42', 'card-direct-redeem-issue');

        $this->post('/gift-cards/'.$card->id.'/redeem', ['amount' => '10.00', 'idempotency_key' => 'forged-direct-redeem'])
            ->assertNotFound();
        self::assertSame('50.00', (string) $card->fresh()->balance);
    }

    public function test_return_create_screen_provides_replacement_line_controls_for_an_exchange(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $this->get(route('returns.index'))
            ->assertOk()
            ->assertSee('exchange_lines[0][product_id]', escape: false)
            ->assertSee('Add replacement line');
    }

    public function test_gift_card_idempotency_keys_cannot_alias_issue_void_expire_or_print_requests(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $cards = app(GiftCardAction::class);
        $issued = $cards->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'return', '1', 'card-issue-conflict', 'RET-1');

        foreach ([
            fn () => $cards->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'return', '2', 'card-issue-conflict', 'RET-2'),
            function () use ($cards, $scenario): void {
                $card = $cards->issue($scenario['admin'], '10.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'void', 'card-void-conflict-issue');
                $cards->void($scenario['admin'], $card, 'Customer cancellation', 'card-void-conflict');
                $cards->void($scenario['admin'], $card, 'Different cancellation reason', 'card-void-conflict');
            },
            function () use ($cards, $scenario): void {
                $first = $cards->issue($scenario['admin'], '10.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'expire-one', 'card-expire-conflict-one');
                $second = $cards->issue($scenario['admin'], '10.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'expire-two', 'card-expire-conflict-two');
                $cards->expire($scenario['admin'], $first, 'card-expire-conflict');
                $cards->expire($scenario['admin'], $second, 'card-expire-conflict');
            },
            function () use ($cards, $issued, $scenario): void {
                $cards->print($scenario['admin'], $issued, 'card-print-conflict', 'thermal', 'Customer copy');
                $cards->print($scenario['admin'], $issued, 'card-print-conflict', 'a4', 'Customer copy');
            },
        ] as $replay) {
            try {
                $replay();
                self::fail('An idempotency key must not alias a different Gift Card request.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertArrayHasKey('idempotency_key', $exception->errors());
            }
        }
    }

    public function test_non_sellable_return_posts_only_to_a_visible_damaged_store(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $damagedStore = $this->store($scenario['branch'], 'RET-DAMAGED', 'damaged');
        $return = app(RetailReturnAction::class)->create($scenario['admin'], [
            'source_sale_id' => $scenario['sale']->id,
            'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'damaged', 'disposition' => 'quarantine']],
            'settlement_type' => 'cash_refund',
            'reason' => 'Damaged return',
        ], 'ret-damaged-store');

        app(RetailReturnAction::class)->submit($scenario['admin'], $return);
        app(RetailReturnAction::class)->approve($scenario['admin'], $return);
        app(RetailReturnAction::class)->complete($scenario['admin'], $return, 'ret-damaged-store-complete', $scenario['cashMethod']->id);

        self::assertSame('0.000000', (string) DB::table('stock_balances')->where('product_id', $scenario['product']->id)->where('store_id', $scenario['store']->id)->value('on_hand'));
        self::assertSame('1.000000', (string) DB::table('stock_balances')->where('product_id', $scenario['product']->id)->where('store_id', $damagedStore->id)->value('on_hand'));
    }

    public function test_exchange_difference_requires_an_active_payment_method(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $return = RetailReturn::query()->create([
            'branch_id' => $scenario['branch']->id, 'store_id' => $scenario['store']->id, 'cashier_id' => $scenario['admin']->id,
            'source_sale_id' => $scenario['sale']->id, 'return_number' => 'RET-EXCHANGE-PAYMENT', 'status' => 'approved',
            'settlement_type' => 'exchange', 'reason' => 'Different item', 'eligible_value' => '20.00', 'settlement_value' => '20.00',
            'currency_code' => 'EGP', 'idempotency_key' => 'ret-exchange-payment', 'lock_version' => 1,
        ]);
        RetailReturnLine::query()->create([
            'retail_return_id' => $return->id, 'sale_line_id' => $scenario['saleLine']->id, 'product_id' => $scenario['product']->id,
            'line_number' => $scenario['saleLine']->line_number, 'quantity' => '1', 'unit_value' => '20.00', 'eligible_value' => '20.00',
            'condition' => 'sellable', 'disposition' => 'restock',
        ]);
        $exchange = Exchange::query()->create(['retail_return_id' => $return->id, 'exchange_number' => 'EX-PAYMENT', 'status' => 'draft', 'replacement_value' => '25.00', 'difference_value' => '5.00', 'difference_direction' => 'collect']);
        $exchange->lines()->create(['product_id' => $scenario['product']->id, 'direction' => 'outbound', 'quantity' => '1', 'unit_value' => '25.00', 'item_code' => $scenario['product']->item_code, 'name_ar' => $scenario['product']->name_ar, 'name_en' => $scenario['product']->name_en]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RetailReturnAction::class)->complete($scenario['admin'], $return, 'ret-exchange-payment-complete');

        self::assertSame('approved', $return->fresh()->status);
        self::assertSame(0, $return->fresh()->settlements()->count());
    }

    public function test_cash_refund_requires_an_active_cash_payment_method(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $return = app(RetailReturnAction::class)->create($scenario['admin'], [
            'source_sale_id' => $scenario['sale']->id,
            'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock']],
            'settlement_type' => 'cash_refund',
            'reason' => 'Cash refund',
        ], 'ret-cash-payment');
        app(RetailReturnAction::class)->submit($scenario['admin'], $return);
        app(RetailReturnAction::class)->approve($scenario['admin'], $return);

        try {
            app(RetailReturnAction::class)->complete($scenario['admin'], $return, 'ret-cash-payment-complete');
            self::fail('A cash refund must not be completed without an active cash payment method.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('payment', $exception->errors());
        }

        $scenario['cashMethod']->update(['status' => 'inactive']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RetailReturnAction::class)->complete($scenario['admin'], $return, 'ret-cash-payment-complete', $scenario['cashMethod']->id);
    }

    public function test_non_super_admin_return_maker_cannot_approve_their_own_return(): void
    {
        $scenario = $this->scenario();
        $role = Role::query()->create(['code' => 'returns-maker-checker', 'name_ar' => 'Ù…Ø±ØªØ¬Ø¹Ø§Øª', 'name_en' => 'Returns maker checker', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('code', ['returns.create', 'returns.submit', 'returns.approve'])->pluck('id'));
        $maker = $this->userWith('return-maker', [], false, [$scenario['branch']->id], [$scenario['store']->id]);
        $approver = $this->userWith('return-approver', [], false, [$scenario['branch']->id], [$scenario['store']->id]);
        $maker->roles()->sync([$role->id]);
        $approver->roles()->sync([$role->id]);
        $action = app(RetailReturnAction::class);
        $return = $action->create($maker, [
            'source_sale_id' => $scenario['sale']->id,
            'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock']],
            'settlement_type' => 'cash_refund',
            'reason' => 'Maker checker boundary',
        ], 'ret-maker-checker');
        $action->submit($maker, $return);

        try {
            $action->approve($maker, $return);
            self::fail('A non-super-admin return maker must not approve their own return.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('approval', $exception->errors());
        }

        self::assertSame('approved', $action->approve($approver, $return)->status);
    }

    public function test_original_tender_refund_is_bounded_to_the_immutable_source_payment(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $scenario['product']->update(['fractional_quantity' => true]);
        $method = PaymentMethod::query()->create(['code' => 'RET-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);
        $payment = SalePayment::query()->create(['sale_id' => $scenario['sale']->id, 'payment_method_id' => $method->id, 'method_code' => $method->code, 'method_type' => $method->type, 'amount' => '10.00', 'tendered_amount' => '10.00', 'change_amount' => '0.00', 'idempotency_key' => 'ret-source-payment-1', 'created_by' => $scenario['admin']->id]);
        $action = app(RetailReturnAction::class);
        $first = $action->create($scenario['admin'], ['source_sale_id' => $scenario['sale']->id, 'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '0.25', 'condition' => 'sellable', 'disposition' => 'restock']], 'settlement_type' => 'original_tender', 'reason' => 'Partial refund'], 'ret-original-1');
        $action->submit($scenario['admin'], $first);
        $action->approve($scenario['admin'], $first);
        $action->complete($scenario['admin'], $first, 'ret-original-1-complete', null, $payment->id);
        self::assertSame('5.00', (string) $first->fresh()->settlements->first()->amount);

        $second = $action->create($scenario['admin'], ['source_sale_id' => $scenario['sale']->id, 'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '0.5', 'condition' => 'sellable', 'disposition' => 'restock']], 'settlement_type' => 'original_tender', 'reason' => 'Second partial refund'], 'ret-original-2');
        $action->submit($scenario['admin'], $second);
        $action->approve($scenario['admin'], $second);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $action->complete($scenario['admin'], $second, 'ret-original-2-complete', null, $payment->id);
    }

    public function test_foreign_scope_and_no_access_cannot_open_instrument_surfaces(): void
    {
        $scenario = $this->scenario();
        $foreignBranch = Branch::query()->create(['company_id' => $scenario['company']->id, 'code' => 'FOREIGN', 'name_ar' => 'Foreign', 'name_en' => 'Foreign', 'timezone' => 'UTC', 'status' => 'active']);
        $foreignStore = Store::query()->create(['company_id' => $scenario['company']->id, 'branch_id' => $foreignBranch->id, 'code' => 'FOREIGN', 'name_ar' => 'Foreign', 'name_en' => 'Foreign', 'type' => 'selling', 'status' => 'active']);
        $noAccess = $this->userWith('returns-no-access', [], false, [$foreignBranch->id], [$foreignStore->id]);

        $this->actingAs($noAccess)->get('/gift-cards')->assertForbidden();
        $this->actingAs($noAccess)->get('/returns')->assertForbidden();
        $this->actingAs($noAccess)->get('/gift-receipts')->assertForbidden();
    }

    /** @return array{admin: User, company: Company, branch: Branch, store: Store, cashMethod: PaymentMethod, product: Product, sale: Sale, saleLine: SaleLine} */
    private function scenario(): array
    {
        $company = $this->company();
        $branch = $this->branch('RET-BR');
        $store = $this->store($branch, 'RET-ST');
        $admin = $this->administrator('returns-admin');
        $admin->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $cashMethod = PaymentMethod::query()->create(['code' => 'RET-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);
        $cashMethod->update(['code' => 'RET-CASH-REFUND']);
        $category = Category::query()->create(['code' => 'RET-CAT', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'RET-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active', 'fractional_quantity' => false]);
        DB::table('stock_balances')->insert(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '0', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '10', 'total_value' => '0', 'version' => 1]);
        $sale = Sale::query()->create(['branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $admin->id, 'status' => 'approved', 'idempotency_key' => 'sale-'.uniqid(), 'subtotal' => '20.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '20.00', 'paid_total' => '20.00', 'change_total' => '0.00', 'cash_rounding_amount' => '0.00', 'payable_total' => '20.00', 'currency_code' => 'EGP', 'approved_at' => now(), 'lock_version' => 1]);
        $saleLine = SaleLine::query()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => '1.000000', 'unit_price' => '20.0000', 'gross_amount' => '20.00', 'discount_amount' => '0.00', 'net_amount' => '20.00']);
        $this->documentSequence('gift_receipt', 'GR-');
        $this->documentSequence('retail_return', 'RET-');
        $this->documentSequence('retail_exchange', 'EX-');
        return compact('admin', 'company', 'branch', 'store', 'cashMethod', 'product', 'sale', 'saleLine');
    }
}
