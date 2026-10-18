<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateStockTransferDraftAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int, array{product_id: int, quantity_requested: string, allocations?: array<int|string, string>}> $lines */
    public function execute(
        int $id,
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode = null,
        ?string $notes = null,
        ?int $expectedVersion = null,
    ): StockTransfer {
        Gate::authorize('transfers.edit');

        return DB::transaction(function () use ($id, $sourceStoreId, $destinationStoreId, $lines, $reasonCode, $notes, $expectedVersion): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);
            if ($transfer->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft transfers can be edited.'));
            }
            if ($expectedVersion !== null && (int) $transfer->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This transfer changed in another session. Please reload before saving.'));
            }
            if ($sourceStoreId === $destinationStoreId) {
                throw new InvalidArgumentException(__('Source and destination stores must be different.'));
            }
            $this->scope->execute($sourceStoreId);
            $this->scope->execute($destinationStoreId);
            $source = Store::query()->findOrFail($sourceStoreId);
            $destination = Store::query()->findOrFail($destinationStoreId);
            $originalSourceBranchId = Store::query()->whereKey($transfer->source_store_id)->value('branch_id');
            if ($source->branch_id === null || (int) $source->branch_id !== (int) $originalSourceBranchId) {
                throw new InvalidArgumentException(__('A numbered transfer cannot move to another source branch. Create a new transfer instead.'));
            }
            $sourceBranchCompanyId = $source->branch()->value('company_id');
            if ($source->company_id === null
                || $destination->company_id === null
                || $sourceBranchCompanyId === null
                || (int) $source->company_id !== (int) $sourceBranchCompanyId
                || (int) $source->company_id !== (int) $destination->company_id) {
                throw new InvalidArgumentException(__('Source and destination stores must belong to the same company.'));
            }
            app(AssertNoOpenStockCountTransfer::class)->execute($sourceStoreId, $destinationStoreId);
            $normalized = $this->normalizeLines($lines, $sourceStoreId);
            $before = $transfer->only(['source_store_id', 'destination_store_id', 'reason_code', 'notes', 'status', 'lock_version']);
            $transfer->mutateApprovedDocument([
                'source_store_id' => $sourceStoreId,
                'destination_store_id' => $destinationStoreId,
                'reason_code' => $this->nullableTrimmed($reasonCode),
                'notes' => $this->nullableTrimmed($notes),
                'requested_by' => Auth::id(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            $transfer->lines()->delete();
            foreach ($normalized as $line) {
                $allocations = $line['allocations'] ?? [];
                unset($line['allocations']);
                $savedLine = $transfer->lines()->create($line);
                foreach ($allocations as $destinationId => $quantity) {
                    $savedLine->allocations()->create(['destination_store_id' => (int) $destinationId, 'quantity' => $quantity]);
                }
            }
            app(RecordAuditEvent::class)->execute(
                category: 'inventory',
                event: 'update_stock_transfer_draft',
                source: $transfer,
                before: $before,
                after: $transfer->fresh('lines')->only(['transfer_number', 'source_store_id', 'destination_store_id', 'status', 'reason_code', 'notes', 'lock_version']),
                storeId: $sourceStoreId,
                reasonCode: $transfer->reason_code,
                metadata: ['destination_store_id' => $destinationStoreId, 'line_count' => count($normalized), 'actor_id' => Auth::id()],
            );

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @param array<int, array{product_id: int, quantity_requested: string}> $lines @return array<int, array<string, mixed>> */
    private function normalizeLines(array $lines, int $sourceStoreId): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
        }
        $normalized = [];
        foreach ($lines as $line) {
            $product = Product::query()->sellable()->find((int) ($line['product_id'] ?? 0));
            if ($product === null) {
                throw new InvalidArgumentException(__('Every transfer line must reference an active product.'));
            }
            $quantity = trim((string) ($line['quantity_requested'] ?? ''));
            if (! preg_match('/^\d+(?:\.\d+)?$/', $quantity)) {
                throw new InvalidArgumentException(__('Invalid transfer quantity.'));
            }
            $quantity = bcadd($quantity, '0', 6);
            if (bccomp($quantity, '0', 6) <= 0 || bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0) {
                throw new InvalidArgumentException(__('Transfer quantity is invalid for this product.'));
            }
            if (isset($normalized[$product->id])) {
                throw new InvalidArgumentException(__('A transfer cannot contain the same product more than once.'));
            }
            $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $sourceStoreId)->lockForUpdate()->first();
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
                $amount = bcadd((string) $allocated, '0', 6);
                if (bccomp($amount, '0', 6) < 0) {
                    throw new InvalidArgumentException(__('Destination quantities cannot be negative.'));
                }
                if (bccomp($amount, '0', 6) > 0) {
                    $this->scope->execute($destinationId);
                    $allocations[$destinationId] = $amount;
                }
            }
            $allocatedTotal = array_reduce($allocations, fn (string $total, string $amount): string => bcadd($total, $amount, 6), '0');
            if (bccomp($allocatedTotal, $quantity, 6) > 0) {
                throw new InvalidArgumentException(__('The distributed quantity cannot exceed the available source quantity.'));
            }
            $normalized[$product->id] = ['product_id' => $product->id, 'quantity_requested' => $quantity, 'unit_cost' => bcadd((string) ($balance?->average_cost ?? '0'), '0', 4), 'allocations' => $allocations];
        }

        return array_values($normalized);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
