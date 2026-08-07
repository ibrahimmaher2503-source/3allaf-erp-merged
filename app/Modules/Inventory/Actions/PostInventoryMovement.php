<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostInventoryMovement
{
    public function execute(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType = null, ?int $sourceId = null, ?int $sourceLineId = null, bool $allowNegative = false): StockMovement
    {
        return DB::transaction(function () use ($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $allowNegative): StockMovement {
            $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $quantity = $this->decimal($quantity);
            if (bccomp($quantity, '0', 6) === 0) {
                throw new InvalidArgumentException(__('Inventory movement quantity cannot be zero.'));
            }

            $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->first();
            if ($balance === null) {
                $balance = StockBalance::query()->create(['product_id' => $productId, 'store_id' => $storeId, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
                $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            }

            $newOnHand = bcadd($this->decimal($balance->on_hand), $quantity, 6);
            if (bccomp($newOnHand, '0', 6) < 0 && ! $allowNegative) {
                throw new InvalidArgumentException(__('Negative stock is blocked by default. An authorized override with a reason is required.'));
            }

            $cost = $unitCost !== null ? $this->decimal($unitCost) : $this->decimal($balance->average_cost);
            $oldValue = $this->decimal($balance->total_value);
            $consumedCost = '0.0000';
            if (bccomp($quantity, '0', 6) > 0) {
                $totalCost = bcmul($quantity, $cost, 4);
                $newValue = bcadd($oldValue, $totalCost, 4);
            } else {
                $consumedCost = bcmul(bcsub('0', $quantity, 6), $this->decimal($balance->average_cost), 4);
                $totalCost = bcsub('0', $consumedCost, 4);
                $newValue = bcsub($oldValue, $consumedCost, 4);
            }
            $newAverage = bccomp($newOnHand, '0', 6) === 0 ? '0.0000' : bcdiv($newValue, $newOnHand, 4);

            $movement = StockMovement::query()->create([
                'product_id' => $productId,
                'store_id' => $storeId,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => $totalCost,
                'consumed_cost' => $consumedCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'idempotency_key' => $idempotencyKey,
                'posted_at' => now(),
                'created_by' => Auth::id(),
            ]);

            $balance->update(['on_hand' => $newOnHand, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);

            return $movement;
        });
    }

    public function adjustInTransit(int $productId, int $storeId, string $quantity): void
    {
        $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->first();
        if ($balance === null) {
            $balance = StockBalance::query()->create(['product_id' => $productId, 'store_id' => $storeId, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
            $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }
        $newTransit = bcadd($this->decimal($balance->in_transit), $this->decimal($quantity), 6);
        if (bccomp($newTransit, '0', 6) < 0) {
            throw new InvalidArgumentException(__('In-transit stock cannot become negative.'));
        }
        $balance->update(['in_transit' => $newTransit, 'version' => $balance->version + 1]);
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal inventory quantity or cost.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }
}
