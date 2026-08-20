<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** UI-PUR-002 / PUR-04..05 purchase-order receipt and accessible duplicate-reference controls. */
final class PurchaseInvoicePurchaseOrderUiTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->documentSequence('purchase_order', 'PO-TEST-');
        $this->documentSequence('purchase_invoice', 'PINV-TEST-');
    }

    public function test_authorized_requester_can_select_an_approved_purchase_order_from_the_purchase_invoice_form(): void
    {
        [$supplier, $product, $store] = $this->masterData('select-po');
        $requester = $this->requester('invoice-po-requester', $store);
        $approver = $this->approver('invoice-po-approver', $store);
        $order = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);

        $this->actingAs($requester);

        Livewire::test('purchasing::invoices')
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->assertSee('Purchase order')
            ->assertSee($order->po_number);
    }

    public function test_invoice_saved_from_the_form_retains_the_approved_purchase_order_and_posts_its_received_line_once(): void
    {
        [$supplier, $product, $store] = $this->masterData('linked-receipt');
        $requester = $this->requester('invoice-link-requester', $store);
        $approver = $this->approver('invoice-link-approver', $store);
        $order = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);
        $poLine = $order->lines->firstOrFail();

        $this->actingAs($requester);
        Livewire::test('purchasing::invoices')
            ->call('openCreateModal')
            ->set('invoiceForm.supplier_id', (string) $supplier->id)
            ->set('invoiceForm.store_id', (string) $store->id)
            ->set('invoiceForm.purchase_order_id', (string) $order->id)
            ->set('invoiceForm.supplier_reference', 'PO-RECEIPT-001')
            ->set('lineItems.0.product_id', (string) $product->id)
            ->set('lineItems.0.purchase_order_line_id', (string) $poLine->id)
            ->set('lineItems.0.quantity', '3')
            ->set('lineItems.0.unit_cost', '12.50')
            ->call('saveInvoice')
            ->assertSet('showFormModal', false);

        $invoice = PurchaseInvoice::query()->where('supplier_reference', 'PO-RECEIPT-001')->firstOrFail();
        self::assertSame($order->id, $invoice->purchase_order_id);
        self::assertSame($poLine->id, $invoice->lines()->sole()->purchase_order_line_id);

        $submitted = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $this->actingAs($approver);
        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($submitted->id, $submitted->lock_version);

        self::assertSame('approved', $approved->status);
        self::assertSame('3.000000', (string) $approved->lines()->sole()->quantity_received);
        self::assertSame('3.000000', (string) $poLine->fresh()->quantity_received);
        self::assertSame('received', $order->fresh()->status);
        self::assertSame(1, StockMovement::query()->where('source_id', $invoice->id)->count());
    }

    public function test_duplicate_supplier_reference_returns_an_accessible_form_error_and_leaves_the_draft_unposted(): void
    {
        [$supplier, $product, $store] = $this->masterData('duplicate-reference');
        $requester = $this->requester('invoice-duplicate-requester', $store);
        $this->actingAs($requester);

        app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'supplier_reference' => 'SUP-DUP-001'],
            [$this->invoiceLine($product)],
        );

        Livewire::test('purchasing::invoices')
            ->call('openCreateModal')
            ->set('invoiceForm.supplier_id', (string) $supplier->id)
            ->set('invoiceForm.store_id', (string) $store->id)
            ->set('invoiceForm.supplier_reference', 'SUP-DUP-001')
            ->set('lineItems.0.product_id', (string) $product->id)
            ->set('lineItems.0.quantity', '1')
            ->set('lineItems.0.unit_cost', '12.50')
            ->call('saveInvoice')
            ->assertHasErrors(['invoiceForm.supplier_reference'])
            ->assertSet('showFormModal', true)
            ->assertSet('invoiceForm.supplier_reference', 'SUP-DUP-001');

        self::assertSame(1, PurchaseInvoice::query()->count());
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(1, (int) DocumentSequence::query()->where('document_type', 'purchase_invoice')->value('next_value'));
    }

    public function test_requester_does_not_receive_a_self_approval_control_for_their_submitted_purchase_invoice(): void
    {
        [$supplier, $product, $store] = $this->masterData('self-approval');
        $requester = $this->requester('invoice-self-approval-requester', $store, canApprove: true);
        $this->actingAs($requester);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'supplier_reference' => 'SELF-APPROVAL-001'],
            [$this->invoiceLine($product)],
        );
        $submitted = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        Livewire::test('purchasing::invoices')
            ->assertSee('SELF-APPROVAL-001')
            ->assertDontSee('Approve & post');

        self::assertSame('submitted', $submitted->fresh()->status);
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_save_rejects_an_unapproved_purchase_order_without_creating_invoice_or_stock_effects(): void
    {
        [$supplier, $product, $store] = $this->masterData('unapproved-po');
        $requester = $this->requester('invoice-unapproved-po-requester', $store);
        $this->actingAs($requester);
        $draft = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '1', 'unit_cost' => '12.50']],
        );

        $this->assertInvoiceSaveIsRejected(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'purchase_order_id' => $draft->id, 'supplier_reference' => 'UNAPPROVED-PO-001'],
            [[...$this->invoiceLine($product), 'purchase_order_line_id' => $draft->lines()->sole()->id]],
        );
    }

    public function test_save_rejects_a_purchase_order_with_a_different_supplier_without_creating_invoice_or_stock_effects(): void
    {
        [$supplier, $product, $store] = $this->masterData('po-supplier-mismatch');
        $requester = $this->requester('invoice-po-supplier-requester', $store);
        $approver = $this->approver('invoice-po-supplier-approver', $store);
        $order = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);
        $this->actingAs($this->administrator('invoice-po-second-supplier-fixture'));
        $otherSupplier = app(SaveSupplierAction::class)->execute([
            'code' => 'SUP-PI-po-supplier-mismatch-OTHER',
            'name_ar' => 'Ù…ÙˆØ±Ø¯ Ø¢Ø®Ø±',
            'name_en' => 'Other supplier',
            'status' => 'active',
        ]);
        $this->actingAs($requester);

        $this->assertInvoiceSaveIsRejected(
            ['supplier_id' => $otherSupplier->id, 'store_id' => $store->id, 'purchase_order_id' => $order->id, 'supplier_reference' => 'SUPPLIER-MISMATCH-001'],
            [[...$this->invoiceLine($product), 'purchase_order_line_id' => $order->lines()->sole()->id]],
        );
    }

    public function test_save_rejects_a_purchase_order_with_a_different_receiving_store_without_creating_invoice_or_stock_effects(): void
    {
        [$supplier, $product, $store] = $this->masterData('po-store-mismatch');
        $requester = $this->requester('invoice-po-store-requester', $store);
        $approver = $this->approver('invoice-po-store-approver', $store);
        $order = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);
        $otherStore = $this->store($store->branch()->firstOrFail(), 'ST-PI-po-store-mismatch-OTHER');
        $this->actingAs($requester);

        $this->assertInvoiceSaveIsRejected(
            ['supplier_id' => $supplier->id, 'store_id' => $otherStore->id, 'purchase_order_id' => $order->id, 'supplier_reference' => 'STORE-MISMATCH-001'],
            [[...$this->invoiceLine($product), 'purchase_order_line_id' => $order->lines()->sole()->id]],
        );
    }

    public function test_save_rejects_a_purchase_order_line_that_does_not_belong_to_the_selected_order_without_side_effects(): void
    {
        [$supplier, $product, $store] = $this->masterData('po-line-mismatch');
        $requester = $this->requester('invoice-po-line-requester', $store);
        $approver = $this->approver('invoice-po-line-approver', $store);
        $selectedOrder = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);
        $otherOrder = $this->approvedPurchaseOrder($supplier, $product, $store, $requester, $approver);
        $this->actingAs($requester);

        $this->assertInvoiceSaveIsRejected(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'purchase_order_id' => $selectedOrder->id, 'supplier_reference' => 'LINE-MISMATCH-001'],
            [[...$this->invoiceLine($product), 'purchase_order_line_id' => $otherOrder->lines()->sole()->id]],
        );
    }

    private function approvedPurchaseOrder(Supplier $supplier, Product $product, Store $store, User $requester, User $approver): PurchaseOrder
    {
        $this->actingAs($requester);
        $draft = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '3', 'unit_cost' => '12.50']],
        );
        $submitted = app(SubmitPurchaseOrderAction::class)->execute($draft->id, $draft->lock_version);

        $this->actingAs($approver);

        return app(ApprovePurchaseOrderAction::class)->execute($submitted->id, $submitted->lock_version);
    }

    /** @return array<string, string|null> */
    private function invoiceLine(Product $product): array
    {
        return [
            'product_id' => (string) $product->id,
            'quantity' => '1',
            'unit_cost' => '12.50',
            'discount_type' => null,
            'discount_value' => '0',
            'tax_rate' => '0',
        ];
    }

    /** @param array<string, mixed> $data
     *  @param array<int, array<string, mixed>> $lines
     */
    private function assertInvoiceSaveIsRejected(array $data, array $lines): void
    {
        try {
            app(SavePurchaseInvoiceAction::class)->execute($data, $lines);
            self::fail('The purchase-invoice save must reject an invalid purchase-order link.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        self::assertSame(0, PurchaseInvoice::query()->count());
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(1, (int) DocumentSequence::query()->where('document_type', 'purchase_invoice')->value('next_value'));
    }

    private function requester(string $username, Store $store, bool $canApprove = false): User
    {
        return $this->scopedActor($username, $store, [
            'purchase_orders.create',
            'purchase_orders.edit',
            'purchase_invoices_supplier_returns.view',
            'purchase_invoices_supplier_returns.create',
            'purchase_invoices_supplier_returns.edit',
            ...($canApprove ? ['purchase_invoices_supplier_returns.approve'] : []),
        ]);
    }

    private function approver(string $username, Store $store): User
    {
        return $this->scopedActor($username, $store, [
            'purchase_orders.approve',
            'purchase_invoices_supplier_returns.approve',
        ]);
    }

    /** @param array<int, string> $permissions */
    private function scopedActor(string $username, Store $store, array $permissions): User
    {
        $role = Role::query()->create([
            'code' => 'invoice-ui-'.str_replace('_', '-', $username),
            'name_ar' => 'Ø¯ÙˆØ± '.$username,
            'name_en' => 'Invoice UI '.$username,
            'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id')->all());

        return $this->userWith($username, [$role->code], false, [$store->branch_id], [$store->id]);
    }

    /** @return array{0: Supplier, 1: Product, 2: Store} */
    private function masterData(string $suffix): array
    {
        $branch = $this->branch('BR-PI-'.$suffix);
        $store = $this->store($branch, 'ST-PI-'.$suffix);
        $this->actingAs($this->administrator('invoice-master-'.$suffix));
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'SUP-PI-'.$suffix,
            'name_ar' => 'Ù…ÙˆØ±Ø¯ '.$suffix,
            'name_en' => 'Supplier '.$suffix,
            'status' => 'active',
        ]);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-PI-'.$suffix,
            'name_ar' => 'ØªØµÙ†ÙŠÙ '.$suffix,
            'name_en' => 'Category '.$suffix,
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'ITEM-PI-'.$suffix,
            'name_ar' => 'Ù…Ù†ØªØ¬ '.$suffix,
            'name_en' => 'Product '.$suffix,
            'category_id' => $category->id,
            'product_type' => 'standard',
            'status' => 'active',
        ]);

        return [$supplier, $product, $store];
    }
}
