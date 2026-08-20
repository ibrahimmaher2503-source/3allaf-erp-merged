<?php

use App\Modules\Customer\Actions\StageCustomerImportAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Platform\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CustomerImportPhaseCTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_import_route_name_is_reserved(): void
    {
        $this->assertSame('/customers/import', parse_url(route('customers.import'), PHP_URL_PATH));
    }

    public function test_customer_import_workspace_renders_inside_the_authenticated_arabic_app_shell(): void
    {
        app()->setLocale('ar');
        $this->actingAs(User::query()->where('is_super_admin', true)->firstOrFail());

        $this->get(route('customers.import'))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('<ui-sidebar', false)
            ->assertSee(__('Customer Import'));
    }

    public function test_xlsx_customer_template_is_read_without_writing_customers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StageCustomerImportAction::FIELDS));
        $writer->addRow(Row::fromValues(['أحمد', 'علي', 'Ahmed', 'Ali', '01012345678', 'ahmed@example.test', '', 'registration', 'granted']));
        $writer->close();

        try {
            $this->assertSame([[
                'first_name_ar' => 'أحمد', 'last_name_ar' => 'علي', 'first_name_en' => 'Ahmed', 'last_name_en' => 'Ali',
                'phone' => '01012345678', 'email' => 'ahmed@example.test', 'customer_group' => '', 'consent_purpose' => 'registration', 'consent_status' => 'granted',
            ]], StageCustomerImportAction::readSpreadsheet($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_short_spreadsheet_rows_are_padded_to_the_template_width(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StageCustomerImportAction::FIELDS));
        $writer->addRow(Row::fromValues(['أحمد', 'علي', 'Ahmed', 'Ali', '01012345678']));
        $writer->close();

        try {
            $this->assertSame('', StageCustomerImportAction::readSpreadsheet($path)[0]['consent_status']);
        } finally {
            @unlink($path);
        }
    }

    public function test_international_phone_value_starting_with_plus_is_not_treated_as_a_formula(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StageCustomerImportAction::FIELDS));
        $writer->addRow(Row::fromValues(['أحمد', 'علي', '', '', '+20 1012345678', '', '', 'registration', 'granted']));
        $writer->close();

        try {
            $this->assertSame('+20 1012345678', StageCustomerImportAction::readSpreadsheet($path)[0]['phone']);
        } finally {
            @unlink($path);
        }
    }

    public function test_same_normalized_phone_twice_in_a_workbook_is_rejected_during_staging(): void
    {
        $rows = StageCustomerImportAction::validateRows([
            ['first_name_ar' => 'أحمد', 'last_name_ar' => 'علي', 'first_name_en' => '', 'last_name_en' => '', 'phone' => '01012345678', 'email' => '', 'customer_group' => '', 'consent_purpose' => 'registration', 'consent_status' => 'granted'],
            ['first_name_ar' => 'منى', 'last_name_ar' => 'علي', 'first_name_en' => '', 'last_name_en' => '', 'phone' => '+20 1012345678', 'email' => '', 'customer_group' => '', 'consent_purpose' => 'registration', 'consent_status' => 'granted'],
        ]);

        $this->assertSame(['Duplicate phone in this import batch'], $rows[1]['errors']);
    }

    public function test_a_customer_group_value_is_rejected_until_groups_have_stable_import_codes(): void
    {
        $rows = StageCustomerImportAction::validateRows([[
            'first_name_ar' => 'أحمد', 'last_name_ar' => 'علي', 'first_name_en' => '', 'last_name_en' => '', 'phone' => '01012345678', 'email' => '', 'customer_group' => 'VIP', 'consent_purpose' => 'registration', 'consent_status' => 'granted',
        ]]);

        $this->assertSame(['Customer group imports require a configured stable group code.'], $rows[0]['errors']);
    }

    public function test_active_customer_group_name_is_resolved_within_the_import_store_company(): void
    {
        $store = Store::query()->where('status', 'active')->firstOrFail();
        $name = 'QA Group '.Str::uuid();
        $group = CustomerGroup::query()->create(['company_id' => $store->company_id, 'name_ar' => $name, 'name_en' => $name, 'status' => 'active', 'lock_version' => 1]);

        $rows = StageCustomerImportAction::validateRows([[
            'first_name_ar' => 'أحمد', 'last_name_ar' => 'علي', 'first_name_en' => '', 'last_name_en' => '', 'phone' => '01012345678', 'email' => '', 'customer_group' => $name, 'consent_purpose' => 'registration', 'consent_status' => 'granted',
        ]], 'create_only', $store);

        $this->assertSame($group->id, $rows[0]['raw']['customer_group_id']);
        $this->assertSame([], $rows[0]['errors']);
    }

    public function test_update_existing_accepts_the_matching_normalized_phone(): void
    {
        $store = Store::query()->where('status', 'active')->firstOrFail();
        $phone = '010'.random_int(10000000, 99999999);
        $customer = Customer::query()->create(['phone_normalized' => $phone, 'phone_display' => $phone, 'first_name_ar' => 'قديم', 'last_name_ar' => 'عميل', 'name_ar' => 'قديم عميل', 'name_en' => 'Old Customer', 'status' => 'active', 'idempotency_key' => 'qa-customer-import-'.Str::uuid(), 'lock_version' => 1]);

        $rows = StageCustomerImportAction::validateRows([[
            'first_name_ar' => 'جديد', 'last_name_ar' => 'عميل', 'first_name_en' => 'New', 'last_name_en' => 'Customer', 'phone' => '+20 '.substr($phone, 1), 'email' => '', 'customer_group' => '', 'consent_purpose' => 'registration', 'consent_status' => 'granted',
        ]], 'update_existing', $store);

        $this->assertSame([], $rows[0]['errors']);
        $this->assertSame($customer->id, $rows[0]['raw']['customer_id']);
    }
}
