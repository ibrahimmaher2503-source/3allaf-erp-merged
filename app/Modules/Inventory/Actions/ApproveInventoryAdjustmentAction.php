<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApproveInventoryAdjustmentAction
{
    public function execute(int $id): InventoryAdjustment
    {
        Gate::authorize('inventory_stock_card.approve');

        return DB::transaction(function () use ($id): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($adjustment->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted adjustments can be approved.'));
            }
            if ($adjustment->created_by === Auth::id()) {
                throw new InvalidArgumentException(__('The adjustment creator cannot approve the same adjustment.'));
            }
            if ($adjustment->allow_negative) {
                Gate::authorize('inventory_stock_card.override');
            }
            $poster = app(PostInventoryMovement::class);
            foreach ($adjustment->lines as $line) {
                $beforeOnHand = (string) StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $adjustment->store_id)->lockForUpdate()->value('on_hand');
                $poster->execute($line->product_id, $adjustment->store_id, (string) $line->quantity_delta, 'inventory_'.$adjustment->adjustment_type, (string) $line->unit_cost, 'DEMO-ADJUSTMENT:'.$adjustment->id.':'.$line->id, InventoryAdjustment::class, $adjustment->id, $line->id, $adjustment->allow_negative);
                $afterOnHand = (string) StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $adjustment->store_id)->value('on_hand');
                $line->update(['before_on_hand' => $beforeOnHand, 'after_on_hand' => $afterOnHand]);
            }
            $before = $adjustment->only(['status', 'lock_version']);
            $adjustment->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now(), 'lock_version' => $adjustment->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'approve_inventory_adjustment', $adjustment, $before, $adjustment->only(['status', 'approved_by', 'approved_at', 'lock_version']), storeId: $adjustment->store_id, reasonCode: $adjustment->reason_code, reasonText: $adjustment->reason_notes);

            return $adjustment->fresh(['store', 'lines.product']);
        });
    }
}
