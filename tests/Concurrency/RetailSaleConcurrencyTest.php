<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Support\Str;

/**
 * CONC-POS-003 (testing/results/CONCURRENCY-SCENARIOS.md) — concurrent sale
 * against one stock balance, plus the same duplicate-submission proof as
 * StockBalanceConcurrencyTest but through the real POS checkout action.
 *
 * RetailSaleAction::finalize() takes a lockForUpdate() on the StockBalance
 * row BEFORE checking sufficiency and holds it for the rest of the
 * surrounding transaction, so a second concurrent sale against the same
 * limited stock must see the post-commit balance, not a stale read — this
 * is what prevents oversell under real concurrency.
 */
final class RetailSaleConcurrencyTest extends ConcurrencyTestCase
{
    /** @return array{0: Product, 1: Store, 2: Branch} */
    private function productStoreWithApprovedPrice(string $tag, string $amount = '20.000'): array
    {
        $this->documentSequence('retail_sale', 'SALE-');
        $branch = $this->branch($tag.'-'.Str::random(6));
        $store = $this->store($branch, $tag.'-'.Str::random(6));
        $admin = $this->administrator($tag.'-admin-'.Str::random(6));
        $this->actingAs($admin);

        $category = app(SaveCategoryAction::class)->execute([
            'code' => $tag.'-CAT-'.Str::random(6), 'name_ar' => 'فئة', 'name_en' => 'Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => $tag.'-'.Str::random(8), 'name_ar' => 'منتج', 'name_en' => 'Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        $proposer = $this->userWith($tag.'-pp-'.Str::random(6), ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);
        $approver = $this->userWith($tag.'-pa-'.Str::random(6), ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->actingAs($proposer);
        $version = app(CreatePriceProposalAction::class)->execute($product, $store, $tag.'-LIST-'.Str::random(6), 'قائمة', 'List', $amount, 'product_card', 'A', null, null, null);
        $version = app(SubmitPriceProposalAction::class)->execute($version);
        $this->actingAs($approver);
        app(ApprovePriceProposalAction::class)->execute($version);
        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '0.05', 'value_type' => 'decimal', 'version' => ((int) PosFinancialSettingVersion::query()->where('key', PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION)->max('version')) + 1, 'created_by' => $approver->id,
        ]);
        PaymentMethod::query()->firstOrCreate(['code' => 'cash'], [
            'code' => 'cash', 'name_ar' => 'Cash', 'name_en' => 'Cash', 'type' => 'cash',
            'requires_evidence' => false, 'status' => 'active',
        ]);

        return [$product, $store, $branch];
    }

    private function cashierWithOpenShift(string $tag, $branch, $store): User
    {
        $cashier = $this->userWith($tag.'-cashier-'.Str::random(6), ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => $tag.'-DR-'.Str::random(6), 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cashier;
    }

    public function test_two_concurrent_sales_against_limited_stock_never_oversell(): void
    {
        $this->seedCanonicalAuthorization();
        [$product, $store, $branch] = $this->productStoreWithApprovedPrice('CONC-POS');
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '10', 'opening_adjustment', '15.0000', 'SETUP-OPENING-'.$product->id);

        $cashierA = $this->cashierWithOpenShift('CONC-POS-A', $branch, $store);
        $cashierB = $this->cashierWithOpenShift('CONC-POS-B', $branch, $store);

        $results = $this->race([
            ['sale', ['user_id' => $cashierA->id, 'store_id' => $store->id, 'lines' => [['product_id' => $product->id, 'quantity' => '6']], 'idempotency_key' => 'RACE-SALE-A-'.Str::random(10)]],
            ['sale', ['user_id' => $cashierB->id, 'store_id' => $store->id, 'lines' => [['product_id' => $product->id, 'quantity' => '6']], 'idempotency_key' => 'RACE-SALE-B-'.Str::random(10)]],
        ]);

        $succeeded = array_filter($results, fn ($r) => $r['ok'] ?? false);
        $failed = array_filter($results, fn ($r) => ! ($r['ok'] ?? false));

        self::assertCount(1, $succeeded, 'Stock (10) covers only one 6-unit sale; exactly one racer must succeed. Results: '.json_encode($results));
        self::assertCount(1, $failed, 'The loser must fail cleanly with insufficient stock, not oversell. Results: '.json_encode($results));
        self::assertSame('InvalidArgumentException', array_values($failed)[0]['exception'], 'The loser must fail with the normal business validation exception, not a raw DB/lock error.');
        self::assertStringContainsString('Insufficient stock', array_values($failed)[0]['message']);

        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('4.000000', (string) $balance->on_hand, 'On-hand must reflect exactly one deduction (10 - 6 = 4), never negative and never double-deducted.');
    }

    public function test_two_concurrent_identical_idempotency_key_sale_submissions_collapse_to_one_sale(): void
    {
        $this->seedCanonicalAuthorization();
        [$product, $store, $branch] = $this->productStoreWithApprovedPrice('CONC-POS-DUP');
        app(PostInventoryMovement::class)->execute($product->id, $store->id, '10', 'opening_adjustment', '15.0000', 'SETUP-OPENING-'.$product->id);
        $cashier = $this->cashierWithOpenShift('CONC-POS-DUP', $branch, $store);

        $duplicateKey = 'RACE-SALE-DUPLICATE-'.Str::random(10);
        $params = ['user_id' => $cashier->id, 'store_id' => $store->id, 'lines' => [['product_id' => $product->id, 'quantity' => '3']], 'idempotency_key' => $duplicateKey];
        $results = $this->race([
            ['sale', $params],
            ['sale', $params],
        ]);

        self::assertTrue($results[0]['ok'] ?? false, 'Worker A must not surface a raw DB error: '.json_encode($results[0]));
        self::assertTrue($results[1]['ok'] ?? false, 'Worker B must not surface a raw DB error: '.json_encode($results[1]));
        self::assertSame($results[0]['result']['sale_id'], $results[1]['result']['sale_id'], 'Both racers must resolve to the SAME sale (idempotent replay), not two sales.');

        self::assertSame(1, Sale::query()->where('idempotency_key', $duplicateKey)->count(), 'Exactly one Sale row for the duplicate key.');
        self::assertSame(1, StockMovement::query()->where('source_type', Sale::class)->where('source_id', $results[0]['result']['sale_id'])->count(), 'Stock must be posted exactly once for the duplicate submission, not twice.');
        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('7.000000', (string) $balance->on_hand, 'Quantity must be deducted exactly once (10 - 3 = 7), not twice.');
    }
}
