<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_pos_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->unique()->constrained('gift_cards')->restrictOnDelete();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index(['shift_id', 'activated_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Gift Card POS sale history requires a separately authorized rollback migration.');
    }
};
