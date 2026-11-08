<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Support\LowStockRule;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Models\Alert;

final class EvaluateAlertsAction
{
    public function execute(?User $user = null): int
    {
        $count = 0;
        $storeIds = Store::query()->when($user !== null, fn ($query) => $query->visibleTo($user))
            ->whereNotNull('branch_id')->where('status', 'active')->pluck('id')->all();
        StockBalance::reportRows($storeIds)
            ->with(['product', 'store.branch'])
            ->where(function ($query): void {
                $query->whereRaw('(stock_balances.on_hand - stock_balances.reserved) <= 0')->orWhere(fn ($low): mixed => LowStockRule::apply($low));
            })
            ->orderBy('store_id')->orderBy('product_id')->chunk(200, function ($balances) use (&$count): void {
                foreach ($balances as $balance) {
                    $store = $balance->store;
                    if ($store === null) {
                        continue;
                    }
                    $available = (float) $balance->on_hand - (float) $balance->reserved;
                    $isZero = $available <= 0;
                    $description = ($store->branch?->name_ar ?? $store->branch_id).' — '.$store->name_ar.' — '.($balance->product?->name_ar ?? $balance->product_id).' — '.number_format($available, 2);
                    $metadata = ['product_id' => $balance->product_id, 'available_qty' => $available, 'reorder_threshold' => (float) ($balance->product?->reorder_threshold ?? 0)];
                    $alert = Alert::query()->firstOrCreate(['alert_key' => ($isZero ? 'zero-stock:' : 'low-stock:').$balance->product_id.':'.$balance->store_id], [
                        'alert_type' => $isZero ? 'zero_stock' : 'low_stock', 'severity' => $isZero ? 'critical' : 'action_required',
                        'title' => $isZero ? 'Zero stock item' : 'Low stock item',
                        'description' => $description,
                        'source_type' => StockBalance::class, 'source_id' => (string) $balance->product_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                        'status' => 'open', 'metadata' => $metadata,
                    ]);
                    if ($alert->wasRecentlyCreated) {
                        $count++;
                    } else {
                        $alert->update(['description' => $description, 'metadata' => $metadata]);
                    }

                    if (! $isZero) {
                        Alert::query()->where('alert_key', 'zero-stock:'.$balance->product_id.':'.$balance->store_id)->where('status', '!=', 'resolved')->update(['status' => 'resolved', 'resolved_at' => now()]);
                    }

                    $priced = $balance->product !== null && bccomp((string) ($balance->product->sale_price ?? '0'), '0', 4) > 0;
                    if (! $priced) {
                        $unpriced = Alert::query()->firstOrCreate(['alert_key' => 'unpriced:'.$balance->product_id.':'.$balance->store_id], [
                            'alert_type' => 'unpriced_product', 'severity' => 'warning', 'title' => 'Unpriced product',
                            'description' => 'The canonical base consumer selling price is missing or not positive.',
                            'source_type' => Product::class, 'source_id' => (string) $balance->product_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                            'status' => 'open', 'metadata' => ['product_id' => $balance->product_id],
                        ]);
                        if ($unpriced->wasRecentlyCreated) {
                            $count++;
                        }
                    } else {
                        Alert::query()->where('alert_key', 'unpriced:'.$balance->product_id.':'.$balance->store_id)
                            ->where('status', '!=', 'resolved')->update(['status' => 'resolved', 'resolved_at' => now()]);
                    }
                }
            });

        return $count;
    }
}
