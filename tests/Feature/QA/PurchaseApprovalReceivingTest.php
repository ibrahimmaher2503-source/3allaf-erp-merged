<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ProductPricingReadiness;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseReceivingAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Queries\SupplierProductPrice;
use App\Support\ProductQuantity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

final class PurchaseApprovalReceivingTest extends TestCase
{
    use DatabaseTransactions;

    private array $fixture;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
        $this->supplier = Supplier::query()->create(['code' => 'SUP-9123', 'name_ar' => 'مورد اختبار', 'name_en' => 'QA supplier', 'status' => 'active']);
        $this->product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'name_ar' => 'منتج الاستلام', 'sale_price' => 25, 'average_cost' => 10, 'model_number' => null]);
        $this->product->barcodes()->create(['barcode' => 'QA-RECEIVING-CODE', 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        request()->setLaravelSession(app('session')->driver());
    }

    private function draft(string $quantity = '1000', string $cost = '12', string $price = '30', ?Store $store = null): PurchaseInvoice
    {
        return app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => ($store ?? $this->fixture['receiving'])->id, 'invoice_date' => '2026-10-12'],
            [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'base_consumer_price' => $price]],
        );
    }

    private function submit(PurchaseInvoice $invoice): PurchaseInvoice
    {
        return app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
    }

    private function ordinaryApprover(): User
    {
        $role = Role::query()->firstOrCreate(['code' => 'qa-purchase-approver'], ['name_ar' => 'معتمد مشتريات', 'name_en' => 'Purchase approver', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('code', ['purchase_invoices.view', 'purchase_invoices.draft', 'purchase_invoices.approve', 'purchase_invoices.change_cost', 'purchase_invoices.change_list0_price', 'purchase_invoices_supplier_returns.edit', 'purchase_invoices_supplier_returns.approve'])->pluck('id'));
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);
        $user->storeScopes()->create(['store_id' => $this->fixture['receiving']->id, 'status' => 'active']);

        return $user;
    }

    public function test_system_administrator_role_can_approve_own_invoice_with_audit_and_no_duplicate_posting(): void
    {
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->roles()->attach(Role::query()->where('code', 'system-administrator')->firstOrFail());
        $this->actingAs($administrator);
        self::assertFalse($administrator->is_super_admin);
        self::assertTrue($administrator->isSystemAdministrator());

        $invoice = $this->submit($this->draft('1000'));
        $line = $invoice->lines->firstOrFail();
        app()->setLocale('ar-EG');
        $component = Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id);
        self::assertStringContainsString('اعتماد الفاتورة', $component->html());
        self::assertStringContainsString('الكمية الأصلية', $component->html());
        self::assertStringNotContainsString('Verify accepted quantities before approval.', $component->html());
        $component->set('receivedQuantities.'.$line->id, '900')->call('approveInvoice', $invoice->id)->assertHasNoErrors();

        $approved = $invoice->fresh();
        self::assertSame('approved', $approved->status);
        self::assertSame($administrator->id, $approved->created_by);
        self::assertSame($administrator->id, $approved->approved_by);
        self::assertSame('1000.000000', $line->fresh()->original_quantity);
        self::assertSame('900.000000', $line->fresh()->quantity);
        self::assertSame('900.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame('12.00', $this->product->fresh()->average_cost);
        self::assertSame('30.00', $this->product->fresh()->sale_price);
        self::assertSame(1, PurchaseInvoiceLine::query()->withShortage()->count());
        self::assertSame(1, StockMovement::query()->count());
        $approval = ApprovalRecord::query()->where('source_type', 'purchase_invoices')->where('source_id', (string) $invoice->id)->firstOrFail();
        self::assertSame($administrator->id, $approval->requester_id);
        self::assertSame($administrator->id, $approval->approver_id);
        self::assertTrue((bool) AuditLog::query()->where('event', 'approval_approved')->latest('id')->firstOrFail()->metadata['system_administrator_bypass']);
        $auditCount = AuditLog::query()->count();
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame($auditCount, AuditLog::query()->count());
    }

    public function test_shared_approval_record_bypass_requires_invoice_approve_context(): void
    {
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->roles()->attach(Role::query()->where('code', 'system-administrator')->firstOrFail());
        $this->actingAs($administrator);
        $invoice = $this->submit($this->draft('10'));
        $approval = ApprovalRecord::query()->where('source_type', 'purchase_invoices')->where('source_id', (string) $invoice->id)->firstOrFail();

        self::assertTrue(Gate::forUser($administrator)->allows('decide', $approval));
        foreach ([['purchase_invoices', 'cancel'], ['purchase_orders', 'approve'], ['purchase_returns', 'approve'], ['platform_settings', 'approve']] as [$sourceType, $requestedAction]) {
            DB::table('approval_records')->where('id', $approval->id)->update(['source_type' => $sourceType, 'requested_action' => $requestedAction]);
            $record = $approval->fresh();
            self::assertFalse(Gate::forUser($administrator)->allows('decide', $record));
            try {
                app(ApproveRequest::class)->execute($record, (string) $invoice->lock_version);
                self::fail('System Administrator self-approved a non-invoice-approval record.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('approver', $exception->errors());
            }
        }
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_ordinary_creator_is_blocked_but_another_authorized_approver_can_approve(): void
    {
        $creator = $this->ordinaryApprover();
        $approver = $this->ordinaryApprover();
        $this->actingAs($creator);
        $invoice = $this->submit($this->draft('10'));
        self::assertFalse($creator->canBypassPurchaseInvoiceApproval());
        try {
            app(SavePurchaseReceivingAction::class)->execute($invoice->id, [], $invoice->lock_version);
            self::fail('Ordinary creator saved a receiving review.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(__('The invoice creator cannot approve the same invoice.'), $exception->getMessage());
        }
        try {
            app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
            self::fail('Ordinary creator approved their own invoice.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(__('The invoice creator cannot approve the same invoice.'), $exception->getMessage());
        }
        self::assertSame(0, StockMovement::query()->count());
        $this->actingAs($approver);
        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame('approved', $approved->status);
        self::assertSame($creator->id, $approved->created_by);
        self::assertSame($approver->id, $approved->approved_by);
    }

    public function test_purchase_receiving_localizes_egyptian_arabic_and_english(): void
    {
        $invoice = $this->submit($this->draft('10'));
        foreach (['ar-EG' => ['rtl', 'اعتماد الفاتورة', 'الكمية الأصلية'], 'en' => ['ltr', 'Approve invoice', 'Original quantity']] as $locale => [$direction, $heading, $quantity]) {
            app()->setLocale($locale);
            $html = Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id)->html();
            self::assertStringContainsString($heading, $html);
            self::assertStringContainsString($quantity, $html);
            $page = $this->get(route('purchasing.invoices'))->assertOk()->getContent();
            self::assertIsString($page);
            self::assertStringContainsString('dir="'.$direction.'"', $page);
            if ($locale === 'ar-EG') {
                self::assertStringNotContainsString('Verify accepted quantities before approval.', $html);
                self::assertStringNotContainsString('Accepted quantity', $html);
            }
        }
    }

    public function test_direct_approval_posts_only_accepted_stock_and_current_prices_once(): void
    {
        $invoice = $this->draft();
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        self::assertSame('25.00', $this->product->fresh()->sale_price);
        self::assertSame(0, StockMovement::query()->count());
        $invoice = $this->submit($invoice);
        $line = $invoice->lines->first();
        $invoice = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '900'], $invoice->lock_version);
        self::assertSame('1000.000000', $line->fresh()->original_quantity);
        self::assertSame('900.000000', $line->fresh()->quantity);
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        $version = $invoice->lock_version;
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $version);
        self::assertSame('approved', $invoice->status);
        self::assertSame('900.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame('12.00', $this->product->fresh()->average_cost);
        self::assertSame('30.00', $this->product->fresh()->sale_price);
        self::assertSame('100.000000', $line->fresh()->shortageQuantity());
        self::assertSame(1, PurchaseInvoiceLine::query()->withShortage()->count());
        self::assertSame(0, StockTransfer::query()->count());
        self::assertSame(0, DB::table('purchase_returns')->count());
        $auditCount = AuditLog::query()->count();
        $snapshot = $line->fresh()->getAttributes();
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $version);
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame($auditCount, AuditLog::query()->count());
        self::assertSame($snapshot, $line->fresh()->getAttributes());
        $this->get(route('purchasing.quantity-differences'))->assertOk()->assertSee('100')->assertSee($invoice->invoice_number);
        $export = $this->get(route('purchasing.quantity-differences', ['export' => 'csv']))->assertOk()->streamedContent();
        self::assertStringContainsString('1000.000000,900.000000,100.000000', $export);
        $this->get(route('purchasing.invoices.print', $invoice))->assertOk()->assertSee('Original quantity')->assertSee('Accepted quantity');
    }

    public function test_partial_optional_distribution_and_blank_cells_preserve_receiving_stock(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $blankStore = Store::factory()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => $this->fixture['branch']->id, 'type' => 'warehouse']);
        $invoice = app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$line->id => [$this->fixture['store']->id => '3', $blankStore->id => '']], $invoice->lock_version);
        self::assertCount(1, $invoice->distributions);
        self::assertSame('30.000', $invoice->distributions->first()->effective_selling_price);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        self::assertSame('7.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame(1, StockTransfer::query()->count());
        self::assertSame('3.000000', StockBalance::query()->where('store_id', $this->fixture['store']->id)->firstOrFail()->in_transit);
        self::assertSame(0, PurchaseInvoiceLine::query()->withShortage()->count());
    }

    public function test_invalid_distribution_is_visible_in_ui_and_rolls_back(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $component = Livewire::test('purchasing::invoices')->call('openDistribution', $invoice->id)
            ->set('selectedDestinationIds', [(string) $this->fixture['store']->id])
            ->set('distributionMatrix', [$line->id => [$this->fixture['store']->id => '11']])
            ->call('saveDistribution')->assertHasErrors(['distributionMatrix.'.$line->id]);
        self::assertStringContainsString('Distribution cannot exceed the accepted quantity.', $component->html());
        self::assertSame(0, $invoice->distributions()->count());
        self::assertSame($invoice->lock_version, $invoice->fresh()->lock_version);
        $component->set('distributionMatrix', [$line->id => [$this->fixture['store']->id => '']])->call('saveDistribution')->assertHasNoErrors();
    }

    public function test_draft_edit_preserves_original_and_later_approval_preserves_history(): void
    {
        $invoice = $this->draft();
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'store_id' => $invoice->store_id],
            [['product_id' => $this->product->id, 'quantity' => '900', 'unit_cost' => '12', 'base_consumer_price' => '30', 'original_quantity' => '900']], $invoice->id, $invoice->lock_version);
        self::assertSame('1000.000000', $invoice->lines->first()->original_quantity);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($this->submit($invoice)->id);
        $lineSnapshot = $invoice->lines->first()->getAttributes();
        $movementSnapshot = StockMovement::query()->firstOrFail()->getAttributes();
        $later = $this->submit($this->draft('5', '18', '45'));
        app(ApprovePurchaseInvoiceAction::class)->execute($later->id);
        self::assertSame('18.00', $this->product->fresh()->average_cost);
        self::assertSame('45.00', $this->product->fresh()->sale_price);
        self::assertSame($lineSnapshot, $invoice->lines->first()->fresh()->getAttributes());
        self::assertSame($movementSnapshot, StockMovement::query()->findOrFail($movementSnapshot['id'])->getAttributes());
        self::assertSame(1, PurchaseInvoiceLine::query()->withShortage()->count());
        $this->expectException(\LogicException::class);
        $invoice->lines->first()->update(['original_quantity' => '900']);
    }

    public function test_livewire_received_quantity_is_saved_atomically_with_approval(): void
    {
        $invoice = $this->submit($this->draft());
        $line = $invoice->lines->first();
        Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id)
            ->set('receivedQuantities.'.$line->id, '900')->call('approveInvoice', $invoice->id)->assertHasNoErrors()->assertSet('showReceiving', false);
        self::assertSame('approved', $invoice->fresh()->status);
        self::assertSame('900.000000', $line->fresh()->quantity_received);
    }

    public function test_livewire_full_quantity_approval_posts_once_and_shows_current_selling_price(): void
    {
        $invoice = $this->submit($this->draft('5'));
        $line = $invoice->lines->firstOrFail();
        app()->setLocale('ar-EG');

        $component = Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id);
        $html = $component->html();
        foreach (['المنتج', 'الكمية الأصلية', 'سعر البيع', 'الكمية المستلمة فعليًا', '25.00'] as $expected) {
            self::assertStringContainsString($expected, $html);
        }

        $component->call('approveInvoice', $invoice->id)->assertHasNoErrors()->assertSet('showReceiving', false);
        self::assertSame('approved', $invoice->fresh()->status);
        self::assertSame('5.000000', $line->fresh()->quantity_received);
        self::assertSame('5.000000', StockBalance::query()->where('store_id', $invoice->store_id)->firstOrFail()->on_hand);
        self::assertSame('12.00', $this->product->fresh()->average_cost);
        self::assertSame(1, StockMovement::query()->count());
        self::assertSame(1, AuditLog::query()->where('event', 'approve_purchase_invoice')->count());

        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame(1, StockMovement::query()->count());
    }

    public function test_active_unassigned_central_warehouse_approves_full_receipt_once(): void
    {
        $central = Store::factory()->warehouse()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => null, 'status' => 'active']);
        $invoice = $this->submit($this->draft('5', store: $central));

        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);

        self::assertSame('approved', $approved->status);
        self::assertSame($central->id, $approved->store_id);
        self::assertNotNull($approved->invoice_number);
        self::assertSame('5.000000', StockBalance::query()->where('store_id', $central->id)->firstOrFail()->on_hand);
        self::assertSame($central->id, StockMovement::query()->sole()->store_id);
        self::assertSame('12.00', $this->product->fresh()->average_cost);
        self::assertSame(1, AuditLog::query()->where('event', 'approve_purchase_invoice')->count());
        $auditCount = AuditLog::query()->count();

        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);

        self::assertSame(1, StockMovement::query()->count());
        self::assertSame($auditCount, AuditLog::query()->count());
    }

    public function test_active_unassigned_central_warehouse_approves_partial_receipt(): void
    {
        $central = Store::factory()->warehouse()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => null, 'status' => 'active']);
        $invoice = $this->submit($this->draft('5', store: $central));
        $line = $invoice->lines->firstOrFail();

        Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id)
            ->set('receivedQuantities.'.$line->id, '3')->call('approveInvoice', $invoice->id)->assertHasNoErrors();

        self::assertSame('approved', $invoice->fresh()->status);
        self::assertSame('5.000000', $line->fresh()->original_quantity);
        self::assertSame('3.000000', $line->fresh()->quantity_received);
        self::assertSame('3.000000', StockBalance::query()->where('store_id', $central->id)->firstOrFail()->on_hand);
        self::assertSame($central->id, StockMovement::query()->sole()->store_id);
    }

    public function test_active_branch_linked_warehouse_still_approves(): void
    {
        $invoice = $this->submit($this->draft('5'));

        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);

        self::assertSame('approved', $approved->status);
        self::assertSame($this->fixture['receiving']->id, StockMovement::query()->sole()->store_id);
    }

    public function test_inactive_or_nonexistent_receiving_warehouse_cannot_be_approved(): void
    {
        $central = Store::factory()->warehouse()->create(['company_id' => $this->fixture['company']->id, 'branch_id' => null, 'status' => 'active']);
        $invoice = $this->submit($this->draft('5', store: $central));
        $central->update(['status' => 'inactive']);

        try {
            app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
            self::fail('An inactive receiving warehouse was approved.');
        } catch (ModelNotFoundException) {
            self::assertSame('submitted', $invoice->fresh()->status);
            self::assertSame(0, StockMovement::query()->count());
        }

        try {
            app(SavePurchaseInvoiceAction::class)->execute(
                ['supplier_id' => $this->supplier->id, 'store_id' => 999999999, 'invoice_date' => '2026-10-12'],
                [['product_id' => $this->product->id, 'quantity' => '5', 'unit_cost' => '12', 'base_consumer_price' => '30']],
            );
            self::fail('A nonexistent receiving warehouse was accepted.');
        } catch (ModelNotFoundException) {
            self::assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_invalid_received_quantity_shows_arabic_error_and_leaves_approval_unposted(): void
    {
        $invoice = $this->submit($this->draft('5'));
        $line = $invoice->lines->firstOrFail();
        app()->setLocale('ar-EG');

        $component = Livewire::test('purchasing::invoices')->call('openReceivingReview', $invoice->id)
            ->set('receivedQuantities.'.$line->id, '-1')
            ->call('approveInvoice', $invoice->id)
            ->assertHasErrors(['approval'])
            ->assertSet('showReceiving', true);
        self::assertStringContainsString('يجب أن تكون كميات المنتجات أعدادًا صحيحة.', $component->html());
        self::assertSame('submitted', $invoice->fresh()->status);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame(0, AuditLog::query()->where('event', 'approve_purchase_invoice')->count());
    }

    public function test_unauthorized_approval_and_report_are_denied(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $this->actingAs($this->fixture['user']);
        $this->get(route('purchasing.quantity-differences'))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
    }

    public function test_zero_received_has_shortage_without_inventory_movement(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        $invoice = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '0'], $invoice->lock_version);
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        self::assertSame(0, StockMovement::query()->count());
        self::assertSame('10.000000', $line->fresh()->shortageQuantity());
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        self::assertSame('25.00', $this->product->fresh()->sale_price);
    }

    public function test_model_optional_in_final_draft_edit_readiness_and_product_form(): void
    {
        $data = ['name_ar' => 'منتج بدون موديل', 'name_en' => 'No model', 'category_id' => $this->fixture['category']->id,
            'preferred_supplier_id' => $this->supplier->id, 'barcode_registration_type' => 'local', 'product_type' => 'service', 'sale_price' => '25', 'status' => 'active'];
        $save = app(SaveProductAction::class);
        $product = $save->execute($data);
        self::assertNull($product->model_number);
        self::assertStringStartsWith('9123-', $product->item_code);
        self::assertFalse($product->hasIncompleteCard());
        self::assertTrue(Product::query()->completeCard()->whereKey($product->id)->exists());
        self::assertFalse(Product::query()->incompleteCard()->whereKey($product->id)->exists());
        self::assertTrue(app(ProductPricingReadiness::class)->assessProducts([$product])['product_cards_complete']);
        $product = $save->execute([...$data, 'model_number' => 'KEPT-123'], $product->id, $product->lock_version);
        $product = $save->execute($data, $product->id, $product->lock_version);
        self::assertSame('KEPT-123', $product->model_number);
        $draft = $save->execute([...$data, '_save_as_draft' => true]);
        self::assertNull($draft->model_number);
        $final = $save->execute($data, $draft->id, $draft->lock_version);
        self::assertNotNull($final->item_code);
        Livewire::test('catalog::product-form', ['product' => $final])->call('save')->assertHasNoErrors();
        self::assertNull($final->fresh()->model_number);
    }

    public function test_initial_product_cost_and_supplier_code_remain_optional_and_preserved_on_edit(): void
    {
        $data = ['name_ar' => 'منتج التكلفة', 'name_en' => 'Initial cost product',
            'category_id' => $this->fixture['category']->id, 'preferred_supplier_id' => $this->supplier->id,
            'barcode_registration_type' => 'local', 'product_type' => 'service', 'sale_price' => '25', 'status' => 'active'];
        $save = app(SaveProductAction::class);
        $withCost = $save->execute([...$data, 'average_cost' => '10', 'supplier_item_code' => 'LOCAL-10']);
        self::assertSame('10.00', $withCost->average_cost);
        self::assertSame('LOCAL-10', $withCost->preferredProductSupplier?->supplier_item_code);

        $withCost = $save->execute([...$data, 'name_en' => 'Edited', 'average_cost' => '', 'supplier_item_code' => ''], $withCost->id, $withCost->lock_version);
        self::assertSame('10.00', $withCost->fresh()->average_cost);
        self::assertSame('LOCAL-10', $withCost->fresh()->preferredProductSupplier?->supplier_item_code);

        $withoutCost = $save->execute([...$data, 'name_en' => 'No cost', 'average_cost' => '', 'supplier_item_code' => '']);
        self::assertNull($withoutCost->average_cost);
        self::assertNull($withoutCost->preferredProductSupplier?->supplier_item_code);
        self::assertFalse($withoutCost->hasIncompleteCard());
    }

    public function test_system_administrator_product_card_shows_optional_cost_without_an_active_permission_row(): void
    {
        Permission::query()->where('code', 'products_categories_brands.cost_view')->update(['status' => 'inactive']);
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->roles()->attach(Role::query()->where('code', 'system-administrator')->firstOrFail());
        self::assertTrue($administrator->hasPermission('products_categories_brands.cost_view'));

        $this->actingAs($administrator);
        app()->setLocale('ar');
        Livewire::test('catalog::product-form')->assertSee('سعر التكلفة')->assertSeeHtml('wire:model="productForm.average_cost"');
        app()->setLocale('en');
        Livewire::test('catalog::product-form')->assertSee('Cost price')->assertSeeHtml('wire:model="productForm.average_cost"');

        $data = ['name_ar' => 'منتج تكلفة الإدارة', 'name_en' => 'Administrator cost product',
            'category_id' => $this->fixture['category']->id, 'preferred_supplier_id' => $this->supplier->id,
            'barcode_registration_type' => 'local', 'product_type' => 'service', 'sale_price' => '25', 'status' => 'active'];
        $save = app(SaveProductAction::class);
        $product = $save->execute([...$data, 'average_cost' => '10']);
        self::assertSame('10.00', $product->average_cost);
        Livewire::test('catalog::product-form', ['product' => $product])->assertSet('productForm.average_cost', '10.00');
        $product = $save->execute([...$data, 'name_en' => 'Unrelated edit', 'average_cost' => ''], $product->id, $product->lock_version);
        self::assertSame('10.00', $product->fresh()->average_cost);
        self::assertNull($save->execute([...$data, 'name_en' => 'No initial cost'])->average_cost);

        $role = Role::query()->firstOrCreate(['code' => 'qa-card-editor'], ['name_ar' => 'محرر بطاقة', 'name_en' => 'Card editor', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('code', ['products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit'])->pluck('id'));
        $editor = User::factory()->create(['status' => 'active']);
        $editor->roles()->attach($role);
        $editor->storeScopes()->create(['store_id' => $this->fixture['receiving']->id, 'status' => 'active']);
        self::assertFalse($editor->hasPermission('products_categories_brands.cost_view'));
        $this->actingAs($editor);
        Livewire::test('catalog::product-form')->assertDontSeeHtml('wire:model="productForm.average_cost"');
        Livewire::test('catalog::product-form', ['product' => $product->fresh()])->assertSet('productForm.average_cost', '')->assertDontSeeHtml('wire:model="productForm.average_cost"');
    }

    public function test_current_product_cost_defaults_po_and_invoice_without_mutating_drafts_or_history(): void
    {
        $prices = app(SupplierProductPrice::class);
        self::assertSame('10.00', $prices->resolve($this->product, $this->supplier->id, $this->fixture['company']->id)['unit_cost']);
        $earlierOrder = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'branch_id' => $this->fixture['branch']->id, 'store_id' => $this->fixture['receiving']->id, 'order_date' => '2026-10-11'],
            [['product_id' => $this->product->id, 'quantity_ordered' => 1, 'unit_cost' => '10']],
        );
        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $this->supplier->id, 'branch_id' => $this->fixture['branch']->id, 'store_id' => $this->fixture['receiving']->id, 'order_date' => '2026-10-12'],
            [['product_id' => $this->product->id, 'quantity_ordered' => 1, 'unit_cost' => '20']],
        );
        $invoice = $this->draft('1', '20');
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($this->submit($invoice)->id);
        self::assertSame('20.00', $this->product->fresh()->average_cost);
        self::assertSame('20.00', $prices->resolve($this->product->fresh(), $this->supplier->id, $this->fixture['company']->id)['unit_cost']);
        self::assertSame('10.0000', $earlierOrder->lines->firstOrFail()->fresh()->unit_cost);
        self::assertSame('20.0000', $order->lines->firstOrFail()->fresh()->unit_cost);
        self::assertSame('20.0000', $approved->lines->firstOrFail()->unit_cost);
    }

    public function test_workbook_import_allows_multiple_blank_models_without_fabricating_identity(): void
    {
        $path = storage_path('app/model-optional.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Instructions');
        foreach ([['Template'], ['schema', 'toyjoy.product-card.v2'], ['version', '2'], ['company', $this->fixture['company']->code.' QA']] as $metadata) {
            $writer->addRow(Row::fromValues($metadata));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('Data Entry');
        $headers = ImportProductCardsWorkbookAction::HEADERS;
        $writer->addRow(Row::fromValues($headers));
        foreach (['مستورد أول', 'مستورد ثان'] as $name) {
            $data = ['barcode_registration_type' => 'local', 'supplier_code' => $this->supplier->code, 'name_ar' => $name,
                'base_consumer_price' => '25', 'category_code' => $this->fixture['category']->code, 'product_type' => 'standard', 'status' => 'active'];
            $writer->addRow(Row::fromValues(array_map(fn ($key) => $data[$key] ?? '', $headers)));
        }
        $writer->close();
        $result = app(ImportProductCardsWorkbookAction::class)->execute($path);
        self::assertSame(2, $result['added'], json_encode($result['rejections']));
        self::assertSame(0, $result['rejected']);
        $products = Product::query()->whereIn('name_ar', ['مستورد أول', 'مستورد ثان'])->get();
        self::assertCount(2, $products);
        foreach ($products as $product) {
            self::assertNull($product->model_number);
            self::assertStringStartsWith('9123-', $product->item_code);
        }
        self::assertNotSame($products[0]->item_code, $products[1]->item_code);
    }

    public function test_stale_review_and_cross_company_distribution_cannot_write(): void
    {
        $invoice = $this->submit($this->draft('10'));
        $line = $invoice->lines->first();
        try {
            app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$line->id => '9'], $invoice->lock_version - 1);
            self::fail('Stale review was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('another session', $exception->getMessage());
        }
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->create(['company_id' => $otherCompany->id]);
        $other = Store::factory()->create(['type' => 'warehouse', 'company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id]);
        try {
            app(SavePurchaseDistributionAction::class)->execute($invoice->id, [$line->id => [$other->id => '1']], $invoice->lock_version);
            self::fail('Cross-company distribution was accepted.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('company and store scope', $exception->getMessage());
        }
        self::assertSame('10.000000', $line->fresh()->quantity);
        self::assertSame(0, $invoice->distributions()->count());
        self::assertSame(0, StockMovement::query()->count());
    }

    public function test_baseline_blank_distribution_cell_failure_is_meaningful_validation(): void
    {
        try {
            ProductQuantity::normalize('', 'distribution.1.1', true);
            self::fail('Baseline normalization unexpectedly accepted a blank cell.');
        } catch (ValidationException $exception) {
            self::assertSame('Product quantities must be whole numbers.', $exception->errors()['distribution.1.1'][0]);
        }
        // The old action sent blank cells here and UserSafeError masked this exception.
        self::assertStringContainsString('Unable to complete the request', file_get_contents(app_path('Support/UserSafeError.php')));
    }
}
