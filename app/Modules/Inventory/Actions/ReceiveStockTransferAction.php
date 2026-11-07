<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceipt;
use App\Modules\Inventory\Models\StockTransferReceivingDraft;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReceiveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function finalizeDraft(int $transferId, int $destinationStoreId): StockTransfer
    {
        Gate::authorize('transfers.receive');

        return DB::transaction(function () use ($transferId, $destinationStoreId): StockTransfer {
            $this->scope->execute($destinationStoreId);
            $transfer = StockTransfer::query()->with('lines.allocations')->lockForUpdate()->findOrFail($transferId);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            $draft = StockTransferReceivingDraft::query()->where('stock_transfer_id', $transferId)
                ->where('destination_store_id', $destinationStoreId)->lockForUpdate()->firstOrFail();
            if ($draft->finalized_at !== null) {
                throw new InvalidArgumentException(__('This destination receipt is already final.'));
            }
            $rows = [];
            $difference = false;
            foreach ($transfer->lines as $line) {
                if ($line->allocations->isEmpty() && (int) $transfer->destination_store_id === $destinationStoreId) {
                    $rows[] = [$line, null, (string) $line->id, (string) $line->quantity_dispatched];
                }
                foreach ($line->allocations->where('destination_store_id', $destinationStoreId) as $allocation) {
                    $rows[] = [$line, $allocation, $line->id.'.'.$allocation->id, (string) $allocation->quantity];
                }
            }
            if ($rows === []) {
                throw new InvalidArgumentException(__('The receipt contains a line outside this destination allocation.'));
            }
            foreach ($rows as [, , $key, $sent]) {
                $received = (string) ($draft->received_quantities[$key] ?? '');
                if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $received) || bccomp($received, $sent, 6) > 0) {
                    throw new InvalidArgumentException(__('Received quantity must be between zero and the allocated quantity.'));
                }
                $difference = $difference || bccomp($received, $sent, 6) < 0;
            }
            if ($difference && (blank($draft->difference_type) || blank($draft->difference_reason))) {
                throw new InvalidArgumentException(__('A shortage/damage/refusal reason is required for a transfer difference.'));
            }
            foreach ($rows as [$line, $allocation, $key, $sent]) {
                $received = (string) $draft->received_quantities[$key];
                if (bccomp($received, '0', 6) > 0) {
                    app(PostInventoryMovement::class)->execute(
                        $line->product_id, $destinationStoreId, $received, 'transfer_receipt',
                        (string) $line->unit_cost, 'TRANSFER-RECEIPT-FINAL:'.$draft->id.':'.$key,
                        StockTransfer::class, $transfer->id, $line->id,
                    );
                }
                app(PostInventoryMovement::class)->adjustInTransit($line->product_id, $destinationStoreId, '-'.$sent);
                if ($allocation !== null) {
                    $allocation->update(['quantity_received' => $received]);
                }
            }
            $draft->update(['finalized_at' => now(), 'finalized_by' => Auth::id()]);
            $transfer = $transfer->fresh('lines.allocations');
            foreach ($transfer->lines as $line) {
                $sent = $line->allocations->isEmpty() ? (string) $line->quantity_dispatched : (string) $line->allocations->sum('quantity');
                $received = $line->allocations->isEmpty() ? (string) ($draft->received_quantities[$line->id] ?? '0') : (string) $line->allocations->sum('quantity_received');
                $line->mutateApprovedParentLine([
                    'quantity_received' => $received,
                    'difference_quantity' => bcsub($sent, $received, 6),
                    'difference_type' => bccomp($sent, $received, 6) > 0 ? ($draft->difference_type ?: $line->difference_type) : null,
                    'difference_reason' => bccomp($sent, $received, 6) > 0 ? ($draft->difference_reason ?: $line->difference_reason) : null,
                ]);
            }
            $allocatedDestinations = $transfer->allocations->pluck('destination_store_id')->filter()->unique();
            $destinationIds = $allocatedDestinations->isNotEmpty() ? $allocatedDestinations : collect([$transfer->destination_store_id]);
            $completed = StockTransferReceivingDraft::query()->where('stock_transfer_id', $transferId)->whereNotNull('finalized_at')->whereIn('destination_store_id', $destinationIds)->count();
            $allFinal = $completed === $destinationIds->count();
            $hasDifference = $transfer->lines()->where('difference_quantity', '>', 0)->exists();
            $status = $allFinal ? 'received' : 'in_transit';
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $transfer->mutateApprovedDocument([
                'status' => $status,
                'difference_status' => $hasDifference ? 'recorded' : null,
                'received_by' => $allFinal ? Auth::id() : null,
                'received_at' => $allFinal ? now() : null,
                'lock_version' => $transfer->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('inventory', 'receive_stock_transfer', $transfer, $before, $transfer->only(['status', 'difference_status', 'received_by', 'received_at', 'lock_version']), storeId: $destinationStoreId, reasonCode: $draft->difference_type, reasonText: $draft->difference_reason, metadata: [
                'receiving_draft_id' => $draft->id,
                'destination_store_id' => $destinationStoreId,
                'allocated_quantities' => collect($rows)->mapWithKeys(fn (array $row): array => [$row[2] => $row[3]])->all(),
                'received_quantities' => $draft->received_quantities,
                'variance_quantities' => collect($rows)->mapWithKeys(fn (array $row): array => [$row[2] => bcsub($row[3], (string) $draft->received_quantities[$row[2]], 6)])->all(),
            ]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @param array<int|string, string> $receivedQuantities */
    public function execute(int $id, array $receivedQuantities, ?string $differenceType, ?string $differenceReason, ?string $idempotencyKey = null): StockTransfer
    {
        Gate::authorize('transfers.receive');
        $key = filled($idempotencyKey) ? trim((string) $idempotencyKey) : (string) Str::uuid();

        return DB::transaction(function () use ($id, $receivedQuantities, $differenceType, $differenceReason, $key): StockTransfer {
            $transfer = StockTransfer::query()->with(['lines.allocations'])->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: false, destination: true);
            foreach ($transfer->allocations->pluck('destination_store_id')->unique() as $destinationId) {
                $this->scope->execute((int) $destinationId);
            }
            // [merge rajeh] Multi-destination (allocation) transfers keep rajeh's per-allocation receiving path.
            if ($this->isAllocationReceipt($transfer, $receivedQuantities)) {
                app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
                if ($transfer->status !== 'in_transit') {
                    throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
                }

                return $this->receiveAllocationBatch($transfer, $receivedQuantities);
            }
            $payloadHash = $this->payloadHash($transfer, $receivedQuantities, $differenceType, $differenceReason);
            $existing = StockTransferReceipt::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ((int) $existing->stock_transfer_id !== $transfer->id || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new InvalidArgumentException(__('This idempotency key was already used for a different transfer receipt.'));
                }

                return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
            }

            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            $normalized = $this->normalize($transfer, $receivedQuantities, $differenceType, $differenceReason);

            $receipt = StockTransferReceipt::query()->create([
                'stock_transfer_id' => $transfer->id,
                'receipt_number' => 'TRR-'.$transfer->id.'-'.str_pad((string) (StockTransferReceipt::query()->where('stock_transfer_id', $transfer->id)->count() + 1), 4, '0', STR_PAD_LEFT),
                'status' => $normalized['closes_difference'] ? 'difference' : ($normalized['completes_transfer'] ? 'complete' : 'partial'),
                'received_by' => Auth::id(),
                'received_at' => now(),
                'difference_type' => $normalized['difference_type'],
                'difference_reason' => $normalized['difference_reason'],
                'idempotency_key' => $key,
                'payload_hash' => $payloadHash,
            ]);

            $poster = app(PostInventoryMovement::class);
            foreach ($transfer->lines as $line) {
                $event = $normalized['lines'][$line->id];
                if (bccomp($event['received'], '0', 6) > 0) {
                    $poster->execute($line->product_id, $transfer->destination_store_id, $event['received'], 'transfer_receipt', (string) $line->unit_cost, 'TRANSFER-RECEIPT:'.$receipt->id.':'.$line->id, StockTransfer::class, $transfer->id, $line->id);
                }
                $released = bcadd($event['received'], $event['difference'], 6);
                if (bccomp($released, '0', 6) > 0) {
                    $poster->adjustInTransit($line->product_id, $transfer->destination_store_id, '-'.$released);
                }
                $receipt->lines()->create(['stock_transfer_line_id' => $line->id, 'quantity_received' => $event['received'], 'difference_quantity' => $event['difference']]);
                $line->mutateApprovedParentLine([
                    'quantity_received' => bcadd((string) $line->quantity_received, $event['received'], 6),
                    'difference_quantity' => bcadd((string) $line->difference_quantity, $event['difference'], 6),
                    'difference_type' => $event['difference'] === '0.000000' ? $line->difference_type : $normalized['difference_type'],
                    'difference_reason' => $event['difference'] === '0.000000' ? $line->difference_reason : $normalized['difference_reason'],
                ]);
            }

            $status = $normalized['closes_difference'] ? 'difference_review' : ($normalized['completes_transfer'] ? 'received' : 'in_transit');
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $transfer->mutateApprovedDocument([
                'status' => $status,
                'difference_status' => $status === 'difference_review' ? 'under_review' : null,
                'received_by' => Auth::id(),
                'received_at' => now(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('inventory', 'receive_stock_transfer', $transfer, $before, $transfer->only(['status', 'difference_status', 'received_by', 'received_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $normalized['difference_type'], reasonText: $normalized['difference_reason'], metadata: ['receipt_id' => $receipt->id, 'received_quantities' => $normalized['lines'], 'idempotency_key' => $key]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @param array<int|string, string> $receivedQuantities @return array<string, mixed> */
    private function normalize(StockTransfer $transfer, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): array
    {
        if ($transfer->lines->isEmpty()) {
            throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
        }
        $differenceType = trim((string) $differenceType) ?: null;
        $differenceReason = trim((string) $differenceReason) ?: null;
        if ($differenceType !== null && ! in_array($differenceType, ['shortage', 'damage', 'refusal'], true)) {
            throw new InvalidArgumentException(__('A valid transfer difference type is required.'));
        }
        if (($differenceType === null) !== ($differenceReason === null)) {
            throw new InvalidArgumentException(__('A shortage/damage/refusal type and reason must be provided together.'));
        }

        $lines = [];
        $hasReceived = false;
        $remainingAfter = '0.000000';
        foreach ($transfer->lines as $line) {
            $received = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
            $remaining = bcsub((string) $line->quantity_dispatched, bcadd((string) $line->quantity_received, (string) $line->difference_quantity, 6), 6);
            if (bccomp($received, $remaining, 6) > 0) {
                throw new InvalidArgumentException(__('Received quantity cannot exceed the remaining in-transit quantity.'));
            }
            $after = bcsub($remaining, $received, 6);
            $difference = $differenceType === null ? '0.000000' : $after;
            $lines[$line->id] = ['received' => $received, 'difference' => $difference];
            $hasReceived = $hasReceived || bccomp($received, '0', 6) > 0;
            $remainingAfter = bcadd($remainingAfter, $after, 6);
        }
        if ($differenceType !== null && bccomp($remainingAfter, '0', 6) === 0) {
            throw new InvalidArgumentException(__('A transfer difference can only be recorded when a quantity remains unreceived.'));
        }
        if (! $hasReceived && $differenceType === null) {
            throw new InvalidArgumentException(__('At least one received quantity must be greater than zero.'));
        }

        return ['lines' => $lines, 'difference_type' => $differenceType, 'difference_reason' => $differenceReason, 'closes_difference' => $differenceType !== null && bccomp($remainingAfter, '0', 6) > 0, 'completes_transfer' => bccomp($remainingAfter, '0', 6) === 0];
    }

    /** @param array<int|string, string> $receivedQuantities */
    private function payloadHash(StockTransfer $transfer, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): string
    {
        $lineIds = $transfer->lines->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        if (array_diff(array_map('strval', array_keys($receivedQuantities)), $lineIds) !== []) {
            throw new InvalidArgumentException(__('Received quantities contain a line outside this transfer.'));
        }
        $lines = [];
        foreach ($transfer->lines->sortBy('id') as $line) {
            $lines[(string) $line->id] = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
        }

        return hash('sha256', json_encode([
            'lines' => $lines,
            'difference_type' => trim((string) $differenceType) ?: null,
            'difference_reason' => trim((string) $differenceReason) ?: null,
        ], JSON_THROW_ON_ERROR));
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
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid transfer quantity.'));
        }

        return bcadd($value, '0', 6);
    }
}
