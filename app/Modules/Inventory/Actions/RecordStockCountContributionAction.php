<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountContribution;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\ResolveStockCountProduct;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordStockCountContributionAction
{
    public function execute(int $countId, int $storeId, string $lookup, string $quantity, string $requestId, string $deviceId, string $method = 'scan', ?int $reverses = null, ?string $reason = null): StockCountContribution
    {
        Gate::authorize('stock_counts.participate');

        return DB::transaction(function () use ($countId, $storeId, $lookup, $quantity, $requestId, $deviceId, $method, $reverses, $reason): StockCountContribution {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Count input is closed for this session.'));
            }if (! $count->locations()->where('store_id', $storeId)->exists() || ! $count->members()->where('user_id', $actor->id)->exists()) {
                abort(404);
            }app(AssertInventoryStoreScope::class)->execute($storeId);
            $existing = $count->contributions()->where('request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }$product = app(ResolveStockCountProduct::class)->resolve($lookup);
            $line = $count->lines()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $line) {
                if ($count->count_type === 'full' || in_array($count->scope_type, ['category', 'supplier'], true)) {
                    throw new InvalidArgumentException(__('This product is outside the count-session scope.'));
                }$balance = StockBalance::query()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
                $reference = (string) ($balance?->on_hand ?? '0');
                $line = $count->lines()->create(['store_id' => $storeId, 'product_id' => $product->id, 'reference_on_hand' => $reference, 'reference_movement_id' => StockMovement::query()->where('store_id', $storeId)->where('product_id', $product->id)->max('id') ?? 0, 'is_counted' => false]);
            }$normalized = (string) ProductQuantity::normalize($quantity, 'quantity', true);
            if (bccomp($normalized, '0', 6) === 0) {
                throw new InvalidArgumentException(__('A contribution must add or reverse a non-zero quantity.'));
            }if ($reverses !== null) {
                Gate::authorize('stock_counts.correct');
                $count->contributions()->whereKey($reverses)->lockForUpdate()->firstOrFail();
                if ($reason === null || trim($reason) === '') {
                    throw new InvalidArgumentException(__('A correction reason is required.'));
                }$normalized = bccomp($normalized, '0', 6) > 0 ? '-'.$normalized : $normalized;
            }$contribution = $count->contributions()->create(['store_id' => $storeId, 'product_id' => $product->id, 'quantity' => $normalized, 'counter_id' => $actor->id, 'input_method' => $method, 'request_id' => $requestId, 'device_id' => $deviceId, 'reason_code' => $reason, 'reverses_contribution_id' => $reverses, 'contributed_at' => now()]);
            $total = $count->contributions()->where('store_id', $storeId)->where('product_id', $product->id)->sum('quantity');
            $balance = StockBalance::query()->where('store_id', $storeId)->where('product_id', $product->id)->lockForUpdate()->first();
            $watermark = StockMovement::query()->where('store_id', $storeId)->where('product_id', $product->id)->max('id') ?? 0;
            $line->update(['counted_quantity' => $total, 'is_counted' => true, 'explicit_zero' => false, 'input_method' => $method, 'counted_at' => now(), 'counted_movement_id' => $watermark, 'counted_by' => $actor->id, 'completed_at' => null, 'completed_by' => null]);
            app(RecordAuditEvent::class)->execute('inventory', $reverses ? 'correct_stock_count_contribution' : 'record_stock_count_contribution', $contribution, null, ['quantity' => $normalized, 'total' => $total], branchId: $count->branch_id, storeId: $storeId, reasonCode: $reason, metadata: ['device_id' => $deviceId, 'request_id' => $requestId]);

            return $contribution;
        });
    }
}
