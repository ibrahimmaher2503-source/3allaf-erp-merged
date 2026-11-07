<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'code' => 'sales_invoices.create',
            'module' => 'sales_invoices',
            'action' => 'create',
            'sensitivity' => 'sensitive',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Existing role assignments and historical authorization decisions are preserved.
    }
};
