<?php

declare(strict_types=1);

namespace Tests\Unit\Retail;

use App\Modules\Retail\Services\PosCalculationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requirements: POS-04, POS-05, POS-06, NFR-06. Policy: docs/48 §3-§5 (DEC-066).
 * Test cases: TC-POS-020..029.
 *
 * These assert the arithmetic itself. `docs/48` requires the same figures on
 * screen, on the thermal receipt, on the A4 invoice, and in reports, so this
 * service is the only place the numbers are produced.
 */
final class PosCalculationServiceTest extends TestCase
{
    private function service(): PosCalculationService
    {
        return new PosCalculationService;
    }

    public function test_line_gross_and_total_follow_the_canonical_order(): void
    {
        $result = $this->service()->calculate([
            ['quantity' => '2', 'unit_price' => '15.0000'],
            ['quantity' => '1', 'unit_price' => '7.5000'],
        ]);

        self::assertSame('37.50', $result['subtotal']);
        self::assertSame('0.00', $result['discount_total']);
        self::assertSame('0.00', $result['tax_total']);
        self::assertSame('37.50', $result['total']);
        self::assertSame('30.00', $result['lines'][0]['net_amount']);
        self::assertSame('7.50', $result['lines'][1]['net_amount']);
    }

    public function test_a_line_discount_reduces_only_that_line(): void
    {
        $result = $this->service()->calculate([
            ['quantity' => '1', 'unit_price' => '100.0000', 'discount_amount' => '10.00'],
            ['quantity' => '1', 'unit_price' => '50.0000'],
        ]);

        self::assertSame('150.00', $result['subtotal']);
        self::assertSame('10.00', $result['discount_total']);
        self::assertSame('90.00', $result['lines'][0]['net_amount']);
        self::assertSame('50.00', $result['lines'][1]['net_amount']);
        self::assertSame('140.00', $result['total']);
    }

    /**
     * `docs/48` §3 step 4. Naive per-line rounding loses a cent here and makes
     * the receipt total disagree with the sum of its lines.
     */
    public function test_an_invoice_discount_is_allocated_pro_rata_and_sums_exactly(): void
    {
        $result = $this->service()->calculate(
            [
                ['quantity' => '1', 'unit_price' => '33.3300'],
                ['quantity' => '1', 'unit_price' => '33.3300'],
                ['quantity' => '1', 'unit_price' => '33.3400'],
            ],
            '10.00',
        );

        $allocated = array_map(
            static fn (array $line): string => $line['allocated_invoice_discount'],
            $result['lines'],
        );

        $sum = '0.00';
        foreach ($allocated as $amount) {
            $sum = bcadd($sum, $amount, 2);
        }

        self::assertSame('10.00', $sum, 'The allocation must sum to exactly the invoice discount.');
        self::assertSame('100.00', $result['subtotal']);
        self::assertSame('90.00', $result['total']);

        $lineSum = '0.00';
        foreach ($result['lines'] as $line) {
            $lineSum = bcadd($lineSum, $line['net_amount'], 2);
        }
        self::assertSame($result['total'], $lineSum, 'The total must equal the sum of the line nets.');
    }

    public function test_exclusive_tax_is_added_to_the_taxable_base(): void
    {
        $result = $this->service()->calculate(
            [['quantity' => '1', 'unit_price' => '100.0000']],
            '0.00',
            ['applicable' => true, 'rate' => '14.00', 'inclusive' => false],
        );

        self::assertSame('100.00', $result['taxable_base']);
        self::assertSame('14.00', $result['tax_total']);
        self::assertSame('114.00', $result['total']);
    }

    public function test_inclusive_tax_is_extracted_without_inflating_the_total(): void
    {
        $result = $this->service()->calculate(
            [['quantity' => '1', 'unit_price' => '114.0000']],
            '0.00',
            ['applicable' => true, 'rate' => '14.00', 'inclusive' => true],
        );

        self::assertSame('14.00', $result['tax_total']);
        self::assertSame('114.00', $result['total'], 'An inclusive rate must not add tax on top of a price that already contains it.');
    }

    /**
     * POS-04 lets an authorised user enable tax per invoice, but the rate is a
     * BLK-008 owner value. Defaulting it would fabricate a financial figure.
     */
    public function test_enabling_tax_without_a_configured_rate_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate(
            [['quantity' => '1', 'unit_price' => '100.0000']],
            '0.00',
            ['applicable' => true, 'rate' => null],
        );
    }

    public function test_tax_is_zero_when_the_invoice_does_not_enable_it(): void
    {
        $result = $this->service()->calculate(
            [['quantity' => '1', 'unit_price' => '100.0000']],
            '0.00',
            ['applicable' => false],
        );

        self::assertSame('0.00', $result['tax_total']);
        self::assertSame('100.00', $result['total']);
    }

    /**
     * @param  array<int, array{quantity: string, unit_price: string, discount_amount?: string}>  $lines
     */
    #[DataProvider('rejectedInputs')]
    public function test_invalid_financial_input_is_rejected(array $lines, string $invoiceDiscount): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->calculate($lines, $invoiceDiscount);
    }

    /**
     * @return array<string, array{0: array<int, array{quantity: string, unit_price: string, discount_amount?: string}>, 1: string}>
     */
    public static function rejectedInputs(): array
    {
        return [
            'empty cart' => [[], '0.00'],
            'negative line discount' => [[['quantity' => '1', 'unit_price' => '10.0000', 'discount_amount' => '-1.00']], '0.00'],
            'line discount exceeds line' => [[['quantity' => '1', 'unit_price' => '10.0000', 'discount_amount' => '10.01']], '0.00'],
            'negative invoice discount' => [[['quantity' => '1', 'unit_price' => '10.0000']], '-1.00'],
            'invoice discount exceeds net' => [[['quantity' => '1', 'unit_price' => '10.0000']], '10.01'],
        ];
    }

    /**
     * A discount that consumes the whole line is legitimate (a free item), and
     * must not be confused with the over-discount case above.
     */
    public function test_a_full_line_discount_is_permitted(): void
    {
        $result = $this->service()->calculate([
            ['quantity' => '1', 'unit_price' => '10.0000', 'discount_amount' => '10.00'],
            ['quantity' => '1', 'unit_price' => '5.0000'],
        ]);

        self::assertSame('0.00', $result['lines'][0]['net_amount']);
        self::assertSame('5.00', $result['total']);
    }
}
