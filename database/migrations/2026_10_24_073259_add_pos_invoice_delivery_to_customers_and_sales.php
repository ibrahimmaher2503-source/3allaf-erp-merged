<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('invoice_delivery_method', 10)->default('print')->after('email');
        });

        Schema::create('sale_invoice_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('method', 10);
            $table->string('status', 20);
            $table->timestamp('attempted_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_delivery_attempts');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('invoice_delivery_method');
        });
    }
};
