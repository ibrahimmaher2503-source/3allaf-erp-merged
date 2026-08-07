<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitStockCountAction
{
    public function execute(int $id): StockCount
    {
        Gate::authorize('stock_counts.submit');

        return DB::transaction(function () use ($id): StockCount {
            $count = StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);
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
            app(RecordAuditEvent::class)->execute('inventory', 'submit_stock_count', $count, $before, $count->only(['status', 'submitted_at', 'lock_version']), storeId: $count->store_id, metadata: ['uncounted_lines' => $count->lines->where('is_counted', false)->count()]);

            return $count->fresh(['store', 'lines.product']);
        });
    }
}
