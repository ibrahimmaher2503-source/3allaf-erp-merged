<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReverseOpeningInventoryAction
{
    public function execute(int $id, string $reason): OpeningInventoryDocument
    {
        Gate::authorize('inventory_stock_card.reverse');
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException(__('A reversal reason is required.'));
        return DB::transaction(function () use ($id, $reason): OpeningInventoryDocument {
            $original = OpeningInventoryDocument::query()->with('lines')->lockForUpdate()->findOrFail($id);
            abort_unless((int) $original->company_id === (int) Company::query()->where('status', 'active')->value('id'), 404);
            if ($original->status !== 'approved' || $original->reversal_of_id) throw new InvalidArgumentException(__('Only an approved original opening inventory document can be reversed.'));
            $reversal = OpeningInventoryDocument::query()->create(['company_id' => $original->company_id, 'document_number' => 'OPEN-REV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4)), 'status' => 'approved', 'notes' => $reason, 'created_by' => Auth::id(), 'approved_by' => Auth::id(), 'approved_at' => now(), 'reversal_of_id' => $original->id, 'idempotency_key' => (string) Str::uuid(), 'lock_version' => 1]);
            foreach ($original->lines as $line) {
                $reversalLine = $reversal->lines()->create(['product_id' => $line->product_id, 'store_id' => $line->store_id, 'quantity' => bcsub('0', (string) $line->quantity, 6), 'unit_cost' => $line->unit_cost, 'total_value' => bcsub('0', (string) $line->total_value, 4)]);
                $movement = StockMovement::query()->where('idempotency_key', 'opening-inventory:'.$original->id.':'.$line->id)->firstOrFail();
                app(PostInventoryMovement::class)->execute($line->product_id, $line->store_id, (string) $reversalLine->quantity, 'opening_inventory_reversal', (string) $line->unit_cost, 'opening-inventory-reversal:'.$original->id.':'.$line->id, OpeningInventoryDocument::class, $reversal->id, $reversalLine->id, false, $movement->id);
            }
            $original->update(['status' => 'reversed', 'reversed_by' => Auth::id(), 'reversed_at' => now(), 'reversal_reason' => $reason, 'lock_version' => $original->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'reverse_opening_inventory', $original, ['status' => 'approved'], ['status' => 'reversed', 'reversal_id' => $reversal->id], reasonText: $reason);
            return $reversal->fresh(['lines.product', 'lines.store']);
        });
    }
}
