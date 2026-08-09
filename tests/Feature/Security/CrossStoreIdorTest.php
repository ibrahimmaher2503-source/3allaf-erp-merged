<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Cross-store IDOR/horizontal-escalation regression for direct-route access to
 * store-scoped documents. Every case here holds the correct ability but is
 * scoped to a different store than the target record.
 *
 * Requirements: NFR-XCUT (branch/store isolation), docs/04 "Scope / sensitive
 * access" column for POS Sales, Purchase Orders, Purchase Invoices & Supplier
 * Returns, Purchase Returns.
 */
final class CrossStoreIdorTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        DocumentSequence::query()->create([
            'document_type' => 'purchase_order', 'prefix' => 'IDOR-PO-', 'padding_length' => 5,
            'next_value' => 1, 'status' => 'active', 'lock_version' => 1,
        ]);
    }

    public function test_a_cashier_cannot_view_or_print_a_sale_from_another_store(): void
    {
        $ownerBranch = $this->branch('IDOR-SALE-OWN-BR');
        $ownerStore = $this->store($ownerBranch, 'IDOR-SALE-OWN-ST');
        $foreignBranch = $this->branch('IDOR-SALE-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'IDOR-SALE-FGN-ST');

        $sale = $this->createSale($ownerBranch->code, $ownerStore);

        $foreignCashier = $this->userWith('idor-foreign-cashier', ['cashier'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignCashier);
        // The route intentionally returns 404 (not 403) for an out-of-scope
        // sale, to avoid confirming the record's existence to an actor who
        // cannot see it.
        $this->get(route('sales.show', $sale))->assertNotFound();
        $this->get(route('sales.print', $sale))->assertNotFound();

        $ownStoreCashier = $this->userWith('idor-own-cashier', ['cashier'], branchIds: [$ownerBranch->id], storeIds: [$ownerStore->id]);
        $this->actingAs($ownStoreCashier);
        $this->get(route('sales.show', $sale))->assertOk();
        $this->get(route('sales.print', $sale))->assertOk();
    }

    public function test_a_scoped_administrator_cannot_print_a_purchase_order_from_another_store(): void
    {
        $ownerBranch = $this->branch('IDOR-PO-OWN-BR');
        $ownerStore = $this->store($ownerBranch, 'IDOR-PO-OWN-ST');
        $foreignBranch = $this->branch('IDOR-PO-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'IDOR-PO-FGN-ST');

        $creator = $this->administrator('idor-po-creator');
        $this->actingAs($creator);
        $supplier = Supplier::query()->create(['code' => 'IDOR-PO-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $category = Category::query()->create(['code' => 'IDOR-PO-CAT', 'name_ar' => 'تصنيف', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'IDOR-PO-PROD', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'status' => 'active']);

        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $ownerStore->id],
            [['product_id' => $product->id, 'quantity_ordered' => '2', 'unit_cost' => '10']],
        );

        $foreignAdministrator = $this->userWith('idor-po-foreign-admin', ['system-administrator'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignAdministrator);
        $this->get(route('purchasing.orders.print', $order))->assertForbidden();

        $ownStoreAdministrator = $this->userWith('idor-po-own-admin', ['system-administrator'], branchIds: [$ownerBranch->id], storeIds: [$ownerStore->id]);
        $this->actingAs($ownStoreAdministrator);
        $this->get(route('purchasing.orders.print', $order))->assertOk();
    }

    public function test_a_company_wide_purchase_order_with_no_store_is_printable_by_any_permission_holder(): void
    {
        // Documents the intentional carve-out: a store-less (company-wide)
        // purchase order has nothing to scope against, so any actor holding
        // the `.print` ability may open it once authenticated.
        $creator = $this->administrator('idor-po-global-creator');
        $this->actingAs($creator);
        $supplier = Supplier::query()->create(['code' => 'IDOR-PO-GLOBAL-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $category = Category::query()->create(['code' => 'IDOR-PO-GLOBAL-CAT', 'name_ar' => 'تصنيف', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'IDOR-PO-GLOBAL-PROD', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'status' => 'active']);

        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => null],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '5']],
        );

        $unrelatedBranch = $this->branch('IDOR-PO-GLOBAL-BR');
        $unrelatedStore = $this->store($unrelatedBranch, 'IDOR-PO-GLOBAL-ST');
        $unrelatedAdministrator = $this->userWith('idor-po-unrelated-admin', ['system-administrator'], branchIds: [$unrelatedBranch->id], storeIds: [$unrelatedStore->id]);
        $this->actingAs($unrelatedAdministrator);
        $this->get(route('purchasing.orders.print', $order))->assertOk();
    }

    public function test_a_scoped_administrator_cannot_print_a_purchase_invoice_from_another_store(): void
    {
        $ownerBranch = $this->branch('IDOR-INV-OWN-BR');
        $ownerStore = $this->store($ownerBranch, 'IDOR-INV-OWN-ST');
        $foreignBranch = $this->branch('IDOR-INV-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'IDOR-INV-FGN-ST');

        $creator = $this->administrator('idor-inv-creator');
        $this->actingAs($creator);
        $supplier = Supplier::query()->create(['code' => 'IDOR-INV-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'IDOR-PINV-1', 'supplier_id' => $supplier->id, 'store_id' => $ownerStore->id,
            'status' => 'approved', 'subtotal' => '10.0000', 'tax_amount' => '0.0000', 'discount_amount' => '0.0000',
            'total_amount' => '10.0000', 'idempotency_key' => 'idor-invoice-1', 'approved_at' => now(), 'approved_by' => $creator->id,
            'created_by' => $creator->id, 'lock_version' => 1,
        ]);

        $foreignAdministrator = $this->userWith('idor-inv-foreign-admin', ['system-administrator'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignAdministrator);
        $this->get(route('purchasing.invoices.print', $invoice))->assertForbidden();

        $ownStoreAdministrator = $this->userWith('idor-inv-own-admin', ['system-administrator'], branchIds: [$ownerBranch->id], storeIds: [$ownerStore->id]);
        $this->actingAs($ownStoreAdministrator);
        $this->get(route('purchasing.invoices.print', $invoice))->assertOk();
    }

    public function test_a_scoped_actor_cannot_view_or_print_a_supplier_return_from_another_store(): void
    {
        $ownerBranch = $this->branch('IDOR-RET-OWN-BR');
        $ownerStore = $this->store($ownerBranch, 'IDOR-RET-OWN-ST');
        $foreignBranch = $this->branch('IDOR-RET-FGN-BR');
        $foreignStore = $this->store($foreignBranch, 'IDOR-RET-FGN-ST');

        $creator = $this->administrator('idor-ret-creator');
        $this->actingAs($creator);
        $supplier = Supplier::query()->create(['code' => 'IDOR-RET-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'IDOR-PINV-2', 'supplier_id' => $supplier->id, 'store_id' => $ownerStore->id,
            'status' => 'approved', 'subtotal' => '20.0000', 'tax_amount' => '0.0000', 'discount_amount' => '0.0000',
            'total_amount' => '20.0000', 'idempotency_key' => 'idor-invoice-2', 'approved_at' => now(), 'approved_by' => $creator->id,
            'created_by' => $creator->id, 'lock_version' => 1,
        ]);
        $reason = SupplierReturnReason::query()->create(['code' => 'IDOR-DAMAGED', 'label_ar' => 'تالف', 'label_en' => 'Damaged', 'is_active' => true]);
        $return = PurchaseReturn::query()->create([
            'supplier_id' => $supplier->id, 'purchase_invoice_id' => $invoice->id, 'store_id' => $ownerStore->id,
            'reason_id' => $reason->id, 'return_date' => now()->toDateString(), 'status' => 'draft',
            'subtotal' => '10.0000', 'total_amount' => '10.0000', 'idempotency_key' => 'idor-return-1',
            'created_by' => $creator->id, 'updated_by' => $creator->id,
        ]);

        $foreignAdministrator = $this->userWith('idor-ret-foreign-admin', ['system-administrator'], branchIds: [$foreignBranch->id], storeIds: [$foreignStore->id]);
        $this->actingAs($foreignAdministrator);
        $this->get(route('purchasing.returns.show', $return))->assertForbidden();
        $this->get(route('purchasing.returns.print', $return))->assertForbidden();

        $ownStoreAdministrator = $this->userWith('idor-ret-own-admin', ['system-administrator'], branchIds: [$ownerBranch->id], storeIds: [$ownerStore->id]);
        $this->actingAs($ownStoreAdministrator);
        $this->get(route('purchasing.returns.show', $return))->assertOk();
        $this->get(route('purchasing.returns.print', $return))->assertOk();
    }

    private function createSale(string $branchCode, Store $store): Sale
    {
        $branch = Branch::query()->where('code', $branchCode)->firstOrFail();
        $cashier = $this->userWith('idor-sale-owner-'.$store->id, ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'IDOR-DR-'.$store->id, 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active',
        ]);
        PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id, 'status' => 'open', 'opening_cash' => '0', 'opened_at' => now(),
        ]);
        $category = Category::query()->create(['code' => 'IDOR-SALE-CAT-'.$store->id, 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'IDOR-SALE-PROD-'.$store->id, 'name_ar' => 'Test', 'name_en' => 'Test', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '5', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '50', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'IDOR-PRICE-'.$store->id, 'name_ar' => 'Test', 'name_en' => 'Test', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual',
            'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'amount' => '9.99', 'active_key' => $product->id.'-'.$store->id,
        ]);

        $this->actingAs($cashier);

        return app(RetailSaleAction::class)->create($cashier, $store, [['product_id' => $product->id, 'quantity' => '1']], 'idor-sale-'.$store->id);
    }
}
