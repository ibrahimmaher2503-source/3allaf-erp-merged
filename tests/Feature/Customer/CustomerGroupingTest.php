<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Customer\Actions\CreateCustomerGroupAction;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class CustomerGroupingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_group_parent_must_belong_to_the_context_company(): void
    {
        $company = $this->company();
        $branch = $this->branch('GROUP-A');
        $store = $this->store($branch, 'GROUP-STORE');
        $otherCompany = Company::factory()->create();
        $foreignParent = CustomerGroup::query()->create([
            'company_id' => $otherCompany->id,
            'name_ar' => 'مجموعة أجنبية',
            'name_en' => 'Foreign group',
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $administrator = $this->administrator('customer-group-parent');

        $this->expectException(InvalidArgumentException::class);
        app(CreateCustomerGroupAction::class)->execute($administrator, $store, [
            'name_ar' => 'مجموعة محلية',
            'name_en' => 'Local group',
            'parent_id' => $foreignParent->id,
        ]);

        $this->assertDatabaseMissing('customer_groups', ['name_en' => 'Local group']);
        $this->assertSame($company->id, $store->company_id);
    }

    public function test_group_is_company_scoped_and_audited_when_created(): void
    {
        $branch = $this->branch('GROUP-B');
        $store = $this->store($branch, 'GROUP-STORE-B');
        $administrator = $this->administrator('customer-group-create');

        $group = app(CreateCustomerGroupAction::class)->execute($administrator, $store, [
            'name_ar' => 'مدارس',
            'name_en' => 'Schools',
        ]);

        $this->assertSame($store->company_id, $group->company_id);
        $this->assertNull($group->parent_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'customer_group_created',
            'source_id' => (string) $group->id,
        ]);
    }
}
