<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64);
            $table->string('mode', 30)->default('create_only');
            $table->string('status', 30)->default('staging');
            $table->json('headers')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->foreignId('retry_of_id')->nullable()->constrained('purchase_invoice_import_batches')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['created_by', 'sha256']);
            $table->index(['created_by', 'status', 'created_at']);
        });

        Schema::create('purchase_invoice_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_import_batch_id')->constrained('purchase_invoice_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 30)->default('invalid');
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->timestamps();

            $table->unique(['purchase_invoice_import_batch_id', 'row_number']);
            $table->index(['purchase_invoice_import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_import_rows');
        Schema::dropIfExists('purchase_invoice_import_batches');
    }
};
