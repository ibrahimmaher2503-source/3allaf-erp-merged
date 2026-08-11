<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostInventoryMovement
{
    public function execute(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType = null, ?int $sourceId = null, ?int $sourceLineId = null, bool $allowNegative = false, ?int $reversalOfId = null): StockMovement
    {
        try {
            return $this->attempt($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $allowNegative, $reversalOfId);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_key')) {
                throw $e;
            }

            // Two concurrent callers can both pass the pre-insert idempotency
            // check before either commits (the check-then-insert is not itself
            // lockable). The unique index is the real guard against a
            // duplicate row; this recovers the loser's request as a normal
            // idempotent replay instead of surfacing a raw DB error.
            return $this->replayExisting($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $reversalOfId);
        }
    }

    private function attempt(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, bool $allowNegative, ?int $reversalOfId): StockMovement
    {
        return DB::transaction(function () use ($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $allowNegative, $reversalOfId): StockMovement {
            $quantity = $this->decimal($quantity);

            $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $productId, $storeId, $quantity, $movementType, $unitCost, $sourceType, $sourceId, $sourceLineId, $reversalOfId);
            }

            if (bccomp($quantity, '0', 6) === 0) {
                throw new InvalidArgumentException(__('Inventory movement quantity cannot be zero.'));
            }

            $product = Product::query()->select(['id', 'fractional_quantity'])->findOrFail($productId);
            $isFractional = bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0;
            if ($isFractional && ! $product->fractional_quantity) {
                throw new InvalidArgumentException(__('This product does not allow fractional quantities.'));
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
                'reversal_of_id' => $reversalOfId,
                'created_by' => Auth::id(),
            ]);

            $balance->update(['on_hand' => $newOnHand, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);

            return $movement;
        });
    }

    private function assertReplaySafe(StockMovement $existing, int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, ?int $reversalOfId): StockMovement
    {
        $normalizedCost = $unitCost !== null ? $this->decimal($unitCost) : null;
        $replaySafe = $existing->product_id === $productId
            && $existing->store_id === $storeId
            && $existing->movement_type === $movementType
            && bccomp((string) $existing->quantity, $quantity, 6) === 0
            && ($normalizedCost === null || bccomp((string) $existing->unit_cost, $normalizedCost, 4) === 0)
            && $existing->source_type === $sourceType
            && $existing->source_id === $sourceId
            && $existing->source_line_id === $sourceLineId
            && ($existing->reversal_of_id === null ? null : (int) $existing->reversal_of_id) === $reversalOfId;

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    private function replayExisting(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, ?int $reversalOfId): StockMovement
    {
        $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            // The row that caused the unique-key violation is not (yet) visible
            // to this connection's read. This should not happen outside of
            // extreme replication lag; surface it rather than mask it.
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $this->assertReplaySafe($existing, $productId, $storeId, $this->decimal($quantity), $movementType, $unitCost, $sourceType, $sourceId, $sourceLineId, $reversalOfId);
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
