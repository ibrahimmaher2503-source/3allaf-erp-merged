<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReversePurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.reverse');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reversal reason is required.'));
        }

        return DB::transaction(function () use ($id, $reason, $expectedVersion): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session.'));
            }
            if ($invoice->status === 'reversed') {
                return $invoice->fresh(['lines']);
            }
            if ($invoice->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved invoices can be reversed.'));
            }
            $movements = StockMovement::query()->where('source_type', PurchaseInvoice::class)->where('source_id', $invoice->id)->where('movement_type', 'purchase_receipt')->lockForUpdate()->get();
            if ($movements->isEmpty()) {
                throw new InvalidArgumentException(__('No receipt movement exists to reverse.'));
            }

            foreach ($movements as $movement) {
                $this->reverseMovement($movement);
            }
            foreach ($invoice->lines as $line) {
                if ($line->purchase_order_line_id) {
                    $poLine = PurchaseOrderLine::query()->find($line->purchase_order_line_id);
                    if ($poLine) {
                        $poLine->update(['quantity_received' => $this->subtract($poLine->quantity_received, $line->quantity, 6)]);
                    }
                }
                $line->update(['quantity_received' => 0]);
            }

            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update(['status' => 'reversed', 'cancelled_at' => now(), 'cancelled_by' => Auth::id(), 'cancel_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reverse_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'lock_version']), storeId: $invoice->store_id, metadata: ['stock_reversed' => true]);

            return $invoice->fresh(['lines']);
        });
    }

    private function reverseMovement(StockMovement $movement): void
    {
        $key = 'reversal:stock-movement:'.$movement->id;
        if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
            return;
        }
        $balance = StockBalance::query()->where('product_id', $movement->product_id)->where('store_id', $movement->store_id)->lockForUpdate()->firstOrFail();
        if ($this->compare($balance->on_hand, $movement->quantity) < 0) {
            throw new InvalidArgumentException(__('Cannot reverse a receipt after its quantity has been consumed or transferred.'));
        }
        $newQuantity = $this->subtract($balance->on_hand, $movement->quantity, 6);
        $newValue = $this->subtract($balance->total_value, $movement->total_cost ?? 0, 4);
        $newAverage = $this->compare($newQuantity, 0) === 0 ? '0.0000' : $this->divide($newValue, $newQuantity, 4);
        StockMovement::query()->create(['product_id' => $movement->product_id, 'store_id' => $movement->store_id, 'movement_type' => 'purchase_receipt_reversal', 'quantity' => $this->subtract(0, $movement->quantity, 6), 'unit_cost' => $movement->unit_cost, 'total_cost' => $this->subtract(0, $movement->total_cost ?? 0, 4), 'consumed_cost' => 0, 'source_type' => PurchaseInvoice::class, 'source_id' => $movement->source_id, 'source_line_id' => $movement->source_line_id, 'idempotency_key' => $key, 'posted_at' => now(), 'reversal_of_id' => $movement->id, 'created_by' => Auth::id()]);
        $balance->update(['on_hand' => $newQuantity, 'total_value' => $newValue, 'average_cost' => $newAverage, 'version' => $balance->version + 1]);
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

    /** @return numeric-string */
    private function subtract(mixed $left, mixed $right, int $scale): string
    {
        return bcsub($this->decimal($left), $this->decimal($right), $scale);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }

    /** @return numeric-string */
    private function divide(mixed $left, mixed $right, int $scale): string
    {
        if ($this->compare($right, 0) === 0) {
            throw new InvalidArgumentException(__('Division by zero is not allowed.'));
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $scale);
    }
}
