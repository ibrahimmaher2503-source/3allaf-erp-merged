<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * CR-002 characterization of the company identity UI -> Livewire -> action -> model path.
 *
 * Every test names the production mutation it would catch in its assertion message.
 */
final class CompanyIdentityPersistenceTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_review_action_only_previews_and_never_writes_or_audits(): void
    {
        $this->actingAs($this->administrator('cr002-preview'));

        // Catches a production mutation that makes preview write company rows or audit events.
        Livewire::test('platform::admin.settings')
            ->set('companyForm', $this->identity('CR002-PREVIEW'))
            ->assertSet('companyDirty', true)
            ->call('previewCompany')
            ->assertHasNoErrors()
            ->assertSet('showCompanyPreview', true);

        self::assertSame(0, Company::query()->count(), 'Preview must not create or update a company.');
        self::assertSame(0, AuditLog::query()->count(), 'Preview must not claim an auditable configuration change.');
    }

    public function test_confirmation_persists_every_identity_field_to_the_exact_row_with_one_audit_and_fresh_hydration(): void
    {
        $administrator = $this->administrator('cr002-confirm');
        $company = Company::query()->create($this->identity('CR002-ORIGINAL'));
        $identity = $this->identity('CR002-CONFIRMED');
        $this->actingAs($administrator);

        // Catches a production mutation that drops a submitted identity field, writes a different row, or double-audits.
        Livewire::test('platform::admin.settings')
            ->set('companyForm', $identity)
            ->assertSet('companyDirty', true)
            ->call('previewCompany')
            ->assertSet('showCompanyPreview', true)
            ->call('saveCompany')
            ->assertHasNoErrors()
            ->assertSet('showCompanyPreview', false)
            ->assertSet('companyDirty', false);

        $persisted = $company->fresh();
        foreach ($identity as $field => $value) {
            self::assertSame($value, $persisted->getAttribute($field), "Confirmation must persist company [{$field}] on the selected row.");
        }
        self::assertSame(1, Company::query()->count(), 'Confirmation must update the existing global company rather than create a second row.');

        $audit = AuditLog::query()->where('event', 'update_local_settings')->sole();
        self::assertSame(Company::class, $audit->source_type);
        self::assertSame((string) $company->id, $audit->source_id);
        self::assertSame($administrator->id, $audit->actor_id);

        $freshComponent = Livewire::test('platform::admin.settings');
        foreach ($identity as $field => $value) {
            self::assertSame($value, $freshComponent->get("companyForm.{$field}"), "A new component mount must hydrate persisted [{$field}].");
        }
    }

    public function test_confirmed_identity_survives_a_real_logout_login_and_page_reload(): void
    {
        $administrator = $this->userWith('cr002-relogin', ['system-administrator'], true, password: 'CR002-only-password');
        $identity = $this->identity('CR002-RELOGIN');
        $this->actingAs($administrator);

        // Catches a production mutation that stores identity only in Livewire/session state rather than MariaDB.
        Livewire::test('platform::admin.settings')
            ->set('companyForm', $identity)
            ->call('saveCompany')
            ->assertHasNoErrors();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['username' => $administrator->username, 'password' => 'CR002-only-password'])
            ->assertRedirect(config('fortify.home'));
        $this->assertAuthenticatedAs($administrator->fresh());
        $this->get('/admin/settings')->assertOk();

        $reloaded = Livewire::test('platform::admin.settings');
        foreach ($identity as $field => $value) {
            $expected = $field === 'phone' ? PhoneNormalizer::normalize((string) $value) : $value;
            self::assertSame($expected, $reloaded->get("companyForm.{$field}"), "Reload after login must retain [{$field}].");
        }
    }

    public function test_validation_failure_leaves_the_existing_company_and_audit_log_untouched(): void
    {
        $this->actingAs($this->administrator('cr002-validation'));
        $company = Company::query()->create($this->identity('CR002-VALID-ORIGINAL'));
        $before = $company->only(array_keys($this->identity('CR002-VALID-ORIGINAL')));

        // Catches a production mutation that partially writes invalid company data or emits a success audit.
        Livewire::test('platform::admin.settings')
            ->set('companyForm', array_replace($this->identity('CR002-INVALID'), [
                'code' => '',
                'currency_code' => '',
                'email' => 'not-an-email',
                'locale_default' => 'fr',
            ]))
            ->call('saveCompany')
            ->assertHasErrors([
                'companyForm.code' => 'required',
                'companyForm.currency_code' => 'required',
                'companyForm.email' => 'email',
                'companyForm.locale_default' => 'in',
            ]);

        self::assertSame($before, $company->fresh()->only(array_keys($before)), 'Invalid input must leave every persisted company attribute unchanged.');
        self::assertSame(0, AuditLog::query()->count(), 'Invalid input must not produce an audit log.');
    }

    public function test_an_unauthorized_direct_action_is_denied_without_company_or_audit_mutation(): void
    {
        $company = Company::query()->create($this->identity('CR002-DENIED-ORIGINAL'));
        $before = $company->only(array_keys($this->identity('CR002-DENIED-ORIGINAL')));
        $this->actingAs($this->userWith('cr002-denied', ['branch-manager']));

        // Catches a production mutation that permits a forged direct service call to change company identity.
        try {
            app(SaveLocalSettingsAction::class)->execute(['company' => $this->identity('CR002-FORGED')]);
            self::fail('An unauthorized direct company-identity write was accepted.');
        } catch (AuthorizationException) {
            self::addToAssertionCount(1);
        }

        self::assertSame($before, $company->fresh()->only(array_keys($before)), 'Denied direct mutation must not alter the company row.');
        self::assertSame(0, AuditLog::query()->count(), 'Denied direct mutation must not create an audit log.');
    }

    public function test_the_action_rejects_ambiguous_multiple_company_rows_without_selecting_one_by_first(): void
    {
        $this->actingAs($this->administrator('cr002-singleton'));
        $first = Company::query()->create($this->identity('CR002-FIRST'));
        $second = Company::query()->create($this->identity('CR002-SECOND'));
        $firstBefore = $first->only(array_keys($this->identity('CR002-FIRST')));
        $secondBefore = $second->only(array_keys($this->identity('CR002-SECOND')));

        // Catches a production mutation that would select an arbitrary Company::first() row when the invariant is broken.
        try {
            app(SaveLocalSettingsAction::class)->execute(['company' => $this->identity('CR002-AMBIGUOUS')]);
            self::fail('Ambiguous company identity persistence selected a row instead of rejecting the broken global-company invariant.');
        } catch (ValidationException $exception) {
            self::assertSame(
                __('company.duplicate_save'),
                $exception->errors()['companyForm.code'][0],
                'The duplicate-company invariant must surface as a localized company-form error rather than a raw exception.',
            );
        }

        self::assertSame($firstBefore, $first->fresh()->only(array_keys($firstBefore)), 'An ambiguous global company scope must not update the first row.');
        self::assertSame($secondBefore, $second->fresh()->only(array_keys($secondBefore)), 'An ambiguous global company scope must not update the second row.');
        self::assertSame(0, AuditLog::query()->count(), 'An ambiguous global company scope must not be audited as a successful write.');
    }

    public function test_an_authorized_user_sees_a_localized_blocked_company_editor_when_duplicate_rows_exist(): void
    {
        $this->actingAs($this->administrator('cr002-duplicate-load'));
        Company::query()->create($this->identity('CR002-DUPLICATE-ONE'));
        Company::query()->create($this->identity('CR002-DUPLICATE-TWO'));

        // Catches a production mutation that crashes settings load or silently selects one duplicate company.
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee(__('company.duplicate_load'));

        Livewire::test('platform::admin.settings')
            ->assertSet('companyEditingBlocked', true)
            ->assertSee(__('company.duplicate_load'))
            ->assertDontSee(__('Company Master Information'))
            ->assertDontSee(__('company.review_changes'));

        self::assertSame(2, Company::query()->count(), 'Duplicate detection must not delete or merge company rows.');
        self::assertSame(0, AuditLog::query()->count(), 'Duplicate detection during load must not create an audit event.');
    }

    /** @return array<string, string> */
    private function identity(string $code): array
    {
        return [
            'code' => $code,
            'name_ar' => 'شركة ألعاب وفرح '.$code,
            'name_en' => 'Toy & Joy '.$code,
            'legal_name' => 'Toy & Joy Retail Company '.$code,
            'tax_number' => '300000000000003',
            'commercial_registration' => '1010000000',
            'currency_code' => 'EGP',
            'currency_symbol' => 'ج.م',
            'timezone' => 'Africa/Cairo',
            'locale_default' => 'ar',
            'phone' => '+201001234567',
            'email' => strtolower($code).'@toyjoy.test',
            'address' => '12 Example Street, Cairo, Egypt',
            'status' => 'active',
            'policy_notes' => 'CR-002 disposable characterization fixture.',
        ];
    }
}
