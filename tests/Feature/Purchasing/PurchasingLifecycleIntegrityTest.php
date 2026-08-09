<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Actions\AllocatePurchaseInvoiceNumberAction;
use App\Modules\Purchasing\Actions\AllocatePurchaseOrderNumberAction;
use App\Modules\Purchasing\Actions\AllocatePurchaseReturnNumberAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** PUR-03..PUR-06, PRC-03, NFR-01..NFR-02, and transaction/idempotency controls. */
final class PurchasingLifecycleIntegrityTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CanonicalAuthorizationSeeder::class);
        $this->actingAs($this->administrator('purchasing-setup'));
    }

    public function test_purchase_order_approval_is_segregated_and_has_no_stock_effect(): void
    {
        [$supplier, $product, $store] = $this->masterData('po');
        DocumentSequence::query()->create([
            'document_type' => 'purchase_order', 'prefix' => 'PO-', 'padding_length' => 5,
            'next_value' => 1, 'status' => 'active', 'lock_version' => 1,
        ]);
        $creator = $this->administrator('po-requester');
        $approver = $this->administrator('po-approver');
        $this->actingAs($creator);

        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity_ordered' => '3', 'unit_cost' => '12.50']],
        );
        $order = app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);

        $this->expectException(ValidationException::class);
        app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);

        $this->actingAs($approver);
        $approved = app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);

        self::assertSame('approved', $approved->status);
        self::assertSame('37.5000', (string) $approved->total_amount);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(0, StockBalance::query()->count());
        self::assertSame(0, PurchaseInvoice::query()->count());
    }

    public function test_invoice_approval_posts_exact_stock_and_wac_without_changing_sale_price(): void
    {
        [$supplier, $product, $store] = $this->masterData('invoice');
        $list = PriceList::query()->create([
            'company_id' => $store->company_id, 'code' => 'RETAIL',
            'name_ar' => 'أساسي', 'name_en' => 'Retail', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $list->id, 'version' => 1, 'state' => 'approved',
            'source_type' => 'product_card', 'requested_by' => $this->administrator('price-owner')->id,
        ]);
        $priceLine = PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id,
            'store_id' => $store->id, 'branch_id' => $store->branch_id,
            'amount' => '25.000', 'active_key' => $product->id.':'.$store->id,
        ]);

        $creator = $this->administrator('invoice-requester');
        $approver = $this->administrator('invoice-approver');
        $this->actingAs($creator);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'supplier_reference' => 'SUP-INV-1'],
            [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '10', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        $this->actingAs($approver);
        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();

        self::assertSame('approved', $approved->status);
        self::assertSame('2.000000', (string) $balance->on_hand);
        self::assertSame('10.0000', (string) $balance->average_cost);
        self::assertSame('20.0000', (string) $balance->total_value);
        self::assertSame('25.000', (string) $priceLine->fresh()->amount);
        self::assertSame(1, StockMovement::query()->where('source_id', $invoice->id)->count());
    }

    public function test_invoice_approval_rolls_back_earlier_lines_when_a_later_line_fails(): void
    {
        [$supplier, $product, $store] = $this->masterData('rollback');
        $creator = $this->administrator('rollback-requester');
        $approver = $this->administrator('rollback-approver');
        $this->actingAs($creator);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '10', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0'],
                ['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '20', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0'],
            ],
        );
        // Simulate corrupted persisted data to exercise the approval transaction boundary.
        $invoice->lines()->latest('id')->firstOrFail()->update(['quantity' => 0]);
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        $this->actingAs($approver);
        try {
            app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
            self::fail('Approval should reject a non-positive received quantity.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('greater than zero', $exception->getMessage());
        }

        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(0, StockBalance::query()->count());
        self::assertSame('submitted', $invoice->fresh()->status);
    }

    public function test_supplier_return_uses_original_cost_reduces_stock_and_is_idempotent(): void
    {
        [$supplier, $product, $store] = $this->masterData('return');
        $creator = $this->administrator('return-requester');
        $approver = $this->administrator('return-approver');
        $this->actingAs($creator);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id],
            [['product_id' => $product->id, 'quantity' => '4', 'unit_cost' => '10', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $this->actingAs($approver);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $reason = SupplierReturnReason::query()->create(['code' => 'DAMAGED', 'label_ar' => 'تالف', 'label_en' => 'Damaged', 'is_active' => true]);

        $this->actingAs($creator);
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $invoice->lines->firstOrFail()->id, 'quantity' => '1',
        ]], 'return-key-1');
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);

        $this->actingAs($approver);
        $approved = app(ApprovePurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        $movementCount = StockMovement::query()->where('movement_type', 'purchase_return')->count();

        self::assertSame('approved', $approved->status);
        self::assertSame('10.0000', (string) $approved->lines->firstOrFail()->unit_cost);
        self::assertSame('3.000000', (string) $balance->on_hand);
        self::assertSame('10.0000', (string) $balance->average_cost);
        self::assertSame(1, $movementCount);
        self::assertSame('approved', app(ApprovePurchaseReturnAction::class)->execute($approved->id, $approved->lock_version)->status);
        self::assertSame($movementCount, StockMovement::query()->where('movement_type', 'purchase_return')->count());
        self::assertSame('return-key-1', PurchaseReturn::query()->whereKey($return->id)->value('idempotency_key'));
    }

    public function test_supplier_return_rejects_reuse_of_an_idempotency_key_with_a_conflicting_payload(): void
    {
        [$supplier, $product, $store] = $this->masterData('conflict');
        $creator = $this->administrator('return-conflict-requester');
        $this->actingAs($creator);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'PINV-CONFLICT-1', 'supplier_id' => $supplier->id, 'store_id' => $store->id,
            'status' => 'approved', 'subtotal' => '20.0000', 'tax_amount' => '0.0000', 'discount_amount' => '0.0000',
            'total_amount' => '20.0000', 'idempotency_key' => 'invoice-conflict-1', 'approved_at' => now(), 'approved_by' => $creator->id,
            'created_by' => $creator->id, 'lock_version' => 1,
        ]);
        $line = PurchaseInvoiceLine::query()->create([
            'purchase_invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => '2.000000',
            'quantity_received' => '2.000000', 'unit_cost' => '10.0000', 'discount_amount' => '0.0000',
            'tax_amount' => '0.0000', 'subtotal' => '20.0000', 'line_total' => '20.0000',
        ]);
        $reason = SupplierReturnReason::query()->create(['code' => 'CONFLICT', 'label_ar' => 'تعارض', 'label_en' => 'Conflict', 'is_active' => true]);
        PurchaseReturn::query()->create([
            'supplier_id' => $supplier->id, 'purchase_invoice_id' => $invoice->id, 'store_id' => $store->id,
            'reason_id' => $reason->id, 'return_date' => now()->toDateString(), 'status' => 'draft',
            'subtotal' => '10.0000', 'total_amount' => '10.0000', 'idempotency_key' => 'return-conflict-1',
            'created_by' => $creator->id, 'updated_by' => $creator->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $line->id, 'quantity' => '2',
        ]], 'return-conflict-1');
    }

    public function test_document_number_allocators_advance_each_sequence_without_reuse(): void
    {
        DocumentSequence::query()->create([
            'document_type' => 'purchase_order', 'prefix' => 'PO-', 'padding_length' => 5,
            'next_value' => 7, 'status' => 'active', 'lock_version' => 1,
        ]);

        self::assertSame('PO-00007', app(AllocatePurchaseOrderNumberAction::class)->execute());
        self::assertSame('PO-00008', app(AllocatePurchaseOrderNumberAction::class)->execute());
        self::assertSame('PINV-'.now()->format('Y').'-00001', app(AllocatePurchaseInvoiceNumberAction::class)->execute());
        self::assertSame('PINV-'.now()->format('Y').'-00002', app(AllocatePurchaseInvoiceNumberAction::class)->execute());
        self::assertSame('PRET-'.now()->format('Y').'-00001', app(AllocatePurchaseReturnNumberAction::class)->execute());
        self::assertSame('PRET-'.now()->format('Y').'-00002', app(AllocatePurchaseReturnNumberAction::class)->execute());
    }

    /** @return array{0: Supplier, 1: Product, 2: Store} */
    private function masterData(string $suffix): array
    {
        $branch = $this->branch('BR-'.$suffix);
        $store = $this->store($branch, 'ST-'.$suffix);
        $supplier = app(SaveSupplierAction::class)->execute([
            'code' => 'SUP-'.$suffix, 'name_ar' => 'مورد '.$suffix, 'name_en' => 'Supplier '.$suffix, 'status' => 'active',
        ]);
        $category = app(SaveCategoryAction::class)->execute([
            'code' => 'CAT-'.$suffix, 'name_ar' => 'تصنيف '.$suffix, 'name_en' => 'Category '.$suffix,
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ]);
        $product = app(SaveProductAction::class)->execute([
            'item_code' => 'ITEM-'.$suffix, 'name_ar' => 'منتج '.$suffix, 'name_en' => 'Product '.$suffix,
            'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active',
        ]);

        return [$supplier, $product, $store];
    }
}
