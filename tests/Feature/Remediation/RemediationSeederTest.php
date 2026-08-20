<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SalePayment;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RemediationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class RemediationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_PASSWORD = 'remediation-test-password-2026';

    private string|false $originalPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPassword = getenv('REMEDIATION_FIXTURE_PASSWORD');
        putenv('REMEDIATION_FIXTURE_PASSWORD');
    }

    protected function tearDown(): void
    {
        $this->originalPassword === false
            ? putenv('REMEDIATION_FIXTURE_PASSWORD')
            : putenv('REMEDIATION_FIXTURE_PASSWORD='.$this->originalPassword);

        parent::tearDown();
    }

    public function test_it_refuses_a_non_remediation_database_before_creating_any_fixture_rows(): void
    {
        config()->set('database.connections.mysql.database', 'untrusted_fixture_database');
        putenv('REMEDIATION_FIXTURE_PASSWORD='.self::FIXTURE_PASSWORD);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('toyjoy_phase1_remediation_20260818');

        app(RemediationSeeder::class)->run();
    }

    public function test_it_requires_a_runtime_fixture_password_before_accessing_the_target_database(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('REMEDIATION_FIXTURE_PASSWORD');

        app(RemediationSeeder::class)->run();
    }

    public function test_the_normal_database_seeder_never_installs_remediation_fixtures(): void
    {
        app(DatabaseSeeder::class)->run();

        self::assertSame(0, User::query()->where('username', 'rem-admin')->count());
        self::assertSame(0, Branch::query()->where('code', 'REM-BRANCH')->count());
        self::assertSame(0, Product::query()->where('item_code', 'REM-NORMAL-001')->count());
    }

    public function test_it_is_idempotent_on_the_authorized_target_database(): void
    {
        putenv('REMEDIATION_FIXTURE_PASSWORD='.self::FIXTURE_PASSWORD);

        app(RemediationSeeder::class)->run();
        app(RemediationSeeder::class)->run();

        self::assertSame(12, User::query()->where('username', 'like', 'rem-%')->count());
        self::assertSame(2, Branch::query()->whereIn('code', ['REM-BRANCH', 'REM-ALT-BRANCH'])->count());
        self::assertSame(4, Store::query()->where('code', 'like', 'REM-%')->count());
        self::assertSame(2, BranchSellingStore::query()->where('status', 'active')->count());
        self::assertSame(2, CashDrawer::query()->where('code', 'like', 'REM-%')->count());
        self::assertSame(2, PosShift::query()->where('idempotency_key', 'like', 'remediation-shift-%')->count());
        self::assertSame(2, Category::query()->where('code', 'like', 'REM-%')->count());
        self::assertSame(1, Supplier::query()->where('code', 'REM-SUPPLIER-001')->count());
        self::assertSame(3, Product::query()->where('item_code', 'like', 'REM-%')->count());
        self::assertSame(1, Customer::query()->where('idempotency_key', 'remediation-customer-001')->count());

        $sales = Store::query()->where('code', 'REM-SALES')->sole();
        $warehouse = Store::query()->where('code', 'REM-WAREHOUSE')->sole();
        $party = Store::query()->where('code', 'REM-PARTY')->sole();
        $normal = Product::query()->where('item_code', 'REM-NORMAL-001')->sole();
        $open = Product::query()->where('item_code', 'REM-OPEN-PRICE-001')->sole();

        // The remediation fixture must explicitly install only the payment
        // methods and document sequences its action-driven workflows need.
        $cash = PaymentMethod::query()->where('code', 'REM-CASH')->sole();
        $manualElectronic = PaymentMethod::query()->where('code', 'REM-MANUAL-ELECTRONIC')->sole();
        self::assertSame('cash', $cash->type);
        self::assertTrue($cash->offline_eligible);
        self::assertSame('manual_electronic', $manualElectronic->type);
        self::assertTrue($manualElectronic->offline_eligible);
        self::assertSame(2, PaymentMethod::query()->whereIn('code', ['REM-CASH', 'REM-MANUAL-ELECTRONIC'])->where('status', 'active')->count());
        self::assertSame(6, DocumentSequence::query()->whereIn('document_type', [
            'retail_sale', 'inventory_adjustment', 'stock_transfer', 'party_booking', 'party_invoice', 'party_operating_order',
        ])->where('status', 'active')->count());

        // Pricing must be an approved maker/checker workflow, including the
        // open-price bounds needed by the dedicated POS story fixture.
        $normalPrice = PriceLine::query()->where('product_id', $normal->id)->where('store_id', $sales->id)->where('active_key', $normal->id.':'.$sales->id)->sole();
        $normalVersion = PriceVersion::query()->with('approvalRecord')->findOrFail($normalPrice->price_version_id);
        self::assertSame('approved', $normalVersion->state->value);
        self::assertSame('25.000', (string) $normalPrice->amount);
        self::assertNotNull($normalVersion->approval_record_id);
        self::assertNotSame($normalVersion->requested_by, $normalVersion->approved_by);
        self::assertSame('approved', $normalVersion->approvalRecord?->approval_state->value);

        $openPrice = PriceLine::query()->where('product_id', $open->id)->where('store_id', $sales->id)->where('active_key', $open->id.':'.$sales->id)->sole();
        $openVersion = PriceVersion::query()->with('approvalRecord')->findOrFail($openPrice->price_version_id);
        self::assertSame('approved', $openVersion->state->value);
        self::assertTrue($openPrice->open_price_allowed);
        self::assertSame('100.000', (string) $openPrice->reference_amount);
        self::assertSame('80.0000', (string) $openPrice->open_price_minimum);
        self::assertSame('120.0000', (string) $openPrice->open_price_maximum);
        self::assertNotSame($openVersion->requested_by, $openVersion->approved_by);
        self::assertSame('approved', $openVersion->approvalRecord?->approval_state->value);

        // Stock must reach the selling store through immutable, source-linked
        // documents. A StockBalance-only fixture is not acceptable.
        $adjustment = InventoryAdjustment::query()->where('idempotency_key', 'remediation-opening-adjustment-001')->with('lines')->sole();
        self::assertSame('approved', $adjustment->status);
        self::assertSame($warehouse->id, $adjustment->store_id);
        self::assertNotSame($adjustment->created_by, $adjustment->approved_by);
        self::assertNotEmpty($adjustment->lines);
        self::assertTrue(StockMovement::query()->where('source_type', InventoryAdjustment::class)->where('source_id', (string) $adjustment->id)->exists());

        $transfer = StockTransfer::query()->where('idempotency_key', 'remediation-stock-transfer-001')->with('lines')->sole();
        self::assertSame('received', $transfer->status);
        self::assertSame($warehouse->id, $transfer->source_store_id);
        self::assertSame($sales->id, $transfer->destination_store_id);
        self::assertNotSame($transfer->requested_by, $transfer->approved_by);
        self::assertTrue(StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', (string) $transfer->id)->where('store_id', $sales->id)->exists());

        // Procurement/inventory browser actions use scoped non-super actors;
        // approval is deliberately separated from the requester and receiver.
        $approver = User::query()->where('username', 'rem-approver')->sole();
        self::assertFalse($approver->is_super_admin);
        self::assertTrue($approver->canAccessBranch($sales->branch_id));
        self::assertTrue($approver->storeScopes()->whereIn('store_id', [$warehouse->id, $sales->id])->count() === 2);
        foreach ([
            'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve', 'purchase_returns.approve',
            'inventory_stock_card.approve', 'transfers.approve', 'stock_counts.reconcile',
        ] as $permission) {
            self::assertTrue($approver->hasPermission($permission), "Scoped procurement approver requires {$permission}.");
        }

        $requester = User::query()->where('username', 'rem-requester')->sole();
        self::assertFalse($requester->is_super_admin);
        self::assertTrue($requester->canAccessStore($warehouse->id));
        self::assertFalse($requester->canAccessStore($sales->id));
        foreach ([
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
            'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit',
            'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit',
            'inventory_stock_card.view', 'inventory_stock_card.create', 'inventory_stock_card.submit',
            'transfers.view', 'transfers.create', 'transfers.submit', 'transfers.dispatch',
            'stock_counts.view', 'stock_counts.create', 'stock_counts.edit', 'stock_counts.submit',
        ] as $permission) {
            self::assertTrue($requester->hasPermission($permission), "Scoped procurement requester requires {$permission}.");
        }

        $receiver = User::query()->where('username', 'rem-receiver')->sole();
        self::assertFalse($receiver->is_super_admin);
        self::assertSame(0, $receiver->branchScopes()->count());
        self::assertTrue($receiver->canAccessStore($sales->id));
        self::assertFalse($receiver->canAccessStore($warehouse->id));
        self::assertTrue($receiver->hasPermission('transfers.receive'));
        self::assertNotSame($requester->id, $approver->id);
        self::assertNotSame($requester->id, $receiver->id);
        self::assertNotSame($approver->id, $receiver->id);

        $returnReason = SupplierReturnReason::query()->where('code', 'REM-SUPPLIER-RETURN-REASON')->sole();
        self::assertTrue($returnReason->is_active);
        foreach (['purchase_order', 'purchase_invoice', 'supplier_return', 'inventory_count'] as $documentType) {
            $sequence = DocumentSequence::query()->where('document_type', $documentType)->where('status', 'active')->sole();
            self::assertTrue(AuditLog::query()->where('event', 'create_document_sequence')->where('source_type', DocumentSequence::class)->where('source_id', (string) $sequence->id)->exists());
        }

        // The repeatable source sale is paid, approved, numbered, and linked
        // to its immutable payment and consumption movement exactly once.
        $sale = Sale::query()->where('idempotency_key', 'remediation-source-sale-001')->with(['lines', 'payments'])->sole();
        self::assertSame('approved', $sale->status);
        self::assertNotNull($sale->document_number);
        self::assertSame($sales->id, $sale->store_id);
        self::assertSame(1, $sale->lines->count());
        self::assertSame(1, $sale->payments->count());
        self::assertSame($cash->id, $sale->payments->sole()->payment_method_id);
        self::assertSame(1, SalePayment::query()->where('sale_id', $sale->id)->count());
        $sourceCustomer = Customer::query()->where('idempotency_key', 'remediation-customer-001')->sole();
        self::assertSame($sourceCustomer->id, $sale->customer_id, 'The fixture sale must be selectable as an approved customer sale for loyalty redemption.');
        $saleLine = $sale->lines->sole();
        self::assertNotNull($saleLine->stock_movement_id);
        self::assertTrue(StockMovement::query()->whereKey($saleLine->stock_movement_id)->where('source_type', Sale::class)->where('source_id', (string) $sale->id)->exists());

        // Gift-card tender remains an online-only payment choice. Its audit
        // event proves the remediation setup used the settings action rather
        // than a direct configuration insert.
        $giftCardMethod = PaymentMethod::query()->where('code', 'REM-GIFT-CARD')->sole();
        self::assertSame('gift_card', $giftCardMethod->type);
        self::assertFalse($giftCardMethod->offline_eligible);
        self::assertSame('active', $giftCardMethod->status);
        self::assertTrue(AuditLog::query()->where('event', 'create_payment_method')->where('source_type', PaymentMethod::class)->where('source_id', (string) $giftCardMethod->id)->exists());

        // returns-gifts.php grants UI entry through the canonical aggregate
        // view permission, while its post routes rely on the Action checks.
        // Keep the cashier grant list to exactly those visible workflows.
        $cashierPermissions = Role::query()->where('code', 'cashier')->sole()->permissions()->pluck('code');
        foreach ([
            'returns_exchanges_gift_instruments.view',
            'gift_receipts.issue', 'gift_receipts.print',
            'returns.create', 'returns.submit', 'returns.complete', 'returns.print',
            'gift_cards.issue', 'gift_cards.redeem', 'gift_cards.print',
            'shifts_cash_movements.submit',
        ] as $permission) {
            self::assertTrue($cashierPermissions->contains($permission), "Cashier gift/return workflow requires {$permission}.");
        }
        $approverPermissions = Role::query()->where('code', 'accountant-reviewer')->sole()->permissions()->pluck('code');
        self::assertTrue($approverPermissions->contains('returns.approve'));
        self::assertNotSame(
            User::query()->where('username', 'rem-cashier')->sole()->id,
            User::query()->where('username', 'rem-reviewer')->sole()->id,
            'The return approver fixture must be a distinct identity from the cashier.',
        );

        // Party prerequisites must remain Party-scoped and usable by later
        // booking, invoice, rental-asset, and operating-order workflows.
        $partyCustomer = Customer::query()->where('idempotency_key', 'remediation-party-customer-001')->with('scopes')->sole();
        self::assertTrue($partyCustomer->scopes->contains('store_id', $party->id));
        $asset = RentalAsset::query()->where('code', 'REM-PARTY-ASSET-001')->sole();
        self::assertSame($party->id, $asset->store_id);
        self::assertSame('reserved', $asset->status);
        $booking = PartyBooking::query()->where('idempotency_key', 'remediation-party-booking-001')->with('invoice')->sole();
        self::assertSame('in_operation', $booking->status);
        self::assertNotNull($booking->confirmed_by);
        self::assertNotNull($booking->confirmed_at);
        self::assertSame($partyCustomer->id, $booking->customer_id);
        self::assertSame($party->id, $booking->store_id);
        $invoice = $booking->invoice;
        self::assertInstanceOf(PartyInvoice::class, $invoice);
        // Releasing the order necessarily freezes its preceding active working
        // invoice; the document remains the same Party working-invoice chain.
        self::assertContains($invoice->state, ['active_working', 'frozen_for_operation']);
        $order = PartyOperatingOrder::query()->where('idempotency_key', 'remediation-party-operating-order-001')->sole();
        self::assertSame('released', $order->status);
        self::assertSame($booking->id, $order->party_booking_id);
        self::assertSame($invoice->id, $order->party_invoice_id);

        // Party browser workflows are exercised by the scoped party manager,
        // never by the remediation super administrator. These are the route
        // and action gates actually used by the displayed Party/asset flows.
        $partyManager = Role::query()->where('code', 'party-manager')->sole();
        $partyPermissions = $partyManager->permissions()->pluck('code');
        foreach ([
            'rental_assets.view', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect',
            'quotations.view', 'quotations.create', 'quotations.print',
            'party_bookings_invoices.view', 'party_bookings_invoices.create', 'party_bookings_invoices.approve', 'party_bookings_invoices.print',
            'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.approve',
        ] as $permission) {
            self::assertTrue($partyPermissions->contains($permission), "Party browser workflow requires {$permission}.");
        }
        $partyActor = User::query()->where('username', 'rem-party')->sole();
        $administrator = User::query()->where('username', 'rem-admin')->sole();
        self::assertTrue($partyActor->roles()->whereKey($partyManager->id)->exists());
        self::assertNotSame($administrator->id, $partyActor->id, 'Party UI fixtures require a scoped manager separate from the administrator.');

        // These two workflows allocate immutable documents through their
        // existing Actions and therefore require active sequences created by
        // SaveLocalSettingsAction rather than a direct counter fixture.
        foreach (['quotation', 'party_payment_receipt', 'party_final_invoice', 'party_final_receipt'] as $documentType) {
            $sequence = DocumentSequence::query()->where('document_type', $documentType)->where('status', 'active')->sole();
            self::assertTrue(AuditLog::query()->where('event', 'create_document_sequence')->where('source_type', DocumentSequence::class)->where('source_id', (string) $sequence->id)->exists());
        }

        // These representative source-linked audit and approval records make
        // actor separation and the fixture provenance independently reviewable.
        self::assertTrue(AuditLog::query()->where('event', 'price_version_approved')->where('source_id', (string) $normalVersion->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'approve_inventory_adjustment')->where('source_id', (string) $adjustment->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'receive_stock_transfer')->where('source_id', (string) $transfer->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'finalize_sale')->where('source_id', (string) $sale->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'party_operating_order_released')->where('source_id', (string) $order->id)->exists());
        self::assertSame(2, ApprovalRecord::query()->whereIn('id', [$normalVersion->approval_record_id, $openVersion->approval_record_id])->where('requester_id', '!=', DB::raw('approver_id'))->count());
    }
}
