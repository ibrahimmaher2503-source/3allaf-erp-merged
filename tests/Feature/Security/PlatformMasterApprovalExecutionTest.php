<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class PlatformMasterApprovalExecutionTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_scoped_independent_approver_without_logical_delete_executes_approved_master_deletes(): void
    {
        foreach (['branch_delete', 'store_delete', 'cash_drawer_delete'] as $resource) {
            [$requester, $approver, $target, $branchId, $storeId] = $this->approvalScenario($resource);

            self::assertFalse($approver->hasPermission($this->logicalDeletePermission($resource)));
            $this->actingAs($requester);
            $approval = app(PlatformSettingsApprovalAction::class)->request(
                resource: $resource,
                id: $target->id,
                proposed: ['status' => 'inactive'],
                before: $target->getAttributes(),
                branchId: $branchId,
                storeId: $storeId,
            );

            $this->actingAs($approver);
            app(DecideApprovalSource::class)->approve($approval);

            self::assertSame('approved', $approval->fresh()->approval_state->value);
            self::assertSame('inactive', $target->fresh()->status);
        }
    }

    public function test_platform_master_delete_request_rejects_a_foreign_target_even_when_the_caller_supplies_a_visible_scope(): void
    {
        $localBranch = $this->branch('APP-LOCAL');
        $localStore = $this->store($localBranch, 'APP-LOCAL-ST', 'warehouse');
        $foreignBranch = $this->branch('APP-FOREIGN');
        $foreignStore = $this->store($foreignBranch, 'APP-FOREIGN-ST', 'warehouse');
        $foreignDrawerStore = $this->store($foreignBranch, 'APP-FOREIGN-DRW-ST', 'warehouse');
        $foreignDrawer = $this->drawer($foreignBranch, $foreignDrawerStore, 'APP-FOREIGN-DRW');
        $requester = $this->masterRequester('approval-foreign-requester', [$localBranch->id], []);
        $this->actingAs($requester);

        $rejected = [
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('branch_delete', $foreignBranch, $localBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('store_delete', $foreignStore, $localBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('store_archive', $foreignStore, $localBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('cash_drawer_delete', $foreignDrawer, $localBranch->id, null)),
        ];

        self::assertSame([true, true, true, true], $rejected);
        self::assertDatabaseCount('approval_records', 0);
        self::assertSame('active', $foreignBranch->fresh()->status);
        self::assertSame('active', $foreignStore->fresh()->status);
        self::assertSame('active', $foreignDrawer->fresh()->status);
    }

    public function test_platform_master_delete_request_rejects_mismatched_caller_scope_metadata(): void
    {
        $targetBranch = $this->branch('APP-TARGET');
        $targetStore = $this->store($targetBranch, 'APP-TARGET-ST', 'warehouse');
        $targetDrawerStore = $this->store($targetBranch, 'APP-TARGET-DRW-ST', 'warehouse');
        $targetDrawer = $this->drawer($targetBranch, $targetDrawerStore, 'APP-TARGET-DRW');
        $otherBranch = $this->branch('APP-OTHER');
        $requester = $this->masterRequester(
            'approval-mismatch-requester',
            [$targetBranch->id, $otherBranch->id],
            [],
        );
        $this->actingAs($requester);

        $rejected = [
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('branch_delete', $targetBranch, $otherBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('store_delete', $targetStore, $otherBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('store_archive', $targetStore, $otherBranch->id, null)),
            $this->isRejected(fn (): ApprovalRecord => $this->requestDelete('cash_drawer_delete', $targetDrawer, $otherBranch->id, null)),
        ];

        self::assertSame([true, true, true, true], $rejected);
        self::assertDatabaseCount('approval_records', 0);
    }

    /** @return array{0: User, 1: User, 2: Branch|Store|CashDrawer, 3: int, 4: int|null} */
    private function approvalScenario(string $resource): array
    {
        $branch = $this->branch('APP-EXEC-'.strtoupper($resource));
        $store = $resource === 'branch_delete' ? null : $this->store($branch, 'APP-EXEC-ST-'.strtoupper($resource), 'warehouse');
        $target = match ($resource) {
            'branch_delete' => $branch,
            'store_delete' => $store,
            'cash_drawer_delete' => $this->drawer($branch, $store, 'APP-EXEC-DRW'),
        };
        $requester = $this->masterRequester(
            'approval-requester-'.strtolower($resource),
            [$branch->id],
            [],
        );
        $approver = $this->approvalOnlyReviewer(
            'approval-reviewer-'.strtolower($resource),
            [$branch->id],
            [],
        );

        return [$requester, $approver, $target, $branch->id, $store?->id];
    }

    private function requestDelete(string $resource, Branch|Store|CashDrawer $target, int $branchId, ?int $storeId): ApprovalRecord
    {
        return app(PlatformSettingsApprovalAction::class)->request(
            resource: $resource,
            id: $target->id,
            proposed: ['status' => 'inactive'],
            before: $target->getAttributes(),
            branchId: $branchId,
            storeId: $storeId,
        );
    }

    /** @param list<int> $branchIds @param list<int> $storeIds */
    private function masterRequester(string $username, array $branchIds, array $storeIds): User
    {
        return $this->userWithRole(
            $username,
            ['branches_stores.logical_delete', 'drawers_payments_tax_numbering_printers.logical_delete'],
            $branchIds,
            $storeIds,
        );
    }

    /** @param list<int> $branchIds @param list<int> $storeIds */
    private function approvalOnlyReviewer(string $username, array $branchIds, array $storeIds): User
    {
        return $this->userWithRole($username, ['company_settings.approve'], $branchIds, $storeIds);
    }

    /** @param list<string> $permissions @param list<int> $branchIds @param list<int> $storeIds */
    private function userWithRole(string $username, array $permissions, array $branchIds, array $storeIds): User
    {
        $role = Role::query()->create([
            'code' => 'approval-'.str_replace('_', '-', $username),
            'name_ar' => 'دور '.$username,
            'name_en' => 'Role '.$username,
            'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id')->all());

        return $this->userWith($username, [$role->code], branchIds: $branchIds, storeIds: $storeIds);
    }

    private function logicalDeletePermission(string $resource): string
    {
        return $resource === 'cash_drawer_delete'
            ? 'drawers_payments_tax_numbering_printers.logical_delete'
            : 'branches_stores.logical_delete';
    }

    /** @param callable(): ApprovalRecord $operation */
    private function isRejected(callable $operation): bool
    {
        try {
            $operation();
        } catch (AuthorizationException|ModelNotFoundException|ValidationException) {
            return true;
        }

        return false;
    }

    private function drawer(Branch $branch, Store $store, string $code): CashDrawer
    {
        return CashDrawer::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'code' => $code,
            'name_ar' => 'درج '.$code,
            'name_en' => 'Drawer '.$code,
            'status' => 'active',
        ]);
    }
}
