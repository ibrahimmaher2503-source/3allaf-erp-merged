<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Support\ProductQuantity;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class FinancialBoundaryTest extends PureCalculatorTestCase
{
    public function test_only_new_precision_and_large_value_matrix_rows(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/fixtures/financial-boundaries.json'), true, 512, JSON_THROW_ON_ERROR);
        $p = new PurchaseInvoiceCalculator;
        $pos = new PosCalculationService;
        foreach ($fixture['purchase'] as $n => $row) {
            self::assertSame($row['expected'], $p->calculateLine($row['line']), 'Added precision/carry case '.$n);
        }
        foreach ($fixture['pos'] as $n => $row) {
            self::assertSame($row['expected'], $pos->calculate($row['lines'], $row['discount'], $row['tax']), 'Added large allocation case '.$n);
        }
    }

    public function test_small_and_large_overdiscounts_cannot_pass_comparison_precision(): void
    {
        $p = new PurchaseInvoiceCalculator;
        foreach ([['quantity' => '1', 'unit_cost' => '0.0001', 'discount_type' => 'percentage', 'discount_value' => '100.01'], ['quantity' => '1', 'unit_cost' => '9999999999999.1234', 'discount_type' => 'amount', 'discount_value' => '9999999999999.1235']] as $line) {
            try {
                $p->calculateLine($line);
                self::fail('Overdiscount accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
        $x = $p->calculateLine(['quantity' => '3', 'unit_cost' => '1.99999']);
        self::assertSame('1.9999', $x['unit_cost']);
        self::assertSame('5.9997', $x['line_total']);
        foreach (['1', '0', 1, 0] as $flag) {
            self::assertSame((bool) $flag, $p->calculateLine(['quantity' => '1', 'unit_cost' => '1', 'tax_inclusive' => $flag])['tax_inclusive']);
        }
    }

    public function test_signed_and_unsigned_quantity_boundaries_and_zero_policy(): void
    {
        self::assertSame(PHP_INT_MAX, ProductQuantity::normalizeSigned((string) PHP_INT_MAX));
        self::assertSame(-2, ProductQuantity::normalizeSigned(' -0002.000 '));
        self::assertSame(2, ProductQuantity::normalizeSigned(' 2.000 '));
        self::assertSame('2', ProductQuantity::format(' 0002.000 '));
        foreach ([bcadd((string) PHP_INT_MAX, '1', 0), bcsub((string) PHP_INT_MIN, '1', 0), 'bad1', '1bad', '0', '1.5'] as $bad) {
            try {
                ProductQuantity::normalizeSigned($bad, 'movement');
                self::fail('Invalid signed quantity accepted');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('movement', $e->errors());
            }
        }
        try {
            ProductQuantity::normalize(bcadd((string) PHP_INT_MAX, '1', 0));
            self::fail('Quantity overflow accepted');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('quantity', $e->errors());
        }
        foreach (['bad1', '1bad'] as $bad) {
            try {
                ProductQuantity::format($bad);
                self::fail('Invalid source quantity displayed');
            } catch (\UnexpectedValueException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
        foreach ([true, false] as $allowZero) {
            $rules = ['quantity' => ProductQuantity::rule($allowZero)];
            self::assertSame($allowZero, app('validator')->make(['quantity' => 0], $rules)->passes());
            self::assertFalse(app('validator')->make(['quantity' => -1], $rules)->passes());
            self::assertFalse(app('validator')->make(['quantity' => '1.5'], $rules)->passes());
            self::assertTrue(app('validator')->make(['quantity' => 1], $rules)->passes());
        }
    }

    public function test_schema_valid_large_amount_tax_allocation_and_cash_matrix(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/fixtures/financial-precision.json'), true, 512, JSON_THROW_ON_ERROR);
        $p = new PosCalculationService;
        foreach ($rows['pos'] as $i => $r) {
            self::assertSame($r['expected'], $p->calculate($r['lines'], $r['discount'], $r['tax']), 'Schema-valid POS precision '.$i);
        }
        foreach ($rows['cash'] as $i => $r) {
            self::assertSame($r['expected'], $p->cashRoundingAdjustmentForDenomination($r['amount'], $r['denomination']), 'Cash precision '.$i);
        }
    }

    public function test_purchase_inclusive_precision_at_exact_four_place_boundary(): void
    {
        $p = new PurchaseInvoiceCalculator;
        foreach (['0.0001', '0.0002', '14.1256', '99.9999'] as $rate) {
            $cost = bcadd('100', $rate, 4);
            $x = $p->calculateLine(['quantity' => '1', 'unit_cost' => $cost, 'tax_rate' => $rate, 'tax_inclusive' => true]);
            self::assertSame($rate, $x['tax_amount']);
            self::assertSame('100.0000', $x['subtotal']);
            self::assertSame($cost, $x['line_total']);
        }
    }

    public function test_leading_zero_overflow_cannot_bypass_quantity_limits(): void
    {
        $tooLarge = '0'.bcadd((string) PHP_INT_MAX, '1', 0);
        $tooNegative = '-0'.ltrim(bcsub((string) PHP_INT_MIN, '1', 0), '-');
        foreach ([[$tooLarge, false], [$tooLarge, true], [$tooNegative, true]] as [$value,$signed]) {
            try {
                $signed ? ProductQuantity::normalizeSigned($value) : ProductQuantity::normalize($value);
                self::fail('Padded overflow accepted');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('quantity', $e->errors());
            }
        }
    }

    public function test_large_document_discount_and_two_cent_cash_boundary(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/fixtures/financial-precision.json'), true, 512, JSON_THROW_ON_ERROR);
        $p = new PosCalculationService;
        foreach ($rows['pos'] as $i => $r) {
            if (bccomp($r['discount'], '10000000000000', 2) > 0) {
                self::assertSame($r['expected'], $p->calculate($r['lines'], $r['discount'], $r['tax']), 'Large document discount '.$i);
            }
        }
        foreach ($rows['cash'] as $i => $r) {
            if ($r['denomination'] === '0.02') {
                self::assertSame($r['expected'], $p->cashRoundingAdjustmentForDenomination($r['amount'], $r['denomination']), 'Two cent boundary '.$i);
            }
        }
    }

    public function test_cash_midpoint_near_schema_limit_retains_exact_tie_policy(): void
    {
        self::assertSame('0.02', (new PosCalculationService)->cashRoundingAdjustmentForDenomination('999999999999990.06', '0.04'));
    }

    public function test_document_discount_aggregation_preserves_large_fractional_amounts(): void
    {
        $line = ['quantity' => '1', 'unit_cost' => '9999999999999.1234', 'discount_type' => 'amount', 'discount_value' => '9999999999999.0001'];
        $x = (new PurchaseInvoiceCalculator)->calculateDocument([$line, $line]);
        self::assertSame('19999999999998.0002', $x['discountAmount']);
        self::assertSame('0.2466', $x['totalAmount']);
    }

    public function test_zero_scale_normalization_and_invalid_decimal_text(): void
    {
        self::assertSame('1', DecimalMoney::normalize('1.999', 0));
        foreach (['bad1', '1bad', '', '+1'] as $bad) {
            try {
                DecimalMoney::normalize($bad);
                self::fail('Invalid decimal accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
    }
}
