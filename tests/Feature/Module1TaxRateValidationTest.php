<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\PlatformSettingsApprovalAction;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1TaxRateValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_blank_rate_is_rejected_by_the_settings_form_before_approval_or_audit_is_written(): void
    {
        $company = Company::factory()->create();
        $requester = $this->createSystemAdministrator();
        $before = $this->taxSideEffectCounts($company);

        Livewire::actingAs($requester)
            ->test('platform::admin.settings')
            ->set('activeTab', 'tax')
            ->set('taxSettingForm.code', 'QA-BLANK-RATE')
            ->set('taxSettingForm.name_ar', 'اختبار ضريبة')
            ->set('taxSettingForm.name_en', 'QA tax rule')
            ->set('taxSettingForm.rate', '')
            ->set('taxSettingForm.treatment', 'standard')
            ->set('taxSettingForm.is_default', false)
            ->set('taxSettingForm.is_tax_inclusive', false)
            ->set('taxSettingForm.status', 'active')
            ->call('saveTaxSetting')
            ->assertHasErrors(['taxSettingForm.rate']);

        self::assertSame($before, $this->taxSideEffectCounts($company));
    }

    public function test_invalid_rates_are_rejected_before_an_approval_request_or_audit_is_written(): void
    {
        $company = Company::factory()->create();
        $requester = $this->createSystemAdministrator();
        $this->actingAs($requester);
        self::assertSame($requester->id, auth()->id());
        self::assertTrue(Gate::allows('company_settings.edit'));

        $invalidRateCases = [
            ['rate' => null],
            ['rate' => ''],
            ['rate' => 'not-a-number'],
            ['rate' => -5],
            ['rate' => 150],
            [],
        ];

        foreach ($invalidRateCases as $index => $rateCase) {
            $proposed = [
                'company_id' => $company->id,
                'code' => 'QA-REQUEST-'.$index,
                'name_ar' => 'اختبار ضريبة',
                'name_en' => 'QA tax rule',
                'treatment' => 'standard',
                'is_default' => false,
                'is_tax_inclusive' => false,
                'status' => 'active',
            ];
            if (array_key_exists('rate', $rateCase)) {
                $proposed['rate'] = $rateCase['rate'];
            }
            $before = $this->taxSideEffectCounts($company);

            try {
                app(PlatformSettingsApprovalAction::class)->request('tax_setting', null, $proposed);
                self::fail('An invalid tax rate was accepted into approval.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('taxSettingForm.rate', $exception->errors());
            }

            self::assertSame($before, $this->taxSideEffectCounts($company));
        }
    }

    public function test_invalid_rates_are_rejected_before_tax_or_audit_changes(): void
    {
        $company = Company::factory()->create();
        $user = $this->createSystemAdministrator();
        $this->actingAs($user);

        $default = TaxSetting::query()->create([
            'company_id' => $company->id,
            'code' => 'QA-BASELINE-TAX',
            'name_ar' => 'ضريبة أساسية',
            'name_en' => 'Baseline tax',
            'rate' => '14.00',
            'treatment' => 'standard',
            'is_default' => true,
            'is_tax_inclusive' => false,
            'status' => 'active',
            'policy_notes' => 'Disposable QA fixture.',
        ]);

        $invalidRateCases = [
            ['rate' => null],
            ['rate' => ''],
            ['rate' => 'not-a-number'],
            ['rate' => -5],
            ['rate' => 150],
            [],
        ];

        foreach ($invalidRateCases as $index => $rateCase) {
            $data = [
                'company_id' => $company->id,
                'code' => 'QA-DIRECT-'.$index,
                'name_ar' => 'اختبار ضريبة',
                'name_en' => 'QA tax rule',
                'treatment' => 'standard',
                'is_default' => false,
                'is_tax_inclusive' => false,
                'status' => 'active',
                'policy_notes' => 'Disposable QA input.',
            ];
            if (array_key_exists('rate', $rateCase)) {
                $data['rate'] = $rateCase['rate'];
            }
            $before = $this->taxSideEffectCounts($company);

            try {
                app(SaveLocalSettingsAction::class)->saveTaxSetting($data);
                self::fail('An invalid tax rate was persisted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('taxSettingForm.rate', $exception->errors());
            }

            self::assertSame($before, $this->taxSideEffectCounts($company));
            self::assertTrue($default->fresh()->is_default);
        }
    }

    public function test_zero_rate_remains_valid_for_standard_and_zero_treatment_rules(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->createSystemAdministrator());
        $action = app(SaveLocalSettingsAction::class);

        $standard = $action->saveTaxSetting([
            'company_id' => $company->id,
            'code' => 'QA-ZERO-STANDARD',
            'name_ar' => 'ضريبة صفرية',
            'name_en' => 'Zero standard tax',
            'rate' => '0',
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
            'policy_notes' => 'Disposable QA fixture.',
        ]);
        $exempt = $action->saveTaxSetting([
            'company_id' => $company->id,
            'code' => 'QA-ZERO-EXEMPT',
            'name_ar' => 'معفى',
            'name_en' => 'Exempt tax',
            'rate' => '0',
            'treatment' => 'exempt',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
            'policy_notes' => 'Disposable QA fixture.',
        ]);

        self::assertSame('0.00', $standard->fresh()->rate);
        self::assertSame('0.00', $exempt->fresh()->rate);
    }

    public function test_zero_rate_remains_valid_when_submitted_for_zero_rated_approval(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->createSystemAdministrator());

        $approval = app(PlatformSettingsApprovalAction::class)->request('tax_setting', null, [
            'company_id' => $company->id,
            'code' => 'QA-ZERO-RATED-APPROVAL',
            'name_ar' => 'صفرية',
            'name_en' => 'Zero rated',
            'rate' => '0',
            'treatment' => 'zero_rated',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
        ]);

        self::assertSame(ApprovalState::Approved, $approval->approval_state);
        self::assertSame('0.00', TaxSetting::query()->forCompany($company->id)->where('code', 'QA-ZERO-RATED-APPROVAL')->value('rate'));
    }

    public function test_company_settings_save_for_an_authorized_user_and_refuses_a_user_without_edit_permission(): void
    {
        $company = Company::factory()->create();
        $data = [
            'company' => [
                'code' => $company->code,
                'name_ar' => 'شركة اختبار محدثة',
                'name_en' => 'Updated QA company',
                'legal_name' => 'Updated QA company LLC',
                'tax_number' => 'QA-TAX-001',
                'commercial_registration' => 'QA-REG-001',
                'currency_code' => 'EGP',
                'currency_symbol' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'locale_default' => 'en',
                'phone' => null,
                'email' => null,
                'address' => 'Disposable QA address',
                'status' => 'active',
                'policy_notes' => 'Disposable QA fixture.',
            ],
        ];
        $this->actingAs($this->createSystemAdministrator());
        $auditCount = AuditLog::query()->count();

        Livewire::test('platform::admin.settings')
            ->set('companyForm.name_en', 'Updated QA company')
            ->call('saveCompany')
            ->assertHasNoErrors();

        self::assertSame('Updated QA company', $company->fresh()->name_en);
        self::assertSame($auditCount + 1, AuditLog::query()->count());

        $unauthorizedUser = User::factory()->create()->fresh();
        $this->actingAs($unauthorizedUser);
        $before = $company->fresh()->getAttributes();
        $auditCount = AuditLog::query()->count();

        try {
            app(SaveLocalSettingsAction::class)->execute([
                'company' => [...$data['company'], 'name_en' => 'Unauthorized change'],
            ], $company->id);
            self::fail('A user without company_settings.edit updated company settings.');
        } catch (AuthorizationException) {
            self::assertSame($before, $company->fresh()->getAttributes());
            self::assertSame($auditCount, AuditLog::query()->count());
        }
    }

    public function test_company_form_rejects_a_missing_currency_before_writing(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->createSystemAdministrator());
        $before = $company->fresh()->getAttributes();
        ksort($before);
        $auditCount = AuditLog::query()->count();

        Livewire::test('platform::admin.settings')
            ->set('companyForm.currency_code', '')
            ->call('saveCompany')
            ->assertHasErrors(['companyForm.currency_code']);

        $after = $company->fresh()->getAttributes();
        ksort($after);
        self::assertSame($before, $after);
        self::assertSame($auditCount, AuditLog::query()->count());
    }

    public function test_payment_method_save_succeeds_and_denied_or_invalid_saves_have_no_side_effects(): void
    {
        $action = app(SaveLocalSettingsAction::class);
        $this->actingAs($this->createSystemAdministrator());
        $auditCount = AuditLog::query()->count();

        $paymentComponent = Livewire::test('platform::admin.settings')
            ->set('activeTab', 'payments')
            ->set('paymentMethodForm.code', 'qa-cash')
            ->set('paymentMethodForm.name_ar', 'نقدي')
            ->set('paymentMethodForm.name_en', 'Cash')
            ->set('paymentMethodForm.type', 'cash')
            ->set('paymentMethodForm.offline_eligible', true)
            ->call('savePaymentMethod')
            ->assertHasNoErrors()
            ->assertSee('QA-CASH')
            ->assertSee('Cash');

        $paymentMethod = PaymentMethod::query()->where('code', 'QA-CASH')->firstOrFail();

        self::assertSame('QA-CASH', $paymentMethod->fresh()->code);
        self::assertSame($auditCount + 1, AuditLog::query()->count());

        $unauthorizedUser = User::factory()->create()->fresh();
        $this->actingAs($unauthorizedUser);
        $beforeCount = PaymentMethod::query()->count();
        $auditCount = AuditLog::query()->count();

        try {
            $action->savePaymentMethod([
                'code' => 'qa-denied',
                'name_ar' => 'مرفوض',
                'name_en' => 'Denied',
                'type' => 'cash',
            ]);
            self::fail('A user without company_settings.edit saved a payment method.');
        } catch (AuthorizationException) {
            self::assertSame($beforeCount, PaymentMethod::query()->count());
            self::assertSame($auditCount, AuditLog::query()->count());
        }

        $this->actingAs($this->createSystemAdministrator());
        try {
            $action->savePaymentMethod([
                'code' => 'qa-offline-card',
                'name_ar' => 'بطاقة',
                'name_en' => 'Card',
                'type' => 'card',
                'offline_eligible' => true,
            ]);
            self::fail('A card payment method was incorrectly enabled for offline POS use.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('paymentMethodForm.offline_eligible', $exception->errors());
            self::assertSame($beforeCount, PaymentMethod::query()->count());
            self::assertSame($auditCount, AuditLog::query()->count());
        }
    }

    public function test_corrupt_pending_rate_cannot_be_approved_or_leave_partial_audit_effects(): void
    {
        $company = Company::factory()->create();
        $requester = $this->createSystemAdministrator();
        $approver = $this->createSystemAdministrator();
        $proposed = [
            'company_id' => $company->id,
            'code' => 'QA-CORRUPT-RATE',
            'name_ar' => 'اختبار ضريبة',
            'name_en' => 'QA tax rule',
            'rate' => 'not-a-number',
            'treatment' => 'standard',
            'is_default' => false,
            'is_tax_inclusive' => false,
            'status' => 'active',
        ];
        $context = ['resource' => 'tax_setting', 'id' => null, 'proposed' => $proposed, 'before' => null];
        $sourceHash = hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
        $approval = ApprovalRecord::query()->create([
            'source_type' => 'platform_settings',
            'source_id' => 'tax_setting:new:'.substr($sourceHash, 0, 16),
            'source_version' => 'new',
            'source_hash' => $sourceHash,
            'requested_action' => 'tax_setting',
            'request_permission' => 'company_settings.edit',
            'decision_permission' => 'company_settings.approve',
            'approval_state' => ApprovalState::Pending,
            'requester_id' => $requester->id,
            'limit_context' => $context,
            'requested_at' => now(),
            'idempotency_key' => 'qa-tax-corrupt-'.Str::uuid(),
        ]);
        $before = [
            'taxes' => TaxSetting::query()->forCompany($company->id)->count(),
            'audit' => AuditLog::query()->count(),
        ];

        $this->actingAs($approver);

        try {
            app(PlatformSettingsApprovalAction::class)->approve($approval);
            self::fail('A corrupt pending tax rate was approved and persisted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('taxSettingForm.rate', $exception->errors());
        }

        self::assertSame(ApprovalState::Pending, $approval->fresh()->approval_state);
        self::assertSame($before['taxes'], TaxSetting::query()->forCompany($company->id)->count());
        self::assertSame($before['audit'], AuditLog::query()->count());
    }

    /** @return array{approvals: int, audit: int, taxes: int} */
    private function taxSideEffectCounts(Company $company): array
    {
        return [
            'approvals' => ApprovalRecord::query()->count(),
            'audit' => AuditLog::query()->count(),
            'taxes' => TaxSetting::query()->forCompany($company->id)->count(),
        ];
    }

    private function createSystemAdministrator(): User
    {
        foreach (['company_settings.edit', 'company_settings.approve'] as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                ['module' => 'company_settings', 'action' => Str::afterLast($permissionCode, '.'), 'sensitivity' => 'sensitive', 'status' => 'active'],
            );
            $permission->forceFill(['status' => 'active'])->save();
        }

        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();
        $user = $user->fresh();
        self::assertSame('active', $user->status);
        self::assertTrue($user->is_super_admin);
        self::assertTrue($user->hasGlobalApplicationAccess());
        self::assertTrue($user->hasPermission('company_settings.edit'));

        return $user;
    }
}
