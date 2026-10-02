<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filters share the existing configuration library, with independent assignments.
        if (! Schema::hasTable('product_filter_values')) {
            Schema::create('product_filter_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('product_option_value_id')->constrained('product_option_values')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['product_id', 'product_option_value_id'], 'product_filter_value_unique');
            });
        }

        // Legacy imports stored descriptive selections in the family pivots. Copy only
        // ordinary products; a real family's values define its SKU matrix, not filters.
        // Keep every legacy assignment intact, and allow recovery after partial DDL.
        DB::table('product_filter_values')->insertUsing(
            ['product_id', 'product_option_value_id', 'created_at', 'updated_at'],
            DB::table('product_family_option_values as legacy')
                ->join('products', 'products.id', '=', 'legacy.product_id')
                ->leftJoin('product_filter_values as existing', function ($join): void {
                    $join->on('existing.product_id', '=', 'legacy.product_id')
                        ->on('existing.product_option_value_id', '=', 'legacy.product_option_value_id');
                })
                ->where('products.has_variations', false)
                ->whereNull('products.parent_product_id')
                ->whereNull('existing.id')
                ->select('legacy.product_id', 'legacy.product_option_value_id', 'legacy.created_at', 'legacy.updated_at'),
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('product_filter_values')) {
            if (DB::table('product_filter_values')->exists()) {
                throw new RuntimeException('Product filter assignments exist. Retain this additive table during application rollback.');
            }
            Schema::dropIfExists('product_filter_values');
        }
    }
};
