<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateStockTransferDraftAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /**
     * @param  array<int, array{product_id: int, quantity_requested: string, allocations?: array<int|string, string>}>  $lines
     */
    public function execute(
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): StockTransfer {
        Gate::authorize('transfers.create');

        $key = filled($idempotencyKey) ? trim((string) $idempotencyKey) : (string) Str::uuid();

        try {
            return $this->create($sourceStoreId, $destinationStoreId, $lines, $reasonCode, $notes, $key);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            return $this->replay($sourceStoreId, $destinationStoreId, $lines, $reasonCode, $notes, $key);
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity_requested: string}>  $lines
     */
    private function create(
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode,
        ?string $notes,
        string $idempotencyKey,
    ): StockTransfer {
        return DB::transaction(function () use ($sourceStoreId, $destinationStoreId, $lines, $reasonCode, $notes, $idempotencyKey): StockTransfer {
            $this->assertStores($sourceStoreId, $destinationStoreId);
            app(AssertNoOpenStockCountTransfer::class)->execute($sourceStoreId, $destinationStoreId);
            $normalizedLines = $this->normalizeLines($lines, $sourceStoreId);

            $existing = StockTransfer::query()->with('lines')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplayMatches($existing, $sourceStoreId, $destinationStoreId, $normalizedLines, $reasonCode, $notes);
            }

            $sourceStore = Store::query()->findOrFail($sourceStoreId);
            $sourceBranchId = $sourceStore->branch_id;
            $transfer = StockTransfer::query()->create([
                'transfer_number' => app(AllocateStockTransferNumberAction::class)->execute($sourceStore),
                'source_store_id' => $sourceStoreId,
                'destination_store_id' => $destinationStoreId,
                'status' => 'draft',
                'reason_code' => $this->nullableTrimmed($reasonCode),
                'notes' => $this->nullableTrimmed($notes),
                'requested_by' => Auth::id(),
                'idempotency_key' => $idempotencyKey,
                'lock_version' => 1,
            ]);

            foreach ($normalizedLines as $line) {
                $allocations = $line['allocations'] ?? [];
                unset($line['allocations']);
                $savedLine = $transfer->lines()->create($line);
                foreach ($allocations as $destinationId => $quantity) {
                    $savedLine->allocations()->create(['destination_store_id' => (int) $destinationId, 'quantity' => $quantity]);
                }
            }

            app(RecordAuditEvent::class)->execute(
                category: 'inventory',
                event: 'create_stock_transfer_draft',
                source: $transfer,
                after: $transfer->fresh('lines')->only(['transfer_number', 'source_store_id', 'destination_store_id', 'status', 'reason_code', 'requested_by', 'lock_version']),
                branchId: $sourceBranchId === null ? null : (int) $sourceBranchId,
                storeId: $sourceStoreId,
                reasonCode: $transfer->reason_code,
                metadata: [
                    'destination_store_id' => $destinationStoreId,
                    'line_count' => count($normalizedLines),
                    'product_ids' => array_column($normalizedLines, 'product_id'),
                    'stock_posted' => false,
                    'idempotency_key' => $idempotencyKey,
                ],
            );

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity_requested: string}>  $lines
     */
    private function replay(
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode,
        ?string $notes,
        string $idempotencyKey,
    ): StockTransfer {
        $this->assertStores($sourceStoreId, $destinationStoreId);

        return $this->assertReplayMatches(
            StockTransfer::query()->with('lines')->where('idempotency_key', $idempotencyKey)->firstOrFail(),
            $sourceStoreId,
            $destinationStoreId,
            $this->normalizeLines($lines, $sourceStoreId),
            $reasonCode,
            $notes,
        );
    }

    private function assertStores(int $sourceStoreId, int $destinationStoreId): void
    {
        if ($sourceStoreId === $destinationStoreId) {
            throw new InvalidArgumentException(__('Source and destination stores must be different.'));
        }

        $this->scope->execute($sourceStoreId);
        $this->scope->execute($destinationStoreId);
        $stores = Store::query()->whereKey([$sourceStoreId, $destinationStoreId])->get()->keyBy('id');
        $source = $stores->get($sourceStoreId);
        $destination = $stores->get($destinationStoreId);
        if ($source?->status !== 'active' || $destination?->status !== 'active') {
            throw new InvalidArgumentException(__('The selected store is inactive.'));
        }
        $sourceBranchCompanyId = $source->branch_id === null ? $source->company_id : $source->branch()->value('company_id');
        if ($source?->company_id === null
            || $destination?->company_id === null
            || $sourceBranchCompanyId === null
            || (int) $source->company_id !== (int) $sourceBranchCompanyId
            || (int) $source->company_id !== (int) $destination->company_id) {
            throw new InvalidArgumentException(__('Source and destination stores must belong to the same company.'));
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity_requested: string}>  $lines
     * @return array<int, array{product_id: int, quantity_requested: numeric-string, unit_cost: numeric-string}>
     */
    private function normalizeLines(array $lines, int $sourceStoreId): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
        }

        $sourceCompanyId = Store::query()->whereKey($sourceStoreId)->value('company_id');
        $normalized = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = Product::query()->sellable()->find($productId);
            if ($product === null) {
                throw new InvalidArgumentException(__('Every transfer line must reference an active product.'));
            }

            $quantity = $this->decimal($line['quantity_requested'] ?? '');
            if (bccomp($quantity, '0', 6) <= 0) {
                throw new InvalidArgumentException(__('Transfer quantity must be greater than zero.'));
            }
            if (bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0) {
                throw new InvalidArgumentException(__('This product does not allow fractional quantities.'));
            }
            if (array_key_exists($productId, $normalized)) {
                throw new InvalidArgumentException(__('A transfer cannot contain the same product more than once.'));
            }

            $balance = StockBalance::query()
                ->where('product_id', $productId)
                ->where('store_id', $sourceStoreId)
                ->lockForUpdate()
                ->first();
            $available = bcsub((string) ($balance?->on_hand ?? '0'), (string) ($balance?->reserved ?? '0'), 6);
            if (bccomp($available, $quantity, 6) < 0) {
                throw new InvalidArgumentException(__('The source store does not have enough available stock for this transfer.'));
            }

            $allocations = [];
            foreach ((array) ($line['allocations'] ?? []) as $destinationId => $allocated) {
                $destinationId = (int) $destinationId;
                if ($destinationId <= 0 || $destinationId === $sourceStoreId) {
                    throw new InvalidArgumentException(__('Every destination must be different from the source store.'));
                }
                $amount = $this->decimal($allocated);
                if (bccomp($amount, '0', 6) < 0) {
                    throw new InvalidArgumentException(__('Destination quantities cannot be negative.'));
                }
                if (bccomp($amount, '0', 6) > 0) {
                    $this->scope->execute($destinationId);
                    if (! Store::query()->whereKey($destinationId)->where('status', 'active')->where('company_id', $sourceCompanyId)->exists()) {
                        throw new InvalidArgumentException(__('Select an active destination within your company and store scope.'));
                    }
                    $allocations[$destinationId] = $amount;
                }
            }
            $allocatedTotal = array_reduce($allocations, fn (string $total, string $amount): string => bcadd($total, $amount, 6), '0');
            if (bccomp($allocatedTotal, $quantity, 6) > 0) {
                throw new InvalidArgumentException(__('The distributed quantity cannot exceed the available source quantity.'));
            }
            $normalized[$productId] = [
                'product_id' => $productId,
                'quantity_requested' => $quantity,
                'unit_cost' => $this->decimal($balance?->average_cost ?? '0'),
                'allocations' => $allocations,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array{product_id: int, quantity_requested: numeric-string, unit_cost: numeric-string}>  $lines
     */
    private function assertReplayMatches(
        StockTransfer $transfer,
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode,
        ?string $notes,
    ): StockTransfer {
        $matches = $transfer->source_store_id === $sourceStoreId
            && $transfer->destination_store_id === $destinationStoreId
            && $transfer->status === 'draft'
            && $transfer->reason_code === $this->nullableTrimmed($reasonCode)
            && $transfer->notes === $this->nullableTrimmed($notes)
            && $this->linesMatch($transfer, $lines);

        if (! $matches) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different transfer draft request.'));
        }

        return $transfer->load(['sourceStore', 'destinationStore', 'lines.product']);
    }

    /**
     * @param  array<int, array{product_id: int, quantity_requested: numeric-string, unit_cost: numeric-string}>  $lines
     */
    private function linesMatch(StockTransfer $transfer, array $lines): bool
    {
        if ($transfer->lines->count() !== count($lines)) {
            return false;
        }

        foreach ($lines as $line) {
            $existing = $transfer->lines->firstWhere('product_id', $line['product_id']);
            if ($existing === null || bccomp((string) $existing->quantity_requested, $line['quantity_requested'], 6) !== 0) {
                return false;
            }
        }

        return true;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid transfer quantity.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
