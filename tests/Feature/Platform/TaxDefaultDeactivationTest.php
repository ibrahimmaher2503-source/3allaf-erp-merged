<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class TaxDefaultDeactivationTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('tax-default-deactivation'));
    }

    public function test_deactivating_the_only_active_default_tax_rule_is_rejected_without_mutation(): void
    {
        $action = app(SaveLocalSettingsAction::class);
        $default = $action->saveTaxSetting([
            'code' => 'VAT15',
            'name_ar' => 'ضريبة القيمة المضافة',
            'name_en' => 'Value added tax',
            'treatment' => 'standard',
            'rate' => '15',
            'is_default' => true,
            'status' => 'active',
        ]);

        try {
            $action->saveTaxSetting([
                'code' => 'VAT15',
                'name_ar' => 'ضريبة القيمة المضافة',
                'name_en' => 'Value added tax',
                'treatment' => 'standard',
                'rate' => '15',
                'is_default' => false,
                'status' => 'inactive',
            ], $default->id);
            self::fail('The only active default tax rule was deactivated.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('taxSettingForm.status', $exception->errors());
        }

        self::assertSame('active', $default->fresh()->status);
        self::assertTrue((bool) $default->fresh()->is_default);
    }
}
