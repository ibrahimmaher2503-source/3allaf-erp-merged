<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\ResolveStockCountProduct;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SetStockCountQuantityAction
{
    public function execute(int $countId, int $storeId, string $lookup, string $quantity, string $requestId, string $deviceId, ?string $note): StockCountLine
    {
        Gate::authorize('stock_counts.participate');

        return DB::transaction(function () use ($countId, $storeId, $lookup, $quantity, $requestId, $deviceId, $note): StockCountLine {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Count input is closed for this session.'));
            }
            abort_unless($count->locations()->where('store_id', $storeId)->exists()
                && $count->members()->where('user_id', $actor->id)->exists(), 404);
            app(AssertInventoryStoreScope::class)->execute($storeId);
            $product = app(ResolveStockCountProduct::class)->resolve($lookup);
            $target = (string) ProductQuantity::normalize($quantity, 'quantity', true);
            $line = $count->lines()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
            if ($line === null) {
                if ($count->count_type === 'full' || in_array($count->scope_type, ['category', 'supplier'], true)) {
                    throw new InvalidArgumentException(__('This product is outside the count-session scope.'));
                }
                $balance = StockBalance::query()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
                $watermark = StockMovement::query()->where('store_id', $storeId)->where('product_id', $product->id)->max('id') ?? 0;
                $line = $count->lines()->create(['store_id' => $storeId, 'product_id' => $product->id,
                    'reference_on_hand' => $balance?->on_hand ?? '0', 'reference_movement_id' => $watermark, 'is_counted' => false]);
            }
            if ($count->contributions()->where('request_id', $requestId)->exists()) {
                return $line;
            }
            $note = $note === null ? $line->notes : trim($note);
            $changed = ! $line->is_counted || bccomp((string) $line->counted_quantity, $target, 6) !== 0;
            if ($changed) {
                StockBalance::query()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
                $watermark = StockMovement::query()->where('store_id', $storeId)->where('product_id', $product->id)->max('id') ?? 0;
                $previous = $line->is_counted ? (string) $line->counted_quantity : '0';
                $delta = bcsub($target, $previous, 6);
                if (bccomp($delta, '0', 6) !== 0) {
                    $count->contributions()->create(['store_id' => $storeId, 'product_id' => $product->id,
                        'quantity' => $delta, 'counter_id' => $actor->id, 'input_method' => 'manual',
                        'request_id' => $requestId, 'device_id' => $deviceId, 'reason_code' => 'manual_override',
                        'contributed_at' => now(), 'metadata' => ['target_quantity' => $target]]);
                }
                $line->update(['counted_quantity' => $target, 'is_counted' => true, 'explicit_zero' => $target === '0',
                    'input_method' => 'manual', 'counted_at' => now(), 'counted_movement_id' => $watermark,
                    'counted_by' => $actor->id, 'completed_at' => null, 'completed_by' => null, 'notes' => $note]);
            } elseif ($note !== $line->notes) {
                $line->update(['notes' => $note]);
            }
            app(RecordAuditEvent::class)->execute('inventory', 'set_stock_count_quantity', $line, null,
                ['counted_quantity' => $line->counted_quantity, 'counted_at' => $line->counted_at, 'notes' => $line->notes],
                storeId: $storeId, metadata: ['request_id' => $requestId, 'device_id' => $deviceId]);

            return $line->fresh(['product', 'counter']);
        });
    }
}
