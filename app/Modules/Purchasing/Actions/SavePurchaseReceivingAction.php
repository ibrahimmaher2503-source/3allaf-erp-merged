<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SavePurchaseReceivingAction
{
    /** @param array<int, mixed> $quantities */
    public function execute(int $invoiceId, array $quantities, int $expectedVersion): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($invoiceId, $quantities, $expectedVersion, $actor): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with('lines.distributions')->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload.'));
            }
            if (! in_array($invoice->status, ['submitted', 'awaiting_distribution'], true)) {
                throw new InvalidArgumentException(__('Only submitted purchase invoices can be reviewed.'));
            }
            if ($invoice->created_by === $actor->id && ! $actor->canBypassPurchaseInvoiceApproval()) {
                throw new InvalidArgumentException(__('The invoice creator cannot approve the same invoice.'));
            }
            if (array_diff(array_map('intval', array_keys($quantities)), $invoice->lines->modelKeys()) !== []) {
                throw ValidationException::withMessages(['receivedQuantities' => __('The receiving review contains a line outside this invoice.')]);
            }
            $before = $invoice->lines->map->only(['id', 'original_quantity', 'quantity'])->all();
            foreach ($invoice->lines as $line) {
                $quantity = ProductQuantity::normalize($quantities[$line->id] ?? $line->quantity, 'receivedQuantities.'.$line->id, true, decimalPlaces: 6);
                $allocated = $line->distributions->reduce(fn (string $sum, $allocation): string => bcadd($sum, $allocation->quantity, 6), '0');
                if (bccomp((string) $allocated, (string) $quantity, 6) > 0) {
                    throw ValidationException::withMessages(['receivedQuantities.'.$line->id => __('Distribution cannot exceed the accepted quantity.')]);
                }
                $original = $line->original_quantity ?? $line->quantity;
                $values = $quantity === 0
                    ? ['quantity' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'subtotal' => 0, 'line_total' => 0]
                    : app(PurchaseInvoiceCalculator::class)->calculateLine([...$line->getAttributes(), 'quantity' => $quantity]);
                $line->update([...$values, 'original_quantity' => $original]);
            }
            $totals = ['subtotal' => '0', 'tax_amount' => '0', 'discount_amount' => '0', 'total_amount' => '0'];
            foreach ($invoice->lines as $line) {
                foreach (['subtotal', 'tax_amount', 'discount_amount', 'total_amount'] as $field) {
                    $totals[$field] = bcadd($totals[$field], (string) $line->{$field === 'total_amount' ? 'line_total' : $field}, 4);
                }
            }
            $invoice->update([...$totals, 'lock_version' => $invoice->lock_version + 1, 'updated_by' => $actor->id]);
            ApprovalRecord::query()->where('source_type', 'purchase_invoices')->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')->where('approval_state', ApprovalState::Pending->value)
                ->update(['source_version' => (string) $invoice->lock_version]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'review_purchase_receiving', source: $invoice,
                before: $before, after: $invoice->lines->map->only(['id', 'original_quantity', 'quantity'])->all(), storeId: $invoice->store_id);

            return $invoice->fresh('lines');
        });
    }
}
