<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_movement_id')->nullable()->after('reference_on_hand');
            $table->unsignedBigInteger('counted_movement_id')->nullable()->after('counted_at');
            $table->foreignId('counted_by')->nullable()->after('counted_movement_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counted_by');
            $table->dropColumn(['reference_movement_id', 'counted_movement_id']);
        });
    }
};
