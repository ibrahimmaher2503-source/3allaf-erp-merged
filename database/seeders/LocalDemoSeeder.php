<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
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

        $this->seedSuppliers($admin);
        $this->seedPurchaseOrders($admin);

        // Kept referenced so both store types are visible in the local demo.
        unset($warehouseStore);
    }

    private function seedSuppliers(User $admin): void
    {
        $primarySupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-001'],
            [
                'name_ar' => 'مورد الألعاب الرئيسي التجريبي',
                'name_en' => 'Primary Demo Toy Supplier',
                'contact_name' => 'ممثل المورد التجريبي الرئيسي',
                'email' => 'demo.supplier.primary@toyjoy.local',
                'phone' => '+200000000001',
                'tax_number' => 'DEMO-TAX-SUP-001',
                'payment_terms' => 'Local demo payment terms only; not approved production master data.',
                'address' => 'Local Demo Address - Cairo, Egypt',
                'status' => 'active',
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $secondarySupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-002'],
            [
                'name_ar' => 'مورد الألعاب الثانوي التجريبي',
                'name_en' => 'Secondary Demo Toy Supplier',
                'contact_name' => 'مسؤول المبيعات التجريبي الثانوي',
                'email' => 'demo.supplier.secondary@toyjoy.local',
                'phone' => '+200000000002',
                'tax_number' => 'DEMO-TAX-SUP-002',
                'payment_terms' => 'Local demo payment terms only; not approved production master data.',
                'address' => 'Local Demo Address - Giza, Egypt',
                'status' => 'active',
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $inactiveSupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-003'],
            [
                'name_ar' => 'مورد تجريبي قديم غير نشط',
                'name_en' => 'Inactive Historical Demo Supplier',
                'contact_name' => 'جهة اتصال سابقة تجريبية',
                'email' => 'demo.supplier.inactive@toyjoy.local',
                'phone' => '+200000000003',
                'tax_number' => 'DEMO-TAX-SUP-003',
                'payment_terms' => 'Local demo historical terms only; not approved production master data.',
                'address' => 'Local Demo Address - Historical Archive, Egypt',
                'status' => 'inactive',
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        unset($inactiveSupplier);

        $products = Product::query()->orderBy('id')->take(2)->get();

        if ($products->count() >= 2) {
            $product1 = $products[0];
            $product2 = $products[1];

            ProductSupplier::query()->updateOrCreate(
                ['product_id' => $product1->id, 'supplier_id' => $primarySupplier->id],
                [
                    'supplier_item_code' => 'DEMO-ITEM-P1-S1',
                    'is_preferred' => true,
                    'last_purchase_price' => null,
                    'last_purchase_date' => null,
                    'notes' => 'Local demo link only; not approved production master data.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            ProductSupplier::query()->updateOrCreate(
                ['product_id' => $product1->id, 'supplier_id' => $secondarySupplier->id],
                [
                    'supplier_item_code' => 'DEMO-ITEM-P1-S2',
                    'is_preferred' => false,
                    'last_purchase_price' => null,
                    'last_purchase_date' => null,
                    'notes' => 'Local demo link only; not approved production master data.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            ProductSupplier::query()->updateOrCreate(
                ['product_id' => $product2->id, 'supplier_id' => $secondarySupplier->id],
                [
                    'supplier_item_code' => 'DEMO-ITEM-P2-S2',
                    'is_preferred' => true,
                    'last_purchase_price' => null,
                    'last_purchase_date' => null,
                    'notes' => 'Local demo link only; not approved production master data.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            ProductSupplier::query()->updateOrCreate(
                ['product_id' => $product2->id, 'supplier_id' => $primarySupplier->id],
                [
                    'supplier_item_code' => 'DEMO-ITEM-P2-S1',
                    'is_preferred' => false,
                    'last_purchase_price' => null,
                    'last_purchase_date' => null,
                    'notes' => 'Local demo link only; not approved production master data.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function seedPurchaseOrders(User $admin): void
    {
        $supplier1 = Supplier::query()->where('code', 'DEMO-SUP-001')->first();
        $supplier2 = Supplier::query()->where('code', 'DEMO-SUP-002')->first();
        $store = Store::query()->where('code', 'DEMO-WH')->first();
        $products = Product::query()->orderBy('id')->take(2)->get();

        if (! $supplier1 || ! $store || $products->isEmpty()) {
            return;
        }

        DocumentSequence::query()->firstOrCreate(
            ['document_type' => 'purchase_order'],
            [
                'prefix' => 'PO-DEMO-',
                'padding_length' => 6,
                'next_value' => 4,
                'reset_rule' => 'never',
                'status' => 'active',
                'lock_version' => 1,
                'policy_notes' => 'Local demo sequence only; production numbering policy remains pending owner approval.',
            ],
        );

        $prod1 = $products[0];
        $prod2 = $products->count() > 1 ? $products[1] : $products[0];

        // 1. Draft PO
        $po1 = \App\Modules\Purchasing\Models\PurchaseOrder::query()->updateOrCreate(
            ['po_number' => 'PO-DEMO-000001'],
            [
                'supplier_id' => $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'draft',
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'payment_terms' => 'Local demo payment terms.',
                'notes' => 'Draft order for replenishment testing.',
                'subtotal' => 150.0000,
                'tax_amount' => 0.0000,
                'total_amount' => 150.0000,
                'lock_version' => 0,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
        $po1->lines()->delete();
        $po1->lines()->create([
            'product_id' => $prod1->id,
            'line_number' => 1,
            'quantity_ordered' => 10.0000,
            'quantity_received' => 0.0000,
            'unit_cost' => 15.0000,
            'subtotal' => 150.0000,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // 2. Submitted PO
        $po2 = \App\Modules\Purchasing\Models\PurchaseOrder::query()->updateOrCreate(
            ['po_number' => 'PO-DEMO-000002'],
            [
                'supplier_id' => $supplier2 ? $supplier2->id : $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'submitted',
                'order_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'payment_terms' => 'Local demo payment terms.',
                'notes' => 'Submitted purchase order pending goods receipt.',
                'subtotal' => 200.0000,
                'tax_amount' => 0.0000,
                'total_amount' => 200.0000,
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'submitted_at' => now()->subDays(1),
                'submitted_by' => $admin->id,
            ],
        );
        $po2->lines()->delete();
        $po2->lines()->create([
            'product_id' => $prod2->id,
            'line_number' => 1,
            'quantity_ordered' => 20.0000,
            'quantity_received' => 0.0000,
            'unit_cost' => 10.0000,
            'subtotal' => 200.0000,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // 3. Cancelled PO
        $po3 = \App\Modules\Purchasing\Models\PurchaseOrder::query()->updateOrCreate(
            ['po_number' => 'PO-DEMO-000003'],
            [
                'supplier_id' => $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'cancelled',
                'order_date' => now()->subDays(5)->toDateString(),
                'cancel_reason' => 'Cancelled during local demo testing due to item discontinuation.',
                'subtotal' => 75.0000,
                'tax_amount' => 0.0000,
                'total_amount' => 75.0000,
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'cancelled_at' => now()->subDays(4),
                'cancelled_by' => $admin->id,
            ],
        );
        $po3->lines()->delete();
        $po3->lines()->create([
            'product_id' => $prod1->id,
            'line_number' => 1,
            'quantity_ordered' => 5.0000,
            'quantity_received' => 0.0000,
            'unit_cost' => 15.0000,
            'subtotal' => 75.0000,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
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
