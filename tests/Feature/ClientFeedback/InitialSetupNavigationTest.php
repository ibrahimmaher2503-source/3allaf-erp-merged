<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class InitialSetupNavigationTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    public function test_each_setup_area_has_a_distinct_canonical_destination_contract(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $steps = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->keyBy('key');
        $destinations = $steps->map(fn (array $step): string => $this->canonicalDestination($step));

        self::assertCount($steps->count(), $destinations->unique(), 'Two setup areas must not open the same canonical destination.');
        self::assertSame(route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']), $steps['printers']['route']);
        self::assertSame(route('admin.settings', ['tab' => 'printers', 'section' => 'print-templates']), $steps['print-templates']['route']);
        self::assertSame(route('catalog.suppliers', ['section' => 'supplier-groups']), $steps['supplier-groups']['route']);
        self::assertSame(route('suppliers.index', ['section' => 'supplier-masters']), $steps['suppliers']['route']);
        self::assertSame(route('customers.index'), $steps['customers']['route']);
        self::assertSame(route('party.readiness'), $steps['party-readiness']['route']);
        self::assertSame(route('catalog.brands'), $steps['brands']['route']);
        self::assertSame(route('admin.branches', ['section' => 'selling-store-mapping']), $steps['pos-selling-location']['route']);
        self::assertSame(route('catalog.products.import'), $steps['product-import']['route']);
        self::assertArrayNotHasKey('customers-party', $steps->all());

        $this->get(route('initial-setup'))
            ->assertOk()
            ->assertSee('data-setup-section="foundation"', false)
            ->assertSee('data-setup-section="configuration"', false)
            ->assertSee('data-setup-section="master-data"', false)
            ->assertSee('data-setup-destination="printer-profiles"', false)
            ->assertSee('data-setup-destination="print-templates"', false)
            ->assertSee('data-setup-destination="supplier-groups"', false)
            ->assertSee('data-setup-destination="supplier-masters"', false)
            ->assertSee('data-setup-destination="party-readiness"', false);
    }

    public function test_context_destinations_render_the_area_named_by_the_setup_card(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $this->get(route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']))
            ->assertOk()
            ->assertSee('data-settings-active-section="printer-profiles"', false)
            ->assertDontSee('data-settings-active-section="print-templates"', false);
        $this->get(route('admin.settings', ['tab' => 'printers', 'section' => 'print-templates']))
            ->assertOk()
            ->assertSee('data-settings-active-section="print-templates"', false)
            ->assertDontSee('data-settings-active-section="printer-profiles"', false);
        $this->get(route('catalog.suppliers', ['section' => 'supplier-groups']))
            ->assertOk()
            ->assertSee('data-supplier-workspace="supplier-groups"', false)
            ->assertDontSee('data-supplier-workspace="supplier-masters"', false)
            ->assertSee('data-guide="supplier-groups-workspace"', false)
            ->assertDontSee('data-guide="suppliers-table"', false);
        $this->get(route('suppliers.index', ['section' => 'supplier-masters']))
            ->assertOk()
            ->assertSee('data-supplier-workspace="supplier-masters"', false)
            ->assertDontSee('data-supplier-workspace="supplier-groups"', false)
            ->assertSee('data-guide="suppliers-table"', false)
            ->assertDontSee('data-guide="supplier-groups-workspace"', false);
        $this->get(route('admin.branches', ['section' => 'selling-store-mapping']))
            ->assertOk()
            ->assertSee('data-branch-section="selling-store-mapping"', false)
            ->assertDontSee('data-branch-section="branch-masters"', false);
        $this->get(route('inventory.adjustments.create'))
            ->assertOk()
            ->assertSee('data-inventory-intent="opening-inventory"', false)
            ->assertSee('Opening inventory entry');
    }

    public function test_branch_master_and_selling_store_mapping_workspaces_are_mutually_exclusive(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $this->get(route('admin.branches', ['section' => 'selling-store-mapping']))
            ->assertOk()
            ->assertSee('data-branch-section="selling-store-mapping"', false)
            ->assertSee('data-guide="selling-store-mapping-workspace"', false)
            ->assertSee('data-guide="selling-store-mapping-table"', false)
            ->assertSee(route('admin.branches', ['section' => 'branch-masters']), false)
            ->assertDontSee('data-guide="branch-masters-workspace"', false)
            ->assertDontSee('data-guide="branch-masters-table"', false)
            ->assertDontSee('data-guide="branch-masters-actions"', false)
            ->assertDontSee('data-guide="branch-master-form"', false);

        $this->get(route('admin.branches'))
            ->assertOk()
            ->assertSee('data-branch-section="branch-masters"', false)
            ->assertSee('data-guide="branch-masters-workspace"', false)
            ->assertSee('data-guide="branch-masters-table"', false)
            ->assertSee('data-guide="branch-masters-actions"', false)
            ->assertSee('data-guide="branch-master-form"', false)
            ->assertSee(route('admin.branches', ['section' => 'selling-store-mapping']), false)
            ->assertDontSee('data-guide="selling-store-mapping-workspace"', false)
            ->assertDontSee('data-guide="selling-store-mapping-table"', false);
    }

    public function test_print_template_assignments_offer_only_the_top_level_printer_profile_management_link(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());
        PrinterConfiguration::query()->create([
            'name' => 'Assignment-only printer',
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'connection_type' => 'network',
            'status' => 'active',
        ]);

        $printerProfilesRoute = e(route('admin.settings', ['tab' => 'printers', 'section' => 'printer-profiles']));

        $this->get(route('admin.settings', ['tab' => 'printers', 'section' => 'print-templates']))
            ->assertOk()
            ->assertSee('Manage printer profiles')
            ->assertSee('href="'.$printerProfilesRoute.'"', false)
            ->assertDontSee('Edit profile')
            ->assertDontSee('wire:click="editPrinter(', false);
    }

    public function test_supplier_masters_do_not_offer_group_edits_while_the_group_workspace_does(): void
    {
        $this->seedCanonicalAuthorization();
        $administrator = $this->administrator();
        $this->actingAs($administrator);
        SupplierGroup::query()->create([
            'company_id' => $this->company()->id,
            'name_ar' => 'مجموعة اختبار',
            'name_en' => 'Test group',
            'status' => 'active',
            'created_by' => $administrator->id,
            'updated_by' => $administrator->id,
        ]);

        $this->get(route('suppliers.index', ['section' => 'supplier-masters']))
            ->assertOk()
            ->assertDontSee('wire:click="openEditSupplierGroupModal(', false);
        $this->get(route('catalog.suppliers', ['section' => 'supplier-groups']))
            ->assertOk()
            ->assertSee('wire:click="openEditSupplierGroupModal(', false);
    }

    public function test_optional_product_import_is_ready_without_losing_its_guidance(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $step = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->firstWhere('key', 'product-import');

        self::assertFalse($step['required']);
        self::assertTrue($step['complete']);
        self::assertSame('ready', $step['status']);
        self::assertSame('Manual entry is sufficient; import is optional and never a setup prerequisite.', $step['reason']);
    }

    public function test_initial_setup_grouping_and_guidance_are_translated_in_arabic(): void
    {
        app()->setLocale('ar');

        foreach ([
            'Foundation' => 'الأساسيات',
            'Set the company context and the places where work happens.' => 'حدّد بيانات الشركة والمواقع التي يتم فيها العمل.',
            'Configuration' => 'الإعدادات',
            'Save the financial, numbering, printer-profile, and template-assignment rules used by operations.' => 'احفظ قواعد الدفع والضرائب والترقيم وملفات الطابعات وتعيين القوالب التي تستخدمها العمليات.',
            'Master data' => 'البيانات الأساسية',
            'Prepare catalog definitions, customers, suppliers, products, prices, and opening inventory in dependency order.' => 'جهّز تعريفات الكتالوج والعملاء والموردين والمنتجات والأسعار والمخزون الافتتاحي حسب ترتيب الاعتماديات.',
            'areas' => 'مجالات',
            'Switch to English' => 'English',
            'Follow the sections in order. Each action opens the internal screen that owns the data, and returning here refreshes the readiness status.' => 'اتبع الأقسام بالترتيب. يفتح كل إجراء الشاشة الداخلية المسؤولة عن البيانات، وعند العودة يتم تحديث حالة الجاهزية.',
            'A saved row is counted only when the current readiness rule is met. Financial approvals, production devices, and owner/UAT decisions remain separate gates.' => 'لا يُحتسب السجل المحفوظ إلا عند استيفاء قاعدة الجاهزية الحالية. تظل الاعتمادات المالية وأجهزة الإنتاج وقرارات المالك واختبار القبول بوابات منفصلة.',
        ] as $source => $expected) {
            self::assertSame($expected, __($source));
        }
    }

    public function test_pos_readiness_requires_one_current_selling_mapping_per_active_retail_branch(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $mappedRetailBranch = $this->branch('POS-MAPPED');
        $mappedSellingStore = $this->store($mappedRetailBranch, 'POS-MAPPED-SELL');
        BranchSellingStore::query()->create([
            'branch_id' => $mappedRetailBranch->id,
            'store_id' => $mappedSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        $unmappedRetailBranch = $this->branch('POS-UNMAPPED');
        $unmappedSellingStore = $this->store($unmappedRetailBranch, 'POS-UNMAPPED-SELL');
        $warehouseOnlyBranch = $this->branch('WAREHOUSE-ONLY');
        $this->store($warehouseOnlyBranch, 'WAREHOUSE-ONLY-STORE', 'warehouse');

        self::assertFalse($this->setupStep('branches-stores')['complete']);
        self::assertFalse($this->setupStep('pos-selling-location')['complete']);

        BranchSellingStore::query()->create([
            'branch_id' => $unmappedRetailBranch->id,
            'store_id' => $unmappedSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        self::assertTrue($this->setupStep('branches-stores')['complete']);
        self::assertTrue($this->setupStep('pos-selling-location')['complete']);

        $extraSellingStore = $this->store($unmappedRetailBranch, 'POS-UNMAPPED-SELL-2');
        BranchSellingStore::query()->create([
            'branch_id' => $unmappedRetailBranch->id,
            'store_id' => $extraSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        self::assertFalse($this->setupStep('branches-stores')['complete']);
        self::assertFalse($this->setupStep('pos-selling-location')['complete']);
    }

    public function test_pos_readiness_is_incomplete_when_no_active_selling_branch_is_applicable(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        Store::query()->where('type', 'selling')->update(['status' => 'inactive']);
        $warehouseOnlyBranch = $this->branch('POS-NO-SELLING');
        $this->store($warehouseOnlyBranch, 'POS-NO-SELLING-WAREHOUSE', 'warehouse');

        $step = $this->setupStep('pos-selling-location');

        self::assertFalse(BranchSellingStore::query()
            ->whereHas('branch.stores', fn ($query) => $query->where('status', 'active')->where('type', 'selling'))
            ->exists());
        self::assertFalse($step['complete']);
    }

    public function test_branches_and_stores_readiness_uses_the_same_one_current_selling_mapping_rule_as_pos(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $mappedRetailBranch = $this->branch('SETUP-MAPPED');
        $mappedSellingStore = $this->store($mappedRetailBranch, 'SETUP-MAPPED-SELL');
        BranchSellingStore::query()->create([
            'branch_id' => $mappedRetailBranch->id,
            'store_id' => $mappedSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        $unmappedRetailBranch = $this->branch('SETUP-UNMAPPED');
        $unmappedSellingStore = $this->store($unmappedRetailBranch, 'SETUP-UNMAPPED-SELL');

        self::assertFalse($this->setupStep('branches-stores')['complete']);

        BranchSellingStore::query()->create([
            'branch_id' => $unmappedRetailBranch->id,
            'store_id' => $unmappedSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        self::assertTrue($this->setupStep('branches-stores')['complete']);

        $extraSellingStore = $this->store($unmappedRetailBranch, 'SETUP-UNMAPPED-SELL-2');
        BranchSellingStore::query()->create([
            'branch_id' => $unmappedRetailBranch->id,
            'store_id' => $extraSellingStore->id,
            'status' => 'active',
            'effective_from' => now()->subDay(),
        ]);

        self::assertFalse($this->setupStep('branches-stores')['complete']);
    }

    public function test_catalog_readiness_allows_an_unbranded_sellable_product(): void
    {
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator());

        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'brand_id' => null]);

        $steps = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->keyBy('key');

        self::assertFalse($steps['brands']['required']);
        self::assertTrue($steps['product-masters']['complete']);
    }

    public function test_customer_readiness_uses_consent_policy_configuration_not_customer_records(): void
    {
        $this->seedCanonicalAuthorization();
        $administrator = $this->administrator();
        $this->actingAs($administrator);
        $this->writePolicyVersion('customer.consent.purpose', null, $administrator->id);
        $this->writePolicyVersion('customer.consent.wording', null, $administrator->id);
        $this->writePolicyVersion('customer.consent.retention', null, $administrator->id);

        Customer::query()->create([
            'phone_normalized' => '201001234567',
            'phone_display' => '+20 100 123 4567',
            'name_ar' => 'عميل اختبار',
            'name_en' => 'Test Customer',
            'status' => 'active',
            'idempotency_key' => 'initial-setup-customer-policy',
        ]);

        self::assertFalse($this->setupStep('customers')['complete']);

        $this->writePolicyVersion('customer.consent.purpose', '["profile"]', $administrator->id);
        $this->writePolicyVersion('customer.consent.wording', '{"version":"v1","text":"Customer consent"}', $administrator->id);
        $this->writePolicyVersion('customer.consent.retention', '{"days":30}', $administrator->id);

        $step = $this->setupStep('customers');
        self::assertFalse($step['required']);
        self::assertTrue($step['complete']);
        self::assertSame('ready', $step['status']);
    }

    public function test_party_readiness_never_treats_a_booking_as_policy_configuration(): void
    {
        $this->seedCanonicalAuthorization();
        $administrator = $this->administrator();
        $this->actingAs($administrator);
        $branch = $this->branch('PARTY-SETUP');
        $store = $this->store($branch, 'PARTY-SETUP-STORE', 'party');
        $customer = Customer::query()->create([
            'phone_normalized' => '201001234568',
            'phone_display' => '+20 100 123 4568',
            'name_ar' => 'عميل حفلات',
            'name_en' => 'Party Customer',
            'status' => 'active',
            'idempotency_key' => 'initial-setup-party-customer',
        ]);
        PartyBooking::query()->create([
            'booking_number' => 'PARTY-SETUP-001',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'party_date' => now()->addWeek()->toDateString(),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
            'timezone' => 'Africa/Cairo',
            'location' => 'Test venue',
            'primary_contact' => '+201001234568',
            'status' => 'draft',
            'created_by' => $administrator->id,
            'idempotency_key' => 'initial-setup-party-booking',
            'payload_hash' => str_repeat('a', 64),
        ]);

        $step = $this->setupStep('party-readiness');
        self::assertFalse($step['required']);
        self::assertFalse($step['complete']);
        self::assertSame('incomplete', $step['status']);
    }

    public function test_party_readiness_stays_incomplete_when_only_one_arbitrary_party_policy_is_saved(): void
    {
        $this->seedCanonicalAuthorization();
        $administrator = $this->administrator();
        $this->actingAs($administrator);
        $this->writePolicyVersion('party.any-single-policy', 'configured', $administrator->id);

        $step = $this->setupStep('party-readiness');

        self::assertFalse($step['required']);
        self::assertFalse($step['complete']);
        self::assertSame('incomplete', $step['status']);
        self::assertStringContainsString('owner defines the exact mandatory Party policy subset', $step['description']);
        self::assertStringContainsString('owner decision', strtolower($step['reason']));
    }

    public function test_opening_inventory_copy_keeps_the_zero_start_owner_decision_visible(): void
    {
        $step = $this->setupStep('opening-configuration');

        self::assertStringContainsString('Production zero-start path remains subject to owner approval', $step['description']);
    }

    public function test_arabic_translation_file_has_no_exact_duplicate_keys(): void
    {
        $contents = file_get_contents(base_path('lang/ar.json'));
        self::assertIsString($contents);

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        preg_match_all('/^\s*,?\s*"((?:\\\\.|[^"\\\\])*)"\s*:/m', $contents, $matches);

        $seen = [];
        foreach ($matches[1] as $encodedKey) {
            $key = json_decode('"'.$encodedKey.'"', true, 512, JSON_THROW_ON_ERROR);
            self::assertIsString($key);
            self::assertArrayNotHasKey($key, $seen, "Duplicate Arabic translation key: {$key}");
            $seen[$key] = true;
        }

        self::assertCount(count($decoded), $seen);
    }

    /** @param array<string, mixed> $step */
    private function canonicalDestination(array $step): string
    {
        $query = [];
        parse_str((string) parse_url((string) $step['route'], PHP_URL_QUERY), $query);
        ksort($query);

        return (string) $step['route_name'].'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> */
    private function setupStep(string $key): array
    {
        return collect(app(InitialSetupStatus::class)->snapshot()['steps'])->firstWhere('key', $key);
    }

    private function writePolicyVersion(string $key, ?string $value, int $actorId): void
    {
        CustomerPolicySettingVersion::query()->create([
            'key' => $key,
            'value' => $value,
            'value_type' => 'text',
            'version' => (int) CustomerPolicySettingVersion::query()->where('key', $key)->max('version') + 1,
            'created_by' => $actorId,
        ]);
    }
}
