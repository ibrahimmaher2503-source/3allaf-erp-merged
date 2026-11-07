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
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->string('discount_type', 20)->nullable()->after('unit_cost');
            $table->decimal('discount_value', 19, 4)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 19, 4)->default(0)->after('discount_value');
            $table->decimal('tax_rate', 9, 4)->default(0)->after('discount_amount');
            $table->string('tax_code', 50)->nullable()->after('tax_rate');
            $table->boolean('tax_inclusive')->default(false)->after('tax_code');
            $table->decimal('tax_amount', 19, 4)->default(0)->after('tax_inclusive');
            $table->decimal('line_total', 19, 4)->default(0)->after('subtotal');
        });

        // Legacy purchase-order headers are authoritative and remain untouched.
        // New line snapshot fields start at zero and line_total mirrors the
        // historical subtotal without redistributing header-level tax.
        DB::table('purchase_order_lines')->update([
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_inclusive' => false,
            'tax_amount' => 0,
            'line_total' => DB::raw('subtotal'),
        ]);
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Migration 000115 is intentionally irreversible: dropping purchase-order financial snapshots would destroy historical data. Restore Hotfix41 application files without rolling back this migration.',
        );
    }
};
