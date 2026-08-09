<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApprovePurchaseInvoiceAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.approve');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before approving.'));
            }
            if ($invoice->status === 'approved') {
                return $invoice->fresh(['supplier', 'store', 'lines.product']);
            }
            if ($invoice->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted purchase invoices can be approved.'));
            }
            if ($invoice->created_by === Auth::id()) {
                throw new InvalidArgumentException(__('The invoice creator cannot approve the same invoice.'));
            }
            $this->assertStoreScope($invoice->store_id);

            $before = $invoice->only(['invoice_number', 'status', 'total_amount', 'lock_version']);
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_invoices')
                ->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            $number = $invoice->invoice_number ?: app(AllocatePurchaseInvoiceNumberAction::class)->execute();
            foreach ($invoice->lines as $line) {
                $this->postLine($invoice, $line);
            }
            $this->updatePurchaseOrderState($invoice);

            $invoice->update([
                'invoice_number' => $number,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'lock_version' => $invoice->lock_version + 1,
            ]);
            app(ApproveRequest::class)->execute($approval, (string) $before['lock_version'], decisionNote: __('Purchase invoice and stock receipt approved.'));
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'approve_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['invoice_number', 'status', 'approved_at', 'approved_by', 'lock_version']), storeId: $invoice->store_id, metadata: ['stock_posted' => true, 'wac_posted' => true, 'approval_record_id' => $approval->id]);

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }

    private function postLine(PurchaseInvoice $invoice, PurchaseInvoiceLine $line): void
    {
        $idempotencyKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id;
        if (StockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }
        if ($this->compare($line->quantity, '0') <= 0) {
            throw new InvalidArgumentException(__('Received quantity must be greater than zero.'));
        }

        $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->first();
        if ($balance === null) {
            $balance = StockBalance::query()->create(['product_id' => $line->product_id, 'store_id' => $invoice->store_id, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
            $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }
        if ($this->compare($balance->on_hand, '0') < 0) {
            throw new InvalidArgumentException(__('Negative on-hand balance must be reconciled before receiving.'));
        }

        $quantity = $this->decimal($line->quantity);
        $lineCost = $this->decimal($line->subtotal);
        $newQuantity = $this->add($balance->on_hand, $quantity, 6);
        $newValue = $this->add($balance->total_value, $lineCost, 4);
        $newAverage = $this->divide($newValue, $newQuantity, 4);

        StockMovement::query()->create([
            'product_id' => $line->product_id,
            'store_id' => $invoice->store_id,
            'movement_type' => 'purchase_receipt',
            'quantity' => $quantity,
            'unit_cost' => $this->divide($lineCost, $quantity, 4),
            'total_cost' => $lineCost,
            'consumed_cost' => 0,
            'source_type' => PurchaseInvoice::class,
            'source_id' => $invoice->id,
            'source_line_id' => $line->id,
            'idempotency_key' => $idempotencyKey,
            'posted_at' => now(),
            'created_by' => Auth::id(),
        ]);
        $balance->update(['on_hand' => $newQuantity, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);
        $line->update(['quantity_received' => $quantity]);
    }

    private function updatePurchaseOrderState(PurchaseInvoice $invoice): void
    {
        if (! $invoice->purchase_order_id) {
            return;
        }
        $order = PurchaseOrder::query()->with('lines')->lockForUpdate()->findOrFail($invoice->purchase_order_id);
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->find($line->purchase_order_line_id);
            if (! $poLine) {
                throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
            }
            $newReceived = $this->add($poLine->quantity_received, $line->quantity, 6);
            if ($this->compare($newReceived, $poLine->quantity_ordered) > 0) {
                throw new InvalidArgumentException(__('Over-receipt is not allowed by the approved policy.'));
            }
            if ($this->compare($newReceived, $poLine->quantity_ordered) < 0) {
                throw new InvalidArgumentException(__('Partial receipt is not allowed under invoice-posts-stock Model A; invoice quantity must complete the PO line.'));
            }
            $poLine->mutateApprovedParentLine(['quantity_received' => $newReceived]);
        }
        $allReceived = $order->lines()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
        $someReceived = $order->lines()->where('quantity_received', '>', 0)->exists();
        $order->mutateApprovedDocument(['status' => $allReceived ? 'received' : ($someReceived ? 'partially_received' : $order->status), 'lock_version' => $order->lock_version + 1]);
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
    private function add(mixed $left, mixed $right, int $scale): string
    {
        return bcadd($this->decimal($left), $this->decimal($right), $scale);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }

    /** @return numeric-string */
    private function divide(mixed $left, mixed $right, int $scale): string
    {
        if ($this->compare($right, '0') === 0) {
            throw new InvalidArgumentException(__('Division by zero is not allowed.'));
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $scale);
    }

    private function assertStoreScope(int $storeId): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->is_super_admin) {
            return;
        }
        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected receiving store.'));
        }
    }
}
