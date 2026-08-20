<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\EnrollOfflineDeviceAction;
use App\Modules\Retail\Actions\QueueOfflineTransactionAction;
use App\Modules\Retail\Actions\ResolveOfflineConflictAction;
use App\Modules\Retail\Actions\SyncOfflineTransactionsAction;
use App\Modules\Retail\Enums\OfflineTransactionState;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;
use Throwable;

/**
 * Offline POS contract tests for the owner-authorized Local/Dev remediation.
 *
 * Each named test catches a concrete dangerous regression: turning the flag
 * into a Production bypass, accepting a stolen device token, issuing a final
 * document while offline, replaying a sale twice, silently changing a paid
 * price, or allowing an unscoped reviewer to dispose of a conflict.
 */
final class OfflinePosCoreTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_offline_pos_is_disabled_by_default_at_the_behavior_boundary(): void
    {
        $scenario = $this->scenario();
        config()->set('offline.enabled', false);

        $this->assertDenied(fn () => app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-DEFAULT', 'offline-default-token',
        ));
    }

    public function test_production_denies_offline_device_enrollment_even_when_the_flag_is_enabled(): void
    {
        $scenario = $this->scenario();
        config()->set('offline.enabled', true);
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->assertDenied(fn () => app(EnrollOfflineDeviceAction::class)->execute(
                $scenario['cashier'], $scenario['shift'], 'POS-PRODUCTION-DENIED', 'offline-production-token',
            ));
        } finally {
            app()->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }

    public function test_device_enrollment_hashes_the_token_and_binds_it_to_the_cashier_branch_store_and_shift(): void
    {
        $scenario = $this->enabledScenario();

        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-SCOPE', 'offline-scope-token',
        );

        self::assertNotSame('offline-scope-token', $device->token_hash);
        self::assertTrue(Hash::check('offline-scope-token', $device->token_hash));
        self::assertSame($scenario['cashier']->id, $device->user_id);
        self::assertSame($scenario['shift']->branch_id, $device->branch_id);
        self::assertSame($scenario['store']->id, $device->store_id);
        self::assertSame($scenario['shift']->id, $device->shift_id);
    }

    public function test_a_stolen_device_token_cannot_queue_for_a_different_cashier_and_a_revoked_device_cannot_queue_again(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-REVOKE', 'offline-revocation-token',
        );
        $otherCashier = $this->userWith(
            'offline-other-cashier', ['cashier'], branchIds: [$scenario['shift']->branch_id], storeIds: [$scenario['store']->id],
        );

        $this->assertDenied(fn () => app(QueueOfflineTransactionAction::class)->execute(
            $otherCashier, $device, 'offline-revocation-token', $this->payload($scenario),
        ));

        \DB::table('active_pos_shift_assignments')->where('shift_id', $scenario['shift']->id)->delete();

        $this->assertDenied(fn () => app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-revocation-token', $this->payload($scenario),
        ));

        $device->update(['revoked_at' => now()]);

        $this->assertDenied(fn () => app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device->fresh(), 'offline-revocation-token', $this->payload($scenario),
        ));
        $this->assertDatabaseMissing('offline_transactions', ['local_uuid' => 'offline-retail-transaction-001']);
    }

    public function test_queueing_a_provisional_transaction_creates_no_sale_stock_movement_or_document_number_allocation(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-PROVISIONAL', 'offline-provisional-token',
        );

        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-provisional-token', $this->payload($scenario),
        );

        self::assertSame(OfflineTransactionState::Queued, $transaction->state);
        self::assertNull($transaction->server_sale_id);
        self::assertArrayNotHasKey('customer_id', $transaction->canonical_payload);
        self::assertArrayNotHasKey('cost', $transaction->canonical_payload['lines'][0]);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        self::assertSame(1, DocumentSequence::query()->where('document_type', 'retail_sale')->value('next_value'));
    }

    public function test_a_configured_manual_electronic_payment_can_queue_provisionally_without_posting(): void
    {
        $scenario = $this->enabledScenario();
        $manualElectronic = PaymentMethod::query()->create([
            'code' => 'offline-manual-electronic', 'name_ar' => 'دفع إلكتروني يدوي', 'name_en' => 'Manual electronic',
            'type' => 'manual_electronic', 'offline_eligible' => true, 'requires_evidence' => false, 'status' => 'active',
        ]);
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-MANUAL-ELECTRONIC', 'offline-manual-electronic-token',
        );
        $payload = $this->payload($scenario);
        $payload['payment']['payment_method_id'] = $manualElectronic->id;

        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-manual-electronic-token', $payload,
        );

        self::assertSame(OfflineTransactionState::Queued, $transaction->state);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_offline_queue_rejects_ineligible_credit_wallet_loyalty_and_gift_card_payment_methods(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-PAYMENT-BLOCKS', 'offline-payment-block-token',
        );
        $paymentMethods = [
            ['code' => 'offline-ineligible-electronic', 'type' => 'manual_electronic', 'offline_eligible' => false],
            ['code' => 'offline-credit', 'type' => 'credit', 'offline_eligible' => true],
            ['code' => 'offline-wallet', 'type' => 'wallet', 'offline_eligible' => true],
            ['code' => 'offline-loyalty', 'type' => 'loyalty', 'offline_eligible' => true],
            ['code' => 'offline-gift-card', 'type' => 'gift_card', 'offline_eligible' => true],
        ];

        foreach ($paymentMethods as $method) {
            $payment = PaymentMethod::query()->create([
                ...$method, 'name_ar' => 'وسيلة مرفوضة', 'name_en' => 'Rejected payment', 'requires_evidence' => false, 'status' => 'active',
            ]);
            $payload = $this->payload($scenario);
            $payload['payment']['payment_method_id'] = $payment->id;

            $this->assertDenied(fn () => app(QueueOfflineTransactionAction::class)->execute(
                $scenario['cashier'], $device, 'offline-payment-block-token', $payload,
            ));
        }

        $this->assertDatabaseMissing('offline_transactions', ['local_uuid' => 'offline-retail-transaction-001']);
    }

    public function test_offline_queue_rejects_customer_open_price_special_discount_return_party_and_loyalty_payloads(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-PAYLOAD-BLOCKS', 'offline-payload-block-token',
        );
        $blockedPayloadChanges = [
            ['customer_id' => 999999],
            ['lines' => [[
                'product_id' => $scenario['product']->id, 'quantity' => '1', 'unit_price' => '14.000',
                'price_version_id' => $scenario['price']->price_version_id, 'is_open_price' => true,
            ]]],
            ['special_discount' => ['amount' => '1.00', 'reason' => 'Offline special']],
            ['transaction_type' => 'return'],
            ['transaction_type' => 'party'],
            ['loyalty_redemption' => ['points' => 10]],
            ['lines' => [[
                'product_id' => $scenario['product']->id, 'quantity' => '1', 'unit_price' => '15.0004',
                'price_version_id' => $scenario['price']->price_version_id,
            ]]],
            ['payment' => ['payment_method_id' => $scenario['cash']->id, 'amount' => '14.99']],
        ];

        foreach ($blockedPayloadChanges as $changes) {
            $payload = [...$this->payload($scenario), ...$changes];

            $this->assertDenied(fn () => app(QueueOfflineTransactionAction::class)->execute(
                $scenario['cashier'], $device, 'offline-payload-block-token', $payload,
            ));
        }

        $this->assertDatabaseMissing('offline_transactions', ['local_uuid' => 'offline-retail-transaction-001']);
    }

    public function test_sync_accepts_a_queued_transaction_once_and_an_idempotent_replay_never_posts_a_second_sale(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-REPLAY', 'offline-replay-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-replay-token', $this->payload($scenario),
        );

        $first = app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-replay-token');
        $second = app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device->fresh(), 'offline-replay-token');

        self::assertSame(1, $first['accepted']);
        self::assertSame(0, $second['accepted']);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        self::assertSame(2, DocumentSequence::query()->where('document_type', 'retail_sale')->value('next_value'));
        self::assertNotNull($transaction->fresh()->server_sale_id);
        self::assertSame(OfflineTransactionState::Accepted, $transaction->fresh()->state);
    }

    public function test_a_stale_price_creates_an_explicit_conflict_without_a_sale_or_silent_repricing(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-STALE-PRICE', 'offline-stale-price-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-stale-price-token', $this->payload($scenario),
        );
        PriceLine::query()->whereKey($scenario['price']->id)->update(['amount' => '18.000']);

        $result = app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-stale-price-token');

        self::assertSame(1, $result['conflicted']);
        self::assertSame(OfflineTransactionState::Conflict, $transaction->fresh()->state);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('offline_conflicts', [
            'offline_transaction_id' => $transaction->id,
            'field' => 'price',
            'local_value' => '15.000',
            'server_value' => '18.000',
        ]);
    }

    public function test_insufficient_server_stock_creates_an_explicit_conflict_without_posting_a_sale(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-STOCK', 'offline-stock-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-stock-token', $this->payload($scenario),
        );
        StockBalance::query()->where('product_id', $scenario['product']->id)->where('store_id', $scenario['store']->id)
            ->update(['on_hand' => '0.000000']);

        $result = app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-stock-token');

        self::assertSame(1, $result['conflicted']);
        self::assertSame(OfflineTransactionState::Conflict, $transaction->fresh()->state);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('offline_conflicts', ['offline_transaction_id' => $transaction->id, 'field' => 'stock']);
    }

    public function test_a_closed_shift_creates_an_explicit_conflict_without_posting_a_sale(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-CLOSED-SHIFT', 'offline-closed-shift-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-closed-shift-token', $this->payload($scenario),
        );
        $scenario['shift']->update(['status' => 'closed']);

        $result = app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-closed-shift-token');

        self::assertSame(1, $result['conflicted']);
        self::assertSame(OfflineTransactionState::Conflict, $transaction->fresh()->state);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('offline_conflicts', ['offline_transaction_id' => $transaction->id, 'field' => 'shift']);
    }

    public function test_conflict_disposition_requires_an_authorized_in_scope_reviewer_and_a_reason(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-REVIEW', 'offline-review-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-review-token', $this->payload($scenario),
        );
        PriceLine::query()->whereKey($scenario['price']->id)->update(['amount' => '18.000']);
        app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-review-token');

        $reviewer = $this->userWith(
            'offline-reviewer', ['branch-manager'], branchIds: [$scenario['shift']->branch_id], storeIds: [$scenario['store']->id],
        );
        $unprivileged = $this->userWith(
            'offline-review-denied', ['cashier'], branchIds: [$scenario['shift']->branch_id], storeIds: [$scenario['store']->id],
        );
        $foreignBranch = $this->branch('OFFLINE-FOREIGN-BR');
        $foreignStore = $this->store($foreignBranch, 'OFFLINE-FOREIGN-ST');
        $foreignReviewer = $this->userWith(
            'offline-foreign-reviewer', ['branch-manager'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id],
        );

        $this->assertDenied(fn () => app(ResolveOfflineConflictAction::class)->execute($reviewer, $transaction->fresh(), 'reject', ''));
        $this->assertDenied(fn () => app(ResolveOfflineConflictAction::class)->execute($unprivileged, $transaction->fresh(), 'reject', 'Not authorized.'));
        $this->assertDenied(fn () => app(ResolveOfflineConflictAction::class)->execute($foreignReviewer, $transaction->fresh(), 'reject', 'Wrong branch.'));

        $resolved = app(ResolveOfflineConflictAction::class)->execute(
            $reviewer, $transaction->fresh(), 'reject', 'Customer paid the stale quoted price; reject and retain the audit trail.',
        );

        self::assertSame(OfflineTransactionState::Rejected, $resolved->state);
        $this->assertDatabaseHas('offline_conflicts', [
            'offline_transaction_id' => $transaction->id,
            'disposition' => 'reject',
            'reviewed_by' => $reviewer->id,
            'reason' => 'Customer paid the stale quoted price; reject and retain the audit trail.',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_unsupported_accept_dispositions_leave_the_conflict_unreviewed_until_a_source_correction_workflow_exists(): void
    {
        $scenario = $this->enabledScenario();
        $device = app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'POS-LOCAL-REVIEW-UNSUPPORTED', 'offline-review-unsupported-token',
        );
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-review-unsupported-token', $this->payload($scenario),
        );
        PriceLine::query()->whereKey($scenario['price']->id)->update(['amount' => '18.000']);
        app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, 'offline-review-unsupported-token');
        $reviewer = $this->userWith(
            'offline-review-unsupported-reviewer', ['branch-manager'], branchIds: [$scenario['shift']->branch_id], storeIds: [$scenario['store']->id],
        );

        foreach (['accept', 'accept_with_correction'] as $disposition) {
            $this->assertDenied(fn () => app(ResolveOfflineConflictAction::class)->execute(
                $reviewer, $transaction->fresh(), $disposition, 'No source correction or final sale workflow exists for this disposition.',
            ));

            self::assertSame(OfflineTransactionState::Conflict, $transaction->fresh()->state);
            $this->assertDatabaseHas('offline_conflicts', [
                'offline_transaction_id' => $transaction->id,
                'reviewed_at' => null,
            ]);
        }

        $this->assertDatabaseCount('sales', 0);
    }

    /** @return array{cashier: User, store: Store, shift: PosShift, product: Product, price: PriceLine, cash: PaymentMethod} */
    private function enabledScenario(): array
    {
        config()->set('offline.enabled', true);

        return $this->scenario();
    }

    /** @return array{cashier: User, store: Store, shift: PosShift, product: Product, price: PriceLine, cash: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->grantRolePermissions('cashier', [
            'pos_sales.payment_create',
            'offline_queue_conflicts.create',
            'offline_queue_conflicts.submit',
            'offline_queue_conflicts.view',
        ]);
        $this->grantRolePermissions('branch-manager', [
            'offline_queue_conflicts.view',
            'offline_queue_conflicts.approve',
        ]);
        PosFinancialSettingVersion::query()->firstOrCreate([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'version' => 1,
        ], [
            'value' => '0.01',
            'notes' => 'Offline contract test fixture.',
        ]);
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch('OFFLINE-BR');
        $store = $this->store($branch, 'OFFLINE-ST');
        $cashier = $this->userWith('offline-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'OFFLINE-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id, 'cashier_id' => $cashier->id,
            'opened_by' => $cashier->id, 'status' => 'open', 'opening_cash' => '0.00', 'currency_code' => 'EGP', 'idempotency_key' => 'OFFLINE-SHIFT', 'opened_at' => now(),
        ]);
        \DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = Category::query()->create(['code' => 'OFFLINE-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'OFFLINE-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'OFFLINE-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual', 'approved_by' => $cashier->id,
            'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
        ]);
        $price = PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id, 'branch_id' => $branch->id,
            'amount' => '15.000', 'active_key' => $product->id.':'.$store->id,
        ]);
        $cash = PaymentMethod::query()->create([
            'code' => 'offline-cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'offline_eligible' => true, 'status' => 'active',
        ]);

        return compact('cashier', 'store', 'shift', 'product', 'price', 'cash');
    }

    /** @param array{product: Product, price: PriceLine, cash: PaymentMethod} $scenario @return array<string, mixed> */
    private function payload(array $scenario): array
    {
        return [
            'local_uuid' => 'offline-retail-transaction-001',
            'captured_at' => now()->toAtomString(),
            'price_cached_at' => now()->toAtomString(),
            'lines' => [[
                'product_id' => $scenario['product']->id,
                'quantity' => '1',
                'unit_price' => '15.000',
                'price_version_id' => $scenario['price']->price_version_id,
            ]],
            'payment' => ['payment_method_id' => $scenario['cash']->id, 'amount' => '15.00'],
        ];
    }

    /** @param callable(): mixed $operation */
    private function assertDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('The restricted offline operation was unexpectedly accepted.');
        } catch (Throwable $exception) {
            self::assertTrue(
                $exception instanceof AuthorizationException
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof \InvalidArgumentException
                || $exception instanceof \LogicException,
                'Expected an explicit offline denial, got '.get_debug_type($exception).': '.$exception->getMessage(),
            );
        }
    }

    /** @param array<int, string> $codes */
    private function grantRolePermissions(string $roleCode, array $codes): void
    {
        $permissions = collect($codes)->map(function (string $code): int {
            [$module, $action] = explode('.', $code, 2);

            return Permission::query()->firstOrCreate([
                'code' => $code,
            ], [
                'module' => $module,
                'action' => $action,
                'sensitivity' => 'sensitive',
                'status' => 'active',
            ])->id;
        })->all();

        Role::query()->where('code', $roleCode)->sole()->permissions()->syncWithoutDetaching($permissions);
    }
}
