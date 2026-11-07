<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class DispatchStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.dispatch');

        return $this->dispatchApproved($id);
    }

    public function dispatchApproved(int $id): StockTransfer
    {
        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with(['lines.allocations'])->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: true, destination: false);
            if ($transfer->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved transfers can be dispatched.'));
            }
            if ($transfer->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
            }
            $poster = app(PostInventoryMovement::class);
            foreach ($transfer->lines as $line) {
                $quantity = $line->allocations->isEmpty() ? (string) $line->quantity_requested : (string) $line->allocations->sum('quantity');
                if (bccomp($quantity, '0', 6) <= 0 || bccomp($quantity, (string) $line->quantity_requested, 6) > 0
                    || $line->allocations->contains(fn ($allocation): bool => bccomp((string) $allocation->quantity, '0', 6) <= 0)) {
                    throw new InvalidArgumentException(__('Transfer allocations must be positive and cannot exceed the reviewed quantity.'));
                }
                $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $transfer->source_store_id)->lockForUpdate()->first();
                $available = $balance === null ? '0' : bcsub((string) $balance->on_hand, (string) $balance->reserved, 6);
                if (bccomp($available, $quantity, 6) < 0) {
                    throw new InvalidArgumentException(__('The source store does not have enough available stock for this transfer.'));
                }
                $poster->execute($line->product_id, $transfer->source_store_id, '-'.$quantity, 'transfer_dispatch', (string) $line->unit_cost, 'DEMO-TRANSFER-DISPATCH:'.$transfer->id.':'.$line->id, StockTransfer::class, $transfer->id, $line->id);
                $allocations = $line->allocations;
                if ($allocations->isEmpty()) {
                    $allocations = collect([(object) ['destination_store_id' => $transfer->destination_store_id, 'quantity' => $quantity]]);
                }
                foreach ($allocations as $allocation) {
                    if (bccomp((string) $allocation->quantity, '0', 6) > 0) {
                        $poster->adjustInTransit($line->product_id, (int) $allocation->destination_store_id, (string) $allocation->quantity);
                    }
                }
                $line->mutateApprovedParentLine(['quantity_dispatched' => $quantity]);
            }
            $before = $transfer->only(['status', 'lock_version']);
            $transfer->mutateApprovedDocument(['status' => 'in_transit', 'dispatched_by' => Auth::id(), 'dispatched_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'dispatch_stock_transfer', $transfer, $before, $transfer->only(['status', 'dispatched_by', 'dispatched_at', 'lock_version']), storeId: $transfer->source_store_id);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
