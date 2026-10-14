<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $vatId = DB::table('tax_settings')->where('company_id', $companyId)->where('code', 'EG-VAT-14')->value('id');
            if ($vatId === null) {
                $vatId = DB::table('tax_settings')->insertGetId([
                    'company_id' => $companyId,
                    'code' => 'EG-VAT-14',
                    'name_ar' => 'ضريبة القيمة المضافة المصرية',
                    'name_en' => 'Egypt VAT',
                    'rate' => '14.00',
                    'treatment' => 'standard',
                    'is_default' => false,
                    'is_tax_inclusive' => false,
                    'status' => 'active',
                    'policy_notes' => 'Configurable Egypt default; confirm applicable exemptions and inclusive pricing per product.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $hasEffectiveNonZeroDefault = DB::table('tax_settings')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('is_default', true)
                ->where('id', '!=', $vatId)
                ->where('rate', '>', 0)
                ->exists();
            if (! $hasEffectiveNonZeroDefault) {
                DB::table('tax_settings')->where('company_id', $companyId)->where('is_default', true)->update(['is_default' => false, 'updated_at' => $now]);
                DB::table('tax_settings')->where('id', $vatId)->update(['is_default' => true, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tax_settings')->where('code', 'EG-VAT-14')->delete();
    }
};
