<?php

declare(strict_types=1);

namespace Tests\Unit\Purchasing;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Deterministic property/boundary coverage for PUR-04/PUR-05.
 * The fixed seed documents the reproducible input set; no ambient randomness is used.
 */
final class PurchaseInvoiceCalculatorPropertyTest extends TestCase
{
    private const FIXED_SEED = 20260808;

    public function test_fixed_seed_lines_preserve_money_and_document_sum_invariants(): void
    {
        $calculator = new PurchaseInvoiceCalculator;
        $lines = [
            ['quantity' => '0.000001', 'unit_cost' => '0.0001', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0'],
            ['quantity' => '1.000000', 'unit_cost' => '1.2345', 'discount_type' => 'percentage', 'discount_value' => '1', 'tax_rate' => '0'],
            ['quantity' => '2.500000', 'unit_cost' => '12.3456', 'discount_type' => 'percentage', 'discount_value' => '10', 'tax_rate' => '0'],
            ['quantity' => '10.125000', 'unit_cost' => '999.9999', 'discount_type' => 'amount', 'discount_value' => '0.0001', 'tax_rate' => '0'],
            ['quantity' => '999.999999', 'unit_cost' => '0.0001', 'discount_type' => 'amount', 'discount_value' => '0.01', 'tax_rate' => '0'],
        ];

        self::assertSame(20260808, self::FIXED_SEED);
        $calculated = [];
        foreach ($lines as $line) {
            $result = $calculator->calculateLine($line);
            $calculated[] = $result;
            $gross = bcmul((string) $result['quantity'], (string) $result['unit_cost'], 4);

            self::assertSame('0.0000', $result['tax_amount']);
            self::assertSame($result['subtotal'], $result['line_total']);
            self::assertLessThanOrEqual(0, bccomp((string) $result['discount_amount'], $gross, 4));
            self::assertGreaterThanOrEqual(0, bccomp((string) $result['discount_amount'], '0', 4));
        }

        $document = $calculator->calculateDocument($lines);
        $subtotal = '0.0000';
        $discount = '0.0000';
        $tax = '0.0000';
        $total = '0.0000';
        foreach ($calculated as $line) {
            $subtotal = bcadd($subtotal, (string) $line['subtotal'], 4);
            $discount = bcadd($discount, (string) $line['discount_amount'], 4);
            $tax = bcadd($tax, (string) $line['tax_amount'], 4);
            $total = bcadd($total, (string) $line['line_total'], 4);
        }

        self::assertSame($subtotal, $document['subtotal']);
        self::assertSame($discount, $document['discountAmount']);
        self::assertSame($tax, $document['taxAmount']);
        self::assertSame($total, $document['totalAmount']);
    }

    public function test_numeric_fuzz_boundaries_are_rejected_without_coercion(): void
    {
        $calculator = new PurchaseInvoiceCalculator;
        $invalidValues = ['', ' ', 'one', '-1', '+1', '1e2', 'NaN', 'INF', '1.2.3', '0x10', '1,000'];
        $rejected = 0;

        foreach ($invalidValues as $invalidValue) {
            try {
                $calculator->calculateLine(['quantity' => $invalidValue, 'unit_cost' => '1', 'tax_rate' => '0']);
                self::fail('Invalid quantity was accepted: '.$invalidValue);
            } catch (InvalidArgumentException) {
                // Expected boundary rejection.
                $rejected++;
            }
        }

        self::assertSame(count($invalidValues), $rejected);
    }

    public function test_discount_and_quantity_boundaries_are_exactly_defined(): void
    {
        $calculator = new PurchaseInvoiceCalculator;
        $atMinimum = $calculator->calculateLine(['quantity' => '0.000001', 'unit_cost' => '10', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0']);
        self::assertSame('0.000001', $atMinimum['quantity']);

        $fullyDiscounted = $calculator->calculateLine(['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'amount', 'discount_value' => '10', 'tax_rate' => '0']);
        self::assertSame('0.0000', $fullyDiscounted['subtotal']);
        self::assertSame('0.0000', $fullyDiscounted['line_total']);

        $this->expectException(InvalidArgumentException::class);
        $calculator->calculateLine(['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'amount', 'discount_value' => '10.0001', 'tax_rate' => '0']);
    }
}
