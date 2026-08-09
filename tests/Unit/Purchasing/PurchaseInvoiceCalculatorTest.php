<?php

declare(strict_types=1);

namespace Tests\Unit\Purchasing;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requirements: PUR-04, PUR-05. Test cases: TC-PUR-004 and negative boundaries.
 */
final class PurchaseInvoiceCalculatorTest extends TestCase
{
    private PurchaseInvoiceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PurchaseInvoiceCalculator;
    }

    public function test_percentage_discount_is_applied_before_zero_tax(): void
    {
        $line = $this->calculator->calculateLine([
            'quantity' => '2.500000',
            'unit_cost' => '12.3456',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'tax_rate' => '0',
        ]);

        self::assertSame('27.7776', $line['subtotal']);
        self::assertSame('3.0864', $line['discount_amount']);
        self::assertSame('0.0000', $line['tax_amount']);
        self::assertSame('27.7776', $line['line_total']);
    }

    public function test_fixed_discount_and_document_totals_are_exact(): void
    {
        $document = $this->calculator->calculateDocument([
            ['quantity' => '3', 'unit_cost' => '10', 'discount_type' => 'amount', 'discount_value' => '2.5', 'tax_rate' => '0'],
            ['quantity' => '0.5', 'unit_cost' => '5', 'discount_type' => null, 'discount_value' => '0', 'tax_rate' => '0'],
        ]);

        self::assertSame('30.0000', $document['subtotal']);
        self::assertSame('2.5000', $document['discountAmount']);
        self::assertSame('0.0000', $document['taxAmount']);
        self::assertSame('30.0000', $document['totalAmount']);
    }

    #[DataProvider('invalidLines')]
    public function test_invalid_or_policy_forbidden_lines_are_rejected(array $line): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateLine($line);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidLines(): array
    {
        return [
            'zero quantity' => [['quantity' => '0', 'unit_cost' => '10', 'tax_rate' => '0']],
            'negative cost' => [['quantity' => '1', 'unit_cost' => '-1', 'tax_rate' => '0']],
            'unsupported discount' => [['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'stacked', 'discount_value' => '1', 'tax_rate' => '0']],
            'discount exceeds gross' => [['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'amount', 'discount_value' => '10.0001', 'tax_rate' => '0']],
            'phase one purchase tax' => [['quantity' => '1', 'unit_cost' => '10', 'tax_rate' => '0.01']],
            'non numeric quantity' => [['quantity' => 'one', 'unit_cost' => '10', 'tax_rate' => '0']],
        ];
    }

    public function test_empty_document_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateDocument([]);
    }
}
