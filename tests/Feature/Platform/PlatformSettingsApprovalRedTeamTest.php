<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PlatformSettingsApprovalRedTeamTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authorized_reviewer_approves_an_unchanged_settings_request(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        $requester = $this->userWithPermissions('company_settings.edit', 'company_settings.approve');
        $this->assignBranch($requester, $branch);
        $this->actingAs($requester);
        $code = 'QA-APPROVE-'.strtoupper(bin2hex(random_bytes(4)));
        $approval = $this->requestTaxSetting($company, $code);

        $reviewer = $this->userWithPermissions('company_settings.approve');
        $this->assignBranch($reviewer, $branch);
        $this->actingAs($reviewer);
        app(PlatformSettingsApprovalAction::class)->approve($approval);

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'approval_state' => ApprovalState::Approved->value,
            'approver_id' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('tax_settings', [
            'company_id' => $company->id,
            'code' => $code,
            'rate' => '14.00',
        ]);
    }

    public function test_unprivileged_direct_settings_approval_is_denied_without_effect(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        $requester = $this->userWithPermissions('company_settings.edit', 'company_settings.approve');
        $this->assignBranch($requester, $branch);
        $this->actingAs($requester);
        $code = 'QA-DENY-'.strtoupper(bin2hex(random_bytes(4)));
        $approval = $this->requestTaxSetting($company, $code);
        $unprivileged = User::factory()->create(['status' => 'active']);
        $this->actingAs($unprivileged);

        try {
            app(PlatformSettingsApprovalAction::class)->approve($approval);
            $this->fail('A reviewer without the decision permission must not approve a platform settings request.');
        } catch (AuthorizationException) {
            // Expected server-side policy denial.
        }

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'approval_state' => ApprovalState::Pending->value,
            'approver_id' => null,
        ]);
        $this->assertDatabaseMissing('tax_settings', ['code' => $code]);
    }

    public function test_forged_source_or_context_hash_cannot_apply_a_settings_change(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        $requester = $this->userWithPermissions('company_settings.edit', 'company_settings.approve');
        $this->assignBranch($requester, $branch);
        $this->actingAs($requester);
        $code = 'QA-TAMPER-'.strtoupper(bin2hex(random_bytes(4)));
        $approval = $this->requestTaxSetting($company, $code);
        $originalContext = $approval->limit_context;
        $otherCompany = Company::factory()->create();
        $reviewer = $this->userWithPermissions('company_settings.approve');
        $this->actingAs($reviewer);

        $forgedContext = $originalContext;
        $forgedContext['proposed']['company_id'] = $otherCompany->id;
        DB::table('approval_records')->where('id', $approval->id)->update([
            'limit_context' => json_encode($forgedContext, JSON_THROW_ON_ERROR),
        ]);

        try {
            app(PlatformSettingsApprovalAction::class)->approve($approval);
            $this->fail('A changed company ID in the approval context must invalidate its stored hash.');
        } catch (ValidationException) {
            // Expected canonical payload/hash denial.
        }

        DB::table('approval_records')->where('id', $approval->id)->update([
            'limit_context' => json_encode($originalContext, JSON_THROW_ON_ERROR),
            'source_id' => 'tax_setting:999999',
        ]);

        try {
            app(PlatformSettingsApprovalAction::class)->approve($approval);
            $this->fail('A source identifier that does not match the canonical request must be denied.');
        } catch (ValidationException) {
            // Expected canonical source denial.
        }

        $this->assertDatabaseHas('approval_records', [
            'id' => $approval->id,
            'approval_state' => ApprovalState::Pending->value,
            'approver_id' => null,
        ]);
        $this->assertDatabaseMissing('tax_settings', ['code' => $code]);
        $this->assertFalse(DB::table('audit_logs')
            ->where('event', 'approval_approved')
            ->where('source_type', ApprovalRecord::class)
            ->where('source_id', (string) $approval->id)
            ->exists());
    }

    /** @return array{Company, Branch} */
    private function companyAndBranch(): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        Store::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        return [$company, $branch];
    }

    private function assignBranch(User $user, Branch $branch): void
    {
        $user->branchScopes()->create([
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
    }

    private function userWithPermissions(string ...$permissionCodes): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create([
            'code' => 'qa-approval-redteam-'.strtolower(bin2hex(random_bytes(6))),
            'name_ar' => 'مراجع إعدادات QA',
            'name_en' => 'QA settings reviewer',
            'status' => 'active',
        ]);
        $permissionIds = [];

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
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

    private function requestTaxSetting(Company $company, string $code): ApprovalRecord
    {
        return app(PlatformSettingsApprovalAction::class)->request(
            resource: 'tax_setting',
            id: null,
            proposed: [
                'company_id' => $company->id,
                'code' => $code,
                'name_ar' => 'ضريبة اختبار',
                'name_en' => 'QA Tax Setting',
                'rate' => '14.00',
                'treatment' => 'standard',
                'is_default' => false,
                'is_tax_inclusive' => false,
                'tax_number' => null,
                'effective_from' => null,
                'effective_to' => null,
                'status' => 'active',
                'policy_notes' => null,
            ],
            reason: 'Focused Module 1 approval red-team test',
        );
    }
}
