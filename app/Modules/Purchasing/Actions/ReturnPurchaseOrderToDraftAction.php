<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RejectRequest;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReturnPurchaseOrderToDraftAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize('purchase_orders.approve');
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException(__('A return reason is required.'));

        return DB::transaction(function () use ($id, $reason, $expectedVersion): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);
            $this->assertStoreScope($order->store_id);
            if ($order->status !== 'submitted') throw new InvalidArgumentException(__('Only a submitted purchase order can be returned to draft.'));
            if ($expectedVersion !== null && $order->lock_version !== $expectedVersion) throw new InvalidArgumentException(__('This purchase order was modified in another session. Reload it before returning it.'));
            $approval = ApprovalRecord::query()->where('source_type', 'purchase_orders')->where('source_id', (string) $order->id)
                ->where('requested_action', 'approve')->where('approval_state', ApprovalState::Pending->value)->lockForUpdate()->firstOrFail();
            app(RejectRequest::class)->execute($approval, (string) $order->lock_version, $reason);
            $before = $order->only(['status', 'submitted_at', 'submitted_by', 'lock_version']);
            $order->update(['status' => 'draft', 'submitted_at' => null, 'submitted_by' => null, 'updated_by' => Auth::id(), 'lock_version' => $order->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('procurement', 'return_purchase_order_to_draft', $order, $before, $order->only(['status', 'submitted_at', 'submitted_by', 'lock_version']), branchId: $order->branch_id, storeId: $order->store_id, reasonText: $reason, metadata: ['approval_record_id' => $approval->id]);

            return $order->fresh(['supplier', 'store', 'branch', 'lines.product']);
        });
    }

    private function assertStoreScope(?int $storeId): void
    {
        $user = Auth::user();
        if ($storeId === null || ! $user instanceof User || $user->is_super_admin) {
            return;
        }
        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected purchase-order store.'));
        }
    }
}
