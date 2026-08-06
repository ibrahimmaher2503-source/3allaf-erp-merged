<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitPurchaseReturnAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.edit');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()->with(['lines', 'reason'])->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft supplier returns can be submitted.'));
            }
            if ($return->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A supplier return must contain at least one line.'));
            }
            if ($return->reason === null || ! $return->reason->is_active) {
                throw new InvalidArgumentException(__('An active supplier return reason is required.'));
            }

            $before = $return->only(['status', 'lock_version']);
            $return->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'lock_version' => $return->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'submit_supplier_return',
                source: $return,
                before: $before,
                after: $return->only(['status', 'submitted_at', 'lock_version']),
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
            );

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }
}
