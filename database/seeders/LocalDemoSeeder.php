<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

/**
 * Local-only data for manually exercising the Phase 1 administration screens.
 *
 * This seeder intentionally does not create roles, permission grants, or a
 * production policy. Those remain subject to the owner-approved matrix.
 */
class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('Local demo seeding is only allowed in the local environment.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'demo.admin@toyjoy.local'],
            [
                'name' => 'Local Demo Administrator',
                'username' => 'demo-admin',
                'email_verified_at' => now(),
                'password' => Hash::make('LocalDemoOnly!2026'),
                'is_super_admin' => true,
            ],
        );

        $company = Company::query()->updateOrCreate(
            ['code' => 'TOY-JOY-DEMO'],
            [
                'name_ar' => 'توي آند جوي - بيانات تجريبية',
                'name_en' => 'TOY & JOY - Local Demo',
                'currency_code' => 'TBD',
                'currency_symbol' => 'TBD',
                'timezone' => 'Africa/Cairo',
                'locale_default' => 'ar',
                'status' => 'active',
                'policy_notes' => 'Local demo data only. Not approved production master data.',
            ],
        );

        $branch = Branch::query()->updateOrCreate(
            ['code' => 'DEMO-CAI'],
            [
                'company_id' => $company->id,
                'name_ar' => 'فرع القاهرة التجريبي',
                'name_en' => 'Cairo Demo Branch',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'Local demo branch only.',
            ],
        );

        $sellingStore = $this->store($company, $branch, 'DEMO-SELL', 'selling', 'متجر البيع التجريبي', 'Demo Selling Store');
        $warehouseStore = $this->store($company, $branch, 'DEMO-WH', 'warehouse', 'مخزن تجريبي', 'Demo Warehouse');

        BranchSellingStore::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'store_id' => $sellingStore->id],
            [
                'effective_from' => now()->startOfDay(),
                'effective_to' => null,
                'status' => 'active',
                'approval_notes' => 'Local demo mapping only. Owner approval remains required.',
                'created_by' => $admin->id,
            ],
        );

        CashDrawer::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-DR-01'],
            [
                'company_id' => $company->id,
                'store_id' => $sellingStore->id,
                'assigned_user_id' => $admin->id,
                'name_ar' => 'درج الكاش التجريبي',
                'name_en' => 'Demo Cash Drawer',
                'status' => 'active',
                'policy_notes' => 'Local demo only. No opening balance or shift policy is configured.',
            ],
        );

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'DEMO-CASH'],
            [
                'name_ar' => 'نقدي تجريبي',
                'name_en' => 'Demo Cash',
                'type' => 'manual',
                'requires_evidence' => false,
                'offline_eligible' => false,
                'status' => 'active',
                'policy_notes' => 'Local demo only. Payment policy is TBD.',
            ],
        );

        TaxSetting::query()->updateOrCreate(
            ['code' => 'DEMO-TAX-TBD'],
            [
                'name_ar' => 'ضريبة تجريبية - قيد القرار',
                'name_en' => 'Demo Tax - Pending Decision',
                'rate' => null,
                'is_tax_inclusive' => false,
                'status' => 'inactive',
                'policy_notes' => 'No rate is implied. Owner tax policy is required.',
            ],
        );

        DocumentSequence::query()->updateOrCreate(
            ['document_type' => 'demo-only'],
            [
                'prefix' => 'DEMO-',
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'inactive',
                'lock_version' => 1,
                'policy_notes' => 'Local demo only. Not available for business documents.',
            ],
        );

        PrinterConfiguration::query()->updateOrCreate(
            ['name' => 'Demo Printer - Unconfigured'],
            [
                'printer_type' => 'thermal',
                'paper_size' => '80mm',
                'template_name' => 'demo_unconfigured',
                'connection_type' => 'network',
                'is_default' => false,
                'status' => 'inactive',
                'notes' => 'Local UI sample only. No printer or print format is approved.',
            ],
        );

        // Kept referenced so both store types are visible in the local demo.
        unset($warehouseStore);
    }

    private function store(
        Company $company,
        Branch $branch,
        string $code,
        string $type,
        string $nameAr,
        string $nameEn,
    ): Store {
        return Store::query()->updateOrCreate(
            ['code' => $code],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'type' => $type,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'status' => 'active',
                'allows_negative_stock' => false,
                'policy_notes' => 'Local demo store only. Production structure is pending owner approval.',
            ],
        );
    }
}
