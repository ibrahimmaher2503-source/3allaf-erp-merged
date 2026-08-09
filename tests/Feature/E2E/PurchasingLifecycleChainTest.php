<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Backend business-chain E2E: Supplier -> Purchase Order -> Approval -> Invoice
 * (receipt/stock/WAC) -> Supplier Return -> Audit, tracing one supplier/product/
 * store through the full purchasing lifecycle in a single continuous flow. Every
 * prior test (PurchasingLifecycleIntegrityTest) exercises one stage in isolation
 * with its own throwaway fixture; none proves the chain end-to-end the way a real
 * procurement cycle runs, or that separation-of-duties and idempotency both hold
 * across the whole chain rather than one action at a time.
 *
 * Scenario ID: E2E-10/11/12 (PO, invoice/receipt, supplier return).
 * Requirements: PUR-03..06, PRC-03, NFR-01/02/06.
 * This is a backend/Pest business-integration chain, NOT a browser E2E run — see
 * testing/results/PRODUCTION-RELEASE-GATE.md gate #9 for that distinction.
 */
final class PurchasingLifecycleChainTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        DocumentSequence::query()->create([
            'document_type' => 'purchase_order', 'prefix' => 'CHAIN-PO-', 'padding_length' => 5,
            'next_value' => 1, 'status' => 'active', 'lock_version' => 1,
        ]);
    }

    public function test_a_supplier_flows_through_order_approval_invoice_receipt_and_return(): void
    {
        $administrator = $this->administrator('pur-chain-setup');
        $this->actingAs($administrator);

        $branch = $this->branch('PUR-CHAIN-BR');
        $store = $this->store($branch, 'PUR-CHAIN-ST');
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'PUR-CHAIN-SUP', 'name_ar' => 'مورد السلسلة', 'name_en' => 'Chain Supplier', 'status' => 'active',
        ]);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'PUR-CHAIN-CAT', 'name_ar' => 'تصنيف السلسلة', 'name_en' => 'Chain Category',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'PUR-CHAIN-ITEM', 'name_ar' => 'منتج السلسلة', 'name_en' => 'Chain Product',
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        // A super-admin identity (matching the proven pattern in
        // PurchasingLifecycleIntegrityTest) so the self-approval business
        // rule below is exercised directly, without the Gate::authorize()
        // check for `purchase_orders.approve` short-circuiting first —
        // Purchasing Officer never holds that R-status ability at all
        // (see docs/04-roles-permissions.md / QA-002), so a non-admin
        // requester would fail on authorization, not on self-approval.
        $requester = $this->administrator('pur-chain-requester');
        $poApprover = $this->administrator('pur-chain-po-approver');
        $invoiceApprover = $this->administrator('pur-chain-inv-approver');
        $returnApprover = $this->administrator('pur-chain-ret-approver');

        // --- PURCHASE ORDER: create, submit, approve (no stock effect) ----
        $this->actingAs($requester);
        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '5', 'unit_cost' => '10.00']],
        );
        $order = app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);

        // Self-approval is rejected before a valid, separate approver succeeds.
        try {
            app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
            self::fail('The purchase-order requester approved their own order.');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }
        self::assertSame(0, StockMovement::query()->count(), 'A rejected self-approval must post no stock movement.');

        $this->actingAs($poApprover);
        $order = app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
        self::assertSame('approved', $order->status);
        self::assertSame(0, StockMovement::query()->count(), 'An approved purchase order alone must have no stock effect.');
        self::assertNotNull(
            AuditLog::query()->where('event', 'approve_purchase_order')->where('source_id', (string) $order->id)->first(),
            'The purchase-order approval must be audited.',
        );

        // --- INVOICE: create against the order's supplier/store, submit, approve
        // (receipt: posts stock and WAC) -----------------------------------
        $this->actingAs($requester);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'supplier_reference' => 'PUR-CHAIN-SUPREF-1'],
            [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '10', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        $this->actingAs($invoiceApprover);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('approved', $invoice->status);
        self::assertSame('5.000000', (string) $balance->on_hand, 'The invoice receipt must post the exact ordered quantity.');
        self::assertSame('10.0000', (string) $balance->average_cost);
        self::assertSame('50.0000', (string) $balance->total_value);
        self::assertSame(1, StockMovement::query()->where('source_type', $invoice::class)->where('source_id', $invoice->id)->count());
        self::assertNotNull(
            AuditLog::query()->where('event', 'approve_purchase_invoice')->where('source_id', (string) $invoice->id)->first(),
            'The invoice/receipt approval must be audited.',
        );

        // --- SUPPLIER RETURN: draft (idempotent), submit, approve (reduces
        // stock at the original receipt cost) ------------------------------
        $reason = SupplierReturnReason::query()->create(['code' => 'PUR-CHAIN-DAMAGED', 'label_ar' => 'تالف', 'label_en' => 'Damaged', 'is_active' => true]);

        $this->actingAs($requester);
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id, 'quantity' => '2',
        ]], 'pur-chain-return-key-1');

        // Idempotent replay of the same request returns the same draft, not a duplicate.
        $replay = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id, 'quantity' => '2',
        ]], 'pur-chain-return-key-1');
        self::assertSame($return->id, $replay->id);

        // A conflicting-payload replay of the same key is rejected.
        try {
            app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
                'purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id, 'quantity' => '3',
            ]], 'pur-chain-return-key-1');
            self::fail('A conflicting-payload idempotency replay was accepted.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);

        $this->actingAs($returnApprover);
        $return = app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);

        $balance = $balance->fresh();
        self::assertSame('approved', $return->status);
        self::assertSame('10.0000', (string) $return->lines->firstOrFail()->unit_cost, 'The return must reuse the original receipt cost, not a current/estimated one.');
        self::assertSame('3.000000', (string) $balance->on_hand, 'Stock must reduce by exactly the returned quantity (5 received - 2 returned).');
        self::assertSame('10.0000', (string) $balance->average_cost, 'WAC must be unaffected by a same-cost return.');
        self::assertSame(1, StockMovement::query()->where('movement_type', 'purchase_return')->count());
        self::assertNotNull(
            AuditLog::query()->where('event', 'approve_supplier_return')->where('source_id', (string) $return->id)->first(),
            'The supplier-return approval must be audited.',
        );

        // The full chain traced one supplier/product/store through order,
        // receipt, and return with exactly one stock movement per posting event.
        self::assertSame(2, StockMovement::query()->where('product_id', $product->id)->where('store_id', $store->id)->count());
    }
}
