<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use PHPUnit\Framework\TestCase;

final class FinancialMatrixTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/fixtures/financial-matrix.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_seeded_pos_tax_discount_and_allocation_matrix(): void
    {
        $c = new PosCalculationService;
        foreach ($this->fixture()['pos'] as $i => $row) {
            self::assertSame($row['expected'], $c->calculate($row['lines'], $row['discount'], $row['tax']), 'POS rational matrix case '.$i);
        }
    }

    public function test_purchase_precision_tax_and_discount_matrix(): void
    {
        $c = new PurchaseInvoiceCalculator;
        foreach ($this->fixture()['purchase'] as $i => $row) {
            self::assertSame($row['expected'], $c->calculateLine($row['line']), 'Purchase rational matrix case '.$i);
        }
    }

    public function test_seeded_signed_half_away_rounding_matrix(): void
    {
        foreach ($this->fixture()['rounding'] as $i => $row) {
            self::assertSame($row['expected'], DecimalMoney::round($row['value'], $row['scale']), 'Signed rounding matrix case '.$i);
        }
    }
}
