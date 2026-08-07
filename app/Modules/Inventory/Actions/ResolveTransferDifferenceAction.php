<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ResolveTransferDifferenceAction
{
    public function execute(int $id, string $differenceType, string $differenceReason): StockTransfer
    {
        Gate::authorize('transfers.difference');

        return DB::transaction(function () use ($id, $differenceType, $differenceReason): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($transfer->status !== 'difference_review' || $transfer->difference_status !== 'under_review') {
                throw new InvalidArgumentException(__('Only transfers under difference review can be resolved.'));
            }
            if (! in_array($differenceType, ['shortage', 'damage', 'refusal'], true) || trim($differenceReason) === '') {
                throw new InvalidArgumentException(__('A valid difference type and reason are required.'));
            }

            $line = $transfer->lines->first();
            if ($line === null || bccomp((string) $line->difference_quantity, '0', 6) <= 0) {
                throw new InvalidArgumentException(__('The transfer has no unresolved quantity.'));
            }
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $line->update(['difference_type' => $differenceType, 'difference_reason' => $differenceReason]);
            $transfer->update([
                'status' => 'received',
                'difference_status' => 'resolved',
                'reason_code' => $differenceType,
                'reason_notes' => $differenceReason,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('inventory', 'resolve_transfer_difference', $transfer, $before, $transfer->only(['status', 'difference_status', 'approved_by', 'approved_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $differenceType, reasonText: $differenceReason, metadata: ['resolved_quantity' => $line->difference_quantity]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
