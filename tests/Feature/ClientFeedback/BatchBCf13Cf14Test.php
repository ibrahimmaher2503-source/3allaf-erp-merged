<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\OverrideDocumentSequenceCounter;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\TaxSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Batch B — CF-13/CF-14 focused financial configuration integrity coverage.
 *
 * Run only through the dedicated MariaDB phpunit profile for this cycle.
 */
final class BatchBCf13Cf14Test extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('cf13-cf14-admin'));
    }

    public function test_tax_defaults_and_zero_tax_treatments_are_explicit_and_unique(): void
    {
        $action = app(SaveLocalSettingsAction::class);

        $standard = $action->saveTaxSetting([
            'code' => 'VAT15',
            'name_ar' => 'ضريبة القيمة المضافة',
            'name_en' => 'Value Added Tax',
            'treatment' => 'standard',
            'rate' => '15',
            'is_tax_inclusive' => true,
            'is_default' => true,
            'status' => 'active',
        ]);
        $exempt = $action->saveTaxSetting([
            'code' => 'EXEMPT',
            'name_ar' => 'معفى',
            'name_en' => 'Exempt',
            'treatment' => 'exempt',
            'rate' => '0',
            'is_tax_inclusive' => true,
            'is_default' => false,
            'status' => 'active',
        ]);
        $outOfScope = $action->saveTaxSetting([
            'code' => 'OOS',
            'name_ar' => 'خارج النطاق',
            'name_en' => 'Out of Scope',
            'treatment' => 'out_of_scope',
            'rate' => '0',
            'is_tax_inclusive' => true,
            'is_default' => false,
            'status' => 'active',
        ]);

        $this->assertTrue((bool) $standard->is_default);
        $this->assertSame('exempt', $exempt->treatment);
        $this->assertSame('out_of_scope', $outOfScope->treatment);
        $this->assertSame('0.00', (string) $exempt->rate);
        $this->assertSame('0.00', (string) $outOfScope->rate);
        $this->assertTrue((bool) $standard->is_tax_inclusive);

        $this->expectException(ValidationException::class);
        $action->saveTaxSetting([
            'code' => 'BAD-EXEMPT',
            'name_ar' => 'خطأ',
            'name_en' => 'Invalid exempt',
            'treatment' => 'exempt',
            'rate' => '5',
            'is_default' => false,
            'status' => 'active',
        ]);
    }

    public function test_selecting_a_new_company_default_demotes_the_previous_default(): void
    {
        $action = app(SaveLocalSettingsAction::class);
        $action->saveTaxSetting([
            'code' => 'VAT15', 'name_ar' => 'ضريبة', 'name_en' => 'VAT',
            'treatment' => 'standard', 'rate' => '15', 'is_tax_inclusive' => true,
            'is_default' => true, 'status' => 'active',
        ]);

        $zeroRated = $action->saveTaxSetting([
            'code' => 'ZERO', 'name_ar' => 'صفرية', 'name_en' => 'Zero Rated',
            'treatment' => 'zero_rated', 'rate' => '0', 'is_tax_inclusive' => true,
            'is_default' => true, 'status' => 'active',
        ]);

        $this->assertSame(1, TaxSetting::query()->where('status', 'active')->where('is_default', true)->count());
        $this->assertFalse((bool) TaxSetting::query()->where('code', 'VAT15')->value('is_default'));
        $this->assertTrue((bool) $zeroRated->fresh()->is_default);
    }

    public function test_daily_reset_and_company_branch_sequences_are_persistent_and_independent(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $branch = Branch::query()->create([
            'code' => 'CF14-BRANCH',
            'name_ar' => 'فرع الاختبار',
            'name_en' => 'CF14 Branch',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);

        DocumentSequence::query()->create([
            'document_type' => 'daily_demo', 'scope_type' => 'company', 'scope_id' => null,
            'prefix' => 'C-', 'suffix' => null, 'padding_length' => 6, 'next_value' => 9,
            'reset_rule' => 'daily', 'last_reset_period' => '2026-08-18', 'status' => 'active', 'lock_version' => 1,
        ]);
        DocumentSequence::query()->create([
            'document_type' => 'daily_demo', 'scope_type' => 'branch', 'scope_id' => $branch->id,
            'prefix' => 'B-', 'suffix' => null, 'padding_length' => 4, 'next_value' => 3,
            'reset_rule' => 'daily', 'last_reset_period' => '2026-08-18', 'status' => 'active', 'lock_version' => 1,
        ]);

        $allocator = app(AllocateDocumentNumber::class);
        $this->assertSame('C-000001', $allocator->execute('daily_demo', 'company'));
        $this->assertSame('C-000002', $allocator->execute('daily_demo', 'company'));
        $this->assertSame('B-0001', $allocator->execute('daily_demo', 'branch', $branch->id));

        $company = DocumentSequence::query()->where('scope_key', 'company')->where('document_type', 'daily_demo')->firstOrFail();
        $branchSequence = DocumentSequence::query()->where('scope_key', 'branch:'.$branch->id)->firstOrFail();
        $this->assertSame('2026-08-19', $company->last_reset_period);
        $this->assertSame(3, (int) $company->next_value);
        $this->assertSame(2, (int) $branchSequence->next_value);
    }

    public function test_normal_edits_cannot_change_counter_and_stale_override_is_rejected(): void
    {
        $action = app(SaveLocalSettingsAction::class);
        $sequence = $action->saveDocumentSequence([
            'document_type' => 'override_demo', 'scope_type' => 'company', 'scope_id' => null,
            'prefix' => 'OV-', 'padding_length' => 4, 'next_value' => 10,
            'reset_rule' => 'never', 'status' => 'active',
        ]);

        $action->saveDocumentSequence([
            'document_type' => 'override_demo', 'scope_type' => 'company', 'scope_id' => null,
            'prefix' => 'NEW-', 'padding_length' => 5, 'next_value' => 999,
            'reset_rule' => 'never', 'status' => 'active',
        ], $sequence->id);
        $this->assertSame(10, (int) $sequence->fresh()->next_value);

        $updated = app(OverrideDocumentSequenceCounter::class)->execute($sequence->fresh(), 25, 1, 'Approved CF14 correction.');
        $this->assertSame(25, (int) $updated->next_value);

        $this->expectException(ValidationException::class);
        app(OverrideDocumentSequenceCounter::class)->execute($updated->fresh(), 30, 1, 'Stale override must fail.');
    }

    public function test_settings_tables_explain_tax_treatment_default_and_sequence_branch_scope(): void
    {
        $action = app(SaveLocalSettingsAction::class);
        $action->saveTaxSetting([
            'code' => 'CF14-VAT',
            'name_ar' => 'ضريبة قياسية',
            'name_en' => 'CF14 Standard VAT',
            'treatment' => 'standard',
            'rate' => '15',
            'is_tax_inclusive' => true,
            'is_default' => true,
            'status' => 'active',
        ]);

        $branch = Branch::query()->create([
            'code' => 'CF14-LABEL',
            'name_ar' => 'فرع تسمية النطاق',
            'name_en' => 'CF14 Scope Label Branch',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);

        DocumentSequence::query()->create([
            'document_type' => 'cf14_scope_label',
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'prefix' => 'CF-',
            'padding_length' => 4,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
        ]);

        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'tax')
            ->assertSee('Standard')
            ->assertSee('Default');

        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'sequences')
            ->assertSee('CF14 Scope Label Branch')
            ->assertDontSee('Branch: '.$branch->id);

        $indexNames = collect(Schema::getIndexes('document_sequences'))->pluck('name')->all();
        self::assertContains('document_sequences_document_scope_unique', $indexNames);
        self::assertNotContains('document_sequences_document_type_unique', $indexNames);
    }
}
