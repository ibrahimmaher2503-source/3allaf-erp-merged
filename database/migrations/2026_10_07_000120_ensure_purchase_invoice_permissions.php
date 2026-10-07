<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Upgrades must install the same catalog as fresh installations without reseeding roles.
        DB::transaction(function (): void {
            foreach (['draft', 'distribute', 'approve', 'reverse', 'view_cost', 'change_cost', 'change_list0_price', 'print_labels'] as $action) {
                DB::table('permissions')->insertOrIgnore([
                    'code' => 'purchase_invoices.'.$action,
                    'module' => 'purchase_invoices',
                    'action' => $action,
                    'sensitivity' => in_array($action, ['approve', 'reverse', 'change_cost', 'change_list0_price'], true) ? 'sensitive' : 'normal',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Retain the catalog: removing entries would destroy grants assigned after this upgrade.
    }
};
