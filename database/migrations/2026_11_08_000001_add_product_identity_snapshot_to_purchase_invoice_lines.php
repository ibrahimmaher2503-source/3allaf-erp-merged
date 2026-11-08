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
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->string('item_code_snapshot', 120)->nullable()->after('product_id');
            $table->string('name_ar_snapshot', 255)->nullable()->after('item_code_snapshot');
            $table->string('name_en_snapshot', 255)->nullable()->after('name_ar_snapshot');
        });

        DB::table('purchase_invoice_lines')
            ->join('products', 'products.id', '=', 'purchase_invoice_lines.product_id')
            ->update([
                'purchase_invoice_lines.item_code_snapshot' => DB::raw('products.item_code'),
                'purchase_invoice_lines.name_ar_snapshot' => DB::raw('products.name_ar'),
                'purchase_invoice_lines.name_en_snapshot' => DB::raw('products.name_en'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['item_code_snapshot', 'name_ar_snapshot', 'name_en_snapshot']);
        });
    }
};
