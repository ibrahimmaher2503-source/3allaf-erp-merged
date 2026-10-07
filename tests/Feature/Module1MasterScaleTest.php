<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

final class Module1MasterScaleTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(static fn (User $user, string $ability): bool => true);

        $this->company = Company::factory()->create();

        $this->administrator = User::factory()->create([
            'username' => 'qa-master-scale-admin',
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    public function test_each_settings_master_list_can_reach_and_search_row_101_with_bounded_pages(): void
    {
        for ($index = 0; $index <= 100; $index++) {
            $suffix = sprintf('%03d', $index);

            PaymentMethod::query()->create([
                'code' => 'qa-master-payment-'.$suffix,
                'name_ar' => 'وسيلة دفع '.$suffix,
                'name_en' => 'QA payment '.$suffix,
                'type' => 'manual',
                'status' => 'active',
            ]);

            TaxSetting::query()->create([
                'company_id' => $this->company->id,
                'code' => 'qa-master-tax-'.$suffix,
                'name_ar' => 'ضريبة اختبار '.$suffix,
                'name_en' => 'QA tax '.$suffix,
                'rate' => '10.00',
                'treatment' => 'standard',
                'status' => 'active',
            ]);

            $branch = $this->createBranch('sequence-'.$suffix);
            $documentType = 'qa-master-sequence-'.$suffix;

            DocumentSequence::query()->create([
                'document_type' => $documentType,
                'scope_type' => 'branch',
                'scope_id' => $branch->id,
                'prefix' => $documentType.'-',
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
            ]);

            PrinterConfiguration::query()->create([
                'name' => 'qa-master-printer-'.$suffix,
                'printer_type' => 'thermal',
                'paper_size' => '80mm',
                'template_name' => 'default_thermal',
                'connection_type' => 'network',
                'port' => 9100,
                'status' => 'active',
            ]);
        }

        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($this->administrator);

        $this->assertListCanReachLastRow('payments', 'qa-master-payment-000', 'qa-master-payment-100', 'qa-master-payment-100');
        $this->assertListCanReachLastRow('tax', 'qa-master-tax-000', 'qa-master-tax-100', 'qa-master-tax-100');
        $this->assertListCanReachLastRow('sequences', 'qa-master-sequence-000', 'qa-master-sequence-100', 'qa-master-sequence-100');
        $this->assertListCanReachLastRow('printers', 'qa-master-printer-000', 'qa-master-printer-100', 'qa-master-printer-100');
    }

    public function test_settings_master_search_and_page_state_reset_when_the_tab_changes(): void
    {
        $this->createPaymentMethods(30);
        $this->createTaxSettings(30);

        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($this->administrator);

        $component = Livewire::test('platform::admin.settings')
            ->set('activeTab', 'payments')
            ->call('setPage', 2)
            ->set('masterListSearch', 'qa-master-payment-029')
            ->assertSee('qa-master-payment-029');

        $component->set('masterListSearch', '')
            ->call('setPage', 2)
            ->set('activeTab', 'tax')
            ->assertSet('masterListSearch', '')
            ->assertSee('qa-master-tax-000')
            ->assertDontSee('qa-master-tax-029');
    }

    public function test_only_the_active_master_list_is_queried(): void
    {
        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($this->administrator);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test('platform::admin.settings')->set('activeTab', 'payments');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $masterTableReads = array_values(array_filter(
            $queries,
            static fn (array $query): bool => preg_match('/\bfrom\s+`?(payment_methods|tax_settings|document_sequences|printer_configurations)`?/i', $query['query']) === 1,
        ));

        self::assertNotEmpty($masterTableReads);
        foreach ($masterTableReads as $query) {
            self::assertMatchesRegularExpression('/\bfrom\s+`?payment_methods`?/i', $query['query']);
        }
    }

    public function test_tax_sequence_and_printer_lists_keep_company_and_location_scopes(): void
    {
        $allowedBranch = $this->createBranch('scope-allowed');
        $hiddenBranch = $this->createBranch('scope-hidden');
        $allowedStore = Store::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $allowedBranch->id,
            'code' => 'qa-scope-store',
            'type' => 'selling',
            'name_ar' => 'متجر النطاق',
            'name_en' => 'Scope store',
            'status' => 'active',
            'allows_negative_stock' => false,
        ]);

        $scopedUser = User::factory()->create([
            'username' => 'qa-master-scope-user',
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $scopedUser->branchScopes()->create(['branch_id' => $allowedBranch->id, 'status' => 'active']);
        $scopedUser->storeScopes()->create(['store_id' => $allowedStore->id, 'status' => 'active']);

        TaxSetting::query()->create([
            'company_id' => $this->company->id,
            'code' => 'qa-scope-tax-visible',
            'name_ar' => 'ضريبة ظاهرة',
            'name_en' => 'Visible tax',
            'rate' => '5.00',
            'treatment' => 'standard',
            'status' => 'active',
        ]);
        $otherCompany = Company::factory()->create();
        TaxSetting::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'qa-scope-tax-hidden',
            'name_ar' => 'ضريبة مخفية',
            'name_en' => 'Hidden tax',
            'rate' => '7.00',
            'treatment' => 'standard',
            'status' => 'active',
        ]);

        DocumentSequence::query()->create([
            'document_type' => 'retail_sale',
            'scope_type' => 'branch',
            'scope_id' => $allowedBranch->id,
            'prefix' => 'qa-scope-sequence-visible-',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
        ]);
        DocumentSequence::query()->create([
            'document_type' => 'retail_sale',
            'scope_type' => 'branch',
            'scope_id' => $hiddenBranch->id,
            'prefix' => 'qa-scope-sequence-hidden-',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
        ]);

        PrinterConfiguration::query()->create([
            'name' => 'qa-scope-printer-visible',
            'branch_id' => $allowedBranch->id,
            'store_id' => $allowedStore->id,
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'connection_type' => 'network',
            'port' => 9100,
            'status' => 'active',
        ]);
        PrinterConfiguration::query()->create([
            'name' => 'qa-scope-printer-hidden',
            'branch_id' => $hiddenBranch->id,
            'printer_type' => 'thermal',
            'paper_size' => '80mm',
            'template_name' => 'default_thermal',
            'connection_type' => 'network',
            'port' => 9100,
            'status' => 'active',
        ]);

        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($scopedUser);

        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'tax')
            ->assertSee('qa-scope-tax-visible')
            ->assertDontSee('qa-scope-tax-hidden');

        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'sequences')
            ->assertSee('qa-scope-sequence-visible')
            ->assertDontSee('qa-scope-sequence-hidden');

        Livewire::test('platform::admin.settings')
            ->set('activeTab', 'printers')
            ->assertSee('qa-scope-printer-visible')
            ->assertDontSee('qa-scope-printer-hidden');
    }

    private function assertListCanReachLastRow(string $tab, string $firstRow, string $lastRow, string $searchTerm): void
    {
        $component = Livewire::test('platform::admin.settings')
            ->set('activeTab', $tab)
            ->assertSee($firstRow)
            ->assertDontSee($lastRow)
            ->assertSee('Showing 1–25 of 101 records');

        $component->call('setPage', 5)
            ->assertSee($lastRow)
            ->assertSee('Showing 101–101 of 101 records');

        $component->set('masterListSearch', $searchTerm)
            ->assertSee($lastRow)
            ->assertSee('Showing 1–1 of 1 records');
    }

    private function createBranch(string $suffix): Branch
    {
        return Branch::query()->create([
            'company_id' => $this->company->id,
            'code' => 'QA-'.strtoupper($suffix),
            'name_ar' => 'فرع '.$suffix,
            'name_en' => 'Branch '.$suffix,
            'timezone' => 'UTC',
            'status' => 'active',
        ]);
    }

    private function createPaymentMethods(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            PaymentMethod::query()->create([
                'code' => sprintf('qa-master-payment-%03d', $index),
                'name_ar' => 'وسيلة دفع '.$index,
                'name_en' => 'QA payment '.$index,
                'type' => 'manual',
                'status' => 'active',
            ]);
        }
    }

    private function createTaxSettings(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            TaxSetting::query()->create([
                'company_id' => $this->company->id,
                'code' => sprintf('qa-master-tax-%03d', $index),
                'name_ar' => 'ضريبة اختبار '.$index,
                'name_en' => 'QA tax '.$index,
                'rate' => '10.00',
                'treatment' => 'standard',
                'status' => 'active',
            ]);
        }
    }
}
