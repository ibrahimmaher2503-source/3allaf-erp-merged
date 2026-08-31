<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Catalog\Models\Product;
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
        Gate::authorize('purchase_invoices.approve');

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actor, $actorId, $id, $expectedVersion): PurchaseInvoice {
            $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->with(['lines.distributions', 'lines.product'])
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before approving.'));
            }
            if ($invoice->status === 'approved') {
                return $invoice->fresh(['supplier', 'store', 'lines.product']);
            }
            if (! in_array($invoice->status, ['awaiting_distribution', 'submitted'], true)) {
                throw new InvalidArgumentException(__('Only purchase invoices awaiting distribution can be approved.'));
            }
            if ($invoice->created_by === $actorId && ! $actor->canBypassApproval()) {
                throw new InvalidArgumentException(__('The invoice creator cannot approve the same invoice.'));
            }

            $before = $invoice->only(['invoice_number', 'status', 'total_amount', 'lock_version']);
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_invoices')
                ->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            $this->validateBusinessRules($invoice);

            $number = $invoice->invoice_number ?: app(AllocatePurchaseInvoiceNumberAction::class)->execute();
            foreach ($invoice->lines as $line) {
                $this->postLine($invoice, $line, $actorId);
                $this->distributeLine($invoice, $line, $actorId);
                $this->updateProductCard($invoice, $line, $actorId);
            }
            $this->updatePurchaseOrderState($invoice);

            $invoice->update([
                'invoice_number' => $number,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);
            app(ApproveRequest::class)->execute($approval, (string) $before['lock_version'], decisionNote: __('Purchase invoice and stock receipt approved.'));
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'approve_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['invoice_number', 'status', 'approved_at', 'approved_by', 'lock_version']), storeId: $invoice->store_id, metadata: ['stock_posted' => true, 'wac_posted' => true, 'approval_record_id' => $approval->id]);

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }

    private function validateBusinessRules(PurchaseInvoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $idempotencyKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id;
            if (StockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }
            if ($this->compare($line->quantity, '0') <= 0) {
                throw new InvalidArgumentException(__('Received quantity must be greater than zero.'));
            }
            $distributed = $line->distributions->reduce(fn (string $total, $allocation): string => $this->add($total, $allocation->quantity, 6), '0');
            if ($this->compare($distributed, $line->quantity) !== 0) {
                throw new InvalidArgumentException(__('Every purchased unit must be distributed before approval.'));
            }
            if ($line->distributions->isEmpty() || $line->distributions->contains(fn ($allocation): bool => $this->compare($allocation->quantity, '0') <= 0 || (int) $allocation->destination_store_id === (int) $invoice->store_id)) {
                throw new InvalidArgumentException(__('Distribution destinations and quantities are invalid.'));
            }

            $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->first();
            $onHand = $balance?->on_hand ?? '0';
            if ($this->compare($onHand, '0') < 0) {
                throw new InvalidArgumentException(__('Negative on-hand balance must be reconciled before receiving.'));
            }

            $quantity = $this->decimal($line->quantity);
            $lineCost = $this->decimal($line->subtotal);
            $newQuantity = $this->add($onHand, $quantity, 6);
            $newValue = $this->add($balance?->total_value ?? '0', $lineCost, 4);
            $this->divide($newValue, $newQuantity, 4);
            $this->divide($lineCost, $quantity, 4);
        }

        $this->validatePurchaseOrderRules($invoice);
    }

    private function distributeLine(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): void
    {
        $unitCost = $this->divide($line->subtotal, $line->quantity, 4);
        foreach ($line->distributions as $allocation) {
            $outKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id.':destination:'.$allocation->destination_store_id.':out';
            $inKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id.':destination:'.$allocation->destination_store_id.':in';
            if (StockMovement::query()->where('idempotency_key', $inKey)->exists()) continue;
            $source = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->firstOrFail();
            if ($this->compare($source->on_hand, $allocation->quantity) < 0) throw new InvalidArgumentException(__('The receiving warehouse cannot distribute more than it received.'));
            $cost = bcmul($this->decimal($allocation->quantity), $unitCost, 4);
            StockMovement::query()->create(['product_id'=>$line->product_id,'store_id'=>$invoice->store_id,'movement_type'=>'purchase_distribution_out','quantity'=>bcmul($this->decimal($allocation->quantity),'-1',6),'unit_cost'=>$unitCost,'total_cost'=>bcmul($cost,'-1',4),'consumed_cost'=>$cost,'source_type'=>PurchaseInvoice::class,'source_id'=>$invoice->id,'source_line_id'=>$line->id,'idempotency_key'=>$outKey,'posted_at'=>now(),'created_by'=>$actorId]);
            $source->update(['on_hand'=>$this->add($source->on_hand, bcmul($this->decimal($allocation->quantity),'-1',6), 6),'total_value'=>$this->add($source->total_value, bcmul($cost,'-1',4), 4),'version'=>$source->version+1]);
            $destination = StockBalance::query()->where('product_id',$line->product_id)->where('store_id',$allocation->destination_store_id)->lockForUpdate()->first();
            if (! $destination) $destination = StockBalance::query()->create(['product_id'=>$line->product_id,'store_id'=>$allocation->destination_store_id,'on_hand'=>0,'reserved'=>0,'in_transit'=>0,'average_cost'=>0,'total_value'=>0,'version'=>0]);
            $destination = StockBalance::query()->whereKey($destination->id)->lockForUpdate()->firstOrFail();
            $newQty = $this->add($destination->on_hand,$allocation->quantity,6); $newValue=$this->add($destination->total_value,$cost,4);
            StockMovement::query()->create(['product_id'=>$line->product_id,'store_id'=>$allocation->destination_store_id,'movement_type'=>'purchase_distribution_in','quantity'=>$allocation->quantity,'unit_cost'=>$unitCost,'total_cost'=>$cost,'consumed_cost'=>0,'source_type'=>PurchaseInvoice::class,'source_id'=>$invoice->id,'source_line_id'=>$line->id,'idempotency_key'=>$inKey,'posted_at'=>now(),'created_by'=>$actorId]);
            $destination->update(['on_hand'=>$newQty,'total_value'=>$newValue,'average_cost'=>$this->divide($newValue,$newQty,4),'version'=>$destination->version+1]);
        }
        $remaining = StockBalance::query()->where('product_id',$line->product_id)->where('store_id',$invoice->store_id)->lockForUpdate()->firstOrFail();
        if ($this->compare($remaining->on_hand, '0') !== 0) throw new InvalidArgumentException(__('The temporary receiving warehouse must finish with zero remaining quantity for this receipt.'));
    }

    private function updateProductCard(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($line->product_id);
        $before = ['average_cost'=>$product->average_cost,'sale_price'=>$product->sale_price];
        $line->update(['previous_product_cost'=>$product->average_cost,'previous_base_consumer_price'=>$product->sale_price]);
        $product->update(['average_cost'=>$line->unit_cost,'sale_price'=>$line->base_consumer_price,'lock_version'=>$product->lock_version+1]);
        app(RecordAuditEvent::class)->execute(category:'catalog',event:'purchase_invoice_update_product_cost_and_base_price',source:$product,before:$before,after:['average_cost'=>$product->average_cost,'sale_price'=>$product->sale_price],storeId:$invoice->store_id,metadata:['purchase_invoice_id'=>$invoice->id,'purchase_invoice_line_id'=>$line->id,'actor_id'=>$actorId]);
    }

    private function postLine(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): void
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
            'created_by' => $actorId,
        ]);
        $balance->update(['on_hand' => $newQuantity, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);
        $line->update(['quantity_received' => $quantity]);
    }

    private function updatePurchaseOrderState(PurchaseInvoice $invoice): void
    {
        if (! $invoice->purchase_order_id) {
            return;
        }
        $order = PurchaseOrder::query()
            ->whereKey($invoice->purchase_order_id)
            ->where('store_id', $invoice->store_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->with('lines')
            ->lockForUpdate()
            ->firstOrFail();
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->where('product_id', $line->product_id)
                ->whereKey($line->purchase_order_line_id)
                ->lockForUpdate()
                ->first();
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

    private function validatePurchaseOrderRules(PurchaseInvoice $invoice): void
    {
        if (! $invoice->purchase_order_id) {
            foreach ($invoice->lines as $line) {
                if ($line->purchase_order_line_id) {
                    throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
                }
            }

            return;
        }

        $order = PurchaseOrder::query()
            ->whereKey($invoice->purchase_order_id)
            ->where('store_id', $invoice->store_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->lockForUpdate()
            ->firstOrFail();
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->whereKey($line->purchase_order_line_id)
                ->lockForUpdate()
                ->first();
            if (! $poLine) {
                throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
            }
            if ((int) $poLine->product_id !== (int) $line->product_id) {
                throw new InvalidArgumentException(__('The invoice line product does not match the selected purchase-order line.'));
            }
            $newReceived = $this->add($poLine->quantity_received, $line->quantity, 6);
            if ($this->compare($newReceived, $poLine->quantity_ordered) > 0) {
                throw new InvalidArgumentException(__('Over-receipt is not allowed by the approved policy.'));
            }
            if ($this->compare($newReceived, $poLine->quantity_ordered) < 0) {
                throw new InvalidArgumentException(__('Partial receipt is not allowed under invoice-posts-stock Model A; invoice quantity must complete the PO line.'));
            }
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

}
