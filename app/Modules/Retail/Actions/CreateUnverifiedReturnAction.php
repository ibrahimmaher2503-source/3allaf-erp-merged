<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\RetailReturnSettlement;
use App\Modules\Retail\Support\PaymentReferenceGuard;
use App\Modules\Retail\Support\PosContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateUnverifiedReturnAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data, string $idempotencyKey): RetailReturn
    {
        abort_unless($actor->is_super_admin || ($actor->can('returns.unverified') && $actor->can('returns.approve') && $actor->can('returns.complete')), 403);
        if (($data['confirmed_no_sale'] ?? false) !== true) {
            throw ValidationException::withMessages(['unverified' => __('Confirm that all original-sale lookup paths were attempted before this exception.')]);
        }
        if (! preg_match('/^[A-Za-z0-9-]{8,190}$/', $idempotencyKey)) {
            throw ValidationException::withMessages(['unverified' => __('A valid return submission token is required.')]);
        }
        $quantity = trim((string) ($data['quantity'] ?? ''));
        $amount = trim((string) ($data['amount'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));
        if (! preg_match('/^[1-9]\d{0,5}$/', $quantity)) {
            throw ValidationException::withMessages(['unverifiedQuantity' => __('Enter a positive whole return quantity.')]);
        }
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['unverifiedAmount' => __('Enter a positive manager-approved refund amount with at most two decimals.')]);
        }
        $amount = bcadd($amount, '0', 2);
        if (! in_array($reason, ['defective', 'wrong_item', 'damaged', 'size_color', 'changed_mind', 'sale_error', 'other'], true)) {
            throw ValidationException::withMessages(['unverifiedReason' => __('Select a return reason.')]);
        }
        if (mb_strlen($note) > 1800) {
            throw ValidationException::withMessages(['unverifiedNote' => __('The return note is too long.')]);
        }
        $reference = PaymentReferenceGuard::normalize((string) ($data['safe_reference'] ?? ''));
        $payloadHash = hash('sha256', json_encode([
            'product_id' => (int) ($data['product_id'] ?? 0), 'quantity' => $quantity, 'amount' => $amount,
            'payment_method_id' => (int) ($data['payment_method_id'] ?? 0), 'reason' => $reason,
            'note' => $note, 'safe_reference' => $reference, 'confirmed_no_sale' => true,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $lockName = 'rajeh:unverified-return:'.substr(hash('sha256', $idempotencyKey), 0, 32);
        if ((int) (DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockName])->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages(['unverified' => __('Another return submission is in progress. Retry after it finishes.')]);
        }
        try {
            return DB::transaction(function () use ($actor, $data, $idempotencyKey, $quantity, $amount, $reason, $note, $reference, $payloadHash): RetailReturn {
                $existing = RetailReturn::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! $existing->is_verified && hash_equals((string) $existing->payload_hash, $payloadHash) && $existing->status === 'completed') {
                        return $existing->load(['lines.product', 'settlements.paymentMethod']);
                    }
                    throw ValidationException::withMessages(['unverified' => __('This return submission token was already used with different details.')]);
                }
                $context = app(PosContextResolver::class)->resolve($actor);
                if (! $context->isReady()) {
                    throw ValidationException::withMessages(['unverified' => __('An active branch, selling store, drawer, and shift are required for this return.')]);
                }
                $store = Store::query()->whereKey($context->store?->id)->where('branch_id', $context->branch?->id)
                    ->where('status', 'active')->lockForUpdate()->first();
                if ($store === null) {
                    throw ValidationException::withMessages(['unverified' => __('The operational return stock location is unavailable.')]);
                }
                $product = Product::query()->active()->where('has_variations', false)->availableToCompany((int) $store->company_id, (int) $store->branch_id, (int) $store->id)
                    ->whereKey((int) ($data['product_id'] ?? 0))->lockForUpdate()->first();
                if ($product === null) {
                    throw ValidationException::withMessages(['unverifiedProductId' => __('Select an active product available to this selling location.')]);
                }
                $method = PaymentMethod::query()->whereKey((int) ($data['payment_method_id'] ?? 0))->where('status', 'active')->first();
                if ($method === null || (string) $method->type === 'gift_card') {
                    throw ValidationException::withMessages(['unverifiedMethodId' => __('Select an active cash or electronic refund method.')]);
                }
                if ($method->requires_evidence && $reference === null) {
                    throw ValidationException::withMessages(['unverifiedReference' => __('A safe transaction reference is required for this refund method.')]);
                }
                $number = DocumentSequence::query()->where('document_type', 'retail_return')->exists()
                    ? app(AllocateDocumentNumber::class)->executeForBranch('retail_return', (int) $store->branch_id)
                    : 'RET-'.strtoupper(Str::random(20));
                $now = now();
                $document = RetailReturn::query()->create([
                    'branch_id' => $store->branch_id, 'store_id' => $store->id,
                    'shift_id' => $context->shift?->id, 'cash_drawer_id' => $context->drawer?->id,
                    'cashier_id' => $actor->id, 'approved_by' => $actor->id,
                    'source_sale_id' => null, 'is_verified' => false,
                    'return_number' => $number, 'status' => 'completed',
                    'settlement_type' => 'unverified_refund', 'reason' => $note === '' ? $reason : $reason.' — '.$note,
                    'eligible_value' => $amount, 'subtotal_refund' => $amount,
                    'discount_refund' => '0.00', 'tax_refund' => '0.00', 'rounding_refund' => '0.00',
                    'settlement_value' => $amount, 'manager_approved_amount' => $amount,
                    'currency_code' => (string) $store->company?->currency_code ?: 'EGP',
                    'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                    'completion_idempotency_key' => $idempotencyKey.':complete', 'completion_payload_hash' => $payloadHash,
                    'submitted_at' => $now, 'approved_at' => $now, 'completed_at' => $now,
                    'financial_reversal_snapshot' => ['basis' => 'manager_approved_amount', 'original_sale' => null, 'amount' => $amount, 'method_code' => $method->code],
                ]);
                $line = $document->lines()->create([
                    'sale_line_id' => null, 'product_id' => $product->id, 'return_store_id' => $store->id,
                    'line_number' => 1, 'quantity' => $quantity,
                    'unit_value' => bcdiv($amount, $quantity, 4), 'gross_value' => $amount,
                    'discount_value' => '0.00', 'eligible_value' => $amount, 'tax_value' => '0.00',
                    'condition' => 'uninspected', 'disposition' => 'restock',
                ]);
                app(PostInventoryMovement::class)->execute((int) $product->id, (int) $store->id, $quantity,
                    'retail_return', null, 'unverified-return:'.$document->id.':line:'.$line->id,
                    RetailReturn::class, (int) $document->id, (int) $line->id);
                RetailReturnSettlement::query()->create([
                    'retail_return_id' => $document->id, 'payment_method_id' => $method->id,
                    'original_payment_id' => null, 'allocation_position' => 1,
                    'method_code_snapshot' => $method->code, 'method_type_snapshot' => $method->type,
                    'safe_reference' => $reference, 'direction' => 'refund', 'amount' => $amount,
                    'settlement_type' => 'unverified_refund', 'idempotency_key' => $idempotencyKey.':settlement:1',
                    'created_by' => $actor->id, 'reason' => $document->reason,
                ]);
                app(RecordAuditEvent::class)->execute('retail', 'unverified_retail_return_completed', $document,
                    after: ['return_number' => $number, 'verified' => false, 'product_id' => $product->id,
                        'quantity' => $quantity, 'approved_amount' => $amount, 'payment_method_code' => $method->code,
                        'return_store_id' => $store->id, 'source_sale_id' => null],
                    branchId: (int) $store->branch_id, storeId: (int) $store->id, reasonText: $document->reason,
                    metadata: ['manager_id' => $actor->id, 'idempotency_key' => $idempotencyKey]);

                return $document->load(['lines.product', 'settlements.paymentMethod']);
            }, 5);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}
