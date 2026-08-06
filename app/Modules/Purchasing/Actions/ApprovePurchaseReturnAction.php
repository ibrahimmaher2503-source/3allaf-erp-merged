<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApprovePurchaseReturnAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.approve');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()->with(['lines', 'reason'])->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status === 'approved') {
                return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
            }
            if ($return->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted supplier returns can be approved.'));
            }
            if ($return->created_by === Auth::id()) {
                throw new InvalidArgumentException(__('The supplier return creator cannot approve the same return.'));
            }
            if ($return->reason === null || ! $return->reason->is_active) {
                throw new InvalidArgumentException(__('An active supplier return reason is required.'));
            }
            $this->assertStoreScope($return->store_id);

            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($return->purchase_invoice_id);
            if ($invoice->status !== 'approved' || $invoice->supplier_id !== $return->supplier_id || $invoice->store_id !== $return->store_id) {
                throw new InvalidArgumentException(__('The source purchase invoice is no longer eligible for this supplier return.'));
            }

            foreach ($return->lines as $returnLine) {
                $sourceLine = PurchaseInvoiceLine::query()->lockForUpdate()->find($returnLine->purchase_invoice_line_id);
                if ($sourceLine === null || $sourceLine->purchase_invoice_id !== $invoice->id) {
                    throw new InvalidArgumentException(__('Every supplier return line must reference a line from the source invoice.'));
                }
                if ($this->compare($returnLine->unit_cost, $sourceLine->unit_cost) !== 0) {
                    throw new InvalidArgumentException(__('Supplier return cost must equal the original purchase invoice line cost.'));
                }

                $alreadyReturned = $this->decimal((string) PurchaseReturnLine::query()
                    ->where('purchase_invoice_line_id', $sourceLine->id)
                    ->where('id', '!=', $returnLine->id)
                    ->whereHas('purchaseReturn', static fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled', 'reversed']))
                    ->sum('quantity'));
                $remaining = bcsub($this->decimal($sourceLine->quantity_received), $alreadyReturned, 6);
                if ($this->compare($returnLine->quantity, $remaining) > 0) {
                    throw new InvalidArgumentException(__('Return quantity exceeds the remaining quantity from the original invoice line.'));
                }

                $movementKey = 'purchase-return:'.$return->id.':line:'.$returnLine->id;
                if (StockMovement::query()->where('idempotency_key', $movementKey)->exists()) {
                    continue;
                }

                $balance = StockBalance::query()
                    ->where('product_id', $returnLine->product_id)
                    ->where('store_id', $return->store_id)
                    ->lockForUpdate()
                    ->first();
                if ($balance === null || $this->compare($balance->on_hand, $returnLine->quantity) < 0) {
                    throw new InvalidArgumentException(__('Cannot approve the supplier return because current on-hand stock is insufficient.'));
                }

                $quantity = $this->decimal($returnLine->quantity);
                $totalCost = $this->decimal($returnLine->total_cost);
                $newQuantity = bcsub($this->decimal($balance->on_hand), $quantity, 6);
                $newValue = bcsub($this->decimal($balance->total_value), $totalCost, 4);
                $newAverage = $this->compare($newQuantity, '0') === 0 ? '0.0000' : bcdiv($newValue, $newQuantity, 4);

                StockMovement::query()->create([
                    'product_id' => $returnLine->product_id,
                    'store_id' => $return->store_id,
                    'movement_type' => 'purchase_return',
                    'quantity' => bcsub('0', $quantity, 6),
                    'unit_cost' => $this->decimal($returnLine->unit_cost),
                    'total_cost' => bcsub('0', $totalCost, 4),
                    'consumed_cost' => 0,
                    'source_type' => PurchaseReturn::class,
                    'source_id' => $return->id,
                    'source_line_id' => $returnLine->id,
                    'idempotency_key' => $movementKey,
                    'posted_at' => now(),
                    'created_by' => Auth::id(),
                ]);
                $balance->update([
                    'on_hand' => $newQuantity,
                    'average_cost' => $newAverage,
                    'total_value' => $newValue,
                    'version' => $balance->version + 1,
                ]);
            }

            $before = $return->only(['return_number', 'status', 'total_amount', 'lock_version']);
            $return->update([
                'return_number' => $return->return_number ?: app(AllocatePurchaseReturnNumberAction::class)->execute(),
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'lock_version' => $return->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'approve_supplier_return',
                source: $return,
                before: $before,
                after: $return->only(['return_number', 'status', 'approved_at', 'approved_by', 'lock_version']),
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
                metadata: [
                    'stock_posted' => true,
                    'cost_source' => 'original_purchase_invoice_line_unit_cost',
                    'wac_recalculated' => true,
                ],
            );

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }

    private function assertStoreScope(int $storeId): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->is_super_admin) {
            return;
        }
        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected return store.'));
        }
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal value.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }
}
