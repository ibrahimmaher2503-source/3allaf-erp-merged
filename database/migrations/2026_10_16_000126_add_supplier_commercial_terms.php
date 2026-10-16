<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->decimal('credit_limit', 19, 4)->nullable()->after('payment_terms');
            $table->unsignedInteger('lead_time_days')->nullable()->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['credit_limit', 'lead_time_days']);
        });
    }
};
