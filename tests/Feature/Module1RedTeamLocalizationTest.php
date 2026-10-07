<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1RedTeamLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_approval_decision_denials_use_the_selected_locale_and_leave_the_request_open(): void
    {
        Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'view-approval-inbox' ? true : null);

        $expectedByLocale = [
            'ar' => 'تم رفض الصلاحية',
            'en' => 'Permission denied',
        ];
        $observedByLocale = [];

        foreach ($expectedByLocale as $locale => $expectedMessage) {
            app()->setLocale($locale);

            $branch = Branch::factory()->create();
            $reviewer = User::factory()->create(['status' => 'active']);
            $reviewer->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
            $approval = ApprovalRecord::query()->create([
                'source_type' => 'platform_settings',
                'source_id' => 'branch_delete:'.$branch->id,
                'source_version' => 'current',
                'source_hash' => hash('sha256', 'redteam-approval-'.$branch->id),
                'requested_action' => 'branch_delete',
                'request_permission' => 'branches_stores.logical_delete',
                'decision_permission' => 'company_settings.approve',
                'approval_state' => ApprovalState::Pending,
                'requester_id' => $reviewer->id,
                'branch_id' => $branch->id,
                'reason_code' => 'MASTER_DELETE',
                'reason_text' => 'Disposable localization review fixture.',
                'limit_context' => [
                    'resource' => 'branch_delete',
                    'id' => $branch->id,
                    'before' => ['status' => 'active'],
                    'proposed' => ['deleted' => true],
                ],
                'requested_at' => now(),
            ]);

            $this->withSession(['locale' => $locale])->actingAs($reviewer);

            $component = Livewire::test('platform::system.approval-inbox')
                ->call('showApproval', $approval->id)
                ->call('approve')
                ->assertSet('approvalModalOpen', true);

            $observedByLocale[$locale]['approve'] = $component->errors()->first('approval');
            $component->assertSee($expectedMessage);

            $component->call('closeApproval')
                ->call('showApproval', $approval->id)
                ->call('reject')
                ->assertSet('approvalModalOpen', true);

            $observedByLocale[$locale]['reject'] = $component->errors()->first('approval');
            $component->assertSee($expectedMessage);

            self::assertSame(ApprovalState::Pending, $approval->fresh()->approval_state);
            self::assertNull($approval->fresh()->approver_id);
        }

        self::assertSame([
            'ar' => ['approve' => 'تم رفض الصلاحية', 'reject' => 'تم رفض الصلاحية'],
            'en' => ['approve' => 'Permission denied', 'reject' => 'Permission denied'],
        ], $observedByLocale);
    }

    public function test_required_tax_rate_uses_the_friendly_field_label_in_arabic_and_english(): void
    {
        Gate::before(static fn (User $user, string $ability): bool => true);
        Company::factory()->create();

        $admin = User::factory()->create([
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        $expectedByLocale = [
            'ar' => 'حقل نسبة الضريبة (%) ده مطلوب ومهم.',
            'en' => 'The Tax Rate (%) field is required.',
        ];

        foreach ($expectedByLocale as $locale => $expectedMessage) {
            app()->setLocale($locale);
            $this->withSession(['locale' => $locale])->actingAs($admin);

            $component = Livewire::test('platform::admin.settings')
                ->set('activeTab', 'tax')
                ->set('taxSettingForm.code', 'QA-REQUIRED-RATE')
                ->set('taxSettingForm.name_ar', 'اختبار ضريبة')
                ->set('taxSettingForm.name_en', 'QA tax rule')
                ->set('taxSettingForm.rate', '')
                ->set('taxSettingForm.treatment', 'standard')
                ->set('taxSettingForm.status', 'active')
                ->call('saveTaxSetting')
                ->assertHasErrors(['taxSettingForm.rate' => 'required']);

            $message = (string) $component->errors()->first('taxSettingForm.rate');

            self::assertSame($expectedMessage, $message);
            self::assertStringNotContainsString('taxSettingForm.rate', $message);
            $component->assertSee($message);
        }
    }
}
