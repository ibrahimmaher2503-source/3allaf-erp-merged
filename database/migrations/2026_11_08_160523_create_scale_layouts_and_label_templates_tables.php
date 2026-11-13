<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scale_key_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scale_configuration_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('layer');
            $table->unsignedTinyInteger('key_number');
            $table->unsignedBigInteger('scale_product_mapping_id')->nullable();
            $table->timestamps();
            $table->unique(['scale_configuration_id', 'layer', 'key_number'], 'scale_key_position_unique');
            $table->foreign('scale_product_mapping_id')->references('id')->on('scale_product_mappings')->nullOnDelete();
        });

        Schema::create('scale_label_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scale_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->json('fields');
            $table->string('barcode_type', 30)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scale_label_templates');
        Schema::dropIfExists('scale_key_assignments');
    }
};
