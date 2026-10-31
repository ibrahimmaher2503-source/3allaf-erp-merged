<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->string('invoice_discount_type', 20)->nullable();
            $table->decimal('invoice_discount_value', 14, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_discount_type', 'invoice_discount_value']);
        });
    }
};
