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
use App\Modules\Purchasing\Models\FinancialSettingVersion;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Retail\Models\PosShift;
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
                'name' => 'System Administrator',
                'username' => 'demo-admin',
                'email_verified_at' => now(),
                'password' => Hash::make('LocalDemoOnly!2026'),
                'is_super_admin' => true,
            ],
        );

        $company = Company::query()->updateOrCreate(
            ['code' => 'TOY-JOY-DEMO'],
            [
                'name_ar' => 'توي آند جوي',
                'name_en' => 'TOY & JOY',
                'currency_code' => 'EGP',
                'currency_symbol' => 'ج.م',
                'timezone' => 'Africa/Cairo',
                'locale_default' => 'ar',
                'status' => 'active',
                'policy_notes' => 'Company identity and currency settings.',
            ],
        );

        $branch = Branch::query()->updateOrCreate(
            ['code' => 'DEMO-CAI'],
            [
                'company_id' => $company->id,
                'name_ar' => 'فرع القاهرة',
                'name_en' => 'Cairo Branch',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'Cairo branch operating details.',
            ],
        );

        foreach (['demo-reviewer', 'demo-branch-manager'] as $username) {
            User::query()->where('username', $username)->first()?->branchScopes()->updateOrCreate(
                ['branch_id' => $branch->id],
                ['status' => 'active'],
            );
        }

        $sellingStore = $this->store($company, $branch, 'DEMO-SELL', 'selling', 'متجر البيع', 'Selling Store');
        $warehouseStore = $this->store($company, $branch, 'DEMO-WH', 'warehouse', 'المخزن الرئيسي', 'Main Warehouse');

        BranchSellingStore::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'store_id' => $sellingStore->id],
            [
                'effective_from' => now()->startOfDay(),
                'effective_to' => null,
                'status' => 'active',
                'approval_notes' => 'Selling store assigned to the branch.',
                'created_by' => $admin->id,
            ],
        );

        $cashDrawer = CashDrawer::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DEMO-DR-01'],
            [
                'company_id' => $company->id,
                'store_id' => $sellingStore->id,
                'assigned_user_id' => $admin->id,
                'name_ar' => 'درج النقدية الرئيسي',
                'name_en' => 'Main cash drawer',
                'status' => 'active',
                'policy_notes' => 'Opening, closing, and variance policy.',
            ],
        );

        PosShift::query()->updateOrCreate(
            ['store_id' => $sellingStore->id, 'cash_drawer_id' => $cashDrawer->id, 'cashier_id' => $admin->id, 'status' => 'open'],
            [
                'branch_id' => $branch->id,
                'opening_cash' => '0.00',
                'opened_at' => now()->startOfDay(),
                'policy_notes' => 'Opening, closing, and variance policy.',
            ],
        );

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'DEMO-CASH'],
            [
                'name_ar' => 'نقدي',
                'name_en' => 'Cash',
                'type' => 'manual',
                'requires_evidence' => false,
                'offline_eligible' => false,
                'status' => 'active',
                'policy_notes' => 'Cash payment method.',
            ],
        );

        TaxSetting::query()->updateOrCreate(
            ['code' => 'DEMO-TAX-TBD'],
            [
                'name_ar' => 'ضريبة القيمة المضافة',
                'name_en' => 'Value added tax',
                'rate' => null,
                'is_tax_inclusive' => false,
                'status' => 'inactive',
                'policy_notes' => 'Tax rule awaiting a configured rate.',
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
                'policy_notes' => 'Document numbering settings.',
            ],
        );

        foreach ([
            ['document_type' => 'stock_transfer', 'prefix' => 'TR-', 'policy_notes' => 'Local inventory transfer numbering.'],
            ['document_type' => 'inventory_adjustment', 'prefix' => 'ADJ-', 'policy_notes' => 'Local inventory movement numbering.'],
            ['document_type' => 'stock_count', 'prefix' => 'CNT-', 'policy_notes' => 'Local stock count numbering.'],
        ] as $sequence) {
            DocumentSequence::query()->updateOrCreate(
                ['document_type' => $sequence['document_type']],
                [
                    'prefix' => $sequence['prefix'],
                    'padding_length' => 6,
                    'next_value' => 1,
                    'reset_rule' => 'never',
                    'status' => 'active',
                    'lock_version' => 1,
                    'policy_notes' => $sequence['policy_notes'],
                ],
            );
        }

        PrinterConfiguration::query()->updateOrCreate(
            ['name' => 'Main printer'],
            [
                'printer_type' => 'thermal',
                'paper_size' => '80mm',
                'template_name' => 'demo_unconfigured',
                'connection_type' => 'network',
                'is_default' => false,
                'status' => 'inactive',
                'notes' => 'Printer profile awaiting connection details.',
            ],
        );

        $this->seedDemoFinancialPolicies($admin);
        $this->seedSuppliers($admin);
        $this->seedPurchaseOrders($admin);

        // Kept referenced so both store types are visible in the local demo.
        unset($warehouseStore);
    }

    /**
     * Record the owner-authorized Demo policy examples as pending versions.
     *
     * These rows deliberately have no approval record, so the resolver cannot
     * activate them as operational financial settings.
     */
    private function seedDemoFinancialPolicies(User $admin): void
    {
        /** @var array<int, array{key: string, value: string, value_type: string}> $policies */
        $policies = [
            [
                'key' => 'purchasing.supplier_return.number_prefix',
                'value' => 'DEMO-RET-',
                'value_type' => 'string',
            ],
            [
                'key' => 'purchasing.supplier_return.print_title',
                'value' => 'TOY & JOY - Supplier Return',
                'value_type' => 'string',
            ],
            [
                'key' => 'purchasing.supplier_return.print_footer',
                'value' => 'Supplier return document.',
                'value_type' => 'string',
            ],
            [
                'key' => 'purchasing.supplier_return.approval_limit',
                'value' => '1000.00',
                'value_type' => 'decimal',
            ],
        ];

        foreach ($policies as $policy) {
            FinancialSettingVersion::query()->firstOrCreate(
                [
                    'key' => $policy['key'],
                    'version' => 1,
                ],
                [
                    'value' => $policy['value'],
                    'value_type' => $policy['value_type'],
                    'effective_from' => now()->subMinute(),
                    'effective_to' => now()->addYear(),
                    'created_by' => $admin->id,
                    'approval_record_id' => null,
                    'locked_at' => null,
                    'notes' => 'Supplier return policy settings.',
                ],
            );
        }
    }

    private function seedSuppliers(User $admin): void
    {
        $primarySupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-001'],
            [
                'name_ar' => 'مورد الألعاب الرئيسي',
                'name_en' => 'Primary Toy Supplier',
                'contact_name' => 'ممثل المورد الرئيسي',
                'email' => 'demo.supplier.primary@toyjoy.local',
                'phone' => '+200000000001',
                'tax_number' => 'DEMO-TAX-SUP-001',
                'payment_terms' => 'Net 30 days',
                'address' => 'Cairo, Egypt',
                'status' => 'active',
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $secondarySupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-002'],
            [
                'name_ar' => 'مورد الألعاب الثانوي',
                'name_en' => 'Secondary Toy Supplier',
                'contact_name' => 'مسؤول مبيعات المورد الثانوي',
                'email' => 'demo.supplier.secondary@toyjoy.local',
                'phone' => '+200000000002',
                'tax_number' => 'DEMO-TAX-SUP-002',
                'payment_terms' => 'Net 30 days',
                'address' => 'Giza, Egypt',
                'status' => 'active',
                'lock_version' => 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $inactiveSupplier = Supplier::query()->updateOrCreate(
            ['code' => 'DEMO-SUP-003'],
            [
                'name_ar' => 'مورد قديم غير نشط',
                'name_en' => 'Inactive historical supplier',
                'contact_name' => 'جهة اتصال سابقة',
                'email' => 'demo.supplier.inactive@toyjoy.local',
                'phone' => '+200000000003',
                'tax_number' => 'DEMO-TAX-SUP-003',
                'payment_terms' => 'Historical terms',
                'address' => 'Historical archive, Egypt',
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
                    'notes' => 'Preferred supplier link.',
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
                    'notes' => 'Supplier product link.',
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
                    'notes' => 'Preferred supplier link.',
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
                    'notes' => 'Supplier product link.',
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
                'policy_notes' => 'Purchase order numbering settings.',
            ],
        );

        $prod1 = $products[0];
        $prod2 = $products->count() > 1 ? $products[1] : $products[0];

        // 1. Draft PO
        $po1 = PurchaseOrder::query()->firstOrCreate(
            ['po_number' => 'PO-DEMO-000001'],
            [
                'supplier_id' => $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'draft',
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'payment_terms' => 'Net 30 days',
                'notes' => 'Draft order for replenishment.',
                'subtotal' => 150.0000,
                'tax_amount' => 0.0000,
                'total_amount' => 150.0000,
                'lock_version' => 0,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
        $this->ensurePurchaseOrderLine($po1, $prod1, 10.0000, 15.0000, 150.0000, $admin->id);

        // 2. Submitted PO
        $po2 = PurchaseOrder::query()->firstOrCreate(
            ['po_number' => 'PO-DEMO-000002'],
            [
                'supplier_id' => $supplier2 ? $supplier2->id : $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'submitted',
                'order_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'payment_terms' => 'Net 30 days',
                'notes' => 'Submitted purchase order awaiting goods receipt.',
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
        $this->ensurePurchaseOrderLine($po2, $prod2, 20.0000, 10.0000, 200.0000, $admin->id);

        // 3. Cancelled PO
        $po3 = PurchaseOrder::query()->firstOrCreate(
            ['po_number' => 'PO-DEMO-000003'],
            [
                'supplier_id' => $supplier1->id,
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'status' => 'cancelled',
                'order_date' => now()->subDays(5)->toDateString(),
                'cancel_reason' => 'Cancelled because the item was discontinued.',
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
        $this->ensurePurchaseOrderLine($po3, $prod1, 5.0000, 15.0000, 75.0000, $admin->id);
    }

    private function ensurePurchaseOrderLine(
        PurchaseOrder $purchaseOrder,
        Product $product,
        float $quantity,
        float $unitCost,
        float $subtotal,
        int $adminId,
    ): void {
        if (! $purchaseOrder->wasRecentlyCreated && ! $purchaseOrder->isDraft()) {
            return;
        }

        $lineIds = $purchaseOrder->lines()->pluck('id');

        if ($lineIds->isNotEmpty() && PurchaseInvoiceLine::query()->whereIn('purchase_order_line_id', $lineIds)->exists()) {
            return;
        }

        $purchaseOrder->lines()->delete();
        $purchaseOrder->lines()->create([
            'product_id' => $product->id,
            'line_number' => 1,
            'quantity_ordered' => $quantity,
            'quantity_received' => 0.0000,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
            'created_by' => $adminId,
            'updated_by' => $adminId,
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
                'policy_notes' => 'Store operating details.',
            ],
        );
    }
}
