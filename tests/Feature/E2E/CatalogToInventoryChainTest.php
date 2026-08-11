<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Backend business-chain E2E: Product -> approved price -> POS sale ->
 * sale lines -> stock movement -> stock balance -> audit trail, in one
 * continuous transaction chain. Every prior module test (CatalogMasterBehaviorTest,
 * PriceProposalIntegrityTest, RetailSaleIntegrityTest, InventoryWorkflowIntegrityTest)
 * exercises its own module in isolation with a minimal fixture; none traces a
 * single product through the full chain the way a real sale does. This test
 * closes that gap.
 *
 * Scenario IDs: E2E-05 (catalog), E2E-13 (pricing approval), E2E-17 (POS sale),
 * E2E-15 (inventory posting). Requirements: MD-02..05, PRC-02/03, POS-01/02, INV-01/04.
 * This is a backend/Pest business-integration chain, NOT a browser E2E run —
 * see testing/results/PRODUCTION-RELEASE-GATE.md gate #9 for that distinction.
 */
final class CatalogToInventoryChainTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_a_product_flows_from_catalog_creation_through_an_approved_price_to_a_posted_sale(): void
    {
        $this->seedCanonicalAuthorization();
        $branch = $this->branch('CHAIN-BR');
        $store = $this->store($branch, 'CHAIN-ST');

        // --- CATALOG: create category + product (E2E-05) -----------------
        $administrator = $this->administrator('chain-admin');
        $this->actingAs($administrator);

        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CHAIN-CAT', 'name_ar' => 'تصنيف السلسلة', 'name_en' => 'Chain Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'CHAIN-PROD', 'name_ar' => 'منتج السلسلة', 'name_en' => 'Chain Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);
        self::assertSame('active', $product->fresh()->status);

        // --- INVENTORY: opening stock for the product at this store -------
        $opening = app(PostInventoryMovement::class)->execute(
            $product->id, $store->id, '20', 'opening_adjustment', '15.0000', 'CHAIN-OPENING-1',
        );
        self::assertSame('20.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->value('on_hand'));

        // --- PRICING: propose, submit, and approve a store price (E2E-13) -
        // docs/04-roles-permissions.md line 44: Pricing & Labels Approve = "Pricing A
        // when configured" — Pricing Officer is the only approved approver; a second,
        // distinct Pricing Officer user stands in for the approval, not Accountant/Reviewer.
        $proposer = $this->userWith('chain-price-proposer', ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);
        $approver = $this->userWith('chain-price-approver', ['pricing-officer'], branchIds: [$branch->id], storeIds: [$store->id]);

        $this->actingAs($proposer);
        $version = app(CreatePriceProposalAction::class)->execute(
            $product, $store, 'CHAIN-RETAIL', 'أساسي', 'Retail', '25.000', 'product_card', 'CHAIN-CARD-1', null, null, null,
        );
        $version = app(SubmitPriceProposalAction::class)->execute($version);
        self::assertSame(PriceVersionState::Submitted, $version->state);
        self::assertNotNull($version->approval_record_id);

        $this->actingAs($approver);
        $approved = app(ApprovePriceProposalAction::class)->execute($version->fresh());
        self::assertSame(PriceVersionState::Approved, $approved->state);
        self::assertSame('25.000', (string) $approved->lines->firstOrFail()->amount);

        // --- POS: cashier sells the priced product (E2E-17) ---------------
        $cashier = $this->userWith('chain-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'CHAIN-DR', 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->documentSequence('retail_sale', 'SALE-');
        $cash = PaymentMethod::query()->create([
            'code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash',
            'requires_evidence' => false, 'status' => 'active',
        ]);
        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '0.05',
            'value_type' => 'decimal',
            'version' => 1,
            'created_by' => $cashier->id,
        ]);

        $this->actingAs($cashier);
        // 3 x 25.000 = 75.00 — the chain must settle in full to approve.
        $sale = app(RetailSaleAction::class)->create(
            $cashier,
            $store,
            [['product_id' => $product->id, 'quantity' => '3']],
            'CHAIN-SALE-1',
            false,
            [['method' => $cash, 'amount' => '75.00']],
        );

        // --- Assert the seams, not just the endpoints ----------------------
        self::assertSame('approved', $sale->status);
        self::assertNotNull($sale->document_number);
        self::assertSame('75.00', (string) $sale->total); // 3 * 25.000

        $saleLine = $sale->lines->firstOrFail();
        self::assertSame('3.000000', (string) $saleLine->quantity);
        self::assertSame('25.0000', (string) $saleLine->unit_price);
        self::assertNotNull($saleLine->stock_movement_id, 'The sale line must be linked to the stock movement it posted.');

        $saleMovement = StockMovement::query()->findOrFail($saleLine->stock_movement_id);
        self::assertSame('sale', $saleMovement->movement_type);
        self::assertSame('-3.000000', (string) $saleMovement->quantity);
        self::assertSame(Sale::class, $saleMovement->source_type);
        self::assertSame((string) $sale->id, (string) $saleMovement->source_id);
        self::assertSame($saleLine->id, $saleMovement->source_line_id);
        self::assertSame((string) $saleMovement->consumed_cost, (string) $saleLine->consumed_cost, 'The sale line must record the WAC cost the movement actually consumed.');

        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('17.000000', (string) $balance->on_hand, 'On-hand must be exactly the opening quantity minus the sold quantity.');
        self::assertSame('15.0000', (string) $balance->average_cost, 'WAC is unaffected by a sale (exit at existing average cost).');
        self::assertSame(2, StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->count(), 'Exactly the opening movement plus the sale movement, no more.');

        // --- Audit trail spans the whole chain ------------------------------
        self::assertTrue(AuditLog::query()->where('event', 'price_proposal_submitted')->where('source_id', (string) $version->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'price_version_approved')->where('source_id', (string) $version->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'finalize_sale')->where('source_id', (string) $sale->id)->exists(), 'The finalized sale must produce its own audit record, not only its stock movement.');
    }
}
