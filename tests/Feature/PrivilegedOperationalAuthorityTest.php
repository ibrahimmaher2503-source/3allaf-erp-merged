<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\CompleteStockCountLineAction;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\OpenStockCountSessionAction;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Inventory\Actions\SaveStockCountAction;
use App\Modules\Inventory\Actions\SetStockCountQuantityAction;
use App\Modules\Inventory\Actions\SubmitStockCountAction;
use App\Modules\Inventory\Actions\SubmitStockTransferAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserBranchScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class PrivilegedOperationalAuthorityTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{company: Company, branch: Branch, source: Store, destination: Store, product: Product, admin: User} */
    private function setupTransfer(): array
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $product = Product::factory()->create(['category_id' => $fixture['category']->id, 'status' => 'active', 'sale_price' => 20, 'average_cost' => 10]);
        Barcode::query()->create(['product_id' => $product->id, 'barcode' => 'QA-ROLE-'.Str::upper(Str::random(12)),
            'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $fixture['receiving']->id,
            'on_hand' => 10, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 100, 'version' => 1]);

        return ['company' => $fixture['company'], 'branch' => $fixture['branch'],
            'source' => $fixture['receiving'], 'destination' => $fixture['store'], 'product' => $product, 'admin' => $fixture['admin']];
    }

    private function actor(string $roleCode, Branch $branch): User
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach(Role::query()->where('code', $roleCode)->firstOrFail());
        UserBranchScope::query()->create(['user_id' => $actor->id, 'branch_id' => $branch->id, 'status' => 'active']);

        return $actor;
    }

    /** @param array{source: Store, destination: Store, product: Product} $fixture */
    private function submittedTransfer(array $fixture): StockTransfer
    {
        $transfer = app(CreateStockTransferDraftAction::class)->execute(
            $fixture['source']->id, $fixture['destination']->id,
            [['product_id' => $fixture['product']->id, 'quantity_requested' => '2',
                'allocations' => [$fixture['destination']->id => '2']]],
            reasonCode: 'replenishment', idempotencyKey: (string) Str::uuid(),
        );

        return app(SubmitStockTransferAction::class)->execute($transfer->id);
    }

    public function test_ordinary_authorized_requester_still_cannot_approve_own_transfer(): void
    {
        $fixture = $this->setupTransfer();
        Role::query()->where('code', 'warehouse-manager')->firstOrFail()->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('code', ['transfers.create', 'transfers.submit', 'transfers.approve'])->pluck('id')->all(),
        );
        $actor = $this->actor('warehouse-manager', $fixture['branch']);
        $this->actingAs($actor);
        $transfer = $this->submittedTransfer($fixture);
        $approval = ApprovalRecord::query()->where('source_type', 'stock_transfers')->where('source_id', (string) $transfer->id)->firstOrFail();

        self::assertFalse($actor->canBypassApproval());
        self::assertFalse(Gate::forUser($actor)->allows('decide', $approval));
        self::assertThrows(fn () => app(ApproveStockTransferAction::class)->execute($transfer->id), ValidationException::class);
        self::assertSame('submitted', $transfer->fresh()->status);
        self::assertNull($approval->fresh()->approver_id);
        self::assertSame(0, StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->count());
    }

    public function test_all_privileged_identities_can_self_approve_and_post_once_with_audit(): void
    {
        $fixture = $this->setupTransfer();
        $systemAdministrator = User::factory()->create(['status' => 'active']);
        $systemAdministrator->roles()->attach(Role::query()->where('code', 'system-administrator')->firstOrFail());
        self::assertFalse($systemAdministrator->is_super_admin);
        self::assertTrue($systemAdministrator->isSystemAdministrator());
        $owner = $this->actor('company-owner', $fixture['branch']);
        $ownerRole = Role::query()->where('code', 'company-owner')->firstOrFail();
        $approvalPermission = Permission::query()->where('code', 'transfers.approve')->firstOrFail();
        $ownerRole->permissions()->detach($approvalPermission->id);
        self::assertFalse($ownerRole->permissions()->whereKey($approvalPermission->id)->exists());
        self::assertTrue($owner->hasPermission('transfers.approve'));

        foreach ([$fixture['admin'], $systemAdministrator, $owner] as $actor) {
            $this->actingAs($actor);
            $transfer = $this->submittedTransfer($fixture);
            $approval = ApprovalRecord::query()->where('source_type', 'stock_transfers')->where('source_id', (string) $transfer->id)->firstOrFail();
            self::assertTrue($actor->canBypassApproval());
            self::assertTrue(Gate::forUser($actor)->allows('decide', $approval));

            app(ApproveStockTransferAction::class)->execute($transfer->id);
            self::assertSame($actor->id, $transfer->fresh()->requested_by);
            self::assertSame($actor->id, $transfer->fresh()->approved_by);
            self::assertSame($actor->id, $approval->fresh()->requester_id);
            self::assertSame($actor->id, $approval->fresh()->approver_id);
            $audit = AuditLog::query()->where('event', 'approval_approved')->where('source_id', (string) $approval->id)->latest('id')->firstOrFail();
            self::assertSame($actor->id, $audit->actor_id);
            if ($actor->is_super_admin || $actor->isCompanyOwner()) {
                self::assertTrue((bool) $audit->metadata[$actor->is_super_admin ? 'super_admin_bypass' : 'company_owner_bypass']);
            }

            self::assertSame('in_transit', $transfer->fresh()->status);
            $line = $transfer->lines()->firstOrFail();
            $allocation = $line->allocations()->firstOrFail();
            app(ReceiveStockTransferAction::class)->execute($transfer->id, [$line->id.'.'.$allocation->id => '2'], null, null);
            $movementCount = StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->count();
            self::assertSame(2, $movementCount);
            self::assertThrows(fn () => app(ApproveStockTransferAction::class)->execute($transfer->id), InvalidArgumentException::class);
            self::assertThrows(fn () => app(DispatchStockTransferAction::class)->execute($transfer->id), InvalidArgumentException::class);
            self::assertSame($movementCount, StockMovement::query()->where('source_type', StockTransfer::class)->where('source_id', $transfer->id)->count());
        }
    }

    public function test_privileged_roles_cannot_create_or_approve_transfer_in_another_company(): void
    {
        $fixture = $this->setupTransfer();
        $systemAdministrator = $this->actor('system-administrator', $fixture['branch']);
        $owner = $this->actor('company-owner', $fixture['branch']);
        UserBranchScope::query()->create(['user_id' => $fixture['admin']->id, 'branch_id' => $fixture['branch']->id, 'status' => 'active']);
        $sameCompanyBranch = Branch::query()->create(['company_id' => $fixture['company']->id, 'code' => 'QA-SECOND-BR', 'name_ar' => 'فرع ثان', 'name_en' => 'Second branch', 'timezone' => 'UTC', 'status' => 'active']);
        $sameCompanyStore = Store::query()->create(['company_id' => $fixture['company']->id, 'branch_id' => $sameCompanyBranch->id, 'code' => 'QA-SECOND-S', 'type' => 'warehouse', 'name_ar' => 'مخزن ثان', 'name_en' => 'Second store', 'status' => 'active']);
        $otherCompany = Company::query()->create(['code' => 'QA-OTHER', 'name_ar' => 'شركة أخرى', 'name_en' => 'Other company', 'currency_code' => 'EGP', 'timezone' => 'UTC', 'status' => 'active']);
        $otherBranch = Branch::query()->create(['company_id' => $otherCompany->id, 'code' => 'QA-OTHER-BR', 'name_ar' => 'فرع آخر', 'name_en' => 'Other branch', 'timezone' => 'UTC', 'status' => 'active']);
        $otherSource = Store::query()->create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'code' => 'QA-OTHER-S', 'type' => 'warehouse', 'name_ar' => 'مخزن آخر', 'name_en' => 'Other source', 'status' => 'active']);
        $otherDestination = Store::query()->create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'code' => 'QA-OTHER-D', 'type' => 'warehouse', 'name_ar' => 'وجهة أخرى', 'name_en' => 'Other destination', 'status' => 'active']);

        foreach ([$fixture['admin'], $systemAdministrator, $owner] as $actor) {
            $this->actingAs($actor);
            self::assertTrue($actor->canAccessStore($sameCompanyStore->id));
            self::assertTrue(Store::query()->visibleTo($actor)->whereKey($sameCompanyStore->id)->exists());
            self::assertFalse($actor->canAccessStore($otherSource->id));
            self::assertFalse(Store::query()->visibleTo($actor)->whereKey($otherSource->id)->exists());
            $foreignApproval = new ApprovalRecord(['source_type' => 'stock_transfers', 'source_id' => '9999',
                'requested_action' => 'approve', 'decision_permission' => 'transfers.approve',
                'approval_state' => ApprovalState::Pending, 'requester_id' => $actor->id,
                'branch_id' => $otherBranch->id, 'store_id' => $otherSource->id]);
            self::assertFalse(Gate::forUser($actor)->allows('decide', $foreignApproval));
            self::assertThrows(fn () => app(CreateStockTransferDraftAction::class)->execute(
                $otherSource->id, $otherDestination->id,
                [['product_id' => $fixture['product']->id, 'quantity_requested' => '1']],
            ), AuthorizationException::class);
        }
    }

    public function test_company_owner_can_settle_own_count_after_participating_without_duplicate_movement(): void
    {
        $fixture = $this->setupTransfer();
        $owner = $this->actor('company-owner', $fixture['branch']);
        $this->actingAs($owner);

        $count = app(SaveStockCountAction::class)->execute([
            'branch_id' => $fixture['branch']->id,
            'location_ids' => [$fixture['source']->id],
            'member_ids' => [$owner->id],
            'manager_id' => $fixture['admin']->id,
            'count_type' => 'partial',
            'scope_type' => 'partial',
            'notes' => 'Focused privileged settlement',
        ]);
        app(OpenStockCountSessionAction::class)->execute($count->id);
        $line = app(SetStockCountQuantityAction::class)->execute(
            $count->id, $fixture['source']->id, $fixture['product']->item_code,
            '9', (string) Str::uuid(), 'qa-browser', null,
        );
        app(CompleteStockCountLineAction::class)->execute($line->id);
        app(SubmitStockCountAction::class)->execute($count->id);

        $approval = ApprovalRecord::query()->where('source_type', 'stock_counts')
            ->where('source_id', (string) $count->id)->firstOrFail();
        self::assertSame($owner->id, $approval->requester_id);
        app(ReconcileStockCountAction::class)->execute($count->id);

        self::assertSame('reconciled', $count->fresh()->status);
        self::assertSame($owner->id, $count->fresh()->created_by);
        self::assertSame($owner->id, $count->fresh()->approved_by);
        self::assertSame($owner->id, $approval->fresh()->approver_id);
        self::assertSame(1, StockMovement::query()->where('source_type', StockCount::class)
            ->where('source_id', $count->id)->count());
        self::assertThrows(fn () => app(ReconcileStockCountAction::class)->execute($count->id), InvalidArgumentException::class);
        self::assertSame(1, StockMovement::query()->where('source_type', StockCount::class)
            ->where('source_id', $count->id)->count());
    }
}
