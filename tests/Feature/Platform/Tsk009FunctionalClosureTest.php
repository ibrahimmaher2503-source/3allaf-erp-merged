<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Actions\ManageProductMediaAction;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\AuditLogValueRedactor;
use App\Modules\Platform\Actions\OverrideDocumentSequenceCounter;
use App\Modules\Platform\Actions\QuarantineAttachment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RedactAttachment;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Actions\RejectPurchaseReturnAction;
use App\Modules\Purchasing\Actions\ReversePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseReturnAction;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** @group tsk-009 */
final class Tsk009FunctionalClosureTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_the_central_inbox_denies_unrelated_users_and_lists_only_scoped_requests(): void
    {
        $branch = $this->branch('APR-IN');
        $foreign = $this->branch('APR-OUT');
        $creator = $this->administrator('approval-creator');
        $this->actingAs($creator);
        $mine = $this->approval($branch->id, 'mine');
        $foreignApproval = $this->approval($foreign->id, 'foreign');

        $this->actingAs($this->userWith('approval-none'));
        $this->get(route('admin.approvals'))->assertForbidden();

        $reviewer = $this->userWith('approval-reviewer', ['accountant-reviewer'], false, [$branch->id]);
        $this->actingAs($reviewer);
        Livewire::test('platform::system.approval-inbox')
            ->assertOk()
            ->assertSee('#'.$mine->source_id)
            ->assertDontSee('#'.$foreignApproval->source_id)
            ->call('showApproval', $mine->id)
            ->assertSet('selectedApprovalId', $mine->id);
    }

    public function test_the_real_inbox_approval_route_executes_the_source_action_and_audit_atomically(): void
    {
        [$supplierId, $productId, $storeId] = $this->purchaseOrderReferences();
        DocumentSequence::query()->create(['document_type' => 'purchase_order', 'prefix' => 'PO-', 'padding_length' => 5, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]);
        $requester = $this->administrator('po-inbox-requester');
        $approver = $this->administrator('po-inbox-approver');
        $this->actingAs($requester);
        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplierId, 'store_id' => $storeId],
            [['product_id' => $productId, 'quantity_ordered' => '2', 'unit_cost' => '4.50']],
        );
        $order = app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);
        $approval = ApprovalRecord::query()->where('source_type', 'purchase_orders')->where('source_id', (string) $order->id)->sole();

        $this->actingAs($approver);
        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->call('approve')
            ->assertHasNoErrors();

        self::assertSame('approved', PurchaseOrder::query()->findOrFail($order->id)->status);
        self::assertSame(ApprovalState::Approved, $approval->fresh()->approval_state);
        self::assertTrue(AuditLog::query()->where('event', 'approval_approved')->where('source_id', (string) $approval->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'approve_purchase_order')->where('source_id', (string) $order->id)->exists());
    }

    public function test_approval_evidence_upload_and_real_download_are_private_scoped_and_audited(): void
    {
        Storage::fake('local');
        $branch = $this->branch('EVD-BR');
        $store = $this->store($branch, 'EVD-ST');
        $admin = $this->administrator('evidence-admin');
        $this->actingAs($admin);
        $approval = $this->approval($branch->id, 'evidence', $store->id);

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->set('evidence', UploadedFile::fake()->image('approval.png', 20, 20))
            ->call('uploadEvidence')
            ->assertHasNoErrors();

        $attachment = Attachment::query()->where('source_type', ApprovalRecord::class)->where('source_id', (string) $approval->id)->sole();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->get(route('admin.approvals.attachments.download', [$approval, $attachment]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        self::assertTrue(AuditLog::query()->where('event', 'attachment_accessed')->where('source_id', $attachment->id)->exists());

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->call('revokeEvidence', $attachment->id)
            ->assertHasErrors('revokeReason')
            ->set('revokeReason', 'Superseded by corrected approval evidence.')
            ->call('revokeEvidence', $attachment->id)
            ->assertHasNoErrors();
        self::assertSame('deleted', $attachment->fresh()->status->value);
        self::assertNotNull(AuditLog::query()->where('event', 'attachment_revoked')->where('source_id', $attachment->id)->value('reason_text'));

        $foreign = $this->branch('EVD-OUT');
        $outsider = $this->userWith('evidence-outsider', ['accountant-reviewer'], false, [$foreign->id]);
        $this->actingAs($outsider);
        $this->get(route('admin.approvals.attachments.download', [$approval, $attachment]))->assertForbidden();
    }

    public function test_audit_export_is_permissioned_bounded_formula_safe_and_records_its_own_event(): void
    {
        config(['audit.export_max_rows' => 2]);
        $admin = $this->administrator('export-admin');
        $this->actingAs($admin);
        app(RecordAuditEvent::class)->execute('security', '=formula_event', after: ['safe' => '+formula']);

        $response = $this->get(route('admin.audit.export'));
        $response->assertOk()->assertDownload();
        self::assertStringContainsString("'=formula_event", $response->streamedContent());
        self::assertTrue(AuditLog::query()->where('event', 'audit_log_exported')->exists());

        app(RecordAuditEvent::class)->execute('security', 'third_event');
        $this->getJson(route('admin.audit.export'))->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_viewer_sensitive_audit_values_are_redacted_without_the_field_permission(): void
    {
        $role = Role::query()->create(['code' => 'audit-only', 'name_ar' => 'Audit', 'name_en' => 'Audit only', 'status' => 'active']);
        $role->permissions()->sync([Permission::query()->where('code', 'audit_logs.view')->value('id')]);
        $viewer = $this->userWith('audit-only-user', ['audit-only']);

        $redacted = app(AuditLogValueRedactor::class)->redactForViewer([
            'unit_cost' => '12.00', 'wallet_balance' => '50.00', 'customer_phone' => '0100', 'status' => 'approved',
        ], $viewer);

        self::assertSame('[redacted:cost_permission]', $redacted['unit_cost']);
        self::assertSame('[redacted:wallet_permission]', $redacted['wallet_balance']);
        self::assertSame('[redacted:customer_permission]', $redacted['customer_phone']);
        self::assertSame('approved', $redacted['status']);
    }

    public function test_approved_headers_and_lines_reject_direct_mutation_but_named_lifecycle_mutation_is_allowed(): void
    {
        [$supplierId, $productId, $storeId] = $this->purchaseOrderReferences();
        $order = PurchaseOrder::query()->create(['po_number' => 'IMM-1', 'supplier_id' => $supplierId, 'store_id' => $storeId, 'status' => 'approved', 'order_date' => today(), 'lock_version' => 2, 'created_by' => 1]);
        $line = $order->lines()->create(['product_id' => $productId, 'line_number' => 1, 'quantity_ordered' => 1, 'quantity_received' => 0, 'unit_cost' => 1, 'subtotal' => 1]);

        try {
            $order->update(['notes' => 'forged']);
            self::fail('Approved header mutation was not blocked.');
        } catch (LogicException) {
            self::assertNull($order->fresh()->notes);
        }

        try {
            $line->update(['unit_cost' => 99]);
            self::fail('Approved line mutation was not blocked.');
        } catch (LogicException) {
            self::assertSame('1.0000', (string) $line->fresh()->unit_cost);
        }

        $order->mutateApprovedDocument(['status' => 'closed', 'lock_version' => 3]);
        self::assertSame('closed', $order->fresh()->status);
    }

    public function test_sequence_allocation_fails_without_configuration_and_counter_override_is_stale_safe_and_audited(): void
    {
        $actor = $this->userWith('sequence-admin', ['system-administrator']);
        $this->actingAs($actor);

        try {
            app(AllocateDocumentNumber::class)->execute('not_configured');
            self::fail('Missing document-number configuration must block allocation.');
        } catch (ValidationException) {
            self::assertFalse(DocumentSequence::query()->where('document_type', 'not_configured')->exists());
        }

        $sequence = DocumentSequence::query()->create(['document_type' => 'test_document', 'prefix' => 'T-', 'padding_length' => 3, 'next_value' => 4, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]);

        $updated = app(OverrideDocumentSequenceCounter::class)->execute($sequence, 20, 1, 'Approved recovery after documented counter mismatch.');
        self::assertSame(20, $updated->next_value);
        self::assertTrue(AuditLog::query()->where('event', 'document_sequence_counter_overridden')->where('source_id', (string) $sequence->id)->exists());
        self::assertSame('T-020', app(AllocateDocumentNumber::class)->execute('test_document'));
        self::assertSame(21, $updated->fresh()->next_value);
        self::assertTrue(AuditLog::query()->where('event', 'document_number_allocated')->where('source_id', (string) $sequence->id)->exists());

        $this->expectException(ValidationException::class);
        app(OverrideDocumentSequenceCounter::class)->execute($updated, 30, 1, 'Stale attempt must fail.');
    }

    public function test_approved_invoice_reversal_preserves_the_original_and_reconciles_the_correction_movement_and_audit(): void
    {
        [$supplierId, $productId, $storeId] = $this->purchaseOrderReferences();
        DocumentSequence::query()->create(['document_type' => 'purchase_invoice', 'prefix' => 'REV-', 'padding_length' => 5, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]);
        $creator = $this->administrator('correction-creator');
        $approver = $this->administrator('correction-approver');
        $reverser = $this->administrator('correction-reverser');
        $this->actingAs($creator);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplierId, 'store_id' => $storeId, 'supplier_reference' => 'CORR-INV-1', 'invoice_date' => today()->toDateString()],
            [['product_id' => $productId, 'quantity' => '2', 'unit_cost' => '7', 'discount_type' => '', 'discount_value' => '0', 'tax_rate' => '0']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $this->actingAs($approver);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $original = $invoice->only(['invoice_number', 'supplier_id', 'store_id', 'supplier_reference', 'total_amount']);
        $receipt = StockMovement::query()->where('source_id', $invoice->id)->where('movement_type', 'purchase_receipt')->sole();

        $this->actingAs($reverser);
        $reversed = app(ReversePurchaseInvoiceAction::class)->execute($invoice->id, 'Documented supplier invoice correction.', $invoice->lock_version);
        $reversal = StockMovement::query()->where('reversal_of_id', $receipt->id)->sole();

        self::assertSame('reversed', $reversed->status);
        self::assertSame($original, $reversed->only(array_keys($original)));
        self::assertSame((string) bcmul((string) $receipt->quantity, '-1', 6), (string) $reversal->quantity);
        self::assertSame('0.000000', (string) StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->value('on_hand'));
        self::assertTrue(AuditLog::query()->where('event', 'correction.created')->where('source_id', (string) $reversal->id)->where('metadata->original_source_id', (string) $invoice->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'reverse_purchase_invoice')->where('source_id', (string) $invoice->id)->exists());
    }

    public function test_supplier_return_rejection_transitions_the_source_and_shared_approval_together(): void
    {
        [$supplierId, $productId, $storeId] = $this->purchaseOrderReferences();
        DocumentSequence::query()->create(['document_type' => 'purchase_invoice', 'prefix' => 'INV-', 'padding_length' => 5, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1]);
        $creator = $this->administrator('return-sync-creator');
        $approver = $this->administrator('return-sync-invoice-approver');
        $rejector = $this->administrator('return-sync-rejector');
        $this->actingAs($creator);
        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplierId, 'store_id' => $storeId, 'supplier_reference' => 'SYNC-INV-1', 'invoice_date' => today()->toDateString()],
            [['product_id' => $productId, 'quantity' => '3', 'unit_cost' => '5', 'discount_type' => '', 'discount_value' => '0', 'tax_rate' => '0']],
        );
        $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
        $this->actingAs($approver);
        $invoice = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);

        $reason = SupplierReturnReason::query()->create(['code' => 'SYNC', 'label_ar' => 'Return', 'label_en' => 'Return', 'is_active' => true]);
        $this->actingAs($creator);
        $return = app(CreatePurchaseReturnDraftAction::class)->execute($invoice->id, $reason->id, [[
            'purchase_invoice_line_id' => $invoice->lines->first()->id,
            'quantity' => '1',
        ]], 'return-sync-key');
        $return = app(SubmitPurchaseReturnAction::class)->execute($return->id, $return->lock_version);
        $approval = ApprovalRecord::query()->where('source_type', 'purchase_returns')->where('source_id', (string) $return->id)->sole();

        $this->actingAs($rejector);
        $rejected = app(RejectPurchaseReturnAction::class)->execute($return->id, 'Rejected by independent reviewer.', $return->lock_version);

        self::assertSame('rejected', $rejected->status);
        self::assertSame(ApprovalState::Rejected, $approval->fresh()->approval_state);
        self::assertTrue(AuditLog::query()->where('event', 'approval_rejected')->where('source_id', (string) $approval->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'reject_supplier_return')->where('source_id', (string) $return->id)->exists());
    }

    public function test_product_main_image_replacement_revokes_the_old_attachment_with_a_reason(): void
    {
        Storage::fake('local');
        [, $productId] = $this->purchaseOrderReferences();
        $product = Product::query()->findOrFail($productId);
        $first = app(ManageProductMediaAction::class)->upload($product, UploadedFile::fake()->image('first.png', 20, 20), 'main');
        $second = app(ManageProductMediaAction::class)->upload($product, UploadedFile::fake()->image('second.png', 21, 20), 'main');

        self::assertSame('revoked', $first->fresh()->status);
        self::assertSame('deleted', $first->attachment->fresh()->status->value);
        self::assertSame('active', $second->fresh()->status);
        self::assertTrue(AuditLog::query()->where('event', 'attachment_revoked')->where('source_id', $first->attachment_id)->whereNotNull('reason_text')->exists());
    }

    public function test_attachment_quarantine_and_redaction_are_named_audited_non_deliverable_transitions(): void
    {
        Storage::fake('local');
        $branch = $this->branch('LIFE-BR');
        $admin = $this->administrator('lifecycle-admin');
        $this->actingAs($admin);
        $approval = $this->approval($branch->id, 'lifecycle');
        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->set('evidence', UploadedFile::fake()->image('lifecycle.png', 20, 20))
            ->call('uploadEvidence');
        $attachment = Attachment::query()->where('source_type', ApprovalRecord::class)->where('source_id', (string) $approval->id)->sole();

        app(QuarantineAttachment::class)->execute($attachment, 'Security review required.', fn ($user, $candidate): bool => $candidate->source_id === (string) $approval->id);
        self::assertSame('quarantined', $attachment->fresh()->status->value);
        $this->get(route('admin.approvals.attachments.download', [$approval, $attachment]))->assertForbidden();

        app(RedactAttachment::class)->execute($attachment->fresh(), 'Approved permanent redaction.', fn ($user, $candidate): bool => $candidate->source_id === (string) $approval->id);
        self::assertSame('redacted', $attachment->fresh()->status->value);
        Storage::disk('local')->assertMissing($attachment->storage_path);
        self::assertTrue(AuditLog::query()->where('event', 'attachment_quarantined')->where('source_id', $attachment->id)->exists());
        self::assertTrue(AuditLog::query()->where('event', 'attachment_redacted')->where('source_id', $attachment->id)->exists());
    }

    private function approval(int $branchId, string $reason, ?int $storeId = null): ApprovalRecord
    {
        return app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'pricing_labels', sourceId: (string) random_int(1000, 9999), sourceVersion: '1',
            requestedAction: 'approve_price_version', requestPermission: 'pricing_labels.submit',
            branchId: $branchId, storeId: $storeId, reasonText: $reason,
            idempotencyKey: 'test-approval-'.$reason,
        ));
    }

    /** @return array{int, int, int} */
    private function purchaseOrderReferences(): array
    {
        $admin = $this->administrator('references-'.str()->random(6));
        $this->actingAs($admin);
        $branch = $this->branch('REF-'.str()->upper(str()->random(5)));
        $store = $this->store($branch, 'ST-'.str()->upper(str()->random(5)));
        $supplier = app(SaveSupplierAction::class)->execute(['code' => 'SUP-'.str()->upper(str()->random(5)), 'name_ar' => 'Supplier', 'name_en' => 'Supplier', 'status' => 'active']);
        $category = app(SaveCategoryAction::class)->execute(['code' => 'CAT-'.str()->upper(str()->random(5)), 'name_ar' => 'Category', 'name_en' => 'Category', 'parent_id' => null, 'status' => 'active']);
        $product = app(SaveProductAction::class)->execute(['item_code' => 'ITEM-'.str()->upper(str()->random(5)), 'name_ar' => 'Product', 'name_en' => 'Product', 'category_id' => $category->id, 'product_type' => 'standard', 'status' => 'active']);

        return [$supplier->id, $product->id, $store->id];
    }
}
