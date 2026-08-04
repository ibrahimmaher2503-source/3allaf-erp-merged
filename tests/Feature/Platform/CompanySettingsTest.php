<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-005 — Company, payment, tax, numbering, and printer settings.
 *
 * @group tsk-005
 */
class CompanySettingsTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_settings_screen_is_permission_guarded(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');

        $this->actingAs($this->userWith('tsk005-none'));
        $this->get('/admin/settings')->assertForbidden();

        $this->actingAs($this->userWith('tsk005-reviewer', ['accountant-reviewer']));
        $this->get('/admin/settings')->assertForbidden();

        $this->actingAs($this->administrator('tsk005-admin'));
        $this->get('/admin/settings')->assertOk();
    }

    public function test_saving_company_identity_persists_and_records_exactly_one_audit_event(): void
    {
        $administrator = $this->administrator('tsk005-company');
        $this->actingAs($administrator);

        Livewire::test('platform::admin.settings')
            ->set('companyForm.code', 'TJ-001')
            ->set('companyForm.name_ar', 'توي آند جوي')
            ->set('companyForm.name_en', 'Toy and Joy')
            ->set('companyForm.currency_code', 'EGP')
            ->set('companyForm.currency_symbol', 'ج.م')
            ->set('companyForm.timezone', 'Africa/Cairo')
            ->set('companyForm.locale_default', 'ar')
            ->set('companyForm.status', 'active')
            ->call('saveCompany')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('companies', ['code' => 'TJ-001', 'currency_code' => 'EGP']);

        $events = AuditLog::query()->where('event', 'update_local_settings')->get();
        $this->assertCount(1, $events);
        $this->assertSame('master_data', $events[0]->category);
        $this->assertSame($administrator->id, $events[0]->actor_id);
        $this->assertSame($administrator->name, $events[0]->actor_name);
        $this->assertNotEmpty($events[0]->request_id);
        $this->assertSame(Company::class, $events[0]->source_type);
    }

    public function test_failed_company_validation_creates_no_record_and_no_audit_event(): void
    {
        $this->actingAs($this->administrator('tsk005-invalid'));

        Livewire::test('platform::admin.settings')
            ->set('companyForm.code', '')
            ->set('companyForm.currency_code', '')
            ->set('companyForm.email', 'not-an-email')
            ->set('companyForm.locale_default', 'fr')
            ->call('saveCompany')
            ->assertHasErrors([
                'companyForm.code' => 'required',
                'companyForm.currency_code' => 'required',
                'companyForm.email' => 'email',
                'companyForm.locale_default' => 'in',
            ]);

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unauthorized_settings_mutation_is_denied_and_writes_no_audit_event(): void
    {
        $this->actingAs($this->userWith('tsk005-denied', ['branch-manager']));

        Livewire::test('platform::admin.settings')->assertForbidden();

        try {
            app(SaveLocalSettingsAction::class)->execute(['company' => ['code' => 'HACK']]);
            $this->fail('An unauthorized settings write was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_payment_method_can_be_created_updated_and_deactivated_with_evidence_rules(): void
    {
        $this->actingAs($this->administrator('tsk005-payments'));

        $component = Livewire::test('platform::admin.settings')
            ->set('paymentMethodForm.code', 'CASH')
            ->set('paymentMethodForm.name_ar', 'نقدي')
            ->set('paymentMethodForm.name_en', 'Cash')
            ->set('paymentMethodForm.type', 'cash')
            ->set('paymentMethodForm.requires_evidence', false)
            ->set('paymentMethodForm.status', 'active')
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $this->assertFalse((bool) $method->requires_evidence);

        $component->call('editPaymentMethod', $method->id)
            ->set('paymentMethodForm.requires_evidence', true)
            ->set('paymentMethodForm.type', 'transfer')
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $method->fresh()->requires_evidence);
        $this->assertSame('transfer', $method->fresh()->type);

        $component->call('editPaymentMethod', $method->id)
            ->set('paymentMethodForm.status', 'inactive')
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $this->assertSame('inactive', $method->fresh()->status);
        $this->assertSame(1, PaymentMethod::query()->count());
        $this->assertSame(
            3,
            AuditLog::query()->whereIn('event', ['create_payment_method', 'update_payment_method'])->count(),
            'Each successful payment-method mutation must record exactly one audit event.',
        );
    }

    public function test_a_duplicate_payment_method_code_is_rejected(): void
    {
        $this->actingAs($this->administrator('tsk005-dup-payment'));

        $create = function (string $code) {
            return Livewire::test('platform::admin.settings')
                ->set('paymentMethodForm.code', $code)
                ->set('paymentMethodForm.name_ar', 'طريقة')
                ->set('paymentMethodForm.name_en', 'Method')
                ->set('paymentMethodForm.type', 'cash')
                ->set('paymentMethodForm.status', 'active')
                ->call('savePaymentMethod');
        };

        $create('CARD')->assertHasNoErrors();
        $create('CARD')->assertHasErrors(['paymentMethodForm.code' => 'unique']);

        $this->assertSame(1, PaymentMethod::query()->where('code', 'CARD')->count());
    }

    public function test_a_tax_setting_can_be_created_and_updated_with_bounded_rates(): void
    {
        $this->actingAs($this->administrator('tsk005-tax'));

        $component = Livewire::test('platform::admin.settings')
            ->set('taxSettingForm.code', 'VAT14')
            ->set('taxSettingForm.name_ar', 'ضريبة القيمة المضافة')
            ->set('taxSettingForm.name_en', 'Value Added Tax')
            ->set('taxSettingForm.rate', '14')
            ->set('taxSettingForm.status', 'active')
            ->call('saveTaxSetting')
            ->assertHasNoErrors();

        $tax = TaxSetting::query()->where('code', 'VAT14')->firstOrFail();
        $this->assertSame('14.00', (string) $tax->rate);

        $component->call('editTaxSetting', $tax->id)
            ->set('taxSettingForm.rate', '150')
            ->call('saveTaxSetting')
            ->assertHasErrors(['taxSettingForm.rate' => 'max']);

        $this->assertSame('14.00', (string) $tax->fresh()->rate);

        $component->call('editTaxSetting', $tax->id)
            ->set('taxSettingForm.rate', '10')
            ->call('saveTaxSetting')
            ->assertHasNoErrors();

        $this->assertSame('10.00', (string) $tax->fresh()->rate);
        $this->assertSame(1, AuditLog::query()->where('event', 'create_tax_setting')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'update_tax_setting')->count());
    }

    public function test_tax_effective_periods_are_stored_but_never_collected_or_validated(): void
    {
        // Recorded coverage fact for TSK-005: `tax_settings` carries
        // effective_from/effective_to columns, but no screen, action, or rule
        // collects them, so overlapping effective periods cannot be rejected.
        $this->assertTrue(Schema::hasColumn('tax_settings', 'effective_from'));
        $this->assertTrue(Schema::hasColumn('tax_settings', 'effective_to'));

        $this->actingAs($this->administrator('tsk005-effective'));

        $form = Livewire::test('platform::admin.settings')->get('taxSettingForm');

        $this->assertArrayNotHasKey('effective_from', $form);
        $this->assertArrayNotHasKey('effective_to', $form);

        app(SaveLocalSettingsAction::class)->saveTaxSetting([
            'code' => 'VAT-A', 'name_ar' => 'أ', 'name_en' => 'A', 'rate' => '14', 'status' => 'active',
        ]);
        app(SaveLocalSettingsAction::class)->saveTaxSetting([
            'code' => 'VAT-B', 'name_ar' => 'ب', 'name_en' => 'B', 'rate' => '10', 'status' => 'active',
        ]);

        // Two concurrently active tax settings are accepted today.
        $this->assertSame(2, TaxSetting::query()->where('status', 'active')->count());
    }

    public function test_a_document_sequence_type_is_unique_at_both_the_form_and_database_level(): void
    {
        $this->actingAs($this->administrator('tsk005-sequence'));

        $save = function (string $type) {
            return Livewire::test('platform::admin.settings')
                ->set('documentSequenceForm.document_type', $type)
                ->set('documentSequenceForm.prefix', 'INV')
                ->set('documentSequenceForm.padding_length', 6)
                ->set('documentSequenceForm.next_value', 1)
                ->set('documentSequenceForm.reset_rule', 'never')
                ->set('documentSequenceForm.status', 'active')
                ->call('saveDocumentSequence');
        };

        $save('pos_invoice')->assertHasNoErrors();
        $save('pos_invoice')->assertHasErrors(['documentSequenceForm.document_type' => 'unique']);

        $this->assertSame(1, DocumentSequence::query()->where('document_type', 'pos_invoice')->count());

        // The database constraint is the real guard against a concurrent writer
        // that bypasses the form rule.
        $this->expectException(QueryException::class);
        DocumentSequence::query()->create([
            'document_type' => 'pos_invoice',
            'padding_length' => 6,
            'next_value' => 1,
            'status' => 'active',
        ]);
    }

    public function test_document_sequence_numbers_are_configured_but_never_allocated(): void
    {
        // Recorded coverage fact for TSK-005/TSK-009: `lock_version` exists but
        // no transactional allocation path increments `next_value`, so
        // concurrent number allocation cannot be tested.
        $this->actingAs($this->administrator('tsk005-allocation'));

        $sequence = app(SaveLocalSettingsAction::class)->saveDocumentSequence([
            'document_type' => 'transfer_note',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
        ]);

        $this->assertSame(1, (int) $sequence->fresh()->next_value);
        $this->assertSame(1, (int) $sequence->fresh()->lock_version);
        $this->assertEmpty(
            array_filter(
                get_class_methods(SaveLocalSettingsAction::class),
                fn (string $method) => str_contains(strtolower($method), 'allocate') || str_contains(strtolower($method), 'nextnumber'),
            ),
        );
    }

    public function test_a_printer_configuration_can_be_created_and_updated(): void
    {
        $this->actingAs($this->administrator('tsk005-printer'));

        $component = Livewire::test('platform::admin.settings')
            ->set('printerForm.name', 'POS Thermal 1')
            ->set('printerForm.printer_type', 'thermal')
            ->set('printerForm.paper_size', '80mm')
            ->set('printerForm.template_name', 'default_thermal')
            ->set('printerForm.connection_type', 'network')
            ->set('printerForm.status', 'active')
            ->call('savePrinter')
            ->assertHasNoErrors();

        $printer = PrinterConfiguration::query()->where('name', 'POS Thermal 1')->firstOrFail();

        $component->call('editPrinter', $printer->id)
            ->set('printerForm.paper_size', 'a4')
            ->set('printerForm.printer_type', 'a4')
            ->call('savePrinter')
            ->assertHasNoErrors();

        $this->assertSame('a4', $printer->fresh()->paper_size);
        $this->assertSame(1, AuditLog::query()->where('event', 'create_printer_configuration')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'update_printer_configuration')->count());
    }

    public function test_historical_audit_rows_are_untouched_by_later_settings_changes(): void
    {
        $this->actingAs($this->administrator('tsk005-history'));

        app(SaveLocalSettingsAction::class)->savePaymentMethod([
            'code' => 'WALLET', 'name_ar' => 'محفظة', 'name_en' => 'Wallet', 'type' => 'manual', 'status' => 'active',
        ]);

        $original = AuditLog::query()->latest('id')->firstOrFail();
        $originalSnapshot = $original->getAttributes();

        $method = PaymentMethod::query()->where('code', 'WALLET')->firstOrFail();
        app(SaveLocalSettingsAction::class)->savePaymentMethod([
            'code' => 'WALLET', 'name_ar' => 'محفظة', 'name_en' => 'Wallet', 'type' => 'transfer', 'status' => 'inactive',
        ], $method->id);

        $this->assertSame($originalSnapshot, AuditLog::query()->whereKey($original->id)->firstOrFail()->getAttributes());
        $this->assertSame(2, AuditLog::query()->count());
    }
}
