<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApproveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.approve');

        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);
            if ($transfer->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted transfers can be approved.'));
            }
            if ($transfer->source_store_id === $transfer->destination_store_id) {
                throw new InvalidArgumentException(__('Source and destination stores must be different.'));
            }
            $before = $transfer->only(['status', 'lock_version']);
            $transfer->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'approve_stock_transfer', $transfer, $before, $transfer->only(['status', 'approved_by', 'approved_at', 'lock_version']), storeId: $transfer->source_store_id);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
