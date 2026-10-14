<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SupplierBusinessDecisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tax_id_unique_constraint_allows_multiple_missing_values_but_rejects_duplicates(): void
    {
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-001',
            'name_ar' => 'مورد اختبار 1',
            'name_en' => 'QA Supplier 1',
            'tax_number' => null,
            'country_code' => 'EG',
        ]);
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-002',
            'name_ar' => 'مورد اختبار 2',
            'name_en' => 'QA Supplier 2',
            'tax_number' => null,
            'country_code' => 'EG',
        ]);

        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-003',
            'name_ar' => 'مورد اختبار 3',
            'name_en' => 'QA Supplier 3',
            'tax_number' => '123456789',
            'country_code' => 'EG',
        ]);

        $this->expectException(QueryException::class);
        DB::table('suppliers')->insert([
            'code' => 'QA-SUP-004',
            'name_ar' => 'مورد اختبار 4',
            'name_en' => 'QA Supplier 4',
            'tax_number' => '123456789',
            'country_code' => 'EG',
        ]);
    }
}
