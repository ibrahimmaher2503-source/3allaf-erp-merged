<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReceiveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int|string, string> $receivedQuantities */
    public function execute(int $id, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): StockTransfer
    {
        Gate::authorize('transfers.receive');

        return DB::transaction(function () use ($id, $receivedQuantities, $differenceType, $differenceReason): StockTransfer {
            $transfer = StockTransfer::query()->with(['lines.allocations'])->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: false, destination: true);
            foreach ($transfer->allocations->pluck('destination_store_id')->unique() as $destinationId) {
                $this->scope->execute((int) $destinationId);
            }
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            if ($transfer->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
            }
            if ($this->isAllocationReceipt($transfer, $receivedQuantities)) {
                return $this->receiveAllocationBatch($transfer, $receivedQuantities);
            }
            if ($differenceType !== null && ! in_array($differenceType, ['shortage', 'damage', 'refusal'], true)) {
                throw new InvalidArgumentException(__('A valid transfer difference type is required.'));
            }
            $totalDifference = '0.000000';
            $receivedByLine = [];
            foreach ($transfer->lines as $line) {
                $received = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
                $dispatched = $this->decimal($line->quantity_dispatched);
                if (bccomp($received, '0', 6) < 0 || bccomp($received, $dispatched, 6) > 0) {
                    throw new InvalidArgumentException(__('Received quantity must be between zero and the dispatched quantity.'));
                }
                $difference = bcsub($dispatched, $received, 6);
                $receivedByLine[$line->id] = ['received' => $received, 'dispatched' => $dispatched, 'difference' => $difference];
                $totalDifference = bcadd($totalDifference, $difference, 6);
            }
            if (bccomp($totalDifference, '0', 6) > 0 && (trim((string) $differenceType) === '' || trim((string) $differenceReason) === '')) {
                throw new InvalidArgumentException(__('A shortage/damage/refusal reason is required for a transfer difference.'));
            }
            foreach ($transfer->lines as $line) {
                $quantities = $receivedByLine[$line->id];
                $allocations = $line->allocations;
                if ($allocations->isEmpty()) {
                    $allocations = collect([(object) ['id' => 0, 'destination_store_id' => $transfer->destination_store_id, 'quantity' => $quantities['dispatched'], 'quantity_received' => 0]]);
                }
                foreach ($allocations as $allocation) {
                    $key = $line->id.'.'.$allocation->id;
                    $receivedAllocation = $this->decimal($receivedQuantities[$key] ?? $receivedQuantities[(string) $key] ?? ($allocations->count() === 1 ? ($receivedQuantities[$line->id] ?? '0') : '0'));
                    $dispatchedAllocation = $this->decimal($allocation->quantity);
                    if (bccomp($receivedAllocation, $dispatchedAllocation, 6) > 0) {
                        throw new InvalidArgumentException(__('Received quantity must be between zero and the allocated quantity.'));
                    }
                    if (bccomp($receivedAllocation, '0', 6) > 0) {
                        app(PostInventoryMovement::class)->execute($line->product_id, (int) $allocation->destination_store_id, $receivedAllocation, 'transfer_receipt', (string) $line->unit_cost, 'DEMO-TRANSFER-RECEIPT:'.$transfer->id.':'.$line->id.':'.$allocation->id, StockTransfer::class, $transfer->id, $line->id);
                    }
                    app(PostInventoryMovement::class)->adjustInTransit($line->product_id, (int) $allocation->destination_store_id, '-'.$dispatchedAllocation);
                    if ($allocation->id > 0) {
                        $allocation->update(['quantity_received' => $receivedAllocation]);
                    }
                }
                $line->mutateApprovedParentLine(['quantity_received' => $quantities['received'], 'difference_quantity' => $quantities['difference'], 'difference_type' => $differenceType, 'difference_reason' => $differenceReason]);
            }
            $status = bccomp($totalDifference, '0', 6) === 0 ? 'received' : 'difference_review';
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $transfer->mutateApprovedDocument(['status' => $status, 'difference_status' => $status === 'received' ? null : 'under_review', 'reason_code' => $differenceType, 'reason_notes' => $differenceReason, 'received_by' => Auth::id(), 'received_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'receive_stock_transfer', $transfer, $before, $transfer->only(['status', 'difference_status', 'received_by', 'received_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $differenceType, reasonText: $differenceReason, metadata: ['received_quantities' => $receivedByLine, 'difference_quantity' => $totalDifference]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /**
     * @param  array<int|string, string>  $receivedQuantities
     */
    private function isAllocationReceipt(StockTransfer $transfer, array $receivedQuantities): bool
    {
        return $transfer->lines->contains(fn ($line): bool => $line->allocations->isNotEmpty())
            && collect($receivedQuantities)->keys()->contains(fn ($key): bool => str_contains((string) $key, '.'));
    }

    /**
     * @param  array<int|string, string>  $receivedQuantities
     */
    private function receiveAllocationBatch(StockTransfer $transfer, array $receivedQuantities): StockTransfer
    {
        foreach ($transfer->lines as $line) {
            foreach ($line->allocations as $allocation) {
                $key = $line->id.'.'.$allocation->id;
                $amount = $this->decimal($receivedQuantities[$key] ?? $receivedQuantities[(string) $key] ?? '0');
                $remaining = bcsub((string) $allocation->quantity, (string) $allocation->quantity_received, 6);
                if (bccomp($amount, '0', 6) < 0 || bccomp($amount, $remaining, 6) > 0) {
                    throw new InvalidArgumentException(__('Received quantity must be between zero and the remaining allocated quantity.'));
                }
                if (bccomp($amount, '0', 6) === 0) {
                    continue;
                }
                app(PostInventoryMovement::class)->execute(
                    $line->product_id,
                    (int) $allocation->destination_store_id,
                    $amount,
                    'transfer_receipt',
                    (string) $line->unit_cost,
                    'DEMO-TRANSFER-RECEIPT:'.$transfer->id.':'.$line->id.':'.$allocation->id.':'.$allocation->quantity_received,
                    StockTransfer::class,
                    $transfer->id,
                    $line->id,
                );
                app(PostInventoryMovement::class)->adjustInTransit($line->product_id, (int) $allocation->destination_store_id, '-'.$amount);
                $allocation->update(['quantity_received' => bcadd((string) $allocation->quantity_received, $amount, 6)]);
            }
            $received = (string) $line->allocations->sum('quantity_received');
            $dispatched = (string) $line->allocations->sum('quantity');
            $line->mutateApprovedParentLine(['quantity_received' => $received, 'difference_quantity' => bcsub($dispatched, $received, 6)]);
        }

        $fresh = $transfer->fresh('lines.allocations');
        $complete = $fresh->lines->every(fn ($line): bool => $line->allocations->every(fn ($allocation): bool => bccomp((string) $allocation->quantity_received, (string) $allocation->quantity, 6) === 0));
        $before = $transfer->only(['status', 'difference_status', 'lock_version']);
        $transfer->mutateApprovedDocument([
            'status' => $complete ? 'received' : 'in_transit',
            'difference_status' => null,
            'received_by' => $complete ? Auth::id() : null,
            'received_at' => $complete ? now() : null,
            'lock_version' => $transfer->lock_version + 1,
        ]);
        app(RecordAuditEvent::class)->execute(
            'inventory',
            'receive_stock_transfer',
            $transfer,
            $before,
            $transfer->only(['status', 'received_by', 'received_at', 'lock_version']),
            storeId: $transfer->destination_store_id,
            metadata: ['allocation_receipt' => true],
        );

        return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
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
}
