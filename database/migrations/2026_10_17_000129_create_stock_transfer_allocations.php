<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_line_id')->constrained('stock_transfer_lines')->cascadeOnDelete();
            $table->foreignId('destination_store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('quantity_received', 18, 6)->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_line_id', 'destination_store_id'], 'stock_transfer_allocation_line_destination_unique');
            $table->index(['destination_store_id', 'quantity_received'], 'sta_destination_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_allocations');
    }
};
