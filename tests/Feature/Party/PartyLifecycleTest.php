<?php

declare(strict_types=1);

namespace Tests\Feature\Party;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Actions\PostPartyWalletEntryAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\ProductWalletLedger;
use App\Modules\Customer\Support\PartyWalletBalance;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Party\Actions\CompletePartyOperatingOrderAction;
use App\Modules\Party\Actions\ConfirmPartyBookingAction;
use App\Modules\Party\Actions\CreatePartyBookingAction;
use App\Modules\Party\Actions\CreatePartyOperatingOrderAction;
use App\Modules\Party\Actions\FinalizePartyInvoiceAction;
use App\Modules\Party\Actions\IssuePartyConsumableAction;
use App\Modules\Party\Actions\RecordPartyPaymentAction;
use App\Modules\Party\Actions\ReleasePartyOperatingOrderAction;
use App\Modules\Party\Actions\ReturnPartyConsumableAction;
use App\Modules\Party\Actions\SavePartyInvoiceAction;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyPayment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** @group party @group us-025 @group us-026 @group us-027 */
final class PartyLifecycleTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function seedCanonicalAuthorization(): void
    {
        $this->seed(ProductionSeeder::class);

        $partyManager = Role::query()->where('code', 'party-manager')->firstOrFail();
        $partyManager->permissions()->sync(Permission::query()->where('status', 'active')->pluck('id')->all());
    }

    public function test_party_booking_creates_party_only_working_invoice_and_blocks_retail_lines(): void
    {
        $scenario = $this->scenario();
        $auditBefore = AuditLog::query()->where('event', 'party_booking_created')->count();

        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [
                ['line_type' => 'service', 'description' => 'Birthday package', 'quantity' => '1', 'unit_price' => '1000.00'],
                ['line_type' => 'consumable', 'description' => 'Party cups', 'quantity' => '2', 'unit_price' => '10.00', 'product_id' => $scenario['product']->id],
            ],
        ]);

        self::assertInstanceOf(PartyBooking::class, $booking);
        self::assertSame('draft', $booking->status);
        self::assertSame('draft', $booking->invoice->state);
        self::assertSame('service', $booking->invoice->lines->first()->line_type);
        self::assertSame('1020.0000', (string) $booking->invoice->total_amount);
        self::assertSame(0, $booking->invoice->retailLines()->count());
        self::assertSame($auditBefore + 1, AuditLog::query()->where('event', 'party_booking_created')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'retail', 'description' => 'Retail toy', 'quantity' => '1', 'unit_price' => '50.00']],
        ]);
    }

    public function test_working_invoice_landing_lists_scoped_invoices_without_requiring_an_id(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);

        foreach ([
            'working' => ['Working invoices', route('parties.invoices.show', $booking->invoice)],
            'payments' => ['Party payment invoices', route('parties.invoices.payments', $booking->invoice)],
            'settlement' => ['Party settlement invoices', route('parties.invoices.settle', $booking->invoice)],
        ] as $mode => [$heading, $target]) {
            $this->get(route('parties.invoices.index', ['mode' => $mode, 'q' => $booking->invoice->invoice_number]))
                ->assertOk()
                ->assertSee($heading)
                ->assertSee($booking->invoice->invoice_number)
                ->assertSee($target, false);
        }

        $this->get(route('parties.invoices.index', ['mode' => 'unknown']))->assertNotFound();
    }

    public function test_confirmed_party_booking_rechecks_overlapping_schedule_and_invoice_is_editable_until_final_close(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);

        $overlap = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'party_date' => now()->addDays(5)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '21:00',
        ]);
        $overlap->forceFill(['starts_at' => $booking->starts_at, 'ends_at' => $booking->ends_at])->save();
        $this->expectException(InvalidArgumentException::class);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $overlap);
    }

    public function test_party_payments_are_multiple_idempotent_source_linked_and_reject_overpayment(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $invoice = $booking->invoice->fresh();

        $first = app(RecordPartyPaymentAction::class)->execute($scenario['manager'], $invoice, $scenario['cash'], '400.00', 'PARTY-PAY-1');
        $replay = app(RecordPartyPaymentAction::class)->execute($scenario['manager'], $invoice, $scenario['cash'], '400.00', 'PARTY-PAY-1');
        $second = app(RecordPartyPaymentAction::class)->execute($scenario['manager'], $invoice, $scenario['cash'], '600.00', 'PARTY-PAY-2');

        self::assertInstanceOf(PartyPayment::class, $first);
        self::assertTrue($first->is($replay));
        self::assertNotSame($first->id, $second->id);
        self::assertSame(2, PartyPayment::query()->where('party_invoice_id', $invoice->id)->count());
        self::assertSame('Payment on Account for Party Invoice No. '.$invoice->invoice_number, $first->receipt_label);

        $this->expectException(InvalidArgumentException::class);
        app(RecordPartyPaymentAction::class)->execute($scenario['manager'], $invoice, $scenario['cash'], '1.00', 'PARTY-PAY-3');
    }

    public function test_operating_order_issue_and_referenced_return_reconcile_party_store_stock_and_complete_is_immutable(): void
    {
        $scenario = $this->scenario();
        $auditBefore = AuditLog::query()->where('event', 'party_operating_order_completed')->count();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'consumable', 'description' => 'Cups', 'quantity' => '5', 'unit_price' => '0.00', 'product_id' => $scenario['product']->id]],
        ]);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $order = app(CreatePartyOperatingOrderAction::class)->execute($scenario['manager'], $booking->fresh(), $booking->fresh()->invoice, 'PARTY-ORDER-1');
        app(ReleasePartyOperatingOrderAction::class)->execute($scenario['manager'], $order);
        $order = $order->fresh(['lines']);
        $line = $order->lines->sole();

        $issue = app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '5.000000', 'PARTY-ISSUE-1');
        self::assertTrue($issue->is(app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '5.000000', 'PARTY-ISSUE-1')));
        $returned = app(ReturnPartyConsumableAction::class)->execute($scenario['manager'], $issue, $line, '2.000000', 'PARTY-RETURN-1');
        self::assertTrue($returned->is(app(ReturnPartyConsumableAction::class)->execute($scenario['manager'], $issue, $line, '2.000000', 'PARTY-RETURN-1')));
        app(CompletePartyOperatingOrderAction::class)->execute($scenario['manager'], $order->fresh());

        self::assertSame('7.000000', (string) $scenario['partyStockBalance']->fresh()->on_hand);
        self::assertSame(2, StockMovement::query()->where('source_type', 'party_consumable')->count());
        self::assertSame('completed', $order->fresh()->status);
        self::assertSame($auditBefore + 1, AuditLog::query()->where('event', 'party_operating_order_completed')->count());

        $this->expectException(InvalidArgumentException::class);
        app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order->fresh(), $line->fresh(), '1.000000', 'PARTY-ISSUE-AFTER-COMPLETE');
    }

    public function test_fully_consumed_party_stock_can_be_recorded_and_the_order_can_complete_without_a_return(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'consumable', 'description' => 'Cups', 'quantity' => '5', 'unit_price' => '0.00', 'product_id' => $scenario['product']->id]],
        ]);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $order = app(CreatePartyOperatingOrderAction::class)->execute($scenario['manager'], $booking->fresh(), $booking->fresh()->invoice, 'PARTY-ACTUAL-ORDER');
        app(ReleasePartyOperatingOrderAction::class)->execute($scenario['manager'], $order);
        $line = $order->fresh('lines')->lines->sole();
        app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '5.000000', 'PARTY-ACTUAL-ISSUE');

        $this->post('/parties/orders/'.$order->id.'/actuals', [
            'line_id' => $line->id,
            'consumed_quantity' => '5.000000',
        ])->assertRedirect();

        self::assertSame('5.000000', (string) $line->fresh()->consumed_quantity);
        self::assertSame('completed', app(CompletePartyOperatingOrderAction::class)->execute($scenario['manager'], $order->fresh('lines'))->status);
    }

    public function test_draft_booking_cannot_bypass_confirmation_and_operations_during_final_close(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('confirmed');

        app(FinalizePartyInvoiceAction::class)->execute($scenario['manager'], $booking->invoice, 'PARTY-DRAFT-CLOSE');
    }

    public function test_confirmed_booking_can_be_rescheduled_with_history_and_must_be_reconfirmed(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $newDate = now()->addDays(9)->toDateString();

        $this->post('/parties/bookings/'.$booking->id.'/reschedule', [
            'party_date' => $newDate,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'timezone' => 'UTC',
            'location' => 'Garden room',
            'reason' => 'Customer requested a new date.',
        ])->assertRedirect();

        $booking->refresh();
        self::assertSame('rescheduled', $booking->status);
        self::assertSame($newDate, $booking->party_date->toDateString());
        self::assertSame('Customer requested a new date.', $booking->change_reason);
        self::assertNull($booking->confirmed_at);
    }

    public function test_draft_booking_can_be_cancelled_with_a_reason_and_its_invoice_is_locked(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);

        $this->post('/parties/bookings/'.$booking->id.'/cancel', [
            'reason' => 'Customer cancelled before confirmation.',
        ])->assertRedirect();

        self::assertSame('cancelled', $booking->fresh()->status);
        self::assertSame('cancelled', $booking->invoice->fresh()->state);
    }

    public function test_party_lifecycle_controls_are_available_from_the_relevant_ui(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'consumable', 'description' => 'Cups', 'quantity' => '5', 'unit_price' => '0.00', 'product_id' => $scenario['product']->id]],
        ]);

        $this->get(route('parties.bookings.show', $booking))
            ->assertOk()
            ->assertSee(route('parties.bookings.reschedule', $booking), false)
            ->assertSee(route('parties.bookings.cancel', $booking), false);

        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $order = app(CreatePartyOperatingOrderAction::class)->execute($scenario['manager'], $booking->fresh(), $booking->fresh()->invoice, 'PARTY-UI-ORDER');
        app(ReleasePartyOperatingOrderAction::class)->execute($scenario['manager'], $order);
        $line = $order->fresh('lines')->lines->sole();
        app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '5.000000', 'PARTY-UI-ISSUE');

        $this->get(route('parties.orders.show', $order))
            ->assertOk()
            ->assertSee(route('parties.orders.actuals', $order), false)
            ->assertSee('Record actual consumption');
    }

    public function test_party_booking_and_invoice_forms_offer_catalog_products_instead_of_raw_ids(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'consumable', 'description' => 'Cups', 'quantity' => '2', 'unit_price' => '10.00', 'product_id' => $scenario['product']->id]],
        ]);

        $this->get(route('parties.bookings.create'))
            ->assertOk()
            ->assertSee($scenario['product']->item_code)
            ->assertSee($scenario['product']->name_en)
            ->assertDontSee('Product ID for consumable');
        $this->get(route('parties.invoices.show', $booking->invoice))
            ->assertOk()
            ->assertSee($scenario['product']->item_code)
            ->assertSee($scenario['product']->name_en)
            ->assertSee('Add another Party line')
            ->assertSee('name="lines[1][description]"', false)
            ->assertDontSee('Consumable product ID');
    }

    public function test_party_lines_accept_only_active_catalog_products_on_consumables(): void
    {
        $scenario = $this->scenario();

        try {
            $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
                'lines' => [['line_type' => 'consumable', 'description' => 'Unknown cups', 'quantity' => '2', 'unit_price' => '10.00', 'product_id' => PHP_INT_MAX]],
            ]);
            self::fail('An unknown product must not be accepted by a Party consumable line.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('active catalog product', $exception->getMessage());
        }

        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        try {
            app(SavePartyInvoiceAction::class)->execute($scenario['manager'], $booking->invoice, [
                'lines' => [['line_type' => 'service', 'description' => 'Party host', 'quantity' => '1', 'unit_price' => '10.00', 'product_id' => $scenario['product']->id]],
            ]);
            self::fail('A non-consumable Party line must not retain a catalog product.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Only consumable', $exception->getMessage());
        }
    }

    public function test_view_only_party_user_does_not_receive_mutating_or_print_controls(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $role = Role::query()->create([
            'code' => 'party-viewer-'.Str::lower(Str::random(6)),
            'name_ar' => 'Party viewer',
            'name_en' => 'Party viewer',
            'status' => 'active',
        ]);
        $role->permissions()->sync([
            Permission::query()->where('code', 'party_bookings_invoices.view')->value('id'),
        ]);
        $viewer = $this->userWith('party-viewer-'.Str::random(6), [$role->code], branchIds: [$scenario['partyStore']->branch_id], storeIds: [$scenario['partyStore']->id]);
        $this->actingAs($viewer);

        $this->get(route('parties.bookings.index'))
            ->assertOk()
            ->assertDontSee(route('parties.bookings.create'), false);
        $this->get(route('parties.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee(route('parties.bookings.reschedule', $booking), false)
            ->assertDontSee(route('parties.bookings.cancel', $booking), false)
            ->assertDontSee(route('parties.invoices.print', $booking->invoice), false);
        $this->get(route('parties.invoices.show', $booking->invoice))
            ->assertOk()
            ->assertDontSee('action="'.route('parties.invoices.update', $booking->invoice).'"', false)
            ->assertDontSee(route('parties.invoices.settle', $booking->invoice), false)
            ->assertDontSee(route('parties.invoices.print', $booking->invoice), false);
        $this->get(route('parties.invoices.payments', $booking->invoice))
            ->assertOk()
            ->assertDontSee('action="'.route('parties.invoices.payments.store', $booking->invoice).'"', false);
    }

    public function test_party_movement_idempotency_keys_cannot_be_reused_with_different_quantities(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer'], [
            'lines' => [['line_type' => 'consumable', 'description' => 'Cups', 'quantity' => '5', 'unit_price' => '0.00', 'product_id' => $scenario['product']->id]],
        ]);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $order = app(CreatePartyOperatingOrderAction::class)->execute($scenario['manager'], $booking->fresh(), $booking->fresh()->invoice, 'PARTY-ORDER-IDEMPOTENCY');
        app(ReleasePartyOperatingOrderAction::class)->execute($scenario['manager'], $order);
        $order = $order->fresh(['lines']);
        $line = $order->lines->sole();
        $issue = app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '5.000000', 'PARTY-ISSUE-IDEMPOTENCY');

        try {
            app(IssuePartyConsumableAction::class)->execute($scenario['manager'], $order, $line, '4.000000', 'PARTY-ISSUE-IDEMPOTENCY');
            self::fail('A Party issue idempotency key must not accept different data.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('already used', $exception->getMessage());
        }

        app(ReturnPartyConsumableAction::class)->execute($scenario['manager'], $issue, $line, '2.000000', 'PARTY-RETURN-IDEMPOTENCY');

        try {
            app(ReturnPartyConsumableAction::class)->execute($scenario['manager'], $issue, $line, '1.000000', 'PARTY-RETURN-IDEMPOTENCY');
            self::fail('A Party return idempotency key must not accept different data.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('already used', $exception->getMessage());
        }
    }

    public function test_final_close_is_atomic_party_wallet_only_and_immutable(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $invoice = $booking->fresh()->invoice;
        app(RecordPartyPaymentAction::class)->execute($scenario['manager'], $invoice, $scenario['cash'], '1000.00', 'PARTY-CLOSE-PAY');

        $final = app(FinalizePartyInvoiceAction::class)->execute($scenario['manager'], $invoice->fresh(), 'PARTY-CLOSE-1');
        self::assertSame('final', $final->state);
        self::assertNotNull($final->final_invoice_number);
        self::assertSame('1000.0000', (string) $final->paid_amount);
        self::assertSame('0.0000', (string) $final->balance_due);
        self::assertSame('closed', $booking->fresh()->status);
        self::assertSame(0, ProductWalletLedger::query()->where('customer_id', $scenario['customer']->id)->count());

        $this->expectException(InvalidArgumentException::class);
        app(SavePartyInvoiceAction::class)->execute($scenario['manager'], $final, ['notes' => 'late edit']);
    }

    public function test_final_close_can_apply_party_wallet_credit_without_touching_product_wallet(): void
    {
        $scenario = $this->scenario();
        $booking = $this->createBooking($scenario['manager'], $scenario['partyStore'], $scenario['customer']);
        $this->actingAs($scenario['manager']);
        app(ConfirmPartyBookingAction::class)->execute($scenario['manager'], $booking);
        $invoice = $booking->fresh()->invoice;
        $before = app(PartyWalletBalance::class)->forCustomer($scenario['customer'], $scenario['manager']);
        self::assertSame('0.0000', $before);
        app(PostPartyWalletEntryAction::class)->credit($scenario['manager'], $scenario['customer'], $scenario['partyStore'], '600.0000', 'party_invoice', (string) $invoice->id, 'PARTY-WALLET-CREDIT');
        self::assertSame('600.0000', app(PartyWalletBalance::class)->forCustomer($scenario['customer'], $scenario['manager']));

        $final = app(FinalizePartyInvoiceAction::class)->execute($scenario['manager'], $invoice, 'PARTY-WALLET-CLOSE');
        self::assertSame('600.0000', (string) $final->wallet_applied_amount);
        self::assertSame('400.0000', (string) $final->balance_due);
        self::assertSame('0.0000', app(PartyWalletBalance::class)->forCustomer($scenario['customer'], $scenario['manager']));
        self::assertSame(0, ProductWalletLedger::query()->where('customer_id', $scenario['customer']->id)->count());
    }

    /** @return array{manager: User, partyStore: Store, customer: Customer, product: Product, partyStockBalance: StockBalance, cash: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('party_booking', 'PB-');
        $this->documentSequence('party_invoice', 'PI-');
        $this->documentSequence('party_payment_receipt', 'PPR-');
        $this->documentSequence('party_final_invoice', 'PFI-');
        $this->documentSequence('party_final_receipt', 'PFR-');
        $this->documentSequence('party_operating_order', 'POO-');

        $branch = $this->branch('PARTY-BR-'.Str::random(6));
        $partyStore = $this->store($branch, 'PARTY-ST-'.Str::random(6), 'party');
        $manager = $this->userWith('party-manager-'.Str::random(6), ['party-manager'], branchIds: [$branch->id], storeIds: [$partyStore->id]);
        $policyAdmin = $this->administrator('party-policy-'.Str::random(6));
        $this->configureCustomerLoyaltyPolicies($policyAdmin);
        $this->configureWalletPolicies($policyAdmin);
        $customer = $this->createTestCustomer($manager, $partyStore, '010'.random_int(10000000, 99999999));
        $category = Category::query()->create(['code' => 'PARTY-CAT-'.Str::random(6), 'name_ar' => 'Ø§Ø®ØªØ¨Ø§Ø±', 'name_en' => 'Party consumables', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'PARTY-PROD-'.Str::random(6), 'name_ar' => 'Ù…ÙˆØ§Ø¯', 'name_en' => 'Cups', 'category_id' => $category->id, 'status' => 'active']);
        $partyStockBalance = StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $partyStore->id, 'on_hand' => '10', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '1', 'total_value' => '10', 'version' => 1]);
        $cash = PaymentMethod::query()->create(['code' => 'party-cash-'.Str::random(6), 'name_ar' => 'Ù†Ù‚Ø¯', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);

        return compact('manager', 'partyStore', 'customer', 'product', 'partyStockBalance', 'cash');
    }

    private function createBooking(User $manager, Store $store, Customer $customer, array $overrides = []): PartyBooking
    {
        $this->actingAs($manager);
        $start = now()->addDays(5)->setTime(14, 0);

        return app(CreatePartyBookingAction::class)->execute($manager, $store, [
            'customer_id' => $customer->id,
            'party_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHours(3)->format('H:i'),
            'timezone' => 'UTC',
            'location' => 'Main party room',
            'primary_contact' => '01012345678',
            'notes' => 'Test party',
            'idempotency_key' => 'PARTY-BOOKING-'.Str::uuid(),
            'lines' => [['line_type' => 'service', 'description' => 'Birthday package', 'quantity' => '1', 'unit_price' => '1000.00']],
            ...$overrides,
        ]);
    }
}
