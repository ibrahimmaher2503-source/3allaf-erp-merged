<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $grants = [
            'cashier' => ['dashboard_reports.view', 'transfers.view', 'transfers.receive', 'pos_sales.open_price', 'gift_cards.redeem'],
            'branch-manager' => ['dashboard_reports.view', 'transfers.view', 'transfers.create', 'transfers.edit', 'transfers.submit', 'transfers.receive', 'transfers.difference'],
            'warehouse-manager' => ['transfers.view', 'transfers.create', 'transfers.edit', 'transfers.submit', 'transfers.receive', 'transfers.difference', 'stock_counts.view', 'stock_counts.create', 'stock_counts.edit', 'stock_counts.open', 'stock_counts.close'],
            'stock-counter' => ['stock_counts.view', 'stock_counts.edit'],
        ];

        DB::transaction(function () use ($grants): void {
            $roles = DB::table('roles')->whereIn('code', array_keys($grants))->pluck('id', 'code');
            $permissions = DB::table('permissions')->where('status', 'active')->whereIn('code', collect($grants)->flatten())->pluck('id', 'code');
            $rows = [];
            foreach ($grants as $roleCode => $codes) {
                $roleId = $roles->get($roleCode);
                if ($roleId === null) {
                    continue;
                }
                foreach ($codes as $code) {
                    $permissionId = $permissions->get($code);
                    if ($permissionId !== null) {
                        $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()];
                    }
                }
            }
            if ($rows !== []) {
                DB::table('role_permissions')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        // Grants may be used by staff immediately; removing them could revoke later owner decisions.
    }
};
