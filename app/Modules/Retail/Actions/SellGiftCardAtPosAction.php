<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\GiftCardPosSale;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SellGiftCardAtPosAction
{
    /**
     * @param  list<array{method_id:int,amount?:string,tendered?:string|null,evidence_reference?:string|null,evidence_attachment_id?:string|null}>  $tenders
     */
    public function execute(User $cashier, GiftCard $card, array $tenders, string $idempotencyKey): GiftCardPosSale
    {
        Gate::forUser($cashier)->authorize('pos_sales.create');
        Gate::forUser($cashier)->authorize('pos_sales.payment_create');
        if ($tenders === [] || count($tenders) > 12) {
            throw ValidationException::withMessages(['payments' => __('Select between one and twelve payment portions.')]);
        }

        return DB::transaction(function () use ($cashier, $card, $tenders, $idempotencyKey): GiftCardPosSale {
            $context = app(PosContextResolver::class)->resolve($cashier);
            if (! $context->isReady() || $context->store === null || $context->shift === null || $context->branch === null || $context->drawer === null) {
                throw ValidationException::withMessages(['card' => $context->disabledReason ?? __('Open an assigned cashier shift before selling a Gift Card.')]);
            }
            $card = GiftCard::query()->visibleTo($cashier)->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $existing = GiftCardPosSale::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ((int) $existing->gift_card_id !== (int) $card->id) {
                    throw ValidationException::withMessages(['card' => __('This checkout token belongs to another Gift Card.')]);
                }

                return $existing->load('sale.payments', 'giftCard');
            }
            if ($card->status !== 'unsold' || $card->posSale()->exists()) {
                throw ValidationException::withMessages(['card' => __('This Gift Card has already been sold or is unavailable.')]);
            }
            if ((int) $card->branch_id !== (int) $context->branch->id || strtoupper((string) $card->currency_code) !== strtoupper((string) $context->shift->currency_code)) {
                throw ValidationException::withMessages(['card' => __('The Gift Card branch or currency does not match this cashier shift.')]);
            }
            if ($card->valid_until !== null && $card->valid_until->isPast()) {
                throw ValidationException::withMessages(['card' => __('This Gift Card has expired and cannot be sold.')]);
            }

            $faceValue = (string) $card->issued_value;
            $sale = Sale::query()->create([
                'branch_id' => $context->branch->id,
                'store_id' => $context->store->id,
                'cash_drawer_id' => $context->drawer->id,
                'shift_id' => $context->shift->id,
                'cashier_id' => $cashier->id,
                'status' => 'draft',
                'idempotency_key' => 'GIFT-CARD-POS:'.$idempotencyKey,
                'subtotal' => $faceValue,
                'total' => $faceValue,
                'payable_total' => $faceValue,
                'currency_code' => $card->currency_code,
                'notes' => 'GIFT-CARD:'.$card->identifier,
            ]);
            $payments = app(CapturePaymentAction::class);
            foreach ($tenders as $index => $tender) {
                $method = PaymentMethod::query()->whereKey((int) $tender['method_id'])->where('status', 'active')->firstOrFail();
                if ((string) $method->type === 'gift_card') {
                    throw ValidationException::withMessages(['payments' => __('Use a normal payment method to buy a Gift Card.')]);
                }
                $payments->execute(
                    $cashier, $sale, $method, (string) ($tender['amount'] ?? '0.00'),
                    'GIFT-CARD-POS:'.$sale->id.':TENDER:'.$index.':'.$method->code,
                    $tender['tendered'] ?? null,
                    $tender['evidence_reference'] ?? null,
                    $tender['evidence_attachment_id'] ?? null,
                );
            }
            if (bccomp($payments->paidSoFar($sale), $faceValue, 2) !== 0) {
                throw ValidationException::withMessages(['payments' => __('Gift Card payment must equal its full face value before activation.')]);
            }
            $number = app(AllocateDocumentNumber::class)->executeForBranch('retail_sale', (int) $context->branch->id);
            $sale->update([
                'status' => 'gift_card_sold', 'document_number' => $number, 'approved_at' => now(),
                'paid_total' => $payments->paidSoFar($sale), 'change_total' => $payments->changeSoFar($sale),
                'lock_version' => (int) $sale->lock_version + 1,
            ]);
            $posSale = GiftCardPosSale::query()->create([
                'gift_card_id' => $card->id, 'sale_id' => $sale->id, 'shift_id' => $context->shift->id,
                'branch_id' => $context->branch->id, 'store_id' => $context->store->id,
                'cashier_id' => $cashier->id, 'idempotency_key' => $idempotencyKey,
            ]);
            app(GiftCardAction::class)->activateSold($cashier, $card, $posSale);
            app(RecordAuditEvent::class)->execute('retail', 'gift_card_pos_sale_completed', $posSale, null, [
                'gift_card_id' => $card->id, 'sale_id' => $sale->id, 'shift_id' => $context->shift->id,
                'paid_total' => $sale->paid_total, 'payment_count' => count($tenders), 'stock_posted' => false,
            ], (int) $context->branch->id, (int) $context->store->id);

            return $posSale->load('sale.payments', 'giftCard');
        }, 5);
    }
}
