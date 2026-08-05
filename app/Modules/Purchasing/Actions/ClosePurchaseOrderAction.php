<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ClosePurchaseOrderAction
{
    /**
     * Close an active or received purchase order.
     */
    public function execute(int $id, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize('purchase_orders.edit');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseOrder {
            $userId = Auth::id();
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);

            if ($expectedVersion !== null && $po->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before closing.'));
            }

            if (! in_array($po->status, ['submitted', 'partially_received', 'received'], true)) {
                throw new InvalidArgumentException(__('Only submitted, partially received, or received purchase orders can be closed.'));
            }

            $before = $po->only(['status', 'closed_at', 'closed_by', 'lock_version']);

            $po->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $userId,
                'updated_by' => $userId,
                'lock_version' => $po->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'close_purchase_order',
                source: $po,
                before: $before,
                after: $po->fresh()->only(['status', 'closed_at', 'closed_by', 'lock_version']),
                branchId: $po->branch_id,
                storeId: $po->store_id,
            );

            return $po->fresh(['supplier', 'store', 'lines.product']);
        });
    }
}
