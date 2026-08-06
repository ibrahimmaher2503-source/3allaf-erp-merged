<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
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
            $return = PurchaseReturn::query()->with(['lines', 'reason', 'store'])->lockForUpdate()->findOrFail($id);
            $user = Auth::user();
            if ($user instanceof User && ! $user->is_super_admin && ($return->store === null || ! Store::query()->visibleTo($user)->whereKey($return->store_id)->exists())) {
                throw new InvalidArgumentException(__('You are not authorized for this return store.'));
            }
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
            $nextVersion = $return->lock_version + 1;
            $return->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'lock_version' => $nextVersion,
            ]);
            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'purchase_returns',
                sourceId: (string) $return->id,
                sourceVersion: (string) $nextVersion,
                requestedAction: 'approve',
                requestPermission: 'purchase_returns.edit',
                branchId: $return->store?->branch_id,
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
                limitContext: [
                    'amount' => (string) $return->total_amount,
                    'configured_limit' => app(SupplierReturnPolicy::class)->approvalLimit(),
                    'source' => 'financial_setting_versions',
                ],
                idempotencyKey: 'purchase-return-approval:'.$return->id.':'.$nextVersion,
            ));
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
