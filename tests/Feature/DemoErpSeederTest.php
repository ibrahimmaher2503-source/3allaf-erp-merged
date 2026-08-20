<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DemoErpSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_normal_seeding_builds_one_idempotent_demo_procurement_inventory_and_pos_chain(): void
    {
        app(DatabaseSeeder::class)->run();

        $product = Product::query()->where('item_code', 'DEMO-PRODUCT-001')->firstOrFail();
        $supplier = Supplier::query()->where('code', 'DEMO-SUPPLIER-001')->firstOrFail();
        $customer = Customer::query()->where('idempotency_key', 'demo-customer-001')->firstOrFail();
        $order = PurchaseOrder::query()->where('notes', 'demo:purchase-order-001')->firstOrFail();
        $invoice = PurchaseInvoice::query()->where('supplier_reference', 'DEMO-SUPPLIER-INVOICE-001')->firstOrFail();
        $return = PurchaseReturn::query()->where('idempotency_key', 'demo-purchase-return-001')->firstOrFail();
        $transfer = StockTransfer::query()->where('idempotency_key', 'demo-transfer-001')->firstOrFail();
        $sale = Sale::query()->where('idempotency_key', 'demo-sale-001')->with('payments', 'lines')->firstOrFail();

        self::assertSame('active', $supplier->status);
        self::assertSame('received', $order->status);
        self::assertSame('approved', $invoice->status);
        self::assertSame('approved', $return->status);
        self::assertSame('received', $transfer->status);
        self::assertSame('approved', $sale->status);
        self::assertSame($customer->id, $sale->customer_id);
        self::assertNotEmpty($customer->public_id);
        self::assertSame('15.00', (string) $sale->paid_total);
        self::assertCount(1, $sale->payments);
        self::assertSame('9.000000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $sale->store_id)->value('on_hand'));
        self::assertSame('90.0000', (string) StockBalance::query()->where('product_id', $product->id)->where('store_id', $sale->store_id)->value('total_value'));
        self::assertTrue(PosShift::query()->where('idempotency_key', 'demo-pos-shift-001')->active()->exists());
        self::assertTrue(CashDrawer::query()->where('code', 'DEMO-POS-01')->exists());
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'purchase_receipt')->count());
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'transfer_dispatch')->count());
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'transfer_receipt')->count());
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'sale')->count());

        $counts = [
            'products' => Product::query()->where('item_code', 'like', 'DEMO-PRODUCT-%')->count(),
            'customers' => Customer::query()->where('idempotency_key', 'demo-customer-001')->count(),
            'orders' => PurchaseOrder::query()->where('notes', 'demo:purchase-order-001')->count(),
            'invoices' => PurchaseInvoice::query()->where('supplier_reference', 'DEMO-SUPPLIER-INVOICE-001')->count(),
            'returns' => PurchaseReturn::query()->where('idempotency_key', 'demo-purchase-return-001')->count(),
            'transfers' => StockTransfer::query()->where('idempotency_key', 'demo-transfer-001')->count(),
            'sales' => Sale::query()->where('idempotency_key', 'demo-sale-001')->count(),
        ];

        app(DatabaseSeeder::class)->run();

        self::assertSame($counts['products'], Product::query()->where('item_code', 'like', 'DEMO-PRODUCT-%')->count());
        self::assertSame($counts['customers'], Customer::query()->where('idempotency_key', 'demo-customer-001')->count());
        self::assertSame($counts['orders'], PurchaseOrder::query()->where('notes', 'demo:purchase-order-001')->count());
        self::assertSame($counts['invoices'], PurchaseInvoice::query()->where('supplier_reference', 'DEMO-SUPPLIER-INVOICE-001')->count());
        self::assertSame($counts['returns'], PurchaseReturn::query()->where('idempotency_key', 'demo-purchase-return-001')->count());
        self::assertSame($counts['transfers'], StockTransfer::query()->where('idempotency_key', 'demo-transfer-001')->count());
        self::assertSame($counts['sales'], Sale::query()->where('idempotency_key', 'demo-sale-001')->count());
    }
}
