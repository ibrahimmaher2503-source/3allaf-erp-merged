<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
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
            if ($count->store_id === null) {
                throw new InvalidArgumentException(__('This Local Demo count must target a store before submission.'));
            }
            $this->scope->execute($count->store_id);
            if (! in_array($count->status, ['draft', 'in_progress'], true)) {
                throw new InvalidArgumentException(__('Only an open stock count can be submitted.'));
            }
            foreach ($count->lines as $line) {
                $movementQuantity = StockMovement::query()->where('product_id', $line->product_id)->where('store_id', $count->store_id)->where('posted_at', '>', $count->reference_at)->sum('quantity');
                $expected = bcadd((string) $line->reference_on_hand, (string) $movementQuantity, 6);
                $variance = $line->is_counted && $line->counted_quantity !== null ? bcsub((string) $line->counted_quantity, $expected, 6) : null;
                $line->update(['movement_quantity_after_reference' => $movementQuantity, 'expected_quantity' => $expected, 'variance_quantity' => $variance]);
            }
            $before = $count->only(['status', 'lock_version']);
            $count->update(['status' => 'submitted', 'submitted_at' => now(), 'lock_version' => $count->lock_version + 1]);
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
                limitContext: ['uncounted_lines' => $count->lines->where('is_counted', false)->count()],
                idempotencyKey: 'stock-count-reconciliation:'.$count->id.':'.$count->lock_version,
            ));
            app(RecordAuditEvent::class)->execute('inventory', 'submit_stock_count', $count, $before, $count->only(['status', 'submitted_at', 'lock_version']), storeId: $count->store_id, metadata: ['uncounted_lines' => $count->lines->where('is_counted', false)->count()]);

            return $count->fresh(['store', 'lines.product']);
        });
    }
}
