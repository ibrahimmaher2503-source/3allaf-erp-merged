<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseReceivingAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class MergePurchaseReceivingRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function invoice(): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'submitted']);
        $actor = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $actor->storeScopes()->create(['store_id' => $invoice->store_id, 'status' => 'active']);
        $this->actingAs($actor);
        $product = Product::factory()->fractional()->create();
        $invoice->lines()->create([
            'product_id' => $product->id, 'quantity' => '10', 'entered_quantity' => '10',
            'conversion_factor_snapshot' => '1', 'entered_unit_price' => '20', 'unit_cost' => '20',
            'subtotal' => '200', 'line_total' => '200', 'discount_amount' => '0', 'tax_amount' => '0',
        ]);

        return $invoice->fresh('lines');
    }

    public function test_receiving_preserves_fractional_base_quantity(): void
    {
        $invoice = $this->invoice();
        $result = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$invoice->lines->first()->id => '2.5'], $invoice->lock_version);
        self::assertSame('2.500000', $result->lines->first()->quantity);
        self::assertSame('50.0000', $result->total_amount);
    }

    public function test_receiving_keeps_charges_in_supplier_payable(): void
    {
        $invoice = $this->invoice();
        $invoice->charges()->create(['charge_type' => 'freight', 'amount' => '30', 'accounting_treatment' => 'landed_cost']);
        $result = app(SavePurchaseReceivingAction::class)->execute($invoice->id, [$invoice->lines->first()->id => '5'], $invoice->lock_version);
        self::assertSame('130.0000', $result->total_amount);
    }

    public function test_order_bag_price_and_discount_are_converted_for_a_base_unit_invoice(): void
    {
        $invoice = $this->invoice();
        $product = $invoice->lines->first()->product;
        $unit = Unit::query()->create(['code' => 'QA-KG-MERGE', 'name_ar' => 'كيلو', 'name_en' => 'kg', 'dimension' => 'weight', 'decimal_places' => 6, 'status' => 'active']);
        $base = $product->productUnits()->create(['unit_id' => $unit->id, 'conversion_factor' => '1', 'is_base_unit' => true, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        $order = PurchaseOrder::factory()->create(['supplier_id' => $invoice->supplier_id, 'store_id' => $invoice->store_id, 'branch_id' => $invoice->store->branch_id]);
        $line = $order->lines()->create(['product_id' => $product->id, 'line_number' => 1, 'quantity_ordered' => '100', 'quantity_received' => '0', 'entered_quantity' => '2', 'entered_unit_price' => '1000', 'conversion_factor_snapshot' => '50', 'unit_cost' => '20', 'discount_type' => 'amount', 'discount_value' => '100', 'discount_amount' => '100', 'subtotal' => '1900', 'line_total' => '1900']);
        $order->update(['status' => 'approved']);
        $result = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $invoice->supplier_id, 'store_id' => $invoice->store_id, 'purchase_order_id' => $order->id],
            [['product_id' => $product->id, 'product_unit_id' => $base->id, 'purchase_order_line_id' => $line->id, 'quantity' => '25', 'unit_cost' => '20', 'base_consumer_price' => '30']],
        );
        self::assertSame('20.0000', $result->lines->first()->unit_cost);
        self::assertSame('25.0000', $result->discount_amount);
        self::assertSame('475.0000', $result->total_amount);
    }
}
