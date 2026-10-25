<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceivingDraft;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveStockTransferReceivingDraftAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int|string, mixed> $quantities */
    public function execute(int $transferId, int $destinationStoreId, array $quantities, ?string $differenceType, ?string $differenceReason): StockTransferReceivingDraft
    {
        Gate::authorize('transfers.receive');

        return DB::transaction(function () use ($transferId, $destinationStoreId, $quantities, $differenceType, $differenceReason): StockTransferReceivingDraft {
            $this->scope->execute($destinationStoreId);
            $transfer = StockTransfer::query()->with('lines.allocations')->lockForUpdate()->findOrFail($transferId);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            $allowed = [];
            foreach ($transfer->lines as $line) {
                if ($line->allocations->isEmpty() && (int) $transfer->destination_store_id === $destinationStoreId) {
                    $allowed[(string) $line->id] = (string) $line->quantity_dispatched;
                }
                foreach ($line->allocations->where('destination_store_id', $destinationStoreId) as $allocation) {
                    $allowed[$line->id.'.'.$allocation->id] = (string) $allocation->quantity;
                }
            }
            if ($allowed === [] || array_diff(array_map('strval', array_keys($quantities)), array_keys($allowed)) !== []) {
                throw new InvalidArgumentException(__('The receipt contains a line outside this destination allocation.'));
            }
            $normalized = [];
            $hasDifference = false;
            foreach ($allowed as $key => $sent) {
                $received = ProductQuantity::normalize($quantities[$key] ?? null, 'received_quantities.'.$key, true);
                if (bccomp((string) $received, $sent, 6) > 0) {
                    throw new InvalidArgumentException(__('Received quantity must be between zero and the allocated quantity.'));
                }
                $normalized[$key] = (string) $received;
                $hasDifference = $hasDifference || bccomp((string) $received, $sent, 6) < 0;
            }
            if ($differenceType !== null && ! in_array($differenceType, ['shortage', 'damage', 'refusal'], true)) {
                throw new InvalidArgumentException(__('A valid transfer difference type is required.'));
            }
            $draft = StockTransferReceivingDraft::query()->where('stock_transfer_id', $transferId)
                ->where('destination_store_id', $destinationStoreId)->lockForUpdate()->first();
            if ($draft?->finalized_at !== null) {
                throw new InvalidArgumentException(__('This destination receipt is already final.'));
            }
            $before = $draft?->only(['received_quantities', 'difference_type', 'difference_reason']);
            $draft ??= new StockTransferReceivingDraft(['stock_transfer_id' => $transferId, 'destination_store_id' => $destinationStoreId]);
            $draft->fill([
                'received_quantities' => $normalized,
                'difference_type' => $hasDifference ? $differenceType : null,
                'difference_reason' => $hasDifference ? trim((string) $differenceReason) ?: null : null,
            ])->save();
            app(RecordAuditEvent::class)->execute('inventory', 'save_transfer_receiving_draft', $transfer, $before, $draft->only(['received_quantities', 'difference_type', 'difference_reason']), storeId: $destinationStoreId, metadata: ['destination_store_id' => $destinationStoreId, 'stock_posted' => false]);

            return $draft;
        });
    }
}
