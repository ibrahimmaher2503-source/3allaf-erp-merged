<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Support\StockCountReconciliation;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitStockCountAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockCount
    {
        Gate::authorize('stock_counts.submit');

        return DB::transaction(function () use ($id): StockCount {
            $count = StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($count->status !== 'in_progress') {
                throw new InvalidArgumentException(__('Only an open stock count can be submitted.'));
            }
            if ($count->manager_id !== null && (int) $count->manager_id !== (int) auth()->id() && ! auth()->user()?->can('stock_counts.close')) {
                abort(403);
            }
            if ($count->lines->contains(fn ($line): bool => ! $line->is_counted || $line->completed_at === null)) {
                throw new InvalidArgumentException(__('Complete a count for every scoped product/location, or explicitly confirm zero, before submitting.'));
            }
            foreach ($count->lines as $line) {
                $this->scope->execute((int) $line->store_id);
                $line->update(app(StockCountReconciliation::class)->calculate($count, $line));
            }
            $before = $count->only(['status', 'lock_version']);
            $count->update(['status' => 'submitted', 'entry_closed_at' => now(), 'submitted_at' => now(), 'lock_version' => $count->lock_version + 1]);
            $branchId = Store::query()->whereKey($count->store_id)->value('branch_id');
            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'stock_counts',
                sourceId: (string) $count->id,
                sourceVersion: (string) $count->lock_version,
                requestedAction: 'reconcile',
                requestPermission: 'stock_counts.submit',
                decisionPermission: 'stock_counts.reconcile',
                branchId: $branchId === null ? null : (int) $branchId,
                storeId: $count->store_id,
                limitContext: ['uncounted_lines' => 0, 'locations' => $count->locations()->count()],
                idempotencyKey: 'stock-count-reconciliation:'.$count->id.':'.$count->lock_version,
            ));
            app(RecordAuditEvent::class)->execute('inventory', 'submit_stock_count', $count, $before, $count->only(['status', 'submitted_at', 'lock_version']), storeId: $count->store_id, metadata: ['uncounted_lines' => $count->lines->where('is_counted', false)->count()]);

            return $count->fresh(['store', 'lines.product']);
        });
    }
}
