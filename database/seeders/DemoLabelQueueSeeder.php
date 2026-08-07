<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\LabelPrintEvent;
use App\Modules\Pricing\Models\LabelQueue;
use App\Modules\Pricing\Models\PriceLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoLabelQueueSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! (bool) config('app.demo_auth', false)) {
            throw new LogicException('DemoLabelQueueSeeder requires local Demo Auth.');
        }

        $admin = User::query()->where('username', 'demo-admin')->firstOrFail();
        $store = Store::query()->where('code', 'DEMO-SELL')->firstOrFail();
        $pricedProduct = Product::query()->where('item_code', 'DEMO-PROD-001')->firstOrFail();
        $unpricedProduct = Product::query()->where('item_code', 'DEMO-PROD-002')->firstOrFail();
        $priceLine = PriceLine::query()
            ->with('version')
            ->where('product_id', $pricedProduct->id)
            ->where('store_id', $store->id)
            ->whereHas('version', function ($query): void {
                $query->where('state', 'approved')
                    ->where(fn ($scope) => $scope->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($scope) => $scope->whereNull('effective_to')->orWhere('effective_to', '>', now()));
            })
            ->firstOrFail();

        DB::transaction(function () use ($admin, $store, $pricedProduct, $unpricedProduct, $priceLine): void {
            $this->seedBalance($pricedProduct, $store, '5.000', '2.000', '125.00');
            $this->seedBalance($unpricedProduct, $store, '4.000', '0.000', '90.00');

            $printer = PrinterConfiguration::query()->updateOrCreate(
                ['name' => 'Demo Label Printer - Local'],
                [
                    'printer_type' => 'thermal',
                    'paper_size' => '50x30mm',
                    'template_name' => 'demo_price_label_v1',
                    'connection_type' => 'network',
                    'ip_address' => '192.0.2.25',
                    'port' => 9100,
                    'is_default' => true,
                    'status' => 'active',
                    'notes' => 'DEMO ONLY. Documentation IP; no real hardware or Production acceptance.',
                ],
            );

            $queue = LabelQueue::query()->updateOrCreate(
                ['generation_key' => 'DEMO-LABEL:'.$priceLine->id.':'.$store->id],
                [
                    'price_version_id' => $priceLine->price_version_id,
                    'price_line_id' => $priceLine->id,
                    'product_id' => $pricedProduct->id,
                    'store_id' => $store->id,
                    'branch_id' => $store->branch_id,
                    'printer_configuration_id' => $printer->id,
                    'required_quantity' => 5,
                    'printed_quantity' => 2,
                    'status' => 'partial',
                    'template_name' => $printer->template_name,
                    'paper_size' => $printer->paper_size,
                    'notes' => 'DEMO ONLY. Two initial labels are recorded; three remain pending.',
                ],
            );

            LabelPrintEvent::query()->firstOrCreate(
                ['idempotency_key' => 'DEMO-LABEL-EVENT:'.$queue->id.':initial-001'],
                [
                    'label_queue_id' => $queue->id,
                    'printer_configuration_id' => $printer->id,
                    'user_id' => $admin->id,
                    'event_type' => 'initial',
                    'quantity' => 2,
                    'copies' => 1,
                    'reason' => 'DEMO ONLY initial print walkthrough.',
                    'printed_at' => Carbon::now()->subHour(),
                ],
            );
        });
    }

    private function seedBalance(Product $product, Store $store, string $onHand, string $reserved, string $averageCost): void
    {
        StockBalance::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_id' => $store->id],
            [
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'in_transit' => '0.000',
                'average_cost' => $averageCost,
                'total_value' => number_format((float) $onHand * (float) $averageCost, 4, '.', ''),
                'version' => 1,
            ],
        );
    }
}
