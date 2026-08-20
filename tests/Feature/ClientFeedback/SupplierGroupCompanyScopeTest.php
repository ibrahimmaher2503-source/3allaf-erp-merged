<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Actions\SaveSupplierGroupAction;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class SupplierGroupCompanyScopeTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_supplier_group_parent_from_another_company_is_rejected(): void
    {
        $this->actingAs($this->administrator('supplier-group-scope-admin'));
        $company = $this->company();
        $foreign = Company::query()->create(['code' => 'FOREIGN-SG', 'name_ar' => 'شركة أجنبية', 'name_en' => 'Foreign company', 'currency_code' => 'EGP', 'currency_symbol' => 'EGP', 'timezone' => 'UTC', 'locale_default' => 'ar', 'status' => 'active', 'policy_notes' => 'Test only']);
        $parent = SupplierGroup::query()->create(['company_id' => $foreign->id, 'name_ar' => 'أب أجنبي', 'name_en' => 'Foreign parent', 'status' => 'active', 'lock_version' => 1]);

        $this->expectException(InvalidArgumentException::class);
        app(SaveSupplierGroupAction::class)->execute(['name_ar' => 'محاولة', 'name_en' => 'Attempt', 'parent_id' => $parent->id], (int) $company->id);
    }
}
