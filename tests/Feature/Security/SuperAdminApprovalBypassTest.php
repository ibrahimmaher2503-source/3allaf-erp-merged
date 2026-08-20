<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryAdjustmentLine;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Owner-directed Super Admin approval exception (DEC-087).
 *
 * Each test names the production mutation it catches: restoring a pending
 * review, restoring self-approval denial, granting the exception to an
 * ordinary administrator, accepting inactive privileged users, or resetting
 * the bootstrap credential during canonical seeding.
 */
final class SuperAdminApprovalBypassTest extends TestCase
{
    use PlatformFixtures;
    use DatabaseTransactions;

    public function test_active_super_admin_logically_deletes_a_branch_without_leaving_a_pending_approval(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $superAdmin = $this->userWith('approval-bypass-super', ['system-administrator'], true);
        $branch = $this->branch('BYPASS-BRANCH');
        $this->actingAs($superAdmin);

        $approval = app(PlatformSettingsApprovalAction::class)->request(
            resource: 'branch_delete',
            id: $branch->id,
            proposed: ['deleted' => true],
            before: $branch->getAttributes(),
            branchId: $branch->id,
            reason: 'Owner-authorized Super Admin direct execution.',
        );

        self::assertSame(ApprovalState::Approved, $approval->fresh()->approval_state);
        self::assertSame($superAdmin->id, $approval->fresh()->approver_id);
        self::assertSame('inactive', $branch->fresh()->status);
        self::assertDatabaseMissing('approval_records', ['id' => $approval->id, 'approval_state' => 'pending']);
        self::assertTrue((bool) (AuditLog::query()->where('event', 'approval_approved')->latest('id')->firstOrFail()->metadata['super_admin_bypass'] ?? false));
    }

    public function test_active_super_admin_can_approve_its_own_financial_approval_record_with_a_bypass_audit_context(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $superAdmin = $this->userWith('financial-bypass-super', ['system-administrator'], true);
        $branch = $this->branch('BYPASS-FINANCE');
        $store = $this->store($branch, 'BYPASS-FINANCE-ST');
        $this->actingAs($superAdmin);

        $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'product_wallet_adjustments',
            sourceId: 'financial-bypass-fixture',
            sourceVersion: '1',
            sourceHash: hash('sha256', 'financial-bypass-fixture'),
            requestedAction: 'adjustment',
            requestPermission: 'product_wallet.adjust',
            decisionPermission: 'product_wallet.approve',
            branchId: $branch->id,
            storeId: $store->id,
            reasonText: 'Owner-authorized financial bypass fixture.',
        ));

        app(ApproveRequest::class)->execute($approval, '1', $approval->source_hash, 'Super Admin direct approval.');

        self::assertSame(ApprovalState::Approved, $approval->fresh()->approval_state);
        self::assertSame($superAdmin->id, $approval->fresh()->approver_id);
        self::assertTrue((bool) (AuditLog::query()->where('event', 'approval_approved')->latest('id')->firstOrFail()->metadata['super_admin_bypass'] ?? false));
    }

    public function test_active_super_admin_posts_its_own_inventory_adjustment_without_a_pending_review(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $superAdmin = $this->userWith('inventory-bypass-super', ['system-administrator'], true);
        $branch = $this->branch('BYPASS-INVENTORY');
        $store = $this->store($branch, 'BYPASS-INVENTORY-ST', 'warehouse');
        $category = Category::query()->create(['code' => 'BYPASS-INVENTORY-CAT', 'name_ar' => 'مخزون', 'name_en' => 'Inventory', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'BYPASS-INVENTORY-PROD', 'name_ar' => 'منتج', 'name_en' => 'Product', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '0', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '0', 'total_value' => '0', 'version' => 1]);
        $adjustment = InventoryAdjustment::query()->create([
            'adjustment_number' => 'BYPASS-ADJ-001', 'store_id' => $store->id, 'adjustment_type' => 'entry',
            'status' => 'draft', 'reason_code' => 'owner_override', 'reason_notes' => 'Owner-authorized Super Admin posting.',
            'created_by' => $superAdmin->id, 'idempotency_key' => 'BYPASS-ADJ-001', 'lock_version' => 1,
        ]);
        InventoryAdjustmentLine::query()->create(['inventory_adjustment_id' => $adjustment->id, 'product_id' => $product->id, 'quantity_delta' => '3', 'unit_cost' => '5']);
        $this->actingAs($superAdmin);

        $posted = app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);

        self::assertSame('approved', $posted->status);
        self::assertSame($superAdmin->id, $posted->approved_by);
        self::assertSame('3.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->value('on_hand'));
        self::assertDatabaseMissing('approval_records', ['source_type' => 'inventory_adjustments', 'source_id' => (string) $adjustment->id, 'approval_state' => 'pending']);
    }

    public function test_ordinary_administrator_cannot_approve_its_own_financial_approval_record(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $branch = $this->branch('ORDINARY-FINANCE');
        $store = $this->store($branch, 'ORDINARY-FINANCE-ST');
        $ordinary = $this->ordinaryAdministrator($branch->id, $store->id);
        $this->actingAs($ordinary);
        $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'product_wallet_adjustments',
            sourceId: 'ordinary-financial-fixture',
            sourceVersion: '1',
            sourceHash: hash('sha256', 'ordinary-financial-fixture'),
            requestedAction: 'adjustment',
            requestPermission: 'product_wallet.adjust',
            decisionPermission: 'product_wallet.approve',
            branchId: $branch->id,
            storeId: $store->id,
        ));

        $this->expectException(ValidationException::class);
        app(ApproveRequest::class)->execute($approval, '1', $approval->source_hash);
    }

    public function test_inactive_super_admin_is_denied_the_same_financial_approval(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $superAdmin = $this->userWith('inactive-bypass-super', ['system-administrator'], true);
        $branch = $this->branch('INACTIVE-BYPASS');
        $store = $this->store($branch, 'INACTIVE-BYPASS-ST');
        $this->actingAs($superAdmin);
        $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'product_wallet_adjustments',
            sourceId: 'inactive-financial-fixture',
            sourceVersion: '1',
            sourceHash: hash('sha256', 'inactive-financial-fixture'),
            requestedAction: 'adjustment',
            requestPermission: 'product_wallet.adjust',
            decisionPermission: 'product_wallet.approve',
            branchId: $branch->id,
            storeId: $store->id,
        ));
        $superAdmin->forceFill(['status' => 'inactive'])->save();
        $this->actingAs($superAdmin->fresh());

        $this->expectException(ValidationException::class);
        app(ApproveRequest::class)->execute($approval, '1', $approval->source_hash);
    }

    public function test_canonical_seeder_preserves_the_bootstrap_super_admin_password_and_all_active_permissions(): void
    {
        $this->seed(CanonicalAuthorizationSeeder::class);
        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        $administrator->forceFill(['password' => Hash::make('RetainedPassword!2026')])->save();
        $passwordHash = $administrator->fresh()->password;

        $this->seed(CanonicalAuthorizationSeeder::class);
        $administrator->refresh();

        self::assertTrue($administrator->is_super_admin);
        self::assertSame('active', $administrator->status);
        self::assertTrue(Hash::check('RetainedPassword!2026', $administrator->password));
        self::assertSame($passwordHash, $administrator->password);
        self::assertTrue($administrator->roles()->where('code', 'system-administrator')->exists());
        self::assertSame(Permission::query()->where('status', 'active')->count(), $administrator->roles()->where('code', 'system-administrator')->firstOrFail()->permissions()->where('status', 'active')->count());
    }

    private function ordinaryAdministrator(int $branchId, int $storeId): User
    {
        $role = Role::query()->create([
            'code' => 'ordinary-approval-administrator',
            'name_ar' => 'مدير اختبار عادي',
            'name_en' => 'Ordinary test administrator',
            'status' => 'active',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('code', ['product_wallet.adjust', 'product_wallet.approve'])->pluck('id'));

        return $this->userWith('ordinary-approval-administrator', [$role->code], false, [$branchId], [$storeId]);
    }
}
