<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Retail\Services\PosCalculationService;
use PHPUnit\Framework\TestCase;

final class FinancialPrecisionClosureTest extends TestCase
{
    public function test_inclusive_tax_nearest_half_cent_residues_preserve_exact_money(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/fixtures/financial-final-precision.json'), true, 512, JSON_THROW_ON_ERROR);
        $calculator = new PosCalculationService;
        foreach ($rows['pos'] as $index => $row) {
            self::assertSame($row['expected'], $calculator->calculate($row['lines'], '0', $row['tax']), 'Half-cent residue '.$index);
        }
    }

    public function test_purchase_discount_precision_preserves_four_place_tax_and_cost_snapshots(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/fixtures/financial-final-precision.json'), true, 512, JSON_THROW_ON_ERROR);
        $calculator = new PurchaseInvoiceCalculator;
        foreach ($rows['purchase'] as $index => $row) {
            self::assertSame($row['expected'], $calculator->calculateLine($row['line']), 'Purchase precision boundary '.$index);
        }
    }

    public function test_many_equal_low_value_lines_conserve_discount_and_stable_ties(): void
    {
        $lines = array_fill(0, 17, ['quantity' => '1', 'unit_price' => '0.01']);
        $result = (new PosCalculationService)->calculate($lines, '0.16');
        self::assertSame('0.01', $result['total']);
        self::assertSame(array_merge(array_fill(0, 16, '0.01'), ['0.00']), array_column($result['lines'], 'allocated_invoice_discount'));
        self::assertSame(array_merge(array_fill(0, 16, '0.00'), ['0.01']), array_column($result['lines'], 'net_amount'));
    }
}
