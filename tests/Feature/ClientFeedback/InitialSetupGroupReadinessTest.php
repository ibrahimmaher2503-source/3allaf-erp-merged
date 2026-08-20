<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InitialSetupGroupReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_or_inactive_groups_do_not_make_initial_setup_ready(): void
    {
        $company = Company::factory()->create(['code' => 'SETUP-GROUPS']);
        $foreignCompany = Company::factory()->create(['code' => 'SETUP-GROUPS-FOREIGN']);

        CustomerGroup::query()->create([
            'company_id' => $foreignCompany->id,
            'name_ar' => 'مجموعة أجنبية',
            'name_en' => 'Foreign customer group',
            'status' => 'active',
        ]);
        CustomerGroup::query()->create([
            'company_id' => $company->id,
            'name_ar' => 'مجموعة غير نشطة',
            'name_en' => 'Inactive customer group',
            'status' => 'inactive',
        ]);
        SupplierGroup::query()->create([
            'company_id' => $foreignCompany->id,
            'name_ar' => 'مجموعة مورد أجنبية',
            'name_en' => 'Foreign supplier group',
            'status' => 'active',
        ]);
        SupplierGroup::query()->create([
            'company_id' => $company->id,
            'name_ar' => 'مجموعة مورد غير نشطة',
            'name_en' => 'Inactive supplier group',
            'status' => 'inactive',
        ]);

        $steps = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->keyBy('key');

        self::assertFalse($steps['customer-groups']['complete']);
        self::assertSame(0, $steps['customer-groups']['records']);
        self::assertFalse($steps['supplier-groups']['complete']);
        self::assertSame(0, $steps['supplier-groups']['records']);
    }

    public function test_no_active_company_leaves_group_readiness_false_with_zero_records(): void
    {
        $company = Company::factory()->inactive()->create(['code' => 'SETUP-GROUPS-INACTIVE']);

        CustomerGroup::query()->create([
            'company_id' => $company->id,
            'name_ar' => 'مجموعة عميل نشطة',
            'name_en' => 'Active customer group',
            'status' => 'active',
        ]);
        SupplierGroup::query()->create([
            'company_id' => $company->id,
            'name_ar' => 'مجموعة مورد نشطة',
            'name_en' => 'Active supplier group',
            'status' => 'active',
        ]);

        $steps = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->keyBy('key');

        self::assertFalse($steps['customer-groups']['complete']);
        self::assertSame(0, $steps['customer-groups']['records']);
        self::assertFalse($steps['supplier-groups']['complete']);
        self::assertSame(0, $steps['supplier-groups']['records']);
    }
}
