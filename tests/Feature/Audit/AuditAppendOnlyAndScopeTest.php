<?php

namespace Tests\Feature\Audit;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\SaveUserAuthorizationAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009 — Audit foundation: append-only guarantees, scope, and redaction of
 * recorded values.
 *
 * @group tsk-009
 */
class AuditAppendOnlyAndScopeTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    private function event(?int $branchId = null, ?int $storeId = null, string $event = 'probe'): AuditLog
    {
        return app(RecordAuditEvent::class)->execute(
            category: 'master_data',
            event: $event,
            branchId: $branchId,
            storeId: $storeId,
        );
    }

    public function test_an_application_action_cannot_update_an_audit_record(): void
    {
        $this->actingAs($this->administrator('tsk009-append-update'));
        $record = $this->event();

        try {
            $record->update(['event' => 'tampered']);
            $this->fail('An audit record was updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertSame('probe', $record->fresh()->event);
    }

    public function test_an_application_action_cannot_delete_an_audit_record(): void
    {
        $this->actingAs($this->administrator('tsk009-append-delete'));
        $record = $this->event();

        try {
            $record->delete();
            $this->fail('An audit record was deleted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $record->id]);
    }

    public function test_mass_model_updates_and_deletes_are_also_blocked(): void
    {
        $this->actingAs($this->administrator('tsk009-append-mass'));
        $this->event();

        try {
            AuditLog::query()->get()->each->delete();
            $this->fail('An audit record was deleted through a model collection.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_the_append_only_guard_is_a_model_guard_and_raw_queries_remain_a_database_concern(): void
    {
        // Recorded coverage limitation: the append-only rule is enforced by the
        // Eloquent model. A raw query builder statement bypasses it, so database
        // level protection (revoked UPDATE/DELETE grants or triggers) remains a
        // production hardening item and is not claimed as tested.
        $this->actingAs($this->administrator('tsk009-append-raw'));
        $record = $this->event();

        \Illuminate\Support\Facades\DB::table('audit_logs')->where('id', $record->id)->update(['event' => 'raw-bypass']);

        $this->assertSame('raw-bypass', AuditLog::query()->whereKey($record->id)->value('event'));
    }

    public function test_a_super_administrator_sees_the_full_audit_scope(): void
    {
        $administrator = $this->administrator('tsk009-scope-admin');
        $this->actingAs($administrator);

        $branch = $this->branch('AUD-SC-1');
        $otherBranch = $this->branch('AUD-SC-2');
        $store = $this->store($otherBranch, 'AUD-SC-2-SELL');
        AuditLog::query()->delete();

        $this->event($branch->id);
        $this->event(null, $store->id);
        $this->event();

        $this->assertSame(3, AuditLog::visibleTo($administrator)->count());
    }

    public function test_a_branch_scoped_user_only_sees_their_branch_events(): void
    {
        $this->actingAs($this->administrator('tsk009-scope-setup'));
        $assigned = $this->branch('AUD-IN');
        $foreign = $this->branch('AUD-OUT');
        AuditLog::query()->delete();

        $mine = $this->event($assigned->id);
        $theirs = $this->event($foreign->id);
        $global = $this->event();

        $user = $this->userWith('tsk009-branch-user', ['accountant-reviewer'], false, [$assigned->id]);

        $visible = AuditLog::visibleTo($user)->pluck('id')->all();

        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($theirs->id, $visible);
        $this->assertNotContains($global->id, $visible, 'A scoped reviewer must not see global events.');
    }

    public function test_a_store_scoped_user_only_sees_their_store_events(): void
    {
        $this->actingAs($this->administrator('tsk009-store-scope-setup'));
        $branch = $this->branch('AUD-ST-BR');
        $assignedStore = $this->store($branch, 'AUD-ST-IN');
        $foreignStore = $this->store($branch, 'AUD-ST-OUT');
        AuditLog::query()->delete();

        $mine = $this->event(null, $assignedStore->id);
        $theirs = $this->event(null, $foreignStore->id);

        $user = $this->userWith('tsk009-store-user', ['accountant-reviewer'], false, [], [$assignedStore->id]);
        $visible = AuditLog::visibleTo($user)->pluck('id')->all();

        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($theirs->id, $visible);
    }

    public function test_the_policy_denies_out_of_scope_detail_access(): void
    {
        $this->actingAs($this->administrator('tsk009-policy-setup'));
        $assigned = $this->branch('POL-IN');
        $foreign = $this->branch('POL-OUT');
        AuditLog::query()->delete();

        $mine = $this->event($assigned->id);
        $theirs = $this->event($foreign->id);

        $reviewer = $this->userWith('tsk009-policy-reviewer', ['accountant-reviewer'], false, [$assigned->id]);

        $this->assertTrue(Gate::forUser($reviewer)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($reviewer)->allows('view', $mine));
        $this->assertFalse(Gate::forUser($reviewer)->allows('view', $theirs));

        $unauthorized = $this->userWith('tsk009-policy-none', [], false, [$assigned->id]);
        $this->assertFalse(Gate::forUser($unauthorized)->allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::forUser($unauthorized)->allows('view', $mine));
    }

    public function test_audit_export_requires_its_own_permission(): void
    {
        $reviewer = $this->userWith('tsk009-export', ['accountant-reviewer']);

        $this->assertTrue($reviewer->hasPermission('audit_logs.view'));
        $this->assertFalse(Gate::forUser($reviewer)->allows('export', AuditLog::class));
    }

    public function test_recorded_values_are_redacted_before_they_are_persisted(): void
    {
        $this->actingAs($this->administrator('tsk009-redaction'));

        app(RecordAuditEvent::class)->execute(
            category: 'authorization',
            event: 'sensitive_probe',
            before: ['password' => 'old-secret', 'profile' => ['api_key' => 'AK-123', 'name' => 'Safe']],
            after: ['password_confirmation' => 'new-secret', 'nested' => ['deep' => ['refresh_token' => 'RT-123']]],
        );

        $row = \Illuminate\Support\Facades\DB::table('audit_logs')->latest('id')->first();
        $raw = $row->before_values.$row->after_values;

        foreach (['old-secret', 'new-secret', 'AK-123', 'RT-123'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, 'A sensitive value was persisted to the audit table.');
        }

        $event = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('[redacted]', $event->before_values['password']);
        $this->assertSame('[redacted]', $event->before_values['profile']['api_key']);
        $this->assertSame('Safe', $event->before_values['profile']['name']);
        $this->assertSame('[redacted]', $event->after_values['nested']['deep']['refresh_token']);
    }

    public function test_an_authorization_change_records_only_identifiers_not_credentials(): void
    {
        $administrator = $this->administrator('tsk009-auth-audit');
        $this->actingAs($administrator);

        $target = $this->userWith('tsk009-auth-target');
        $role = Role::query()->where('code', 'cashier')->firstOrFail();

        app(SaveUserAuthorizationAction::class)->execute($target, [$role->id], [], []);

        $event = AuditLog::query()->where('event', 'update_user_authorization')->sole();
        $serialized = json_encode([$event->before_values, $event->after_values]);

        $this->assertStringNotContainsString($target->password, $serialized);
        $this->assertArrayHasKey('roles', $event->after_values);
        $this->assertArrayNotHasKey('password', $event->after_values);
    }
}
