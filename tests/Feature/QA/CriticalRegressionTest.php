<?php
declare(strict_types=1);
namespace Tests\Feature\QA;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Modules\Catalog\Support\ProductPricingContract;
use App\Support\ProductQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
final class CriticalRegressionTest extends TestCase
{
    public function test_exclusive_tax_and_both_discount_levels(): void {
        $x=(new PosCalculationService)->calculate([['quantity'=>'2','unit_price'=>'12.50','discount_amount'=>'1.00']], '2.00', ['applicable'=>true,'rate'=>'14','inclusive'=>false]);
        self::assertSame('25.00',$x['subtotal']); self::assertSame('3.00',$x['discount_total']); self::assertSame('3.08',$x['tax_total']); self::assertSame('25.08',$x['total']);
    }
    public function test_inclusive_tax_does_not_increase_payable(): void {
        $x=(new PosCalculationService)->calculate([['quantity'=>'1','unit_price'=>'114']], '0', ['applicable'=>true,'rate'=>'14','inclusive'=>true]);
        self::assertSame('14.00',$x['tax_total']); self::assertSame('114.00',$x['total']);
    }
    public function test_discount_pennies_are_conserved(): void {
        $x=(new PosCalculationService)->calculate(array_fill(0,3,['quantity'=>'1','unit_price'=>'1']), '0.01');
        self::assertSame(['0.01','0.00','0.00'],array_column($x['lines'],'allocated_invoice_discount')); self::assertSame('2.99',$x['total']);
    }
    public function test_negative_discount_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class); (new PosCalculationService)->calculate([['quantity'=>'1','unit_price'=>'10','discount_amount'=>'-1']]);
    }
    public function test_over_discount_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class); (new PosCalculationService)->calculate([['quantity'=>'1','unit_price'=>'10']], '10.01');
    }
    public function test_cash_rounding_ties_and_directions(): void {
        $c=new PosCalculationService; self::assertSame('0.00',$c->cashRoundingAdjustmentForDenomination('25','5')); self::assertSame('-1.00',$c->cashRoundingAdjustmentForDenomination('26','5')); self::assertSame('2.50',$c->cashRoundingAdjustmentForDenomination('27.50','5')); self::assertSame('1.00',$c->cashRoundingAdjustmentForDenomination('29','5'));
    }
    public function test_purchase_inclusive_and_exclusive_totals(): void {
        $c=new PurchaseInvoiceCalculator;
        $x=$c->calculateLine(['quantity'=>'2','unit_cost'=>'57','tax_rate'=>'14','tax_inclusive'=>true]); self::assertSame('100.0000',$x['subtotal']);self::assertSame('14.0000',$x['tax_amount']);self::assertSame('114.0000',$x['line_total']);
        $x=$c->calculateDocument([['quantity'=>'2','unit_cost'=>'50','discount_type'=>'percentage','discount_value'=>'10','tax_rate'=>'14']]);self::assertSame('90.0000',$x['subtotal']);self::assertSame('12.6000',$x['taxAmount']);self::assertSame('102.6000',$x['totalAmount']);
    }
    public function test_product_cost_precision_and_quantity_rejection(): void {
        self::assertSame(['average_cost'=>'10.1250','sale_price'=>'25.0000'],(new ProductPricingContract)->forProductCard('10.125','25'));
        self::assertSame(2,ProductQuantity::normalize('2.000000'));
        $this->expectException(\Illuminate\Validation\ValidationException::class); ProductQuantity::normalize('1.5');
    }

    public function test_boundary_money_rounding_and_normalization(): void {
        foreach ([['0','0.00'],['000.004','0.00'],['000.005','0.01'],['-0.005','-0.01'],['-12.344','-12.34'],['12.345','12.35'],[' 1.995 ','2.00']] as [$input,$want]) self::assertSame($want,\App\Modules\Retail\Support\DecimalMoney::round($input));
        self::assertSame('2',\App\Modules\Retail\Support\DecimalMoney::round('1.5',0));
        self::assertSame('-1.2300',\App\Modules\Retail\Support\DecimalMoney::normalize(' -1.23 '));
        self::assertSame('1.23',\App\Modules\Retail\Support\DecimalMoney::normalize('1.239',2));
    }
    public function test_boundary_money_rejects_invalid_values(): void {
        foreach (['', 'a','1,2','+1'] as $value) {
            try {\App\Modules\Retail\Support\DecimalMoney::round($value);self::fail('invalid money accepted');} catch(InvalidArgumentException $e) {self::assertNotEmpty($e->getMessage());}
        }
        $this->expectException(InvalidArgumentException::class);\App\Modules\Retail\Support\DecimalMoney::normalize('1',-1);
    }
    public function test_boundary_quantity_format_signed_and_limits(): void {
        foreach ([[null,'0'],['','0'],['0002.000','2'],['-0002.000','-2'],['-000.000','0'],['+012','12']] as [$input,$want]) self::assertSame($want,ProductQuantity::format($input));
        self::assertSame(-2,ProductQuantity::normalizeSigned('-2.000'));self::assertSame(0,ProductQuantity::normalizeSigned('0',allowZero:true));self::assertSame(0,ProductQuantity::normalize('0.00',allowZero:true));
        foreach (['0','-1','1.1','bad',(string)PHP_INT_MAX.'0'] as $input) {
            try {ProductQuantity::normalize($input);self::fail('invalid quantity accepted');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('quantity',$e->errors());}
        }
        $this->expectException(\UnexpectedValueException::class);ProductQuantity::format('1.2');
    }
    public function test_boundary_product_prices_reject_invalid_cost(): void {
        foreach (['-1','1.00001','bad'] as $input) {
            try {(new ProductPricingContract)->forProductCard($input,'25');self::fail('invalid cost accepted');} catch(InvalidArgumentException $e) {self::assertNotEmpty($e->getMessage());}
        }
        self::assertSame('0.0000',(new ProductPricingContract)->explicitConsumerPrice('0'));
    }
    public function test_product_card_cost_is_optional_and_uses_one_validation_contract(): void {
        $contract = new ProductPricingContract;
        foreach ([null, '', '  '] as $cost) {
            self::assertSame(['average_cost'=>null,'sale_price'=>'25.0000'], $contract->forProductCard($cost, '25'));
            self::assertFalse(\Illuminate\Support\Facades\Validator::make(['cost'=>$cost], ['cost'=>$contract->costRules()])->fails());
        }
        foreach (['0', ' 12.34 ', '10.1234'] as $cost) {
            self::assertSame(bcadd(trim($cost), '0', 4), $contract->optionalCost($cost));
            self::assertFalse(\Illuminate\Support\Facades\Validator::make(['cost'=>$cost], ['cost'=>$contract->costRules()])->fails());
        }
        foreach (['-1', '1.00001', 'bad', '1e2'] as $cost) {
            self::assertTrue(\Illuminate\Support\Facades\Validator::make(['cost'=>$cost], ['cost'=>$contract->costRules()])->fails());
        }
        $this->expectException(InvalidArgumentException::class);
        $contract->forProductCard(null, null);
    }
    public function test_boundary_purchase_amount_discount_and_zero_tax(): void {
        $c=new PurchaseInvoiceCalculator;
        $x=$c->calculateLine(['quantity'=>'3','unit_cost'=>'10.50','discount_type'=>'amount','discount_value'=>'1.5','tax_rate'=>'0','tax_code'=>'QA']);
        self::assertSame('30.0000',$x['line_total']);self::assertSame('1.5000',$x['discount_amount']);self::assertSame('QA',$x['tax_code']);self::assertFalse($x['tax_inclusive']);
        $x=$c->calculateDocument([['quantity'=>'1','unit_cost'=>'1'],['quantity'=>'2','unit_cost'=>'2']]);self::assertSame('5.0000',$x['totalAmount']);self::assertCount(2,$x['calculated']);
        foreach ([['quantity'=>'0','unit_cost'=>'1'],['quantity'=>'1','unit_cost'=>'-1'],['quantity'=>'1','unit_cost'=>'1','discount_type'=>'other'],['quantity'=>'1','unit_cost'=>'1','discount_type'=>'amount','discount_value'=>'2'],['quantity'=>'1','unit_cost'=>'1','tax_rate'=>'101']] as $line) {
            try {$c->calculateLine($line);self::fail('invalid purchase accepted');} catch(InvalidArgumentException|\Illuminate\Validation\ValidationException $e) {self::assertNotEmpty($e->getMessage());}
        }
        $this->expectException(InvalidArgumentException::class);$c->calculateDocument([]);
    }
    public function test_boundary_pos_tax_discount_and_rounding_errors(): void {
        $c=new PosCalculationService;
        self::assertSame('0.00',$c->calculate([['quantity'=>'1','unit_price'=>'1','discount_amount'=>'1']])['total']);
        self::assertSame('1.01',$c->calculate([['quantity'=>'1','unit_price'=>'1.005']])['total']);
        self::assertSame('2.000000',$c->normalizeQuantity('2'));
        foreach ([fn()=>$c->calculate([]),fn()=>$c->calculate([['quantity'=>'1','unit_price'=>'1','discount_amount'=>'2']]),fn()=>$c->calculate([['quantity'=>'1','unit_price'=>'1']],'-1'),fn()=>$c->calculate([['quantity'=>'1','unit_price'=>'1']],'0',['applicable'=>true]),fn()=>$c->calculate([['quantity'=>'1','unit_price'=>'1']],'0',['applicable'=>true,'rate'=>'-1'])] as $call) {
            try {$call();self::fail('invalid POS values accepted');} catch(InvalidArgumentException $e) {self::assertNotEmpty($e->getMessage());}
        }
    }
}
