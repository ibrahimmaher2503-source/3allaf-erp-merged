<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitInventoryAdjustmentAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): InventoryAdjustment
    {
        Gate::authorize('inventory_stock_card.submit');

        return DB::transaction(function () use ($id): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->execute($adjustment->store_id);
            if ($adjustment->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft adjustments can be submitted.'));
            }
            if (trim($adjustment->reason_code) === '') {
                throw new InvalidArgumentException(__('An adjustment reason is required.'));
            }
            $before = $adjustment->only(['status', 'lock_version']);
            $adjustment->update(['status' => 'submitted', 'submitted_by' => Auth::id(), 'submitted_at' => now(), 'lock_version' => $adjustment->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'submit_inventory_adjustment', $adjustment, $before, $adjustment->only(['status', 'submitted_by', 'submitted_at', 'lock_version']), storeId: $adjustment->store_id, reasonCode: $adjustment->reason_code, reasonText: $adjustment->reason_notes);

            return $adjustment->fresh(['store', 'lines.product']);
        });
    }
}
