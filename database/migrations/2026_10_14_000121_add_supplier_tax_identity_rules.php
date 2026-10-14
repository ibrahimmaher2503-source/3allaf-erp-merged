<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'country_code')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->string('country_code', 2)->default('EG')->after('tax_number');
            });
        }

        DB::table('suppliers')->where('tax_number', '')->update(['tax_number' => null]);

        $duplicates = DB::table('suppliers')
            ->select('tax_number')
            ->whereNotNull('tax_number')
            ->where('tax_number', '!=', '')
            ->groupBy('tax_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot add supplier Tax ID uniqueness while duplicate Tax IDs exist. Resolve duplicates first.');
        }

        if (! collect(Schema::getIndexes('suppliers'))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'suppliers_tax_number_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique('tax_number', 'suppliers_tax_number_unique');
            });
        }
    }

    public function down(): void
    {
        if (collect(Schema::getIndexes('suppliers'))->contains(fn (array $index): bool => ($index['name'] ?? null) === 'suppliers_tax_number_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropUnique('suppliers_tax_number_unique');
            });
        }

        if (Schema::hasColumn('suppliers', 'country_code')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropColumn('country_code');
            });
        }
    }
};
