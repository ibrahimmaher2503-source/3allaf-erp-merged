<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scale_key_assignments', function (Blueprint $table): void {
            $table->string('sync_status', 20)->default('pending')->after('scale_product_mapping_id');
            $table->unsignedInteger('confirmed_value')->nullable()->after('sync_status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_value');
        });
    }

    public function down(): void
    {
        Schema::table('scale_key_assignments', function (Blueprint $table): void {
            $table->dropColumn(['sync_status', 'confirmed_value', 'confirmed_at']);
        });
    }
};
