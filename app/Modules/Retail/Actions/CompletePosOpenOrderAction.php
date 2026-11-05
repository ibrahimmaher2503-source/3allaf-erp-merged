<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\PosOpenOrder;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Locks the durable cart and joins its state transition to atomic sale posting. */
final class CompletePosOpenOrderAction
{
    public function __construct(private readonly RetailSaleAction $sales) {}

    /** @param array<int, array<string, mixed>> $tenders */
    public function execute(User $cashier, PosContext $context, PosOpenOrder $order, array $tenders, bool $suspend = false, ?string $submittedNotes = null): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        if (! $context->isReady() || $context->store === null || $context->shift === null || $context->drawer === null || $context->branch === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The POS context is no longer active.'));
        }

        return DB::transaction(function () use ($cashier, $context, $order, $tenders, $suspend, $submittedNotes): Sale {
            PosShift::query()->whereKey($context->shift->id)->lockForUpdate()->firstOrFail();
            $locked = PosOpenOrder::query()->with(['lines', 'customer'])->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null || ! $this->matches($locked, $cashier, $context)) {
                throw new InvalidArgumentException(__('This open order is no longer available in the current cashier context.'));
            }

            if ($locked->status !== 'open') {
                if (in_array($locked->status, ['completed', 'suspended'], true) && $locked->completed_sale_id !== null) {
                    $existing = Sale::query()->whereKey($locked->completed_sale_id)
                        ->where('idempotency_key', ($suspend ? 'SUSPEND:' : 'CHECKOUT:').$cashier->id.':'.$locked->checkout_token)
                        ->first();
                    if ($existing !== null) {
                        return $existing;
                    }
                }
                throw new InvalidArgumentException(__('This open order was already completed or cancelled.'));
            }

            $lines = $locked->lines->sortBy('line_position')->map(function ($line): array {
                $payload = $line->draft_payload;
                $payload['product_id'] = (int) $line->product_id;
                $payload['quantity'] = (string) $line->quantity;

                return $payload;
            })->values()->all();
            $customer = $locked->customer_id === null ? null : Customer::query()
                ->visibleFrom($cashier, $locked->branch_id, $locked->store_id)
                ->whereKey($locked->customer_id)->where('status', 'active')->first();
            if ($locked->customer_id !== null && ! $customer instanceof Customer) {
                throw new InvalidArgumentException(__('The selected customer is no longer available for this outlet. Choose another customer or use walk-in.'));
            }

            $sale = $this->sales->create(
                $cashier,
                $context->store,
                $lines,
                ($suspend ? 'SUSPEND:' : 'CHECKOUT:').$cashier->id.':'.$locked->checkout_token,
                $suspend,
                $tenders,
                ['tax_applicable' => (bool) $locked->tax_applicable, 'invoice_discount_type' => $locked->invoice_discount_type, 'invoice_discount_value' => (string) $locked->invoice_discount_value],
                $customer,
            );

            // Notes enter the immutable snapshot in the same outer transaction
            // that creates and approves/suspends the sale. No committed approved
            // document is edited after the transaction boundary.
            $notes = $submittedNotes === null ? $locked->notes : trim($submittedNotes);
            if (filled($notes)) {
                DB::table('sales')->where('id', $sale->id)->update(['notes' => $notes]);
            }
            $locked->update([
                'status' => $suspend ? 'suspended' : 'completed',
                'completed_sale_id' => $sale->id,
                'completed_at' => now(),
                'revision' => $locked->revision + 1,
                'last_activity_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: $suspend ? 'pos_open_order_suspended' : 'pos_open_order_completed',
                source: $locked,
                after: ['status' => $locked->status, 'sale_id' => $sale->id, 'document_number' => $sale->document_number],
                branchId: $locked->branch_id,
                storeId: $locked->store_id,
                reasonText: $suspend ? __('Cashier moved the open order to held sales.') : __('Payment completed and the open order became an approved sale.'),
                metadata: ['cashier_id' => $cashier->id, 'shift_id' => $locked->shift_id, 'cash_drawer_id' => $locked->cash_drawer_id],
            );

            return $sale->fresh(['lines', 'store', 'cashier', 'payments.evidenceAttachment']);
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $tenders */
    public function executeGiftCard(User $cashier, PosContext $context, PosOpenOrder $order, GiftCard $card, array $tenders): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        if (! $context->isReady() || $context->shift === null) {
            throw new InvalidArgumentException($context->disabledReason ?? __('The POS context is no longer active.'));
        }

        return DB::transaction(function () use ($cashier, $context, $order, $card, $tenders): Sale {
            PosShift::query()->whereKey($context->shift->id)->lockForUpdate()->firstOrFail();
            $locked = PosOpenOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $this->matches($locked, $cashier, $context) || $locked->status !== 'open' || $locked->lines()->exists()) {
                throw new InvalidArgumentException(__('This Gift Card checkout requires an empty active product order.'));
            }
            if ($locked->tax_applicable || $locked->invoice_discount_type !== null || bccomp((string) $locked->invoice_discount_value, '0', 2) !== 0) {
                throw new InvalidArgumentException(__('Gift Card face value cannot be discounted or taxed.'));
            }
            $paymentPortions = [];
            foreach ($tenders as $tender) {
                if (($tender['gift_card'] ?? null) !== null || (string) $tender['method']->type === 'gift_card') {
                    throw new InvalidArgumentException(__('Use an active normal payment method to buy a Gift Card.'));
                }
                $paymentPortions[] = [
                    'method_id' => (int) $tender['method']->id,
                    'amount' => (string) ($tender['amount'] ?? '0.00'),
                    'tendered' => $tender['tendered'] ?? null,
                    'evidence_reference' => $tender['evidence_reference'] ?? null,
                    'evidence_attachment_id' => $tender['evidence_attachment_id'] ?? null,
                ];
            }
            $completed = app(SellGiftCardAtPosAction::class)->execute($cashier, $card, $paymentPortions, $locked->checkout_token);
            $sale = $completed->sale;
            $locked->update([
                'status' => 'completed', 'completed_sale_id' => $sale->id,
                'completed_at' => now(), 'revision' => $locked->revision + 1, 'last_activity_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'retail', event: 'pos_open_order_completed', source: $locked,
                after: ['status' => 'completed', 'sale_id' => $sale->id, 'document_number' => $sale->document_number, 'gift_card_id' => $card->id],
                branchId: $locked->branch_id, storeId: $locked->store_id,
                metadata: ['cashier_id' => $cashier->id, 'shift_id' => $locked->shift_id, 'cash_drawer_id' => $locked->cash_drawer_id],
            );

            return $sale->fresh(['store', 'cashier', 'payments.evidenceAttachment']);
        }, 3);
    }

    private function matches(PosOpenOrder $order, User $cashier, PosContext $context): bool
    {
        return (int) $order->company_id === (int) $context->store?->company_id
            && (int) $order->branch_id === (int) $context->branch?->id
            && (int) $order->store_id === (int) $context->store?->id
            && (int) $order->cash_drawer_id === (int) $context->drawer?->id
            && (int) $order->shift_id === (int) $context->shift?->id
            && (int) $order->cashier_id === (int) $cashier->id;
    }
}
