<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

final class ApprovalInboxPresentationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_authorized_reviewer_sees_allowlisted_tax_before_and_proposed_values_only(): void
    {
        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = $this->userWithPermissions('company_settings.approve');
        $approval = $this->taxApproval($requester, [
            'resource' => 'tax_setting',
            'id' => 42,
            'before' => [
                'company_id' => 71,
                'code' => 'VAT-OLD',
                'name_ar' => 'ضريبة قديمة',
                'name_en' => 'Old VAT',
                'rate' => '5.00',
                'treatment' => 'standard',
                'is_default' => true,
                'is_tax_inclusive' => false,
                'tax_number' => 'PRIVATE-TAX-OLD-771',
                'effective_from' => '2026-01-01 00:00:00',
                'effective_to' => null,
                'status' => 'active',
                'policy_notes' => 'PRIVATE-NOTE-OLD-772',
            ],
            'proposed' => [
                'company_id' => 71,
                'code' => 'VAT-NEW',
                'name_ar' => 'ضريبة جديدة',
                'name_en' => 'New VAT',
                'rate' => '14.00',
                'treatment' => 'standard',
                'is_default' => false,
                'is_tax_inclusive' => true,
                'tax_number' => 'PRIVATE-TAX-NEW-773',
                'effective_from' => '2026-02-01 00:00:00',
                'effective_to' => null,
                'status' => 'active',
                'api_token' => 'PRIVATE-TOKEN-774',
                'unlisted_private_field' => 'UNLISTED-PRIVATE-775',
            ],
        ]);
        $sourceHash = $approval->source_hash;

        $this->actingAs($reviewer);

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->assertSee('VAT-OLD')
            ->assertSee('VAT-NEW')
            ->assertSee('5.00')
            ->assertSee('14.00')
            ->assertSee(__('Before'))
            ->assertSee(__('After'))
            ->assertDontSee('PRIVATE-TAX-OLD-771')
            ->assertDontSee('PRIVATE-TAX-NEW-773')
            ->assertDontSee('PRIVATE-NOTE-OLD-772')
            ->assertDontSee('PRIVATE-TOKEN-774')
            ->assertDontSee('UNLISTED-PRIVATE-775')
            ->assertDontSee('unlisted_private_field');

        $approval->refresh();
        $this->assertSame($sourceHash, $approval->source_hash);
        $this->assertSame('14.00', $approval->limit_context['proposed']['rate']);
        $this->assertSame('PRIVATE-TOKEN-774', $approval->limit_context['proposed']['api_token']);
    }

    public function test_branch_review_shows_only_request_time_dependency_counts(): void
    {
        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = $this->userWithPermissions('company_settings.approve');
        $branch = Branch::factory()->create();
        $reviewer->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $approval = $this->approval($requester, [
            'resource' => 'branch_delete',
            'id' => $branch->id,
            'before' => ['status' => 'active'],
            'proposed' => [
                'deleted' => true,
                'store_count' => 4,
                'active_store_count' => 0,
                'active_selling_mapping_count' => 0,
                'active_cash_drawer_count' => 2,
                'active_user_scope_count' => 6,
                'unlisted_private_field' => 'PRIVATE-BRANCH-CONTEXT-776',
            ],
        ], branchId: $branch->id);

        $this->actingAs($reviewer);

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->assertSee(__('Request-time dependency context'))
            ->assertSee(__('Linked stores'))
            ->assertSee(__('Active stores'))
            ->assertSee(__('Active selling-store mappings'))
            ->assertSee(__('Active cash drawers'))
            ->assertSee(__('Active user scope assignments'))
            ->assertSee('4')
            ->assertSee('0')
            ->assertSee('2')
            ->assertSee('6')
            ->assertDontSee('PRIVATE-BRANCH-CONTEXT-776')
            ->assertDontSee('unlisted_private_field');
    }

    public function test_requester_cannot_decide_their_own_settings_approval(): void
    {
        $requester = $this->userWithPermissions('company_settings.approve');
        $approval = $this->taxApproval($requester, [
            'resource' => 'tax_setting',
            'id' => 44,
            'before' => null,
            'proposed' => ['code' => 'VAT-SELF', 'rate' => '14.00'],
        ]);

        $this->actingAs($requester);

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->assertDontSee('wire:click="approve"')
            ->call('approve')
            ->assertHasErrors('approval');

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'approval_state' => ApprovalState::Pending->value,
            'approver_id' => null,
        ]);
    }

    public function test_out_of_scope_reviewer_cannot_open_settings_payload(): void
    {
        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = $this->userWithPermissions('company_settings.approve');
        $approval = $this->taxApproval($requester, [
            'resource' => 'tax_setting',
            'id' => 45,
            'before' => ['code' => 'HIDDEN-OLD', 'rate' => '1.00'],
            'proposed' => ['code' => 'HIDDEN-NEW', 'rate' => '2.00'],
        ], branchId: Branch::factory()->create()->id);

        $this->actingAs($reviewer);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('platform::system.approval-inbox')->call('showApproval', $approval->id);
    }

    public function test_rejection_without_a_reason_leaves_the_approval_pending(): void
    {
        $requester = User::factory()->create(['status' => 'active']);
        $reviewer = $this->userWithPermissions('company_settings.approve');
        $approval = $this->taxApproval($requester, [
            'resource' => 'tax_setting',
            'id' => 46,
            'before' => ['code' => 'VAT-REJECT', 'rate' => '1.00'],
            'proposed' => ['code' => 'VAT-REJECT', 'rate' => '2.00'],
        ]);

        $this->actingAs($reviewer);

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $approval->id)
            ->set('decisionReason', '')
            ->call('reject')
            ->assertHasErrors('decisionReason');

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'approval_state' => ApprovalState::Pending->value,
            'approver_id' => null,
        ]);
    }

    private function userWithPermissions(string ...$permissionCodes): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create([
            'code' => 'qa-approval-reviewer-'.$user->id,
            'name_ar' => 'مراجع الاختبار',
            'name_en' => 'QA approval reviewer',
            'status' => 'active',
        ]);
        $permissionIds = [];

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->updateOrCreate(
                ['code' => $permissionCode],
                [
                    'module' => str($permissionCode)->beforeLast('.')->toString(),
                    'action' => str($permissionCode)->afterLast('.')->toString(),
                    'sensitivity' => 'high',
                    'status' => 'active',
                ],
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id);

        return $user;
    }

    /** @param array<string, mixed> $context */
    private function taxApproval(User $requester, array $context, ?int $branchId = null): ApprovalRecord
    {
        return $this->approval($requester, $context, branchId: $branchId);
    }

    /** @param array<string, mixed> $context */
    private function approval(User $requester, array $context, ?int $branchId = null): ApprovalRecord
    {
        $resource = (string) $context['resource'];
        $id = $context['id'] ?? null;

        return ApprovalRecord::query()->create([
            'source_type' => 'platform_settings',
            'source_id' => $resource.':'.($id === null ? 'new:qa' : (string) $id),
            'source_version' => $id === null ? 'new' : (string) $id,
            'source_hash' => hash('sha256', $resource.':qa-approval-presentation'),
            'requested_action' => $resource,
            'request_permission' => $resource === 'branch_delete'
                ? 'branches_stores.logical_delete'
                : 'company_settings.edit',
            'decision_permission' => 'company_settings.approve',
            'approval_state' => ApprovalState::Pending,
            'requester_id' => $requester->id,
            'branch_id' => $branchId,
            'store_id' => null,
            'reason_code' => $resource === 'branch_delete' ? 'MASTER_DELETE' : null,
            'reason_text' => 'QA approval presentation fixture',
            'limit_context' => $context,
            'requested_at' => now(),
        ]);
    }
}
