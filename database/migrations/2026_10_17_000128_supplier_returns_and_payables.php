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
        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->foreignId('purchase_invoice_id')->nullable()->change();
        });
        Schema::table('purchase_return_lines', function (Blueprint $table): void {
            $table->foreignId('purchase_invoice_line_id')->nullable()->change();
            $table->string('cost_source', 24)->default('purchase_invoice');
        });
        Schema::create('supplier_payable_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 19, 4);
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id'], 'sup_pay_source_uq');
            $table->index(['company_id', 'supplier_id', 'id'], 'sup_pay_balance_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_payable_entries')->exists()
            || DB::table('purchase_returns')->whereNull('purchase_invoice_id')->exists()) {
            throw new RuntimeException('Rollback refused: supplier payable or invoice-free return history exists.');
        }
        Schema::dropIfExists('supplier_payable_entries');
        Schema::table('purchase_return_lines', function (Blueprint $table): void {
            $table->dropColumn('cost_source');
            $table->foreignId('purchase_invoice_line_id')->nullable(false)->change();
        });
        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->foreignId('purchase_invoice_id')->nullable(false)->change();
        });
    }
};
