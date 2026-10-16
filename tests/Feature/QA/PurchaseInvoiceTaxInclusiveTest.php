<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Retail\Services\PosCalculationService;

final class PurchaseInvoiceTaxInclusiveTest extends PureCalculatorTestCase
{
    public function test_supplier_gross_unit_cost_is_the_final_purchase_total(): void
    {
        $result = (new PurchaseInvoiceCalculator)->calculateDocument([[
            'quantity' => '10',
            'unit_cost' => '115.00',
            'discount_type' => null,
            'discount_value' => '0',
            'tax_rate' => '15',
            'tax_code' => 'VAT',
            'tax_inclusive' => false,
        ]]);

        self::assertSame('1150.0000', $result['subtotal']);
        self::assertSame('1150.0000', $result['totalAmount']);
        self::assertSame('0.0000', $result['taxAmount']);
        self::assertSame('0.0000', $result['calculated'][0]['tax_rate']);
        self::assertNull($result['calculated'][0]['tax_code']);
    }

    public function test_discount_applies_to_gross_cost_without_adding_tax(): void
    {
        $result = (new PurchaseInvoiceCalculator)->calculateLine([
            'quantity' => '2',
            'unit_cost' => '115',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'tax_rate' => '14',
            'tax_inclusive' => true,
        ]);

        self::assertSame('23.0000', $result['discount_amount']);
        self::assertSame('207.0000', $result['subtotal']);
        self::assertSame('207.0000', $result['line_total']);
        self::assertSame('0.0000', $result['tax_amount']);
    }

    public function test_sales_pos_tax_calculation_remains_unchanged(): void
    {
        $result = (new PosCalculationService)->calculate(
            [['quantity' => '1', 'unit_price' => '100']],
            '0',
            ['applicable' => true, 'rate' => '14', 'inclusive' => false],
        );

        self::assertSame('14.00', $result['tax_total']);
        self::assertSame('114.00', $result['total']);
    }
}
