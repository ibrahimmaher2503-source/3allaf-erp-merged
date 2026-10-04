<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Catalog\Support\ProductPricingContract;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Retail\Exceptions\PosFinancialConfigurationException;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use App\Support\ProductQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FinancialContractTest extends TestCase
{
    public function test_exact_purchase_snapshot_and_document_preserve_cost_discount_tax(): void
    {
        $c = new PurchaseInvoiceCalculator;
        $input = ['quantity' => '3', 'unit_cost' => ' 10.1234 ', 'discount_type' => 'percentage', 'discount_value' => '12.3456', 'tax_rate' => '14.1256', 'tax_code' => 'QA', 'tax_inclusive' => false];
        $x = $c->calculateLine($input);
        self::assertSame(['quantity' => '3.000000', 'unit_cost' => '10.1234', 'discount_type' => 'percentage', 'discount_value' => '12.3456', 'discount_amount' => '3.7493', 'tax_rate' => '14.1256', 'tax_code' => 'QA', 'tax_inclusive' => false, 'tax_amount' => '3.7603', 'subtotal' => '26.6208', 'line_total' => '30.3811'], $x);
        $d = $c->calculateDocument([$input, $input]);
        self::assertSame(['subtotal' => '53.2416', 'discountAmount' => '7.4986', 'taxAmount' => '7.5206', 'totalAmount' => '60.7622', 'calculated' => [$x, $x]], $d);
    }

    public function test_discount_allocation_uses_largest_remainder_and_stable_line_order(): void
    {
        $x = (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '1'], ['quantity' => '1', 'unit_price' => '3'], ['quantity' => '1', 'unit_price' => '2']], '0.02');
        self::assertSame(['0.00', '0.01', '0.01'], array_column($x['lines'], 'allocated_invoice_discount'));
        self::assertSame(['1.00', '2.99', '1.99'], array_column($x['lines'], 'net_amount'));
        self::assertSame(['1.00', '3.00', '2.00'], array_column($x['lines'], 'gross_amount'));
        self::assertSame('5.98', $x['total']);
    }

    public function test_zero_and_full_discount_tax_boundaries_and_default_exclusive_treatment(): void
    {
        $p = new PurchaseInvoiceCalculator;
        foreach ([false, true] as $inclusive) {
            $x = $p->calculateLine(['quantity' => '1', 'unit_cost' => '0', 'tax_rate' => '100', 'tax_inclusive' => $inclusive]);
            self::assertSame('0.0000', $x['line_total']);
            self::assertSame('0.0000', $x['tax_amount']);
            $x = $p->calculateLine(['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'amount', 'discount_value' => '10', 'tax_rate' => '100', 'tax_inclusive' => $inclusive]);
            self::assertSame('0.0000', $x['line_total']);
            self::assertSame('10.0000', $x['discount_amount']);
        }
        $x = (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '1']], '0', ['applicable' => true, 'rate' => '0']);
        self::assertSame('0.00', $x['tax_total']);
        self::assertSame('1.00', $x['total']);
        $x = (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '100']], '0', ['applicable' => true, 'rate' => '14']);
        self::assertSame('14.00', $x['tax_total']);
        self::assertSame('114.00', $x['total']);
        $x = $p->calculateLine(['quantity' => '1', 'unit_cost' => '10', 'discount_type' => 'amount']);
        self::assertSame('0.0000', $x['discount_value']);
    }

    public function test_inclusive_tax_does_not_round_a_truncated_exclusive_part(): void
    {
        $x = (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '0.04']], '0', ['applicable' => true, 'rate' => '14', 'inclusive' => true]);
        self::assertSame('0.00', $x['tax_total']);
        self::assertSame('0.04', $x['total']);
    }

    public function test_cash_rounding_subcent_zero_denomination_is_configuration_error(): void
    {
        $this->expectException(PosFinancialConfigurationException::class);
        (new PosCalculationService)->cashRoundingAdjustmentForDenomination('10', '0.001');
    }

    public function test_price_whitespace_and_integer_quantity_limits_preserve_exact_values(): void
    {
        self::assertSame(['average_cost' => '10.1234', 'sale_price' => '25.0000'], (new ProductPricingContract)->forProductCard(' 10.1234 ', ' 25 '));
        self::assertSame(PHP_INT_MAX, ProductQuantity::normalize((string) PHP_INT_MAX));
        self::assertSame(PHP_INT_MIN, ProductQuantity::normalizeSigned((string) PHP_INT_MIN));
        self::assertSame(1, ProductQuantity::normalize(' 0001.000 '));
        self::assertSame('0.000001', (new PosCalculationService)->normalizeQuantity('0.000001'));
        self::assertSame('0.0000', DecimalMoney::round('-0.00001', 4));
    }

    public function test_large_weight_cent_differences_preserve_exact_remainder_order(): void
    {
        $lines = array_map(fn ($price) => ['quantity' => '1', 'unit_price' => $price], ['1000000.00', '1000000.01', '1000000.02']);
        $x = (new PosCalculationService)->calculate($lines, '0.01');
        self::assertSame(['0.00', '0.00', '0.01'], array_column($x['lines'], 'allocated_invoice_discount'));
        self::assertSame('3000000.02', $x['total']);
        $x = (new PosCalculationService)->calculate([['quantity' => '99', 'unit_price' => '9999999999.0150']]);
        self::assertSame('989999999902.49', $x['total']);
    }

    public function test_valid_discount_whitespace_preserves_financial_values(): void
    {
        $x = (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '10', 'discount_amount' => ' 1.01 ']], ' 1.01 ');
        self::assertSame('1.01', $x['line_discount_total']);
        self::assertSame('1.01', $x['invoice_discount']);
        self::assertSame('7.98', $x['total']);
    }

    public function test_each_line_discount_is_bounded_even_when_document_net_is_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PosCalculationService)->calculate([['quantity' => '1', 'unit_price' => '1', 'discount_amount' => '2'], ['quantity' => '1', 'unit_price' => '10']]);
    }

    public function test_default_product_quantity_rule_rejects_zero(): void
    {
        self::assertFalse(app('validator')->make(['quantity' => 0], ['quantity' => ProductQuantity::rule()])->passes());
        self::assertTrue(app('validator')->make(['quantity' => 1], ['quantity' => ProductQuantity::rule()])->passes());
    }

    public function test_invalid_financial_text_is_rejected_by_the_domain_validator(): void
    {
        $p = new PurchaseInvoiceCalculator;
        $pos = new PosCalculationService;
        foreach (['bad1', '1bad', '', '1e2', '-0.0001'] as $bad) {
            try {
                $p->calculateLine(['quantity' => '1', 'unit_cost' => $bad]);
                self::fail('Invalid cost accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
        foreach (['bad1', '1bad', ''] as $bad) {
            try {
                $pos->calculate([['quantity' => '1', 'unit_price' => '10', 'discount_amount' => $bad]]);
                self::fail('Invalid discount accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
        try {
            $pos->calculate([['quantity' => '1', 'unit_price' => '10']], '0', ['applicable' => true, 'rate' => '   ']);
            self::fail('Blank tax accepted');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('rate',$e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $p->calculateLine(['quantity' => '1', 'unit_cost' => '10', 'tax_rate' => '100.0001']);
    }
}
