<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-006 — Branches, stores, and selling-store mapping.
 *
 * @group tsk-006
 */
class BranchStoreMappingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_a_branch_can_be_created_edited_and_deactivated_with_one_audit_event_each(): void
    {
        $this->actingAs($this->administrator('tsk006-admin'));
        $action = app(SaveBranchAction::class);

        $branch = $action->execute([
            'code' => 'cai-01',
            'name_ar' => 'فرع القاهرة',
            'name_en' => 'Cairo Branch',
            'timezone' => 'Africa/Cairo',
        ]);

        $this->assertSame('CAI-01', $branch->code, 'Branch codes are normalized to upper case.');
        $this->assertSame(1, AuditLog::query()->where('event', 'create_branch')->count());

        $action->execute(['code' => 'CAI-01', 'name_ar' => 'فرع القاهرة الرئيسي', 'name_en' => 'Cairo Main'], $branch->id);
        $this->assertSame('Cairo Main', $branch->fresh()->name_en);
        $this->assertSame(1, AuditLog::query()->where('event', 'update_branch')->count());

        $action->toggleStatus($branch->id);
        $this->assertSame('inactive', $branch->fresh()->status);

        $statusEvent = AuditLog::query()->where('event', 'toggle_branch_status')->sole();
        $this->assertSame(['status' => 'active'], $statusEvent->before_values);
        $this->assertSame(['status' => 'inactive'], $statusEvent->after_values);
        $this->assertSame($branch->id, $statusEvent->branch_id);
    }

    public function test_branch_and_store_codes_are_unique(): void
    {
        $this->actingAs($this->administrator('tsk006-unique'));

        $this->branch('UNIQUE-01');

        try {
            $this->branch('UNIQUE-01');
            $this->fail('A duplicate branch code was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $branch = Branch::query()->where('code', 'UNIQUE-01')->firstOrFail();
        $this->store($branch, 'STORE-01');

        try {
            $this->store($branch, 'STORE-01');
            $this->fail('A duplicate store code was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_the_branch_form_rejects_a_duplicate_code_before_it_reaches_the_database(): void
    {
        $this->actingAs($this->administrator('tsk006-form-unique'));
        $this->branch('FORM-01');

        Livewire::test('platform::admin.branches')
            ->call('openCreateBranchModal')
            ->set('branchForm.code', 'FORM-01')
            ->set('branchForm.name_ar', 'فرع')
            ->set('branchForm.name_en', 'Branch')
            ->call('saveBranch')
            ->assertHasErrors(['branchForm.code' => 'unique']);

        $this->assertSame(1, Branch::query()->where('code', 'FORM-01')->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_branch_with_active_stores_or_an_active_mapping_cannot_be_deactivated(): void
    {
        $this->actingAs($this->administrator('tsk006-guard'));

        $branch = $this->branch('GUARD-01');
        $this->store($branch, 'GUARD-SELL');

        $auditCountBefore = AuditLog::query()->count();

        try {
            app(SaveBranchAction::class)->toggleStatus($branch->id);
            $this->fail('A branch with an active store was deactivated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('active stores', $exception->getMessage());
        }

        $this->assertSame('active', $branch->fresh()->status);
        $this->assertSame($auditCountBefore, AuditLog::query()->count(), 'A rejected mutation must not write an audit event.');
    }

    public function test_a_store_actively_mapped_to_a_branch_cannot_be_deactivated(): void
    {
        $this->actingAs($this->administrator('tsk006-store-guard'));

        $branch = $this->branch('MAP-GUARD');
        $store = $this->store($branch, 'MAP-GUARD-SELL');
        app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $store->id);

        try {
            app(SaveStoreAction::class)->toggleStatus($store->id);
            $this->fail('An actively mapped selling store was deactivated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('actively mapped', $exception->getMessage());
        }

        $this->assertSame('active', $store->fresh()->status);
    }

    public function test_a_branch_or_store_with_history_cannot_be_deleted(): void
    {
        $this->actingAs($this->administrator('tsk006-delete-guard'));

        $branch = $this->branch('DEL-01');
        $store = $this->store($branch, 'DEL-SELL');
        app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $store->id);

        try {
            app(SaveBranchAction::class)->delete($branch->id);
            $this->fail('A branch with dependencies was deleted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            app(SaveStoreAction::class)->delete($store->id);
            $this->fail('A store with mapping history was deleted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }

    public function test_only_one_selling_store_mapping_is_effective_per_branch_and_history_is_preserved(): void
    {
        $this->actingAs($this->administrator('tsk006-mapping'));

        $branch = $this->branch('MAP-01');
        $first = $this->store($branch, 'MAP-SELL-1');
        $second = $this->store($branch, 'MAP-SELL-2');

        $action = app(SaveBranchSellingStoreMappingAction::class);
        $original = $action->execute($branch->id, $first->id);
        $replacement = $action->execute($branch->id, $second->id);

        $this->assertSame(
            1,
            BranchSellingStore::query()->where('branch_id', $branch->id)->where('status', 'active')->count(),
            'A branch may have only one effective selling-store mapping.',
        );
        $this->assertSame($second->id, $replacement->store_id);
        $this->assertSame(2, BranchSellingStore::query()->where('branch_id', $branch->id)->count(), 'Mapping history must be preserved.');

        $closed = $original->fresh();
        $this->assertSame('inactive', $closed->status);
        $this->assertNotNull($closed->effective_to, 'A superseded mapping must be closed with an effective_to date.');
        $this->assertNull($replacement->effective_to);
        $this->assertTrue(
            $closed->effective_to->lessThanOrEqualTo($replacement->effective_from),
            'Mapping periods must not overlap.',
        );
    }

    public function test_replaying_the_same_mapping_does_not_create_a_duplicate_record(): void
    {
        $this->actingAs($this->administrator('tsk006-replay'));

        $branch = $this->branch('REPLAY-01');
        $store = $this->store($branch, 'REPLAY-SELL');

        $action = app(SaveBranchSellingStoreMappingAction::class);
        $first = $action->execute($branch->id, $store->id);
        $second = $action->execute($branch->id, $store->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BranchSellingStore::query()->where('branch_id', $branch->id)->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'map_branch_selling_store')->count());
    }

    public function test_a_mapping_requires_an_active_selling_type_store_on_an_active_branch(): void
    {
        $this->actingAs($this->administrator('tsk006-mapping-rules'));

        $branch = $this->branch('RULES-01');
        $warehouse = $this->store($branch, 'RULES-WH', 'warehouse');
        $inactiveStore = $this->store($branch, 'RULES-INACTIVE', 'selling', 'inactive');
        $action = app(SaveBranchSellingStoreMappingAction::class);

        try {
            $action->execute($branch->id, $warehouse->id);
            $this->fail('A warehouse store was mapped as a selling store.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Selling Store', $exception->getMessage());
        }

        try {
            $action->execute($branch->id, $inactiveStore->id);
            $this->fail('An inactive selling store was mapped.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('must be active', $exception->getMessage());
        }

        $inactiveBranch = $this->branch('RULES-OFF', 'inactive');
        $store = $this->store($inactiveBranch, 'RULES-OFF-SELL');

        try {
            $action->execute($inactiveBranch->id, $store->id);
            $this->fail('An inactive branch received a selling-store mapping.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('must be active', $exception->getMessage());
        }

        $this->assertDatabaseCount('branch_selling_stores', 0);
        $this->assertSame(0, AuditLog::query()->where('event', 'map_branch_selling_store')->count());
    }

    public function test_branch_and_store_lists_are_isolated_by_assigned_scope(): void
    {
        $this->actingAs($this->administrator('tsk006-scope-setup'));

        $assigned = $this->branch('ALPHA-BR');
        $foreign = $this->branch('BETA-BR');
        $assignedStore = $this->store($assigned, 'ALPHA-SELL');
        $foreignStore = $this->store($foreign, 'BETA-SELL');

        $manager = $this->userWith('tsk006-manager', ['branch-manager'], false, [$assigned->id]);

        $this->assertTrue(Branch::visibleTo($manager)->whereKey($assigned)->exists());
        $this->assertFalse(Branch::visibleTo($manager)->whereKey($foreign)->exists());
        $this->assertTrue(Store::visibleTo($manager)->whereKey($assignedStore)->exists());
        $this->assertFalse(Store::visibleTo($manager)->whereKey($foreignStore)->exists());

        $this->actingAs($manager);
        Livewire::test('platform::admin.branches')
            ->assertSee('ALPHA-BR')
            ->assertDontSee('BETA-BR');
    }

    /**
     * DEFECT-001 (reported, not fixed).
     *
     * `resources/views/platform/admin/branches.blade.php` builds the mapping
     * modal's selling-store options with an unscoped
     * `Store::where('type', 'selling')->where('status', 'active')` query, so a
     * branch-scoped user receives every branch's selling-store codes and names
     * in the rendered markup. This test states the required behavior and is
     * expected to fail until the query is scoped with `visibleTo()`.
     */
    public function test_defect_001_out_of_scope_selling_stores_must_not_be_rendered_to_a_scoped_user(): void
    {
        $this->actingAs($this->administrator('tsk006-leak-setup'));

        $assigned = $this->branch('OWNED-BR');
        $foreign = $this->branch('OTHER-BR');
        $this->store($assigned, 'OWNED-SELL');
        $this->store($foreign, 'OTHER-SELL');

        $manager = $this->userWith('tsk006-leak', ['branch-manager'], false, [$assigned->id]);
        $this->actingAs($manager);

        Livewire::test('platform::admin.branches')
            ->assertDontSee('OTHER-SELL');
    }

    public function test_a_scoped_manager_cannot_mutate_branch_or_store_masters(): void
    {
        $this->actingAs($this->administrator('tsk006-mutation-setup'));
        $branch = $this->branch('DENY-01');
        $store = $this->store($branch, 'DENY-SELL');
        $auditCountBefore = AuditLog::query()->count();

        $manager = $this->userWith('tsk006-deny', ['branch-manager'], false, [$branch->id]);
        $this->actingAs($manager);

        foreach ([
            fn () => app(SaveBranchAction::class)->execute(['code' => 'X', 'name_ar' => 'x', 'name_en' => 'x']),
            fn () => app(SaveBranchAction::class)->toggleStatus($branch->id),
            fn () => app(SaveStoreAction::class)->execute(['code' => 'Y', 'name_ar' => 'y', 'name_en' => 'y']),
            fn () => app(SaveBranchSellingStoreMappingAction::class)->execute($branch->id, $store->id),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A protected master-data write accepted an unauthorized caller.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame($auditCountBefore, AuditLog::query()->count(), 'A denied action must not write an audit event.');
    }

    public function test_no_manager_override_capability_is_implemented(): void
    {
        // Recorded coverage fact for TSK-006: `branches_stores.override` exists
        // in the permission catalog but is granted to no role and referenced by
        // no action, so override behavior cannot be tested.
        $this->assertDatabaseHas('permissions', ['code' => 'branches_stores.override']);

        $granted = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('code', 'branches_stores.override'))
            ->exists();

        $this->assertFalse($granted);
    }

    public function test_the_branch_screen_records_mapping_history_for_review(): void
    {
        $this->actingAs($this->administrator('tsk006-history'));

        $branch = $this->branch('HIST-01');
        $first = $this->store($branch, 'HIST-SELL-1');
        $second = $this->store($branch, 'HIST-SELL-2');

        $action = app(SaveBranchSellingStoreMappingAction::class);
        $action->execute($branch->id, $first->id);
        $action->execute($branch->id, $second->id);

        $component = Livewire::test('platform::admin.branches')->call('openHistoryModal', $branch->id);

        $records = $component->get('historyRecords');

        $this->assertCount(2, $records);
        $this->assertSame('HIST-SELL-2', $records[0]['store_code']);
        $this->assertSame('HIST-SELL-1', $records[1]['store_code']);
        $this->assertNotNull($records[1]['effective_to']);
    }
}
