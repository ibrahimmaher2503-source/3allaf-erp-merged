<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Livewire\Pos\RefundWizard;
use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductOptionAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\BarcodeSvgRenderer;
use App\Modules\Retail\Actions\CreateUnverifiedReturnAction;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\RetailRefundCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PosSmartSplitReturnsTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;

    private Store $store;

    private Product $product;

    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $role = Role::query()->create(['code' => 'QA-RETURNS-'.str()->random(8), 'name_ar' => 'مدير المرتجعات', 'name_en' => 'Returns manager', 'status' => 'active']);
        foreach (['returns.create', 'returns.submit', 'returns.approve', 'returns.complete', 'returns.view', 'returns.print', 'pos_sales.view', 'inventory_stock_card.view', 'gift_receipts.issue', 'gift_receipts.view', 'gift_receipts.print', 'products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'] as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'status' => 'active']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->manager->roles()->sync([$role->id]);
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $this->store = Store::factory()->for($branch)->create(['company_id' => $company->id]);
        DB::table('user_store_scopes')->insert([
            'user_id' => $this->manager->id, 'store_id' => $this->store->id,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $drawer = CashDrawer::factory()->create(['store_id' => $this->store->id, 'branch_id' => $branch->id, 'company_id' => $company->id, 'assigned_user_id' => $this->manager->id]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $this->store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $this->manager->id, 'opened_by' => $this->manager->id,
            'status' => 'open', 'opening_cash' => '0', 'currency_code' => 'EGP',
            'idempotency_key' => (string) str()->uuid(), 'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert(['shift_id' => $shift->id, 'cashier_id' => $this->manager->id, 'cash_drawer_id' => $drawer->id]);
        $this->product = Product::factory()->create(['sale_price' => '999.00', 'name_en' => 'Returned item']);
        $this->product->barcodes()->create(['barcode' => 'RET-TEST-'.$this->product->id, 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $this->cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $this->actingAs($this->manager)->withoutVite();
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return [
            'product_id' => $this->product->id, 'quantity' => '2', 'amount' => '40.00',
            'payment_method_id' => $this->cash->id, 'reason' => 'other', 'note' => 'Manager approved',
            'confirmed_no_sale' => true,
        ];
    }

    public function test_unverified_return_posts_manager_amount_stock_refund_and_audit_once(): void
    {
        $action = app(CreateUnverifiedReturnAction::class);
        $first = $action->execute($this->manager, $this->data(), 'unverified-qa-0001');
        $second = $action->execute($this->manager, $this->data(), 'unverified-qa-0001');

        self::assertSame($first->id, $second->id);
        self::assertFalse($first->is_verified);
        self::assertNull($first->source_sale_id);
        self::assertSame('40.00', $first->manager_approved_amount);
        self::assertSame('completed', $first->status);
        self::assertSame('2.000000', StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $this->store->id)->sole()->on_hand);
        self::assertSame(1, StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $first->id)->count());
        self::assertSame(1, RetailReturnSettlement::query()->where('retail_return_id', $first->id)->where('amount', '40.00')->count());
        self::assertSame(1, AuditLog::query()->where('event', 'unverified_retail_return_completed')->where('source_id', (string) $first->id)->count());
        self::assertSame('999.00', $this->product->fresh()->sale_price);
    }

    public function test_unverified_return_posts_multiple_editable_price_lines_as_one_document(): void
    {
        $second = Product::factory()->create(['sale_price' => '75.00', 'name_en' => 'Second returned item']);
        $data = [...$this->data(), 'lines' => [
            ['product_id' => $this->product->id, 'quantity' => '2', 'unit_price' => '20.00'],
            ['product_id' => $second->id, 'quantity' => '3', 'unit_price' => '15.50'],
        ]];
        $action = app(CreateUnverifiedReturnAction::class);
        $document = $action->execute($this->manager, $data, 'unverified-multiple-0001');
        self::assertSame($document->id, $action->execute($this->manager, $data, 'unverified-multiple-0001')->id);
        self::assertSame('86.50', (string) $document->settlement_value);
        self::assertCount(2, $document->lines);
        self::assertSame('40.00', (string) $document->lines[0]->gross_value);
        self::assertSame('46.50', (string) $document->lines[1]->gross_value);
        self::assertSame(2, StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $document->id)->count());
        self::assertSame('2.000000', StockBalance::query()->where('product_id', $this->product->id)->where('store_id', $this->store->id)->sole()->on_hand);
        self::assertSame('3.000000', StockBalance::query()->where('product_id', $second->id)->where('store_id', $this->store->id)->sole()->on_hand);
        self::assertSame(1, AuditLog::query()->where('event', 'unverified_retail_return_completed')->where('source_id', (string) $document->id)->count());
    }

    public function test_gift_card_code128_and_locked_redeem_refund_keep_one_ledger_entry_per_key(): void
    {
        $cards = app(GiftCardAction::class);
        $card = $cards->issue($this->manager, '500.00', (int) $this->store->branch_id, (int) $this->store->id,
            Sale::class, 'gift-qa-sale', 'gift-qa-issue-0001');
        $svg = app(BarcodeSvgRenderer::class)->render((string) $card->identifier, 'code128', 72);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('rect', $svg);
        $first = $cards->redeem($this->manager, $card, '350.00', 'gift-qa-redeem-0001', Sale::class, 'gift-qa-sale');
        self::assertSame($first->id, $cards->redeem($this->manager, $card, '350.00', 'gift-qa-redeem-0001', Sale::class, 'gift-qa-sale')->id);
        self::assertSame('150.00', (string) $card->fresh()->balance);
        $credit = $cards->refundRedemption($this->manager, $card, '100.00', 'gift-qa-refund-0001', 'refund-qa-1');
        self::assertSame($credit->id, $cards->refundRedemption($this->manager, $card, '100.00', 'gift-qa-refund-0001', 'refund-qa-1')->id);
        self::assertSame('250.00', (string) $card->fresh()->balance);
        self::assertSame(3, $card->ledger()->count());
        try {
            $cards->redeem($this->manager, $card, '300.00', 'gift-qa-redeem-oversized', Sale::class, 'gift-qa-sale');
            self::fail('An over-balance redemption must be rejected.');
        } catch (ValidationException) {
            self::assertSame('250.00', (string) $card->fresh()->balance);
        }
    }

    public function test_gift_receipt_issue_resets_selection_and_keeps_print_link_in_history(): void
    {
        $sale = $this->sale('QA-GIFT-RESET-001');
        $key = (string) str()->uuid();
        $response = $this->post(route('gift.receipts.store'), [
            'idempotency_key' => $key,
            'sale_id' => $sale->id,
            'sale_line_ids' => [$sale->lines()->firstOrFail()->id],
        ]);
        $response->assertRedirect(route('gift.receipts.index'));
        $receipt = GiftReceipt::query()->where('idempotency_key', $key)->firstOrFail();
        $this->get(route('gift.receipts.index'))->assertOk()->assertSee($receipt->reference)
            ->assertSee(route('gift.receipts.print', $receipt), false)
            ->assertDontSee('name="sale_id" value="'.$sale->id.'"', false);
    }

    public function test_filter_value_create_and_edit_ignore_color_input_and_preserve_legacy_swatch(): void
    {
        $group = ProductOptionGroup::query()->create([
            'code' => 'QA-GROUP-'.str()->upper(str()->random(8)),
            'name_ar' => 'مجموعة اختبار', 'name_en' => 'QA group', 'status' => 'active', 'sort_order' => 0,
        ]);
        $action = app(SaveProductOptionAction::class);
        $value = $action->saveValue((int) $group->id, [
            'code' => 'QA-RED', 'name_ar' => 'أحمر', 'name_en' => 'Red', 'status' => 'active', 'sort_order' => 0,
        ]);
        self::assertNull($value->colour_swatch);
        $value->update(['colour_swatch' => '#FF0000']);
        $edited = $action->saveValue((int) $group->id, [
            'code' => 'QA-RED', 'name_ar' => 'أحمر', 'name_en' => 'Red edited', 'status' => 'active', 'sort_order' => 0,
        ], (int) $value->id);
        self::assertSame('#FF0000', $edited->colour_swatch);
        $this->get(route('catalog.product-options'))->assertOk()->assertDontSee('Optional swatch');
    }

    public function test_inventory_balance_search_matches_names_code_and_barcode_in_all_and_store_scope(): void
    {
        app(CreateUnverifiedReturnAction::class)->execute($this->manager, $this->data(), 'unverified-search-0001');
        $terms = [mb_substr((string) $this->product->name_ar, 0, 3), 'Returned',
            mb_substr((string) $this->product->item_code, 0, 4), 'RET-TEST-'];
        foreach ($terms as $term) {
            $response = $this->get(route('inventory.balances', ['q' => $term, 'store_id' => '']))->assertOk();
            $balances = $response->getOriginalContent()->getData()['balances'];
            self::assertTrue($balances->contains('product_id', $this->product->id));
        }
        $selected = $this->get(route('inventory.balances', ['q' => 'Returned', 'store_id' => $this->store->id]))->assertOk();
        self::assertTrue($selected->getOriginalContent()->getData()['balances']->contains('product_id', $this->product->id));
        $missing = $this->get(route('inventory.balances', ['q' => 'not-a-product', 'store_id' => '']))->assertOk();
        self::assertSame(0, $missing->getOriginalContent()->getData()['balances']->total());
    }

    public function test_unverified_return_rejects_missing_reason_confirmation_and_reused_token(): void
    {
        $action = app(CreateUnverifiedReturnAction::class);
        foreach ([['reason' => ''], ['confirmed_no_sale' => false]] as $changes) {
            try {
                $action->execute($this->manager, array_replace($this->data(), $changes), 'unverified-qa-rejected');
                self::fail('Expected validation failure.');
            } catch (ValidationException) {
                self::assertSame(0, RetailReturn::query()->where('idempotency_key', 'unverified-qa-rejected')->count());
            }
        }
        $action->execute($this->manager, $this->data(), 'unverified-qa-0002');
        $this->expectException(ValidationException::class);
        $action->execute($this->manager, array_replace($this->data(), ['amount' => '45.00']), 'unverified-qa-0002');
    }

    public function test_unverified_return_denies_an_ungranted_cashier(): void
    {
        $cashier = User::factory()->create(['status' => 'active', 'is_super_admin' => false]);
        $this->actingAs($cashier);
        try {
            app(CreateUnverifiedReturnAction::class)->execute($cashier, $this->data(), 'unverified-qa-denied');
            self::fail('Expected permission denial.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame(0, RetailReturn::query()->where('idempotency_key', 'unverified-qa-denied')->count());
        }
    }

    private function sale(string $number, ?string $phone = null, ?GiftCard $giftCard = null): Sale
    {
        $customerId = null;
        if ($phone !== null) {
            $customerId = Customer::query()->firstOrCreate(['phone_normalized' => $phone], [
                'idempotency_key' => (string) str()->uuid(), 'name_ar' => 'عميل تجريبي', 'name_en' => 'Test Customer',
                'phone_display' => $phone, 'status' => 'active',
                'created_by' => $this->manager->id, 'created_branch_id' => $this->store->branch_id,
                'created_store_id' => $this->store->id,
            ])->id;
        }
        $sale = Sale::query()->create([
            'branch_id' => $this->store->branch_id, 'store_id' => $this->store->id,
            'cashier_id' => $this->manager->id, 'customer_id' => $customerId,
            'document_number' => $number, 'status' => 'approved', 'idempotency_key' => (string) str()->uuid(),
            'subtotal' => '750.00', 'discount_total' => '100.00', 'tax_total' => '0.00', 'total' => '650.00',
            'paid_total' => '650.00', 'change_total' => '0.00', 'cash_rounding_amount' => '0.00',
            'payable_total' => '650.00', 'currency_code' => 'EGP', 'approved_at' => now(),
        ]);
        $sale->lines()->create([
            'product_id' => $this->product->id, 'line_number' => 1, 'item_code' => $this->product->item_code,
            'name_ar' => $this->product->name_ar, 'name_en' => $this->product->name_en,
            'quantity' => '3', 'unit_price' => '250.0000', 'gross_amount' => '750.00',
            'discount_amount' => '100.00', 'net_amount' => '650.00',
        ]);
        $firstMethod = $giftCard ? PaymentMethod::factory()->create(['type' => 'gift_card']) : $this->cash;
        $sale->payments()->create([
            'payment_method_id' => $firstMethod->id, 'method_code' => $firstMethod->code,
            'method_type' => $giftCard ? 'gift_card' : 'cash', 'gift_card_id' => $giftCard?->id,
            'amount' => '500.00', 'tendered_amount' => '500.00',
            'change_amount' => '0.00', 'idempotency_key' => (string) str()->uuid(), 'created_by' => $this->manager->id,
        ]);
        $card = PaymentMethod::factory()->create(['type' => 'card', 'requires_evidence' => false]);
        $sale->payments()->create([
            'payment_method_id' => $card->id, 'method_code' => $card->code,
            'method_type' => 'card', 'amount' => '150.00', 'tendered_amount' => '150.00',
            'change_amount' => '0.00', 'idempotency_key' => (string) str()->uuid(), 'created_by' => $this->manager->id,
        ]);

        return $sale->load('lines', 'payments.paymentMethod');
    }

    public function test_linked_return_uses_original_economics_split_refund_and_remaining_quantity(): void
    {
        $sale = $this->sale('QA-RET-650');
        $calculator = app(RetailRefundCalculator::class);
        $line = $sale->lines->firstOrFail();
        self::assertSame('3.000000', $calculator->remainingQuantity($line));
        $prepared = $calculator->prepare($this->manager, $sale, [[
            'sale_line_id' => $line->id, 'quantity' => '3', 'condition' => 'uninspected',
            'disposition' => 'restock', 'return_store_id' => $this->store->id,
        ]]);
        self::assertSame('750.00', $prepared['totals']['subtotal_refund']);
        self::assertSame('100.00', $prepared['totals']['discount_refund']);
        self::assertSame('650.00', $prepared['totals']['settlement_value']);
        self::assertSame(['500.00', '150.00'], array_column($calculator->defaultAllocations($sale, '650.00'), 'amount'));

        $action = app(RetailReturnAction::class);
        $data = ['source_sale_id' => $sale->id, 'settlement_type' => 'original_tender', 'reason' => 'defective',
            'lines' => [['sale_line_id' => $line->id, 'quantity' => '1', 'condition' => 'uninspected',
                'disposition' => 'restock', 'return_store_id' => $this->store->id]]];
        $created = $action->create($this->manager, $data, 'linked-qa-001');
        self::assertSame($created->id, $action->create($this->manager, $data, 'linked-qa-001')->id);
        self::assertSame('2.000000', $calculator->remainingQuantity($line));
        $submitted = $action->submit($this->manager, $created);
        $approved = $action->approve($this->manager, $submitted);
        $completed = $action->complete($this->manager, $approved, 'linked-qa-complete-001');
        self::assertSame('completed', $completed->status);
        self::assertTrue($completed->is_verified);
        self::assertSame('2.000000', $calculator->remainingQuantity($line));
        self::assertSame(1, StockMovement::query()->where('source_type', RetailReturn::class)->where('source_id', $completed->id)->count());
        self::assertSame(1, RetailReturnSettlement::query()->where('retail_return_id', $completed->id)->count());
        self::assertSame('999.00', $this->product->fresh()->sale_price);
        $this->expectException(ValidationException::class);
        $action->create($this->manager, array_replace($data, ['lines' => [array_replace($data['lines'][0], ['quantity' => '3'])]]), 'linked-qa-too-many');
    }

    public function test_linked_return_restores_only_the_original_gift_card_share_once(): void
    {
        $card = app(GiftCardAction::class)->issue($this->manager, '500.00', (int) $this->store->branch_id,
            (int) $this->store->id, Sale::class, 'gift-linked-sale', 'gift-linked-issue-0001');
        $sale = $this->sale('QA-GIFT-RETURN-650', null, $card);
        app(GiftCardAction::class)->redeem($this->manager, $card, '500.00', 'gift-linked-redeem-0001', Sale::class, (string) $sale->id);
        $line = $sale->lines->firstOrFail();
        $action = app(RetailReturnAction::class);
        $created = $action->create($this->manager, [
            'source_sale_id' => $sale->id, 'settlement_type' => 'original_tender', 'reason' => 'defective',
            'lines' => [['sale_line_id' => $line->id, 'quantity' => '1', 'condition' => 'uninspected',
                'disposition' => 'restock', 'return_store_id' => $this->store->id]],
        ], 'gift-linked-return-0001');
        $completed = $action->complete($this->manager, $action->approve($this->manager, $action->submit($this->manager, $created)), 'gift-linked-complete-0001');
        self::assertSame('completed', $completed->status);
        self::assertSame('216.67', (string) $card->fresh()->balance);
        self::assertSame(1, $card->ledger()->where('event_type', 'refund')->count());
        self::assertSame($card->id, $completed->settlements()->where('gift_card_id', $card->id)->sole()->gift_card_id);
        self::assertSame(1, $card->ledger()->where('event_type', 'refund')->count());
    }

    public function test_receipt_reference_and_customer_date_search_resolve_sales(): void
    {
        $first = $this->sale('QA-SCAN-001', '201055512345');
        $second = $this->sale('QA-SCAN-002', '201055512345');
        $barcode = app(BarcodeSvgRenderer::class)->render((string) $first->document_number, 'code128', 54);
        self::assertStringContainsString('<svg', $barcode);
        self::assertSame(1, Sale::query()->where('document_number', $first->document_number)->count());
        Livewire::actingAs($this->manager)->test(RefundWizard::class)
            ->call('openWizard')->set('lookupTerm', $first->document_number)->call('lookup')
            ->assertHasNoErrors()->assertSet('saleId', $first->id)->assertSet('step', 2);
        $customerSearch = Livewire::actingAs($this->manager)->test(RefundWizard::class)
            ->call('openWizard')->set('customerTerm', '01055512345')->call('searchSales');
        self::assertSame(2, $customerSearch->instance()->render(app(RetailRefundCalculator::class))->getData()['searchResults']->total());
        $nameSearch = Livewire::actingAs($this->manager)->test(RefundWizard::class)
            ->call('openWizard')->set('customerTerm', 'Test Customer')->call('searchSales');
        self::assertSame(2, $nameSearch->instance()->render(app(RetailRefundCalculator::class))->getData()['searchResults']->total());
        Livewire::actingAs($this->manager)->test(RefundWizard::class)
            ->call('openWizard')->call('setSearchMode', 'date')->set('saleDate', now()->format('Y-m-d'))
            ->call('searchSales')->assertSee($first->document_number)->assertSee($second->document_number);
        $filteredDate = Livewire::actingAs($this->manager)->test(RefundWizard::class)
            ->call('openWizard')->call('setSearchMode', 'date')->set('saleDate', now()->format('Y-m-d'))
            ->set('branchFilter', (string) $this->store->branch_id)->call('searchSales');
        self::assertSame(2, $filteredDate->instance()->render(app(RetailRefundCalculator::class))->getData()['searchResults']->total());
    }

    public function test_return_stock_mapping_rejects_a_different_store_and_missing_pos_context(): void
    {
        $sale = $this->sale('QA-MAPPING-001');
        $otherStore = Store::factory()->for($this->store->branch)->create(['company_id' => $this->store->company_id]);
        try {
            app(RetailRefundCalculator::class)->prepare($this->manager, $sale, [[
                'sale_line_id' => $sale->lines->firstOrFail()->id, 'quantity' => '1',
                'condition' => 'uninspected', 'disposition' => 'restock', 'return_store_id' => $otherStore->id,
            ]]);
            self::fail('A return must not post to a different stock location.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('lines.0.return_store_id', $exception->errors());
        }

        DB::table('active_pos_shift_assignments')->where('cashier_id', $this->manager->id)->delete();
        try {
            app(CreateUnverifiedReturnAction::class)->execute($this->manager, $this->data(), 'unverified-no-context');
            self::fail('Unverified return must fail closed without an explicit active POS context.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('unverified', $exception->errors());
            self::assertSame(0, RetailReturn::query()->where('idempotency_key', 'unverified-no-context')->count());
        }
    }
}
