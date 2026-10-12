<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->decimal('original_quantity', 19, 6)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain original quantity snapshots when rolling back application code.');
    }
};
