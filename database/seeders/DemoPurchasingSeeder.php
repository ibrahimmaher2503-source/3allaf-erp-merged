<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoPurchasingSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $approver = User::query()->where('username', 'qa-approver')->firstOrFail();
            $supplier = Supplier::query()->where('code', 'QA-SUPPLIER')->firstOrFail();
            $warehouse = Store::query()->where('code', 'QA-SAHIL-WH')->firstOrFail();
            $product = Product::query()->where('item_code', 'QA-PURCHASE')->firstOrFail();
            Auth::login($administrator);
            try {
                $draft = app(SavePurchaseInvoiceAction::class)->execute([
                    'supplier_id' => $supplier->id,
                    'store_id' => $warehouse->id,
                    'supplier_reference' => 'QA-DRAFT-INVOICE',
                    'invoice_date' => now()->toDateString(),
                    'currency_code' => 'EGP',
                    'notes' => fake()->sentence(),
                ], [[
                    'product_id' => $product->id,
                    'quantity' => '2.000000',
                    'unit_cost' => '45.0000',
                    'discount_type' => null,
                    'discount_value' => '0.0000',
                    'tax_rate' => '0.0000',
                ]]);
                if ($draft->status !== 'draft') {
                    throw new \LogicException('QA purchase invoice draft unexpectedly posted stock.');
                }

                $order = app(SavePurchaseOrderAction::class)->execute([
                    'supplier_id' => $supplier->id,
                    'branch_id' => $warehouse->branch_id,
                    'store_id' => $warehouse->id,
                    'order_date' => now()->toDateString(),
                    'payment_terms' => fake()->sentence(),
                    'notes' => fake()->sentence(),
                ], [[
                    'product_id' => $product->id,
                    'quantity_ordered' => '10.000000',
                    'unit_cost' => '45.0000',
                    'notes' => fake()->sentence(),
                ]]);
                $order = app(SubmitPurchaseOrderAction::class)->execute($order->id, $order->lock_version);
                if ($order->status === 'submitted') {
                    Auth::login($approver);
                    $order = app(ApprovePurchaseOrderAction::class)->execute($order->id, $order->lock_version);
                    Auth::login($administrator);
                }
                $order = $order->fresh('lines');
                $invoice = app(SavePurchaseInvoiceAction::class)->execute([
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $order->id,
                    'store_id' => $warehouse->id,
                    'supplier_reference' => 'QA-APPROVED-INVOICE',
                    'invoice_date' => now()->toDateString(),
                    'currency_code' => 'EGP',
                    'notes' => fake()->sentence(),
                ], [[
                    'product_id' => $product->id,
                    'purchase_order_line_id' => $order->lines->firstOrFail()->id,
                    'quantity' => '10.000000',
                    'unit_cost' => '45.0000',
                    'discount_type' => null,
                    'discount_value' => '0.0000',
                    'tax_rate' => '0.0000',
                ]]);
                $invoice = app(SubmitPurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
                if ($invoice->status === 'submitted') {
                    Auth::login($approver);
                    app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id, $invoice->lock_version);
                }
            } finally {
                Auth::logout();
            }
        });
    }
}
