<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-007 — Cash drawer masters and assignments.
 *
 * @group tsk-007
 */
class CashDrawerMasterTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_drawer_screen_is_permission_guarded(): void
    {
        $this->get('/admin/cash-drawers')->assertRedirect('/login');

        $this->actingAs($this->userWith('tsk007-none'));
        $this->get('/admin/cash-drawers')->assertForbidden();

        $this->actingAs($this->userWith('tsk007-manager', ['branch-manager']));
        $this->get('/admin/cash-drawers')->assertForbidden();

        $this->actingAs($this->administrator('tsk007-admin'));
        $this->get('/admin/cash-drawers')->assertOk();
    }

    public function test_a_drawer_can_be_created_edited_and_deactivated_with_audit_events(): void
    {
        $this->actingAs($this->administrator('tsk007-lifecycle'));
        $branch = $this->branch('DRW-BR');
        $store = $this->store($branch, 'DRW-SELL');
        $action = app(SaveCashDrawerAction::class);

        $drawer = $action->execute([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'code' => 'drw-01',
            'name_ar' => 'درج نقدية',
            'name_en' => 'Cash Drawer 1',
        ]);

        $this->assertSame('DRW-01', $drawer->code);
        $this->assertSame($store->id, $drawer->store_id);
        $this->assertSame(1, AuditLog::query()->where('event', 'create_cash_drawer')->count());

        $action->execute([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'code' => 'DRW-01',
            'name_ar' => 'درج نقدية محدث',
            'name_en' => 'Cash Drawer One',
        ], $drawer->id);

        $this->assertSame('Cash Drawer One', $drawer->fresh()->name_en);
        $this->assertSame(1, AuditLog::query()->where('event', 'update_cash_drawer')->count());

        $action->toggleStatus($drawer->id, 'inactive');
        $this->assertSame('inactive', $drawer->fresh()->status);

        $statusEvent = AuditLog::query()->where('event', 'toggle_cash_drawer_status')->sole();
        $this->assertSame(['status' => 'active'], $statusEvent->before_values);
        $this->assertSame(['status' => 'inactive'], $statusEvent->after_values);
        $this->assertSame($branch->id, $statusEvent->branch_id);
        $this->assertSame($store->id, $statusEvent->store_id);
    }

    public function test_a_drawer_code_is_unique_within_its_branch(): void
    {
        $this->actingAs($this->administrator('tsk007-unique'));
        $branch = $this->branch('UNQ-BR');
        $other = $this->branch('UNQ-BR-2');
        $action = app(SaveCashDrawerAction::class);

        $payload = fn (int $branchId) => [
            'branch_id' => $branchId,
            'code' => 'POS-1',
            'name_ar' => 'درج',
            'name_en' => 'Drawer',
        ];

        $action->execute($payload($branch->id));

        // The same code is allowed in a different branch by design.
        $action->execute($payload($other->id));
        $this->assertSame(2, CashDrawer::query()->where('code', 'POS-1')->count());

        $this->expectException(QueryException::class);
        $action->execute($payload($branch->id));
    }

    public function test_the_drawer_form_rejects_a_duplicate_code_in_the_same_branch(): void
    {
        $this->actingAs($this->administrator('tsk007-form-unique'));
        $branch = $this->branch('FRM-BR');
        app(SaveCashDrawerAction::class)->execute([
            'branch_id' => $branch->id, 'code' => 'POS-9', 'name_ar' => 'درج', 'name_en' => 'Drawer',
        ]);

        Livewire::test('platform::admin.drawers')
            ->call('openCreateDrawerModal')
            ->set('drawerForm.branch_id', $branch->id)
            ->set('drawerForm.code', 'POS-9')
            ->set('drawerForm.name_ar', 'درج')
            ->set('drawerForm.name_en', 'Drawer')
            ->call('saveDrawer')
            ->assertHasErrors(['drawerForm.code' => 'unique']);

        $this->assertSame(1, CashDrawer::query()->where('code', 'POS-9')->count());
    }

    public function test_a_drawer_cannot_be_assigned_to_a_store_from_another_branch(): void
    {
        $this->actingAs($this->administrator('tsk007-cross-branch'));

        $branch = $this->branch('X-BR-1');
        $otherBranch = $this->branch('X-BR-2');
        $foreignStore = $this->store($otherBranch, 'X-SELL-2');
        $auditBefore = AuditLog::query()->count();

        try {
            app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $branch->id,
                'store_id' => $foreignStore->id,
                'code' => 'X-1',
                'name_ar' => 'درج',
                'name_en' => 'Drawer',
            ]);
            $this->fail('A cross-branch store assignment was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('does not belong to the chosen branch', $exception->getMessage());
        }

        $this->assertDatabaseCount('cash_drawers', 0);
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_a_drawer_cannot_be_created_on_an_inactive_branch(): void
    {
        $this->actingAs($this->administrator('tsk007-inactive-branch'));
        $branch = $this->branch('OFF-BR', 'inactive');

        try {
            app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $branch->id, 'code' => 'OFF-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
            ]);
            $this->fail('A drawer was assigned to an inactive branch.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('inactive branch', $exception->getMessage());
        }

        $this->assertDatabaseCount('cash_drawers', 0);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->administrator('tsk007-status'));
        $branch = $this->branch('ST-BR');
        $drawer = app(SaveCashDrawerAction::class)->execute([
            'branch_id' => $branch->id, 'code' => 'ST-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
        ]);

        try {
            app(SaveCashDrawerAction::class)->toggleStatus($drawer->id, 'retired');
            $this->fail('An unsupported drawer status was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('active', $drawer->fresh()->status);

        app(SaveCashDrawerAction::class)->toggleStatus($drawer->id, 'maintenance');
        $this->assertSame('maintenance', $drawer->fresh()->status);
    }

    public function test_drawer_writes_are_denied_without_the_drawer_permission(): void
    {
        $this->actingAs($this->administrator('tsk007-deny-setup'));
        $branch = $this->branch('DNY-BR');
        $drawer = app(SaveCashDrawerAction::class)->execute([
            'branch_id' => $branch->id, 'code' => 'DNY-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
        ]);
        $auditBefore = AuditLog::query()->count();

        $this->actingAs($this->userWith('tsk007-deny', ['branch-manager'], false, [$branch->id]));

        foreach ([
            fn () => app(SaveCashDrawerAction::class)->execute(['branch_id' => $branch->id, 'code' => 'HACK', 'name_ar' => 'x', 'name_en' => 'x']),
            fn () => app(SaveCashDrawerAction::class)->toggleStatus($drawer->id, 'inactive'),
            fn () => app(SaveCashDrawerAction::class)->delete($drawer->id),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('An unauthorized drawer mutation was accepted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('active', $drawer->fresh()->status);
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_the_drawer_list_is_scoped_to_visible_branches(): void
    {
        $this->actingAs($this->administrator('tsk007-scope'));

        $visible = $this->branch('VIS-BR');
        $hidden = $this->branch('HID-BR');
        $action = app(SaveCashDrawerAction::class);
        $action->execute(['branch_id' => $visible->id, 'code' => 'VIS-1', 'name_ar' => 'درج', 'name_en' => 'Visible Drawer']);
        $action->execute(['branch_id' => $hidden->id, 'code' => 'HID-1', 'name_ar' => 'درج', 'name_en' => 'Hidden Drawer']);

        Livewire::test('platform::admin.drawers')
            ->assertSee('VIS-1')
            ->assertSee('HID-1');

        $this->assertSame(2, CashDrawer::query()->count());
    }

    public function test_an_active_shift_blocks_drawer_retirement_and_reassignment(): void
    {
        $this->actingAs($this->administrator('tsk007-shift'));
        $branch = $this->branch('SHF-BR');
        $store = $this->store($branch, 'SHF-SELL');
        $destinationBranch = $this->branch('SHF-DST');
        $destinationStore = $this->store($destinationBranch, 'SHF-DST-SELL');
        $drawer = app(SaveCashDrawerAction::class)->execute([
            'store_id' => $store->id,
            'branch_id' => $branch->id, 'code' => 'SHF-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
        ]);
        $cashier = $this->userWith('tsk007-active-shift-cashier', ['cashier'], false, [$branch->id], [$store->id]);
        PosShift::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0.00',
            'opened_at' => now(),
        ]);
        $auditBefore = AuditLog::query()->count();

        $retirementBlocked = false;
        try {
            app(SaveCashDrawerAction::class)->toggleStatus($drawer->id, 'inactive');
        } catch (InvalidArgumentException) {
            $retirementBlocked = true;
        }

        $reassignmentBlocked = false;
        try {
            app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $destinationBranch->id,
                'store_id' => $destinationStore->id,
                'code' => 'SHF-1',
                'name_ar' => 'Ø¯Ø±Ø¬',
                'name_en' => 'Drawer',
            ], $drawer->id);
        } catch (InvalidArgumentException) {
            $reassignmentBlocked = true;
        }

        $editDeactivationBlocked = false;
        try {
            app(SaveCashDrawerAction::class)->execute([
                'branch_id' => $branch->id,
                'store_id' => $store->id,
                'code' => 'SHF-1',
                'name_ar' => 'درج',
                'name_en' => 'Drawer',
                'status' => 'inactive',
            ], $drawer->id);
        } catch (InvalidArgumentException) {
            $editDeactivationBlocked = true;
        }

        $this->assertTrue($retirementBlocked, 'An active drawer was deactivated while its POS shift was active.');
        $this->assertTrue($reassignmentBlocked, 'An active drawer was reassigned while its POS shift was active.');
        $this->assertTrue($editDeactivationBlocked, 'The drawer edit form deactivated a drawer with an active POS shift.');
        $this->assertSame('active', $drawer->fresh()->status);
        $this->assertSame($branch->id, $drawer->fresh()->branch_id);
        $this->assertSame($store->id, $drawer->fresh()->store_id);

        $blockedEvents = AuditLog::query()
            ->where('event', 'cash_drawer_mutation_blocked')
            ->where('source_id', (string) $drawer->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $blockedEvents);
        $this->assertSame($auditBefore + 3, AuditLog::query()->count());
        $this->assertSame('active_shift', $blockedEvents[0]->reason_code);
        $this->assertSame('deactivate', $blockedEvents[0]->metadata['operation']);
        $this->assertSame('reassign', $blockedEvents[1]->metadata['operation']);
        $this->assertSame('deactivate', $blockedEvents[2]->metadata['operation']);
        $this->assertSame($branch->id, $blockedEvents[0]->branch_id);
        $this->assertSame($store->id, $blockedEvents[0]->store_id);
        $this->assertSame('active', $blockedEvents[0]->before_values['status']);
        $this->assertSame('active', $blockedEvents[0]->after_values['status']);
    }

    public function test_a_drawer_delete_removes_the_record_and_records_the_prior_state(): void
    {
        $this->actingAs($this->administrator('tsk007-delete'));
        $branch = $this->branch('DEL-BR');
        $drawer = app(SaveCashDrawerAction::class)->execute([
            'branch_id' => $branch->id, 'code' => 'DEL-1', 'name_ar' => 'درج', 'name_en' => 'Drawer',
        ]);

        app(SaveCashDrawerAction::class)->delete($drawer->id);

        $this->assertDatabaseMissing('cash_drawers', ['id' => $drawer->id]);

        $event = AuditLog::query()->where('event', 'delete_cash_drawer')->sole();
        $this->assertSame('DEL-1', $event->before_values['code']);
        $this->assertSame(['deleted' => true], $event->after_values);
        $this->assertSame($drawer->id, $event->metadata['deleted_source_id']);
    }
}
