<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class BranchStoreDrawerMutationScopeTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_a_scoped_branch_manager_cannot_edit_or_toggle_a_foreign_branch(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedMasterActorAndBranches();
        $this->actingAs($actor);

        try {
            app(SaveBranchAction::class)->execute($this->branchData('FORGED-BRANCH'), $branchB->id);
            self::fail('A scoped actor edited a branch outside their visible scope.');
        } catch (ModelNotFoundException) {
            self::addToAssertionCount(1);
        }

        try {
            app(SaveBranchAction::class)->toggleStatus($branchB->id);
            self::fail('A scoped actor changed a branch outside their visible scope.');
        } catch (ModelNotFoundException) {
            self::addToAssertionCount(1);
        }

        self::assertSame('SCOPE-B', $branchB->fresh()->code);
        self::assertSame('active', $branchB->fresh()->status);
        self::assertSame($branchA->id, Branch::visibleTo($actor)->sole()->id);
    }

    public function test_a_scoped_branch_manager_gets_not_found_for_a_forged_branch_editor_id(): void
    {
        [$actor, , $branchB] = $this->scopedMasterActorAndBranches();
        $this->actingAs($actor);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test('platform::admin.branches')->call('openEditBranchModal', $branchB->id);
    }

    public function test_a_scoped_branch_manager_cannot_toggle_a_foreign_store_through_an_action_or_livewire(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedMasterActorAndBranches();
        $foreignStore = $this->store($branchB, 'SCOPE-STORE-B', 'warehouse');
        $this->actingAs($actor);

        try {
            app(SaveStoreAction::class)->toggleStatus($foreignStore->id);
            self::fail('A scoped actor changed a store outside their visible scope.');
        } catch (ModelNotFoundException) {
            self::addToAssertionCount(1);
        }

        Livewire::test('platform::admin.stores')->call('toggleStoreStatus', $foreignStore->id);

        self::assertSame('active', $foreignStore->fresh()->status);
        self::assertSame($branchA->id, Store::visibleTo($actor)->sole()->branch_id);
    }

    public function test_a_scoped_branch_manager_cannot_edit_or_toggle_a_foreign_cash_drawer_through_an_action_or_livewire(): void
    {
        [$actor, $branchA, $branchB] = $this->scopedMasterActorAndBranches();
        $localStore = $this->sellingStore($branchA, 'SCOPE-DRAWER-A');
        $foreignStore = $this->sellingStore($branchB, 'SCOPE-DRAWER-B');
        $foreignDrawer = CashDrawer::query()->create([
            'company_id' => $branchB->company_id,
            'branch_id' => $branchB->id,
            'store_id' => $foreignStore->id,
            'code' => 'SCOPE-DRW-B',
            'name_ar' => 'درج نطاق ب',
            'name_en' => 'Scope drawer B',
            'status' => 'active',
        ]);
        $this->actingAs($actor);

        try {
            app(SaveCashDrawerAction::class)->execute([
                ...$this->drawerData($branchA, $localStore),
                'code' => 'FORGED-DRAWER',
            ], $foreignDrawer->id);
            self::fail('A scoped actor edited a cash drawer outside their visible scope.');
        } catch (ModelNotFoundException) {
            self::addToAssertionCount(1);
        }

        try {
            app(SaveCashDrawerAction::class)->toggleStatus($foreignDrawer->id, 'maintenance');
            self::fail('A scoped actor changed a cash drawer outside their visible scope.');
        } catch (ModelNotFoundException) {
            self::addToAssertionCount(1);
        }

        Livewire::test('platform::admin.drawers')
            ->set('editingDrawerId', $foreignDrawer->id)
            ->set('drawerForm', $this->drawerData($branchA, $localStore))
            ->call('saveDrawer')
            ->call('toggleDrawerStatus', $foreignDrawer->id, 'maintenance');

        self::assertSame($branchB->id, $foreignDrawer->fresh()->branch_id);
        self::assertSame($foreignStore->id, $foreignDrawer->fresh()->store_id);
        self::assertSame('active', $foreignDrawer->fresh()->status);
    }

    /** @return array{0: User, 1: Branch, 2: Branch} */
    private function scopedMasterActorAndBranches(): array
    {
        $branchA = $this->branch('SCOPE-A');
        $branchB = $this->branch('SCOPE-B');
        $this->store($branchA, 'SCOPE-STORE-A', 'warehouse');
        $actor = $this->userWith('scoped-branch-master', ['branch-manager'], branchIds: [$branchA->id]);
        $role = $actor->roles()->where('code', 'branch-manager')->sole();
        $role->permissions()->syncWithoutDetaching(
            Permission::query()
                ->whereIn('code', [
                    'branches_stores.edit',
                    'drawers_payments_tax_numbering_printers.view',
                    'drawers_payments_tax_numbering_printers.edit',
                ])
                ->pluck('id')
                ->all(),
        );

        return [$actor->fresh(), $branchA, $branchB];
    }

    /** @return array<string, mixed> */
    private function branchData(string $code): array
    {
        return [
            'code' => $code,
            'name_ar' => 'فرع مزور',
            'name_en' => 'Forged branch',
            'timezone' => 'UTC',
            'status' => 'active',
        ];
    }

    /** @return array<string, mixed> */
    private function drawerData(Branch $branch, Store $store): array
    {
        return [
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'code' => 'SCOPE-DRW-A',
            'name_ar' => 'درج نطاق أ',
            'name_en' => 'Scope drawer A',
            'status' => 'active',
        ];
    }

    private function sellingStore(Branch $branch, string $code): Store
    {
        $store = $this->store($branch, $code);
        BranchSellingStore::query()->create([
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'effective_from' => now()->subMinute(),
            'status' => 'active',
        ]);

        return $store;
    }
}
