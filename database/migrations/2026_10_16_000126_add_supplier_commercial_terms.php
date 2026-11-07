<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // [merge 3allaf+rajeh] 3allaf migration 2026_09_15_000117 already adds suppliers.credit_limit.
    // Only add the columns that do not exist yet, and only drop what this migration added.
    public function up(): void
    {
        $addCreditLimit = ! Schema::hasColumn('suppliers', 'credit_limit');
        $addLeadTime = ! Schema::hasColumn('suppliers', 'lead_time_days');

        Schema::table('suppliers', function (Blueprint $table) use ($addCreditLimit, $addLeadTime): void {
            if ($addCreditLimit) {
                $table->decimal('credit_limit', 19, 4)->nullable()->after('payment_terms');
            }
            if ($addLeadTime) {
                $table->unsignedInteger('lead_time_days')->nullable()->after('credit_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['lead_time_days']);
        });
        if (! Schema::hasColumn('suppliers', 'payment_policy') && Schema::hasColumn('suppliers', 'credit_limit')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropColumn(['credit_limit']);
            });
        }
    }
};
