<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RejectRequest;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009: canonical decision actions must compose with caller-owned transactions.
 *
 * @group tsk-009
 */
class ApprovalTransactionCompositionTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('approval-tx-requester'));
        $this->branch = $this->branch('APR-TX');
    }

    public function test_approve_request_commits_standalone_with_its_audit(): void
    {
        $record = $this->pending('approve-standalone');
        $this->actingAs($this->administrator('approval-tx-approve-standalone'));
        $entryLevel = DB::transactionLevel();

        $approved = app(ApproveRequest::class)->execute($record, 'v1', null, 'Approved.');

        $this->assertSame(ApprovalState::Approved, $approved->approval_state);
        $this->assertSame($entryLevel, DB::transactionLevel());
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_approved')->count());
    }

    public function test_reject_request_commits_standalone_with_its_audit(): void
    {
        $record = $this->pending('reject-standalone');
        $this->actingAs($this->administrator('approval-tx-reject-standalone'));
        $entryLevel = DB::transactionLevel();

        $rejected = app(RejectRequest::class)->execute($record, 'v1', 'Recount required.');

        $this->assertSame(ApprovalState::Rejected, $rejected->approval_state);
        $this->assertSame($entryLevel, DB::transactionLevel());
        $this->assertSame(1, AuditLog::query()->where('event', 'approval_rejected')->count());
    }

    public function test_approve_request_remains_part_of_an_outer_transaction_and_outer_rollback_restores_it(): void
    {
        $record = $this->pending('approve-outer-rollback');
        $this->actingAs($this->administrator('approval-tx-approve-outer'));
        $levels = [];
        $entryLevel = DB::transactionLevel();

        try {
            DB::transaction(function () use ($record, &$levels): void {
                $levels['before'] = DB::transactionLevel();
                app(ApproveRequest::class)->execute($record, 'v1', null, 'Approved then rolled back.');
                $levels['after'] = DB::transactionLevel();
                DB::select('select 1');

                throw new RuntimeException('Force caller rollback after canonical approval.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force caller rollback after canonical approval.', $exception->getMessage());
        }

        $this->assertSame(['before' => $entryLevel + 1, 'after' => $entryLevel + 1], $levels);
        $this->assertSame($entryLevel, DB::transactionLevel());
        $this->assertSame(ApprovalState::Pending, $record->fresh()->approval_state);
        $this->assertSame(0, AuditLog::query()->where('event', 'approval_approved')->count());
    }

    public function test_reject_request_remains_part_of_an_outer_transaction_and_outer_rollback_restores_it(): void
    {
        $record = $this->pending('reject-outer-rollback');
        $this->actingAs($this->administrator('approval-tx-reject-outer'));
        $levels = [];
        $entryLevel = DB::transactionLevel();

        try {
            DB::transaction(function () use ($record, &$levels): void {
                $levels['before'] = DB::transactionLevel();
                app(RejectRequest::class)->execute($record, 'v1', 'Recount then roll back.');
                $levels['after'] = DB::transactionLevel();
                DB::select('select 1');

                throw new RuntimeException('Force caller rollback after canonical rejection.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force caller rollback after canonical rejection.', $exception->getMessage());
        }

        $this->assertSame(['before' => $entryLevel + 1, 'after' => $entryLevel + 1], $levels);
        $this->assertSame($entryLevel, DB::transactionLevel());
        $this->assertSame(ApprovalState::Pending, $record->fresh()->approval_state);
        $this->assertSame(0, AuditLog::query()->where('event', 'approval_rejected')->count());
    }

    public function test_an_audit_failure_rolls_back_the_canonical_approval_transition(): void
    {
        $record = $this->pending('audit-failure');
        $this->actingAs($this->administrator('approval-tx-audit-failure'));
        $entryLevel = DB::transactionLevel();
        $this->mock(RecordAuditEvent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('Forced audit failure.'));
        });

        try {
            app(RejectRequest::class)->execute($record, 'v1', 'This must roll back.');
            $this->fail('The forced audit failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame($entryLevel, DB::transactionLevel());
        $this->assertSame(ApprovalState::Pending, $record->fresh()->approval_state);
        $this->assertSame(0, AuditLog::query()->where('event', 'approval_rejected')->count());
    }

    private function pending(string $action): ApprovalRecord
    {
        $this->actingAs($this->administrator('approval-tx-requester-'.$action));

        return app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'branches_stores',
            sourceId: (string) $this->branch->id,
            sourceVersion: 'v1',
            requestedAction: $action,
            requestPermission: 'branches_stores.edit',
            branchId: $this->branch->id,
            reasonCode: 'TX-TEST',
            reasonText: 'Transaction composition test.',
        ));
    }
}
