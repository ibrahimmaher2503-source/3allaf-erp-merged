<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('scale_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scale_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('plu');
            $table->string('label', 20);
            $table->decimal('confirmed_price', 8, 2)->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamps();
            $table->unique(['scale_configuration_id', 'plu'], 'scale_mapping_plu_unique');
            $table->unique(['scale_configuration_id', 'product_unit_id'], 'scale_mapping_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scale_product_mappings');
        Schema::table('scale_configurations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
