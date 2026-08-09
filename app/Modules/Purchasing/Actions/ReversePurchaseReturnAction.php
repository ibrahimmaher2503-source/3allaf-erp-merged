<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ExecuteCorrection;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\CorrectionReferenceData;
use App\Modules\Platform\Enums\CorrectionType;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReversePurchaseReturnAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.reverse');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reversal reason is required.'));
        }

        return DB::transaction(function () use ($id, $reason, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $user = Auth::user();
            if ($user instanceof User && ! $user->is_super_admin && ! Store::query()->visibleTo($user)->whereKey($return->store_id)->exists()) {
                throw new InvalidArgumentException(__('You are not authorized for this return store.'));
            }
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status === 'reversed') {
                return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
            }
            if ($return->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved supplier returns can be reversed.'));
            }
            if ($return->approved_by === Auth::id() || $return->created_by === Auth::id()) {
                throw new InvalidArgumentException(__('The creator or original approver cannot reverse this supplier return.'));
            }
            $reversalMovementIds = [];
            foreach ($return->lines as $line) {
                $original = StockMovement::query()->where('source_type', PurchaseReturn::class)->where('source_id', $return->id)->where('source_line_id', $line->id)->where('movement_type', 'purchase_return')->lockForUpdate()->first();
                if ($original === null) {
                    throw new InvalidArgumentException(__('The original stock movement is missing; reversal is blocked.'));
                }
                $key = 'purchase-return-reversal:'.$return->id.':line:'.$line->id;
                if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
                    $reversalMovementIds[] = (int) StockMovement::query()->where('idempotency_key', $key)->value('id');

                    continue;
                }
                $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $return->store_id)->lockForUpdate()->firstOrFail();
                $quantity = bcsub('0', (string) $original->quantity, 6);
                // @phpstan-ignore argument.type
                $cost = bcsub('0', (string) $original->total_cost, 4);
                $newQuantity = bcadd((string) $balance->on_hand, $quantity, 6);
                $newValue = bcadd((string) $balance->total_value, $cost, 4);
                $average = bccomp($newQuantity, '0', 6) === 0 ? '0.0000' : bcdiv($newValue, $newQuantity, 4);
                $reversal = StockMovement::query()->create(['product_id' => $line->product_id, 'store_id' => $return->store_id, 'movement_type' => 'purchase_return_reversal', 'quantity' => $quantity, 'unit_cost' => $original->unit_cost, 'total_cost' => $cost, 'consumed_cost' => 0, 'source_type' => PurchaseReturn::class, 'source_id' => $return->id, 'source_line_id' => $line->id, 'idempotency_key' => $key, 'reversal_of_id' => $original->id, 'posted_at' => now(), 'created_by' => Auth::id()]);
                $reversalMovementIds[] = $reversal->id;
                $balance->update(['on_hand' => $newQuantity, 'total_value' => $newValue, 'average_cost' => $average, 'version' => $balance->version + 1]);
            }
            $before = $return->only(['status', 'lock_version']);
            $requestId = Context::get('request_id') ?? (string) Str::uuid();
            $reference = new CorrectionReferenceData(
                originalSourceType: $return->sourceType(), originalSourceId: $return->sourceId(),
                originalSourceVersion: $return->sourceVersion(), originalSourceHash: $return->sourceHash(),
                correctionType: CorrectionType::Reversal, correctionSourceType: StockMovement::class,
                correctionSourceId: (string) min($reversalMovementIds), reason: $reason,
                requestedBy: $user->id, approvedBy: $user->id,
                branchId: $return->sourceBranchId(), storeId: $return->sourceStoreId(),
                requestId: $requestId, idempotencyKey: 'purchase-return-reversal:'.$return->id, createdAt: now(),
            );
            app(ExecuteCorrection::class)->execute(
                $reference,
                $return,
                $user,
                [CorrectionType::Reversal],
                fn (User $actor): mixed => Gate::forUser($actor)->authorize('purchase_returns.reverse'),
                function () use ($return, $reason): PurchaseReturn {
                    $return->mutateApprovedDocument(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => Auth::id(), 'reversal_reason' => $reason, 'updated_by' => Auth::id(), 'lock_version' => $return->lock_version + 1]);

                    return $return;
                },
            );
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reverse_supplier_return', source: $return, before: $before, after: $return->only(['status', 'reversed_at', 'reversed_by', 'lock_version']), storeId: $return->store_id, reasonText: $reason, metadata: ['reversal_movement_type' => 'purchase_return_reversal', 'reversal_movement_ids' => $reversalMovementIds, 'correction_request_id' => $requestId]);

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }
}
