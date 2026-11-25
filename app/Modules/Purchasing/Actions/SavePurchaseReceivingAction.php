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
    /**
     * Accepted quantities are base units. `$quantities` holds the accepted PAID quantity per line and
     * `$bonusQuantities` the accepted free bonus quantity; stock receives their sum.
     *
     * @param  array<int, mixed>  $quantities
     * @param  array<int, mixed>  $bonusQuantities
     */
    public function execute(int $invoiceId, array $quantities, int $expectedVersion, array $bonusQuantities = []): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($invoiceId, $quantities, $bonusQuantities, $expectedVersion, $actor): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with(['lines.distributions', 'lines.product', 'charges'])->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload.'));
            }
            if (! in_array($invoice->status, ['submitted', 'awaiting_distribution'], true)) {
                throw new InvalidArgumentException(__('Only submitted purchase invoices can be reviewed.'));
            }
            if ($invoice->created_by === $actor->id && ! $actor->canBypassPurchaseInvoiceApproval()) {
                throw new InvalidArgumentException(__('The invoice creator cannot approve the same invoice.'));
            }
            $submittedLineIds = array_map('intval', [...array_keys($quantities), ...array_keys($bonusQuantities)]);
            if (array_diff($submittedLineIds, $invoice->lines->modelKeys()) !== []) {
                throw ValidationException::withMessages(['receivedQuantities' => __('The receiving review contains a line outside this invoice.')]);
            }
            $before = $invoice->lines->map->only(['id', 'original_quantity', 'quantity', 'original_bonus_quantity', 'bonus_quantity'])->all();
            foreach ($invoice->lines as $line) {
                $paid = (string) ProductQuantity::normalize($quantities[$line->id] ?? $line->paidQuantity(), 'receivedQuantities.'.$line->id, true, decimalPlaces: 6);
                $bonus = (string) ProductQuantity::normalize($bonusQuantities[$line->id] ?? ($line->bonus_quantity ?? '0'), 'receivedBonusQuantities.'.$line->id, true, decimalPlaces: 6);
                $quantity = bcadd($paid, $bonus, 6);
                $allocated = $line->distributions->reduce(fn (string $sum, $allocation): string => bcadd($sum, $allocation->quantity, 6), '0');
                if (bccomp((string) $allocated, $quantity, 6) > 0) {
                    throw ValidationException::withMessages(['receivedQuantities.'.$line->id => __('Distribution cannot exceed the accepted quantity.')]);
                }
                $original = $line->original_quantity ?? $line->quantity;
                $originalBonus = $line->original_bonus_quantity ?? ($line->bonus_quantity ?? '0');
                $originalPaid = bcsub((string) $original, (string) $originalBonus, 6);
                // [merge 3allaf] Accepted quantity is in base units; recalculate in base units and keep the
                // line's purchase-unit snapshot (unit, factor, entered price) with a derived entered quantity.
                $factor = (string) ($line->conversion_factor_snapshot ?? '1');
                if (bccomp($factor, '0', 6) <= 0) {
                    $factor = '1';
                }
                if (bccomp($paid, '0', 6) === 0 && bccomp($bonus, '0', 6) > 0) {
                    throw ValidationException::withMessages(['receivedQuantities.'.$line->id => __('Bonus units need at least one accepted paid unit on the same line.')]);
                }
                if (bccomp($paid, '0', 6) === 0) {
                    $values = ['quantity' => 0, 'bonus_quantity' => 0, 'entered_quantity' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'subtotal' => 0, 'line_total' => 0];
                } else {
                    // A fixed-amount discount belongs to the original paid quantity; prorate it to the accepted paid quantity.
                    $discountValue = (string) $line->discount_value;
                    if ($line->discount_type === 'amount' && bccomp($originalPaid, '0', 6) > 0 && bccomp($paid, $originalPaid, 6) !== 0) {
                        $discountValue = bcdiv(bcmul($discountValue, $paid, 10), $originalPaid, 4);
                    }
                    $values = app(PurchaseInvoiceCalculator::class)->calculateLine([
                        ...$line->getAttributes(),
                        'product' => $line->product,
                        'product_unit' => null,
                        'quantity' => $paid,
                        'entered_quantity' => $paid,
                        'bonus_quantity' => $bonus,
                        'discount_value' => $discountValue,
                        'entered_unit_price' => (string) $line->unit_cost,
                    ]);
                    $values = [
                        ...$values,
                        'discount_value' => (string) $line->discount_value,
                        'product_unit_id' => $line->product_unit_id,
                        'conversion_factor_snapshot' => $line->conversion_factor_snapshot ?? $values['conversion_factor_snapshot'],
                        'entered_unit_price' => $line->entered_unit_price ?? $values['entered_unit_price'],
                        'entered_quantity' => bcdiv($paid, $factor, 6),
                    ];
                }
                $line->update([...$values, 'original_quantity' => $original, 'original_bonus_quantity' => $originalBonus]);
            }
            $totals = ['subtotal' => '0', 'tax_amount' => '0', 'discount_amount' => '0', 'total_amount' => '0'];
            foreach ($invoice->lines as $line) {
                foreach (['subtotal', 'tax_amount', 'discount_amount', 'total_amount'] as $field) {
                    $totals[$field] = bcadd($totals[$field], (string) $line->{$field === 'total_amount' ? 'line_total' : $field}, 4);
                }
            }
            foreach ($invoice->charges as $charge) {
                $totals['total_amount'] = bcadd($totals['total_amount'], (string) $charge->amount, 4);
            }
            $invoice->update([...$totals, 'lock_version' => $invoice->lock_version + 1, 'updated_by' => $actor->id]);
            ApprovalRecord::query()->where('source_type', 'purchase_invoices')->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')->where('approval_state', ApprovalState::Pending->value)
                ->update(['source_version' => (string) $invoice->lock_version]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'review_purchase_receiving', source: $invoice,
                before: $before, after: $invoice->lines->map->only(['id', 'original_quantity', 'quantity', 'original_bonus_quantity', 'bonus_quantity'])->all(), storeId: $invoice->store_id);

            return $invoice->fresh('lines');
        });
    }
}
