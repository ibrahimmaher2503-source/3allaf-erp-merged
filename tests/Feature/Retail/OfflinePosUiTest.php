<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\EnrollOfflineDeviceAction;
use App\Modules\Retail\Actions\QueueOfflineTransactionAction;
use App\Modules\Retail\Actions\SyncOfflineTransactionsAction;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\OfflineTransaction;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * HTTP/UI contracts for the restricted Local/Dev offline POS vertical slice.
 *
 * These tests deliberately use real device, queue, sync, and conflict actions.
 * They protect against a route/view implementation that leaks cross-scope data,
 * presents a final sale as provisional, or bypasses the server's idempotent sync.
 */
final class OfflinePosUiTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    private bool $offlineAuthorizationSeeded = false;

    public function test_readiness_ui_stays_disabled_by_default_and_exposes_only_the_enabled_local_dev_policy_status(): void
    {
        $scenario = $this->scenario('readiness');
        config()->set('offline.enabled', false);

        $this->actingAs($scenario['cashier'])
            ->get('/pos/offline-readiness')
            ->assertOk()
            ->assertSee('Offline POS is disabled')
            ->assertSee('OFF-01')
            ->assertSee('OFF-05');

        config()->set('offline.enabled', true);
        $deviceToken = 'offline-ui-readiness-secret';
        app(EnrollOfflineDeviceAction::class)->execute(
            $scenario['cashier'], $scenario['shift'], 'UI readiness device', $deviceToken,
        );

        $this->actingAs($scenario['cashier'])
            ->get('/pos/offline-readiness')
            ->assertOk()
            ->assertSee('Offline POS is enabled')
            ->assertSee('OFF-01..OFF-05-local-dev-v1')
            ->assertDontSee($deviceToken);
    }

    public function test_queue_page_shows_only_the_authenticated_cashiers_provisional_references_without_final_or_sensitive_sale_data(): void
    {
        $scenario = $this->enabledScenario('queue-own');
        $device = $this->deviceFor($scenario, 'UI queue own device', 'offline-ui-own-token');
        app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, 'offline-ui-own-token', $this->payload($scenario, 'offline-ui-own-reference'),
        );

        $foreign = $this->enabledScenario('queue-foreign');
        $foreignDevice = $this->deviceFor($foreign, 'UI queue foreign device', 'offline-ui-foreign-token');
        app(QueueOfflineTransactionAction::class)->execute(
            $foreign['cashier'], $foreignDevice, 'offline-ui-foreign-token', $this->payload($foreign, 'offline-ui-foreign-reference'),
        );

        $this->actingAs($scenario['cashier'])
            ->get('/pos/offline/queue')
            ->assertOk()
            ->assertSee('offline-ui-own-reference')
            ->assertDontSee('offline-ui-foreign-reference')
            ->assertDontSee('SALE-000001')
            ->assertDontSee('offline-ui-own-token')
            ->assertDontSee('customer_id')
            ->assertDontSee('wallet_id')
            ->assertDontSee('loyalty_redemption')
            ->assertDontSee('expected_cash')
            ->assertDontSee('average_cost');
    }

    public function test_queue_and_sync_http_endpoints_require_the_bound_cashier_and_preserve_real_action_idempotency(): void
    {
        $scenario = $this->enabledScenario('http');
        $token = 'offline-ui-http-token';
        $device = $this->deviceFor($scenario, 'UI HTTP device', $token);
        $request = [
            'offline_device_id' => $device->id,
            'token' => $token,
            'payload' => $this->payload($scenario, 'offline-ui-http-reference'),
        ];
        $unscoped = $this->userWith('offline-ui-http-denied', ['cashier']);

        $this->actingAs($unscoped)
            ->post('/pos/offline/queue', $request)
            ->assertForbidden();

        $this->actingAs($scenario['cashier'])
            ->post('/pos/offline/queue', $request)
            ->assertRedirect('/pos/offline/queue');
        $this->actingAs($scenario['cashier'])
            ->post('/pos/offline/queue', $request)
            ->assertRedirect('/pos/offline/queue');
        $this->assertDatabaseCount('offline_transactions', 1);
        $this->assertDatabaseCount('sales', 0);

        $syncRequest = ['offline_device_id' => $device->id, 'token' => $token];
        $this->actingAs($scenario['cashier'])
            ->post('/pos/offline/sync', $syncRequest)
            ->assertRedirect('/pos/offline/queue');
        $this->actingAs($scenario['cashier'])
            ->post('/pos/offline/sync', $syncRequest)
            ->assertRedirect('/pos/offline/queue');

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $acceptedTransaction = OfflineTransaction::query()->where('local_uuid', 'offline-ui-http-reference')->sole();
        self::assertNotNull($acceptedTransaction->server_sale_id);
        $this->assertDatabaseHas('offline_transactions', [
            'id' => $acceptedTransaction->id,
            'state' => 'accepted',
        ]);
    }

    public function test_conflict_list_denies_cashiers_scopes_reviewers_and_requires_a_reasoned_disposition_post(): void
    {
        $scenario = $this->enabledScenario('conflict-own');
        $transaction = $this->stalePriceConflict($scenario, 'offline-ui-conflict-own', 'offline-ui-conflict-own-token');

        $foreign = $this->enabledScenario('conflict-foreign');
        $this->stalePriceConflict($foreign, 'offline-ui-conflict-foreign', 'offline-ui-conflict-foreign-token');

        $reviewer = $this->userWith(
            'offline-ui-reviewer', ['branch-manager'], branchIds: [$scenario['shift']->branch_id], storeIds: [$scenario['store']->id],
        );

        $this->actingAs($scenario['cashier'])
            ->get('/offline/conflicts')
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->get('/offline/conflicts')
            ->assertOk()
            ->assertSee('offline-ui-conflict-own')
            ->assertDontSee('offline-ui-conflict-foreign');

        $this->actingAs($reviewer)
            ->post('/offline/conflicts/'.$transaction->id.'/resolve', [
                'disposition' => 'reject',
                'reason' => '',
            ])
            ->assertRedirect('/offline/conflicts/'.$transaction->id)
            ->assertSessionHasErrors('reason');

        $this->actingAs($reviewer)
            ->get('/offline/conflicts/'.$transaction->id)
            ->assertOk()
            ->assertSee('Acceptance and source correction are unavailable in this Local/Dev slice.')
            ->assertDontSee('value="accept"')
            ->assertDontSee('value="accept_with_correction"');

        $this->actingAs($reviewer)
            ->post('/offline/conflicts/'.$transaction->id.'/resolve', [
                'disposition' => 'accept',
                'reason' => 'No source correction or final sale workflow exists for this disposition.',
            ])
            ->assertRedirect('/offline/conflicts/'.$transaction->id)
            ->assertSessionHasErrors('disposition');

        $this->assertDatabaseHas('offline_conflicts', [
            'offline_transaction_id' => $transaction->id,
            'reviewed_at' => null,
        ]);
        $this->assertDatabaseHas('offline_transactions', [
            'id' => $transaction->id,
            'state' => 'conflict',
        ]);

        $this->actingAs($reviewer)
            ->post('/offline/conflicts/'.$transaction->id.'/resolve', [
                'disposition' => 'reject',
                'reason' => 'The server price changed after the customer paid; preserve the conflict for correction.',
            ])
            ->assertRedirect('/offline/conflicts');

        $this->assertDatabaseHas('offline_conflicts', [
            'offline_transaction_id' => $transaction->id,
            'disposition' => 'reject',
            'reviewed_by' => $reviewer->id,
            'reason' => 'The server price changed after the customer paid; preserve the conflict for correction.',
        ]);
    }

    /** @return array{cashier: User, store: Store, shift: PosShift, product: Product, price: PriceLine, cash: PaymentMethod} */
    private function enabledScenario(string $key): array
    {
        config()->set('offline.enabled', true);

        return $this->scenario($key);
    }

    /** @return array{cashier: User, store: Store, shift: PosShift, product: Product, price: PriceLine, cash: PaymentMethod} */
    private function scenario(string $key): array
    {
        if (! $this->offlineAuthorizationSeeded) {
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
                'notes' => 'Offline UI contract test fixture.',
            ]);
            $this->documentSequence('retail_sale', 'SALE-');
            $this->offlineAuthorizationSeeded = true;
        }

        $key = strtoupper($key);
        $branch = $this->branch('OFFUI-'.$key.'-BR');
        $store = $this->store($branch, 'OFFUI-'.$key.'-ST');
        $cashier = $this->userWith('offline-ui-'.strtolower($key), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => $cashier->id,
            'code' => 'OFFUI-'.$key.'-DR',
            'name_ar' => 'درج',
            'name_en' => 'Offline UI drawer',
            'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0.00',
            'currency_code' => 'EGP',
            'idempotency_key' => 'OFFUI-'.$key.'-SHIFT',
            'opened_at' => now(),
        ]);
        \DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $category = Category::query()->create([
            'code' => 'OFFUI-'.$key.'-CAT',
            'name_ar' => 'فئة',
            'name_en' => 'Offline UI category',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'item_code' => 'OFFUI-'.$key.'-PROD',
            'name_ar' => 'لعبة',
            'name_en' => 'Offline UI toy',
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'on_hand' => '5.000000',
            'reserved' => '0.000000',
            'in_transit' => '0.000000',
            'average_cost' => '10.000000',
            'total_value' => '50.000000',
            'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id,
            'code' => 'OFFUI-'.$key.'-PRICE',
            'name_ar' => 'سعر',
            'name_en' => 'Offline UI price',
            'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id,
            'version' => 1,
            'state' => 'approved',
            'source_type' => 'manual',
            'approved_by' => $cashier->id,
            'approved_at' => now(),
            'effective_from' => now()->subMinute(),
            'lock_version' => 1,
        ]);
        $price = PriceLine::query()->create([
            'price_version_id' => $version->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'amount' => '15.000',
            'active_key' => $product->id.':'.$store->id,
        ]);
        $cash = PaymentMethod::query()->create([
            'code' => 'OFFUI-'.$key.'-CASH',
            'name_ar' => 'نقدي',
            'name_en' => 'Cash',
            'type' => 'cash',
            'offline_eligible' => true,
            'status' => 'active',
        ]);

        return compact('cashier', 'store', 'shift', 'product', 'price', 'cash');
    }

    /** @param array{cashier: User, shift: PosShift} $scenario */
    private function deviceFor(array $scenario, string $name, string $token): OfflineDevice
    {
        return app(EnrollOfflineDeviceAction::class)->execute($scenario['cashier'], $scenario['shift'], $name, $token);
    }

    /** @param array{product: Product, price: PriceLine, cash: PaymentMethod} $scenario @return array<string, mixed> */
    private function payload(array $scenario, string $reference): array
    {
        return [
            'local_uuid' => $reference,
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

    /** @param array{cashier: User, price: PriceLine, shift: PosShift} $scenario */
    private function stalePriceConflict(array $scenario, string $reference, string $token): OfflineTransaction
    {
        $device = $this->deviceFor($scenario, 'UI conflict '.$reference, $token);
        $transaction = app(QueueOfflineTransactionAction::class)->execute(
            $scenario['cashier'], $device, $token, $this->payload($scenario, $reference),
        );
        PriceLine::query()->whereKey($scenario['price']->id)->update(['amount' => '18.000']);
        app(SyncOfflineTransactionsAction::class)->execute($scenario['cashier'], $device, $token);

        return $transaction->fresh();
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
