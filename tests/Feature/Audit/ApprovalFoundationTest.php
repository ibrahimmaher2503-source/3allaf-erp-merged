<?php

namespace Tests\Feature\Audit;

use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\CancelApprovalRequest;
use App\Modules\Platform\Actions\ExpireApprovalRequest;
use App\Modules\Platform\Actions\RejectRequest;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Actions\WithdrawApprovalRequest;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009 — Approval foundation.
 *
 * The approval infrastructure exists (records, transitions, policy, audit) but
 * no current Platform source requests approval, so only the reusable action
 * layer is covered here. Source and UI integration are NOT tested.
 *
 * @group tsk-009
 */
class ApprovalFoundationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('tsk009-approval-setup'));
        $this->branch = $this->branch('APR-BR');
    }

    private function data(array $overrides = []): ApprovalRequestData
    {
        return new ApprovalRequestData(
            sourceType: $overrides['sourceType'] ?? 'branches_stores',
            sourceId: $overrides['sourceId'] ?? (string) $this->branch->id,
            sourceVersion: array_key_exists('sourceVersion', $overrides) ? $overrides['sourceVersion'] : 'v1',
            requestedAction: $overrides['requestedAction'] ?? 'deactivate',
            requestPermission: $overrides['requestPermission'] ?? 'branches_stores.edit',
            branchId: $overrides['branchId'] ?? $this->branch->id,
            storeId: $overrides['storeId'] ?? null,
            reasonCode: $overrides['reasonCode'] ?? 'OPS-09',
            reasonText: $overrides['reasonText'] ?? 'Automated regression request.',
            limitContext: $overrides['limitContext'] ?? ['amount' => 100],
            sourceHash: array_key_exists('sourceHash', $overrides) ? $overrides['sourceHash'] : null,
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
            expiresAt: $overrides['expiresAt'] ?? null,
        );
    }

    public function test_a_request_is_created_pending_and_audited(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());

        $this->assertSame(ApprovalState::Pending, $record->approval_state);
        $this->assertNotNull($record->pending_key);
        $this->assertNotNull($record->requested_at);

        $event = AuditLog::query()->where('event', 'approval_requested')->sole();
        $this->assertSame('workflow', $event->category);
        $this->assertSame($record->id, $event->metadata['approval_record_id']);
        $this->assertSame('branches_stores.approve', $event->metadata['decision_permission']);
    }

    public function test_a_request_without_a_source_version_or_hash_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(RequestApproval::class)->execute($this->data(['sourceVersion' => null, 'sourceHash' => null]));
    }

    public function test_a_second_pending_request_for_the_same_source_action_is_rejected(): void
    {
        app(RequestApproval::class)->execute($this->data());

        try {
            app(RequestApproval::class)->execute($this->data());
            $this->fail('A duplicate pending approval request was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source', $exception->errors());
        }

        $this->assertSame(1, ApprovalRecord::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_requested')->count());
    }

    public function test_an_idempotency_key_returns_the_original_record_without_a_second_audit_event(): void
    {
        $first = app(RequestApproval::class)->execute($this->data(['idempotencyKey' => 'IDEMPOTENT-1']));
        $second = app(RequestApproval::class)->execute($this->data(['idempotencyKey' => 'IDEMPOTENT-1']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ApprovalRecord::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_requested')->count());
    }

    public function test_a_reused_idempotency_key_for_a_different_request_is_rejected(): void
    {
        app(RequestApproval::class)->execute($this->data(['idempotencyKey' => 'IDEMPOTENT-2']));

        $this->expectException(ValidationException::class);

        app(RequestApproval::class)->execute($this->data([
            'idempotencyKey' => 'IDEMPOTENT-2',
            'requestedAction' => 'a-different-action',
        ]));
    }

    public function test_a_requester_cannot_approve_their_own_request(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());

        try {
            app(ApproveRequest::class)->execute($record, 'v1');
            $this->fail('Separation of duties was not enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approver', $exception->errors());
        }

        $this->assertSame(ApprovalState::Pending, $record->fresh()->approval_state);
    }

    public function test_a_second_administrator_can_approve_and_the_decision_is_audited(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());

        $approver = $this->administrator('tsk009-approver');
        $this->actingAs($approver);

        $approved = app(ApproveRequest::class)->execute($record, 'v1', null, 'Approved during regression.');

        $this->assertSame(ApprovalState::Approved, $approved->approval_state);
        $this->assertSame($approver->id, $approved->approver_id);
        $this->assertNotNull($approved->decided_at);
        $this->assertNull($approved->pending_key, 'A decided request must release its pending key.');

        $event = AuditLog::query()->where('event', 'approval_approved')->sole();
        $this->assertSame('pending', $event->before_values['approval_state']);
        $this->assertSame('approved', $event->after_values['approval_state']);
    }

    public function test_a_stale_source_version_is_rejected(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());
        $this->actingAs($this->administrator('tsk009-stale-approver'));

        try {
            app(ApproveRequest::class)->execute($record, 'v2');
            $this->fail('A stale approval decision was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_version', $exception->errors());
        }

        $this->assertSame(ApprovalState::Pending, $record->fresh()->approval_state);
        $this->assertSame(0, AuditLog::query()->where('event', 'approval_approved')->count());
    }

    public function test_a_terminal_request_cannot_be_decided_again(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());
        $this->actingAs($this->administrator('tsk009-double-approver'));
        app(ApproveRequest::class)->execute($record, 'v1');

        try {
            app(RejectRequest::class)->execute($record->fresh(), 'v1', 'Too late.');
            $this->fail('A terminal approval record was decided twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_state', $exception->errors());
        }

        $this->assertSame(ApprovalState::Approved, $record->fresh()->approval_state);
    }

    public function test_a_requester_can_withdraw_and_an_authorized_actor_can_cancel(): void
    {
        $withdrawable = app(RequestApproval::class)->execute($this->data(['requestedAction' => 'withdraw-me']));
        $withdrawn = app(WithdrawApprovalRequest::class)->execute($withdrawable, 'v1', null, 'No longer needed.');
        $this->assertSame(ApprovalState::Withdrawn, $withdrawn->approval_state);

        $cancellable = app(RequestApproval::class)->execute($this->data(['requestedAction' => 'cancel-me']));
        $cancelled = app(CancelApprovalRequest::class)->execute($cancellable, 'v1', 'Cancelled by administrator.');
        $this->assertSame(ApprovalState::Cancelled, $cancelled->approval_state);

        $this->assertSame(1, AuditLog::query()->where('event', 'approval_withdrawn')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_cancelled')->count());
    }

    public function test_an_expired_request_moves_to_the_expired_state(): void
    {
        $record = app(RequestApproval::class)->execute($this->data([
            'expiresAt' => now()->addMinute(),
        ]));

        $this->travelTo(now()->addHours(2));

        $expired = app(ExpireApprovalRequest::class)->execute($record->fresh());

        $this->assertSame(ApprovalState::Expired, $expired->approval_state);
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_expired')->count());
    }

    public function test_an_approval_record_cannot_be_updated_outside_a_named_transition(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());

        try {
            $record->update(['decision_note' => 'tampered']);
            $this->fail('An approval record was updated outside a transition.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('named transition', $exception->getMessage());
        }

        $this->assertNull($record->fresh()->decision_note);
    }

    public function test_an_approval_record_cannot_be_deleted(): void
    {
        $record = app(RequestApproval::class)->execute($this->data());

        try {
            $record->delete();
            $this->fail('An approval record was deleted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('preserved', $exception->getMessage());
        }

        $this->assertDatabaseHas('approval_records', ['id' => $record->id]);
    }

    public function test_an_out_of_scope_requester_is_denied(): void
    {
        $foreignBranch = $this->branch('APR-OUT');

        $scoped = $this->userWith('tsk009-approval-scoped', ['branch-manager'], false, [$this->branch->id]);
        $this->actingAs($scoped);

        $this->expectException(HttpException::class);

        app(RequestApproval::class)->execute($this->data([
            'branchId' => $foreignBranch->id,
            'requestPermission' => 'branches_stores.view',
        ]));
    }

    public function test_the_approval_foundation_is_not_wired_to_any_current_source(): void
    {
        // Recorded coverage fact: no current Platform action requests approval,
        // so end-to-end approval behavior cannot be tested.
        foreach (glob(app_path('Modules/Platform/Actions/Save*.php')) ?: [] as $file) {
            $this->assertStringNotContainsString('RequestApproval', (string) file_get_contents($file));
        }

        $approvalRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'approval'));

        $this->assertTrue($approvalRoutes->isEmpty());
    }

    public function test_the_document_immutability_and_correction_slice_is_absent(): void
    {
        // Recorded coverage fact for TSK-009: append-only guards exist on the
        // audit and approval models only. No document state machine, referenced
        // correction, or reversal document exists to test.
        $this->assertFalse(Schema::hasTable('document_states'));

        foreach (glob(app_path('Modules/Platform/Actions/Save*.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertStringNotContainsString('correction', strtolower($contents));
            $this->assertStringNotContainsString('reversal', strtolower($contents));
        }
    }
}
