<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-authorized (2026-11-23) supplier bonus quantity: free units that raise stock
 * without raising the supplier payable. Additive columns only; zero keeps old behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('bonus_quantity', 19, 6)->default(0)->after('quantity_received');
            $table->decimal('bonus_quantity_received', 19, 6)->default(0)->after('bonus_quantity');
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->decimal('bonus_quantity', 19, 6)->default(0)->after('quantity');
            $table->decimal('original_bonus_quantity', 19, 6)->nullable()->after('bonus_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['bonus_quantity', 'original_bonus_quantity']);
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn(['bonus_quantity', 'bonus_quantity_received']);
        });
    }
};
