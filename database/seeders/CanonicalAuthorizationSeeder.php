<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CanonicalAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'system-administrator' => ['مسؤول النظام', 'System Administrator'],
            'branch-manager' => ['مدير الفرع', 'Branch Manager'],
            'cashier' => ['أمين الصندوق', 'Cashier'],
            'purchasing-officer' => ['مسؤول المشتريات', 'Purchasing Officer'],
            'warehouse-manager' => ['مدير المستودع', 'Warehouse Manager'],
            'pricing-officer' => ['مسؤول التسعير', 'Pricing Officer'],
            'party-manager' => ['مدير الحفلات', 'Party Manager'],
            'stock-counter' => ['مراقب المخزون', 'Stock Counter'],
            'accountant-reviewer' => ['المحاسب / المراجع', 'Accountant / Reviewer'],
        ];

        foreach ($roles as $code => [$nameAr, $nameEn]) {
            Role::query()->updateOrCreate(['code' => $code], ['name_ar' => $nameAr, 'name_en' => $nameEn, 'status' => 'active']);
        }

        $permissions = [
            'manage-settings' => ['company_settings', 'manage', 'sensitive'],
            'manage-branches-stores' => ['branches_stores', 'manage', 'sensitive'],
            'view-authorization-baseline' => ['authorization', 'view', 'sensitive'],
            'manage-authorization' => ['authorization', 'manage', 'sensitive'],
            'view-platform-status' => ['platform', 'view', 'normal'],
            'view-ui-showcase' => ['platform', 'view_patterns', 'normal'],
        ];

        foreach ($permissions as $code => [$module, $action, $sensitivity]) {
            Permission::query()->updateOrCreate(['code' => $code], compact('module', 'action', 'sensitivity') + ['status' => 'active']);
        }

        $modules = [
            'company_settings', 'branches_stores', 'drawers_payments_tax_numbering_printers', 'users_roles_permissions', 'products_categories_brands', 'suppliers', 'purchase_orders', 'purchase_invoices_supplier_returns', 'pricing_labels', 'inventory_stock_card', 'transfers', 'stock_counts', 'pos_sales', 'suspended_sales', 'shifts_cash_movements', 'customers_children', 'loyalty', 'product_wallet', 'party_wallet', 'returns_exchanges_gift_instruments', 'party_bookings_invoices', 'party_operating_orders_consumables', 'rental_assets', 'quotations', 'dashboard_reports', 'audit_logs', 'offline_queue_conflicts',
        ];
        foreach ($modules as $module) {
            foreach (['view', 'create', 'edit', 'logical_delete', 'print', 'approve', 'export', 'reverse', 'cancel', 'override'] as $action) {
                Permission::query()->updateOrCreate(['code' => $module.'.'.$action], ['module' => $module, 'action' => $action, 'sensitivity' => in_array($action, ['approve', 'export', 'reverse', 'cancel', 'override', 'logical_delete'], true) ? 'sensitive' : 'normal', 'status' => 'active']);
            }
        }

        $rolePermissions = [
            'system-administrator' => [
                'company_settings.view', 'company_settings.create', 'company_settings.edit',
                'branches_stores.view', 'branches_stores.create', 'branches_stores.edit',
                'drawers_payments_tax_numbering_printers.view', 'drawers_payments_tax_numbering_printers.create', 'drawers_payments_tax_numbering_printers.edit',
                'users_roles_permissions.view', 'users_roles_permissions.create', 'users_roles_permissions.edit',
                'dashboard_reports.view', 'audit_logs.view', 'products_categories_brands.view',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.cancel', 'purchase_orders.print', 'purchase_orders.approve',
            ],
            'branch-manager' => ['branches_stores.view', 'pos_sales.view', 'purchase_orders.view'],
            'cashier' => ['pos_sales.view', 'pos_sales.create', 'pos_sales.print', 'products_categories_brands.view'],
            'purchasing-officer' => ['products_categories_brands.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.cancel', 'purchase_orders.print'],
            'warehouse-manager' => ['products_categories_brands.view', 'suppliers.view', 'purchase_orders.view'],
            'pricing-officer' => ['products_categories_brands.view'],
            'accountant-reviewer' => ['dashboard_reports.view', 'audit_logs.view', 'products_categories_brands.view', 'suppliers.view', 'purchase_orders.view', 'purchase_orders.print', 'purchase_orders.approve'],
        ];

        foreach ($roles as $code => $_) {
            $role = Role::query()->where('code', $code)->firstOrFail();
            $codes = $rolePermissions[$code] ?? [];
            $role->permissions()->sync(Permission::query()->whereIn('code', $codes)->pluck('id')->all());
        }

        if (app()->environment('local')) {
            $demoUsers = [
                'demo-admin' => ['Local Demo Administrator', 'demo.admin@toyjoy.local', 'system-administrator', true],
                'demo-branch-manager' => ['Local Demo Branch Manager', 'demo.branch.manager@toyjoy.local', 'branch-manager', false],
                'demo-cashier' => ['Local Demo Cashier', 'demo.cashier@toyjoy.local', 'cashier', false],
                'demo-reviewer' => ['Local Demo Reviewer', 'demo.reviewer@toyjoy.local', 'accountant-reviewer', false],
                'demo-no-access' => ['Local Demo No Access', 'demo.no.access@toyjoy.local', null, false],
            ];

            foreach ($demoUsers as $username => [$name, $email, $roleCode, $isSuperAdmin]) {
                $user = User::query()->updateOrCreate(['username' => $username], [
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(bin2hex(random_bytes(32))),
                    'is_super_admin' => $isSuperAdmin,
                ]);

                $user->roles()->sync($roleCode ? [Role::query()->where('code', $roleCode)->value('id')] : []);
            }

            $branchId = Branch::query()->where('code', 'DEMO-CAI')->value('id');
            $storeId = Store::query()->where('code', 'DEMO-SELL')->value('id');

            if ($branchId !== null) {
                User::query()->where('username', 'demo-branch-manager')->first()?->branchScopes()->updateOrCreate(['branch_id' => $branchId], ['status' => 'active']);
                User::query()->where('username', 'demo-reviewer')->first()?->branchScopes()->updateOrCreate(['branch_id' => $branchId], ['status' => 'active']);
            }

            if ($storeId !== null) {
                User::query()->where('username', 'demo-cashier')->first()?->storeScopes()->updateOrCreate(['store_id' => $storeId], ['status' => 'active']);
            }
        }
    }
}
