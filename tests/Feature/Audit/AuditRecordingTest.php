<?php

namespace Tests\Feature\Audit;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009 — Audit foundation: recording contract.
 *
 * @group tsk-009
 */
class AuditRecordingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_a_successful_platform_mutation_records_exactly_one_complete_audit_event(): void
    {
        $administrator = $this->administrator('tsk009-recording');
        $this->actingAs($administrator);

        $requestId = 'REQUEST-ID-0123456789';

        $this->withHeader('X-Request-ID', $requestId)->get('/admin/branches')->assertOk();

        $branch = app(SaveBranchAction::class)->execute([
            'code' => 'AUD-01',
            'name_ar' => 'فرع التدقيق',
            'name_en' => 'Audit Branch',
        ]);

        $this->assertSame(1, AuditLog::query()->count());

        $event = AuditLog::query()->sole();
        $this->assertSame('master_data', $event->category);
        $this->assertSame('create_branch', $event->event);
        $this->assertSame($administrator->id, $event->actor_id);
        $this->assertSame($administrator->name, $event->actor_name);
        $this->assertSame(Branch::class, $event->source_type);
        $this->assertSame((string) $branch->id, $event->source_id);
        $this->assertSame($branch->id, $event->branch_id);
        $this->assertNull($event->before_values);
        $this->assertSame('AUD-01', $event->after_values['code']);
        $this->assertContains('code', $event->changed_fields);
        $this->assertTrue(Str::isUuid($event->event_id));
        $this->assertNotEmpty($event->request_id);
        $this->assertNotNull($event->created_at);
    }

    public function test_an_update_records_both_before_and_after_values(): void
    {
        $this->actingAs($this->administrator('tsk009-before-after'));
        $action = app(SaveBranchAction::class);

        $branch = $action->execute(['code' => 'AUD-02', 'name_ar' => 'قبل', 'name_en' => 'Before']);
        $action->execute(['code' => 'AUD-02', 'name_ar' => 'بعد', 'name_en' => 'After'], $branch->id);

        $event = AuditLog::query()->where('event', 'update_branch')->sole();

        $this->assertSame('Before', $event->before_values['name_en']);
        $this->assertSame('After', $event->after_values['name_en']);
        $this->assertContains('name_en', $event->changed_fields);
    }

    public function test_the_request_id_is_carried_from_the_middleware_context(): void
    {
        $this->actingAs($this->administrator('tsk009-request-id'));

        Context::add('request_id', 'CONTEXT-REQUEST-0001');

        app(SaveBranchAction::class)->execute(['code' => 'AUD-03', 'name_ar' => 'ر', 'name_en' => 'R']);

        $this->assertSame('CONTEXT-REQUEST-0001', AuditLog::query()->sole()->request_id);
    }

    public function test_a_failed_validation_creates_no_audit_event(): void
    {
        $this->actingAs($this->administrator('tsk009-validation'));
        $branch = $this->branch('AUD-GUARD');
        $this->store($branch, 'AUD-GUARD-SELL');
        AuditLog::query()->delete();

        try {
            app(SaveBranchAction::class)->toggleStatus($branch->id);
            $this->fail('A guarded deactivation succeeded.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_authorization_denial_creates_no_audit_event(): void
    {
        $this->actingAs($this->userWith('tsk009-denied', ['branch-manager']));

        try {
            app(SaveBranchAction::class)->execute(['code' => 'HACK', 'name_ar' => 'x', 'name_en' => 'x']);
            $this->fail('An unauthorized mutation succeeded.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('branches', 0);
    }

    public function test_a_rolled_back_transaction_leaves_no_orphan_audit_row(): void
    {
        $this->actingAs($this->administrator('tsk009-rollback'));
        $branch = $this->branch('ROLL-BR');
        $otherBranch = $this->branch('ROLL-BR-2');
        $foreignStore = $this->store($otherBranch, 'ROLL-SELL-2');
        AuditLog::query()->delete();

        try {
            app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $branch->id,
                'store_id' => $foreignStore->id,
                'code' => 'ROLL-1',
                'name_ar' => 'درج',
                'name_en' => 'Drawer',
            ]);
            $this->fail('A cross-branch drawer assignment succeeded.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('cash_drawers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_audit_row_is_rolled_back_with_its_failing_transaction(): void
    {
        $this->actingAs($this->administrator('tsk009-atomic'));
        $branch = $this->branch('ATOMIC-BR');

        try {
            DB::transaction(function () use ($branch): void {
                app(SaveCashDrawerAction::class)->execute([
                    'branch_id' => $branch->id, 'code' => 'ATOMIC-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
                ]);

                throw new InvalidArgumentException('Simulated downstream failure inside the same transaction.');
            });
            $this->fail('The outer transaction did not fail.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, CashDrawer::query()->count());
        $this->assertSame(
            0,
            AuditLog::query()->where('event', 'create_cash_drawer')->count(),
            'An audit row must never survive the rollback of the mutation it records.',
        );
    }

    public function test_a_duplicate_submission_does_not_create_a_second_audit_event(): void
    {
        $this->actingAs($this->administrator('tsk009-duplicate'));
        $action = app(SaveBranchAction::class);

        $action->execute(['code' => 'DUP-AUD', 'name_ar' => 'مكرر', 'name_en' => 'Duplicate']);

        try {
            $action->execute(['code' => 'DUP-AUD', 'name_ar' => 'مكرر', 'name_en' => 'Duplicate']);
            $this->fail('A duplicate branch code was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, AuditLog::query()->where('event', 'create_branch')->count());
    }

    public function test_the_recorder_captures_reason_scope_and_metadata(): void
    {
        $this->actingAs($this->administrator('tsk009-metadata'));
        $branch = $this->branch('META-BR');
        $store = $this->store($branch, 'META-SELL');
        AuditLog::query()->delete();

        app(RecordAuditEvent::class)->execute(
            category: 'workflow',
            event: 'manual_probe',
            source: $branch,
            before: ['status' => 'active'],
            after: ['status' => 'inactive'],
            branchId: $branch->id,
            storeId: $store->id,
            reasonCode: 'OPS-01',
            reasonText: 'Recorded during automated regression.',
            metadata: ['probe' => true],
        );

        $event = AuditLog::query()->sole();
        $this->assertSame('workflow', $event->category);
        $this->assertSame('OPS-01', $event->reason_code);
        $this->assertSame('Recorded during automated regression.', $event->reason_text);
        $this->assertSame($branch->id, $event->branch_id);
        $this->assertSame($store->id, $event->store_id);
        $this->assertTrue($event->metadata['probe']);
    }

    public function test_an_unauthenticated_system_mutation_is_recorded_without_an_actor(): void
    {
        app(RecordAuditEvent::class)->execute(
            category: 'platform',
            event: 'system_probe',
        );

        $event = AuditLog::query()->sole();
        $this->assertNull($event->actor_id);
        $this->assertNull($event->actor_name);
        $this->assertNotEmpty($event->request_id);
    }
}
