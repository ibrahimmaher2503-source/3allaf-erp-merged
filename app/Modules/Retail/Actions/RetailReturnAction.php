<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Models\Exchange;
use App\Modules\Retail\Models\ExchangeLine;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnLine;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RetailReturnAction
{
    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, string $idempotencyKey): RetailReturn
    {
        $this->authorize($actor, 'returns.create');
        return DB::transaction(function () use ($actor, $data, $idempotencyKey): RetailReturn {
            $existing = RetailReturn::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) return $existing->load('lines', 'exchange.lines');
            $receipt = null;
            if (! empty($data['source_gift_receipt_id']) || ! empty($data['source_gift_receipt_reference'])) {
                $receiptQuery = GiftReceipt::query()->visibleTo($actor)->with('lines', 'sale')->lockForUpdate();
                $receipt = ! empty($data['source_gift_receipt_id'])
                    ? $receiptQuery->whereKey((int) $data['source_gift_receipt_id'])->first()
                    : $receiptQuery->where('reference', trim((string) $data['source_gift_receipt_reference']))->first();
                if ($receipt === null) throw ValidationException::withMessages(['source' => __('The Gift Receipt is not available in your scope.')]);
                if ($receipt->status !== 'active') throw ValidationException::withMessages(['source' => __('The Gift Receipt has already been used or voided.')]);
            }
            $saleId = (int) ($receipt?->sale_id ?? ($data['source_sale_id'] ?? 0));
            $sale = Sale::query()->visibleTo($actor)->approved()->with('lines')->whereKey($saleId)->lockForUpdate()->first();
            if ($sale === null) throw (new ModelNotFoundException)->setModel(Sale::class, [$saleId]);
            if ($receipt !== null && (int) $receipt->store_id !== (int) $sale->store_id) throw ValidationException::withMessages(['source' => __('The Gift Receipt source is inconsistent.')]);
            $lines = $this->resolveLines($data['lines'] ?? [], $sale, $receipt);
            if ($lines === []) throw ValidationException::withMessages(['lines' => __('Select at least one item to return.')]);
            $settlementType = (string) ($data['settlement_type'] ?? 'cash_refund');
            if (! in_array($settlementType, ['cash_refund', 'original_tender', 'gift_card', 'exchange'], true)) throw ValidationException::withMessages(['settlement_type' => __('Select a supported return settlement.')]);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') throw ValidationException::withMessages(['reason' => __('A return reason is required.')]);
            $eligible = '0.00';
            foreach ($lines as $line) $eligible = bcadd($eligible, (string) $line['eligible_value'], 2);
            $return = RetailReturn::query()->create([
                'branch_id' => $sale->branch_id, 'store_id' => $sale->store_id, 'cashier_id' => $actor->id, 'customer_id' => $sale->customer_id,
                'source_sale_id' => $sale->id, 'source_gift_receipt_id' => $receipt?->id, 'return_number' => $this->number('retail_return', 'RET-'),
                'status' => 'draft', 'settlement_type' => $settlementType, 'reason' => $reason, 'eligible_value' => $eligible,
                'settlement_value' => $eligible, 'currency_code' => (string) $sale->currency_code, 'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'lock_version' => 1,
            ]);
            foreach ($lines as $line) $return->lines()->create($line);
            if ($settlementType === 'exchange') $this->createExchange($return, $actor, $data['exchange_lines'] ?? [], (int) $sale->store_id);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_created', $return, null, $this->auditReturn($return, $lines), (int) $sale->branch_id, (int) $sale->store_id, reasonText: $return->reason, metadata: ['actor_id' => $actor->id, 'source_sale_id' => $sale->id, 'source_gift_receipt_id' => $receipt?->id]);
            return $return->load('lines', 'exchange.lines');
        }, 5);
    }

    public function submit(User $actor, RetailReturn $return): RetailReturn
    {
        $this->authorize($actor, 'returns.submit');
        return DB::transaction(function () use ($actor, $return): RetailReturn {
            $locked = $this->lockVisible($actor, $return);
            if ($locked->status !== 'draft' && $locked->status !== 'inspection') throw ValidationException::withMessages(['status' => __('Only a draft return can be submitted.')]);
            $before = ['status' => $locked->status];
            $locked->update(['status' => 'submitted', 'submitted_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_submitted', $locked, $before, ['status' => 'submitted'], (int) $locked->branch_id, (int) $locked->store_id, metadata: ['actor_id' => $actor->id]);
            return $locked->fresh('lines', 'exchange.lines');
        }, 5);
    }

    public function approve(User $actor, RetailReturn $return): RetailReturn
    {
        $this->authorize($actor, 'returns.approve');
        return DB::transaction(function () use ($actor, $return): RetailReturn {
            $locked = $this->lockVisible($actor, $return);
            if ($locked->status !== 'submitted') throw ValidationException::withMessages(['status' => __('Only a submitted return can be approved.')]);
            if (! $actor->is_super_admin && (int) $locked->cashier_id === (int) $actor->id) throw ValidationException::withMessages(['approval' => __('The return creator cannot approve their own return.')]);
            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_approved', $locked, ['status' => 'submitted'], ['status' => 'approved', 'approved_by' => $actor->id], (int) $locked->branch_id, (int) $locked->store_id, metadata: ['actor_id' => $actor->id]);
            return $locked->fresh('lines', 'exchange.lines');
        }, 5);
    }

    public function complete(User $actor, RetailReturn $return, string $idempotencyKey, ?int $paymentMethodId = null, ?int $originalPaymentId = null): RetailReturn
    {
        $this->authorize($actor, 'returns.complete');
        // MariaDB's transaction snapshot can otherwise make two independent
        // return workers observe the same remaining quantity. Serialize all
        // completions for the immutable source sale before opening the
        // transaction; row locks below still protect each source line and
        // stock balance. GET_LOCK is connection-scoped and released in the
        // finally block even when validation or posting fails.
        $lockName = 'toyjoy:return-sale:'.(int) $return->source_sale_id;
        $lockAcquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockName])->acquired ?? 0) === 1;
        if (! $lockAcquired) throw ValidationException::withMessages(['concurrency' => __('Another return for this sale is being completed. Retry after it finishes.')]);
        try {
            return DB::transaction(function () use ($actor, $return, $idempotencyKey, $paymentMethodId, $originalPaymentId): RetailReturn {
            $locked = $this->lockVisible($actor, $return)->load('lines', 'exchange.lines');
            if ($locked->status === 'completed') return $locked;
            if ($locked->status !== 'approved') throw ValidationException::withMessages(['status' => __('Only an approved return can be completed.')]);
            $before = $locked->only(['status', 'eligible_value', 'settlement_value']);
            $sourceSale = Sale::query()->approved()->whereKey($locked->source_sale_id)->lockForUpdate()->firstOrFail();
            $exchange = null;
            if ($locked->settlement_type === 'exchange') {
                $exchange = $locked->exchange()->with('lines')->firstOrFail();
                if (bccomp((string) $exchange->difference_value, '0.00', 2) !== 0) {
                    $method = $paymentMethodId === null
                        ? null
                        : PaymentMethod::query()->whereKey($paymentMethodId)->where('status', 'active')->first();
                    if ($method === null) throw ValidationException::withMessages(['payment' => __('Select an active payment method to settle the exchange difference.')]);
                }
            }
            $eligible = '0.00';
            $damagedStore = null;
            foreach ($locked->lines as $line) {
                $saleLine = SaleLine::query()->whereKey($line->sale_line_id)->lockForUpdate()->firstOrFail();
                if ((int) $saleLine->sale_id !== (int) $sourceSale->id) throw ValidationException::withMessages(['lines' => __('A return line is not from the referenced sale.')]);
                // This must be a current locking read. A normal aggregate can
                // retain the transaction's repeatable-read snapshot after the
                // worker waited on the source line, allowing an over-return.
                $completed = (string) RetailReturnLine::query()->where('sale_line_id', $saleLine->id)->whereHas('retailReturn', fn ($q) => $q->where('status', 'completed'))->lockForUpdate()->sum('quantity');
                $remaining = bcsub((string) $saleLine->quantity, $completed, 6);
                if (bccomp((string) $line->quantity, $remaining, 6) > 0) throw ValidationException::withMessages(['lines' => __('The selected quantity is no longer returnable.')]);
                $lineValue = bcmul((string) $line->quantity, (string) $line->unit_value, 2);
                $eligible = bcadd($eligible, $lineValue, 2);
                $line->update(['eligible_value' => $lineValue]);
                if ($line->disposition === 'restock' && $line->condition === 'sellable') {
                    app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $locked->store_id, (string) $line->quantity, 'retail_return', (string) ($saleLine->consumed_cost ?? '0.0000'), $idempotencyKey.':line:'.$line->id, RetailReturn::class, (int) $locked->id, (int) $line->id);
                } elseif ($this->requiresDamagedStore($line)) {
                    $damagedStore ??= Store::query()
                        ->visibleTo($actor)
                        ->where('branch_id', $locked->branch_id)
                        ->where('type', 'damaged')
                        ->where('status', 'active')
                        ->first();
                    if ($damagedStore === null) throw ValidationException::withMessages(['disposition' => __('An active damaged store in your branch is required for this return disposition.')]);
                    app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $damagedStore->id, (string) $line->quantity, 'retail_return_damaged', (string) ($saleLine->consumed_cost ?? '0.0000'), $idempotencyKey.':line:'.$line->id, RetailReturn::class, (int) $locked->id, (int) $line->id);
                }
            }
            $locked->update(['eligible_value' => $eligible, 'settlement_value' => $eligible]);
            if ($locked->settlement_type === 'gift_card') {
                $card = app(GiftCardAction::class)->issue($actor, $eligible, (int) $locked->branch_id, (int) $locked->store_id, RetailReturn::class, (string) $locked->id, 'return-gift-card:'.$locked->id, $locked->return_number, $locked->customer_id, (string) $locked->currency_code);
                RetailReturnSettlement::query()->create(['retail_return_id' => $locked->id, 'gift_card_id' => $card->id, 'direction' => 'refund', 'amount' => $eligible, 'settlement_type' => 'gift_card', 'idempotency_key' => $idempotencyKey.':settlement', 'created_by' => $actor->id, 'reason' => $locked->reason]);
            } elseif ($locked->settlement_type === 'exchange') {
                /** @var Exchange $exchange */
                foreach ($exchange->lines as $line) app(PostInventoryMovement::class)->execute((int) $line->product_id, (int) $locked->store_id, '-'.(string) $line->quantity, 'retail_exchange_out', null, $idempotencyKey.':exchange:'.$line->id, RetailReturn::class, (int) $locked->id, (int) $line->id);
                $difference = (string) $exchange->difference_value;
                if (bccomp($difference, '0.00', 2) !== 0) RetailReturnSettlement::query()->create(['retail_return_id' => $locked->id, 'payment_method_id' => $paymentMethodId, 'direction' => bccomp($difference, '0.00', 2) > 0 ? 'collect' : 'refund', 'amount' => ltrim($difference, '-'), 'settlement_type' => 'exchange_difference', 'idempotency_key' => $idempotencyKey.':settlement', 'created_by' => $actor->id, 'reason' => $locked->reason]);
                $exchange->update(['status' => 'completed']);
            } else {
                if ($locked->settlement_type === 'original_tender') {
                    $originalPayment = $originalPaymentId === null
                        ? null
                        : $sourceSale->payments()->whereKey($originalPaymentId)->lockForUpdate()->first();
                    if ($originalPayment === null) throw ValidationException::withMessages(['payment' => __('Select an original payment from the referenced sale.')]);
                    $alreadyRefunded = (string) RetailReturnSettlement::query()
                        ->where('original_payment_id', $originalPayment->id)
                        ->where('direction', 'refund')
                        ->lockForUpdate()
                        ->sum('amount');
                    $remainingPayment = bcsub((string) $originalPayment->amount, $alreadyRefunded, 2);
                    if (bccomp($eligible, $remainingPayment, 2) > 0) throw ValidationException::withMessages(['payment' => __('The original payment does not have enough unreversed value for this return.')]);
                    $originalPaymentId = (int) $originalPayment->id;
                    $paymentMethodId = (int) $originalPayment->payment_method_id;
                } elseif ($locked->settlement_type === 'cash_refund') {
                    $method = $paymentMethodId === null
                        ? null
                        : PaymentMethod::query()->whereKey($paymentMethodId)->where('status', 'active')->where('type', 'cash')->first();
                    if ($method === null) throw ValidationException::withMessages(['payment' => __('Select an active cash payment method for this refund.')]);
                } elseif ($paymentMethodId !== null) {
                    $method = PaymentMethod::query()->whereKey($paymentMethodId)->where('status', 'active')->first();
                    if ($method === null) throw ValidationException::withMessages(['payment' => __('Select an active refund payment method.')]);
                }
                RetailReturnSettlement::query()->create(['retail_return_id' => $locked->id, 'payment_method_id' => $paymentMethodId, 'original_payment_id' => $originalPaymentId, 'direction' => 'refund', 'amount' => $eligible, 'settlement_type' => $locked->settlement_type, 'idempotency_key' => $idempotencyKey.':settlement', 'created_by' => $actor->id, 'reason' => $locked->reason]);
            }
            $locked->update(['status' => 'completed', 'completed_at' => now(), 'lock_version' => (int) $locked->lock_version + 1]);
            if ($locked->source_gift_receipt_id !== null) {
                $receipt = GiftReceipt::query()->whereKey($locked->source_gift_receipt_id)->lockForUpdate()->firstOrFail();
                if ($receipt->status !== 'active') throw ValidationException::withMessages(['source' => __('The Gift Receipt was used by another return.')]);
                $receipt->update(['status' => 'used', 'used_return_id' => $locked->id, 'used_by' => $actor->id, 'used_at' => now(), 'lock_version' => (int) $receipt->lock_version + 1]);
                app(RecordAuditEvent::class)->execute('retail', 'gift_receipt_used', $receipt, ['status' => 'active'], ['status' => 'used', 'return_id' => $locked->id], (int) $receipt->branch_id, (int) $receipt->store_id, metadata: ['actor_id' => $actor->id]);
            }
            app(RecordAuditEvent::class)->execute('retail', 'retail_return_completed', $locked, $before, ['status' => 'completed', 'eligible_value' => $eligible, 'settlement_value' => $eligible, 'settlement_type' => $locked->settlement_type], (int) $locked->branch_id, (int) $locked->store_id, reasonText: $locked->reason, metadata: ['actor_id' => $actor->id, 'source_sale_id' => $locked->source_sale_id, 'idempotency_key' => $idempotencyKey]);
            return $locked->fresh('lines', 'settlements', 'exchange.lines');
            }, 5);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /** @param array<int, array<string, mixed>> $requested @return array<int, array<string, mixed>> */
    private function resolveLines(array $requested, Sale $sale, ?GiftReceipt $receipt): array
    {
        $allowed = $receipt?->lines->keyBy('sale_line_id');
        $result = [];
        foreach ($requested as $index => $input) {
            $saleLine = $sale->lines->firstWhere('id', (int) ($input['sale_line_id'] ?? 0));
            if ($saleLine === null || ($allowed !== null && ! $allowed->has($saleLine->id))) throw ValidationException::withMessages(['lines.'.$index => __('A return line is not eligible from this source.')]);
            $quantity = (string) ($input['quantity'] ?? '0');
            if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) throw ValidationException::withMessages(['lines.'.$index.'.quantity' => __('Enter a valid positive return quantity.')]);
            $condition = (string) ($input['condition'] ?? 'sellable');
            $disposition = (string) ($input['disposition'] ?? ($condition === 'sellable' ? 'restock' : 'quarantine'));
            if (! in_array($condition, ['sellable', 'non_sellable', 'damaged', 'manager_review'], true) || ! in_array($disposition, ['restock', 'quarantine'], true)) throw ValidationException::withMessages(['lines.'.$index => __('The condition or stock disposition is invalid.')]);
            $unitValue = bcdiv((string) $saleLine->net_amount, (string) $saleLine->quantity, 4);
            $result[] = ['sale_line_id' => $saleLine->id, 'product_id' => $saleLine->product_id, 'line_number' => $saleLine->line_number, 'quantity' => $quantity, 'unit_value' => $unitValue, 'eligible_value' => bcmul($quantity, $unitValue, 2), 'condition' => $condition, 'disposition' => $disposition, 'inspection_notes' => isset($input['inspection_notes']) ? trim((string) $input['inspection_notes']) : null];
        }
        return $result;
    }

    /** @param array<int, array<string, mixed>> $requested */
    private function createExchange(RetailReturn $return, User $actor, array $requested, int $storeId): void
    {
        if ($requested === []) throw ValidationException::withMessages(['exchange_lines' => __('An exchange must include replacement lines.')]);
        $exchange = Exchange::query()->create(['retail_return_id' => $return->id, 'exchange_number' => $this->number('retail_exchange', 'EX-'), 'status' => 'draft', 'replacement_value' => '0.00', 'difference_value' => '0.00', 'difference_direction' => 'none']);
        $replacement = '0.00';
        foreach ($requested as $index => $input) {
            $product = Product::query()->sellable()->whereKey((int) ($input['product_id'] ?? 0))->first();
            $price = $product === null ? null : app(EffectivePriceResolver::class)->resolve((int) $product->id, $storeId);
            $quantity = (string) ($input['quantity'] ?? '0');
            if ($product === null || $price === null || ! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) throw ValidationException::withMessages(['exchange_lines.'.$index => __('The replacement product is not currently priced or the quantity is invalid.')]);
            $value = bcmul($quantity, (string) $price->amount, 2);
            $exchange->lines()->create(['product_id' => $product->id, 'direction' => 'outbound', 'quantity' => $quantity, 'unit_value' => $price->amount, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en]);
            $replacement = bcadd($replacement, $value, 2);
        }
        $difference = bcsub($replacement, (string) $return->eligible_value, 2);
        $exchange->update(['replacement_value' => $replacement, 'difference_value' => $difference, 'difference_direction' => bccomp($difference, '0.00', 2) > 0 ? 'collect' : (bccomp($difference, '0.00', 2) < 0 ? 'refund' : 'none')]);
    }

    private function lockVisible(User $actor, RetailReturn $return): RetailReturn
    {
        return RetailReturn::query()->visibleTo($actor)->whereKey($return->id)->lockForUpdate()->firstOrFail();
    }

    private function requiresDamagedStore(RetailReturnLine $line): bool
    {
        return $line->disposition === 'quarantine' || in_array($line->condition, ['non_sellable', 'damaged'], true);
    }

    private function authorize(User $actor, string $permission): void { abort_unless($actor->is_super_admin || $actor->can($permission), 403); }
    private function number(string $type, string $prefix): string
    {
        if (DocumentSequence::query()->where('document_type', $type)->exists()) return app(AllocateDocumentNumber::class)->execute($type);
        return $prefix.strtoupper(Str::random(20));
    }
    /** @param array<int, array<string, mixed>> $lines @return array<string, mixed> */
    private function auditReturn(RetailReturn $return, array $lines): array { return ['return_number' => $return->return_number, 'status' => $return->status, 'source_sale_id' => $return->source_sale_id, 'source_gift_receipt_id' => $return->source_gift_receipt_id, 'line_count' => count($lines), 'settlement_type' => $return->settlement_type, 'inspection' => array_map(static fn (array $line): array => ['sale_line_id' => $line['sale_line_id'], 'condition' => $line['condition'], 'disposition' => $line['disposition'], 'inspection_notes' => $line['inspection_notes']], $lines)]; }
}
