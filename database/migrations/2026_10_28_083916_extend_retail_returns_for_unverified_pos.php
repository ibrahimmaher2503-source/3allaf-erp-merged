<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_returns', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(true)->after('source_sale_id');
            $table->decimal('manager_approved_amount', 14, 2)->nullable()->after('settlement_value');
        });
        Schema::table('retail_return_lines', function (Blueprint $table): void {
            $table->foreignId('sale_line_id')->nullable()->change();
            $table->unique(['retail_return_id', 'line_number'], 'retail_return_line_position_unique');
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['status', 'approved_at', 'id'], 'sales_return_date_lookup');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->index('name_ar', 'customers_return_name_ar_lookup');
            $table->index('name_en', 'customers_return_name_en_lookup');
        });
        DB::table('permissions')->updateOrInsert(['code' => 'returns.unverified'], [
            'module' => 'returns', 'action' => 'unverified', 'sensitivity' => 'sensitive',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('retail_returns')->where('is_verified', false)->exists()) {
            throw new RuntimeException('Cannot roll back unverified return support while unverified returns exist. Preserve and migrate those documents first.');
        }
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_return_name_ar_lookup');
            $table->dropIndex('customers_return_name_en_lookup');
        });
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex('sales_return_date_lookup'));
        Schema::table('retail_return_lines', function (Blueprint $table): void {
            $table->dropUnique('retail_return_line_position_unique');
            $table->foreignId('sale_line_id')->nullable(false)->change();
        });
        Schema::table('retail_returns', fn (Blueprint $table) => $table->dropColumn(['is_verified', 'manager_approved_amount']));
        DB::table('permissions')->where('code', 'returns.unverified')->whereNotIn('id', DB::table('role_permissions')->select('permission_id'))->delete();
    }
};
