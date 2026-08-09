<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\OpenPricePolicy;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SuspendedSale;
use App\Modules\Retail\Services\DiscountPolicy;
use App\Modules\Retail\Services\PosCalculationService;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Atomic POS sale posting boundary for POS-03 through POS-06 and PRC-08. */
final class RetailSaleAction
{
    public function __construct(
        private readonly EffectivePriceResolver $prices,
        private readonly PosCalculationService $calculator,
        private readonly CapturePaymentAction $payments,
        private readonly DiscountPolicy $discounts,
        private readonly OpenPricePolicy $openPrices,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $requestedLines
     * @param array<int, array{method: PaymentMethod, amount?: numeric-string, tendered?: numeric-string|null, evidence_reference?: string|null, evidence_attachment_id?: string|null}> $tenders
     * @param array{tax_applicable?: bool} $financial
     */
    public function create(
        User $cashier,
        Store $store,
        array $requestedLines,
        string $idempotencyKey,
        bool $suspend = false,
        array $tenders = [],
        array $financial = [],
    ): Sale {
        abort_unless($cashier->can('pos_sales.create'), 403);
        abort_unless(Store::query()->visibleTo($cashier)->whereKey($store->id)->exists(), 403);
        $store->loadMissing('company');

        $lines = $this->resolveLines($cashier, $store, $requestedLines);
        $tax = $this->resolveTax($cashier, (bool) ($financial['tax_applicable'] ?? false));
        $tenders = $this->orderedTenders($tenders);
        $fingerprint = $this->fingerprint($store, $cashier, $lines, $tax, $tenders, $suspend);

        $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines', 'payments')->first();
        if ($existing !== null) {
            return $this->assertReplaySafe($existing, $store, $cashier, $fingerprint, $suspend);
        }

        try {
            $shift = $this->openShift($cashier, $store);

            return $this->createSale($cashier, $store, $shift, $lines, $idempotencyKey, $fingerprint, $tax, $suspend, $tenders);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines', 'payments')->first();
            if ($existing === null) {
                throw new InvalidArgumentException(__('The checkout is already being processed. Retry with the same checkout token.'));
            }

            return $this->assertReplaySafe($existing, $store, $cashier, $fingerprint, $suspend);
        }
    }

    private function assertReplaySafe(Sale $existing, Store $store, User $cashier, string $fingerprint, bool $suspend): Sale
    {
        $safe = (int) $existing->store_id === (int) $store->id
            && (int) $existing->cashier_id === (int) $cashier->id
            && ($existing->suspended_at !== null) === $suspend
            && hash_equals((string) $existing->request_fingerprint, $fingerprint);

        if (! $safe) {
            throw new InvalidArgumentException(__('This checkout token was already used with a different basket or financial payload.'));
        }

        return $existing;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function createSale(
        User $cashier,
        Store $store,
        PosShift $shift,
        array $lines,
        string $idempotencyKey,
        string $fingerprint,
        ?TaxSetting $tax,
        bool $suspend,
        array $tenders,
    ): Sale {
        return DB::transaction(function () use ($cashier, $store, $shift, $lines, $idempotencyKey, $fingerprint, $tax, $suspend, $tenders): Sale {
            $shift = PosShift::query()->lockForUpdate()->findOrFail((int) $shift->getKey());
            /** @var ShiftState $shiftState */
            $shiftState = $shift->status;
            if (! $shiftState->acceptsActivity()) {
                throw new RuntimeException(__('This shift is closing and no longer accepts sales.'));
            }
            if (! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
                ->where('cashier_id', $cashier->id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
                throw new RuntimeException(__('The shift no longer has an active cashier and drawer assignment.'));
            }

            $this->assertPricesRemainCurrent($store, $lines);

            $totals = $this->calculator->calculate(
                array_map(static fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                ], $lines),
                '0.00',
                $tax === null
                    ? ['applicable' => false]
                    : ['applicable' => true, 'rate' => (string) $tax->rate, 'inclusive' => (bool) $tax->is_tax_inclusive],
            );

            $hasCash = ! $suspend && $this->hasCashTender($tenders);
            $cashRounding = $hasCash ? $this->calculator->cashRoundingAdjustment($totals['total']) : '0.00';
            $payable = bcadd($totals['total'], $cashRounding, 2);

            $currencyCode = trim((string) $store->company?->currency_code);
            if ($currencyCode === '' || strtoupper($currencyCode) === 'TBD') {
                throw new InvalidArgumentException(__('The company currency must be configured before a sale can be posted.'));
            }
            if (strtoupper($currencyCode) !== strtoupper((string) $shift->currency_code)) {
                throw new InvalidArgumentException(__('The active shift currency does not match the selling store currency.'));
            }

            $sale = Sale::query()->create([
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'cash_drawer_id' => $shift->cash_drawer_id,
                'shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'status' => $suspend ? 'suspended' : 'draft',
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'currency_code' => $currencyCode,
                'suspended_at' => $suspend ? now() : null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'tax_applicable' => $tax !== null,
                'tax_setting_id' => $tax?->id,
                'tax_rate_snapshot' => $tax?->rate,
                'tax_inclusive_snapshot' => (bool) ($tax?->is_tax_inclusive ?? false),
                'total' => $totals['total'],
                'paid_total' => '0.00',
                'change_total' => '0.00',
                'cash_rounding_amount' => $cashRounding,
                'payable_total' => $payable,
            ]);

            foreach ($lines as $index => $line) {
                $computed = $totals['lines'][$index];
                $saleLine = SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'line_number' => $index + 1,
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'reference_price' => $line['reference_price'],
                    'is_open_price' => $line['is_open_price'],
                    'open_price_authorized_by' => $line['is_open_price'] ? $cashier->id : null,
                    'open_price_minimum_snapshot' => $line['open_price_minimum'],
                    'open_price_maximum_snapshot' => $line['open_price_maximum'],
                    'open_price_reason' => $line['open_price_reason'],
                    'gross_amount' => $computed['gross_amount'],
                    'discount_amount' => $computed['discount_amount'],
                    'discount_type' => $line['discount_type'],
                    'discount_reason' => $line['discount_reason'],
                    'discount_applied_by' => $line['discount_type'] ? $cashier->id : null,
                    'discount_replaced_by' => $line['discount_replaced'] ? $cashier->id : null,
                    'discount_replaced_at' => $line['discount_replaced'] ? now() : null,
                    'allocated_invoice_discount' => '0.00',
                    'net_amount' => $computed['net_amount'],
                ]);

                if ($line['discount_replaced']) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'retail',
                        event: 'sale_discount_replaced',
                        source: $saleLine,
                        before: ['type' => $line['discount_previous_type'], 'amount' => $line['discount_previous_amount']],
                        after: ['type' => $line['discount_type'], 'amount' => $line['discount_amount']],
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        reasonText: $line['discount_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id],
                    );
                }
                if ($line['is_open_price']) {
                    app(RecordAuditEvent::class)->execute(
                        category: 'pricing',
                        event: 'sale_open_price_applied',
                        source: $saleLine,
                        before: ['reference_price' => $line['reference_price']],
                        after: ['selling_price' => $line['unit_price'], 'minimum' => $line['open_price_minimum'], 'maximum' => $line['open_price_maximum']],
                        branchId: (int) $store->branch_id,
                        storeId: (int) $store->id,
                        reasonText: $line['open_price_reason'],
                        metadata: ['actor_id' => $cashier->id, 'sale_id' => $sale->id],
                    );
                }
            }

            if ($suspend) {
                SuspendedSale::query()->create([
                    'sale_id' => $sale->id,
                    'resume_code' => 'S-'.strtoupper(Str::random(10)),
                    'created_by' => $cashier->id,
                    'status' => 'suspended',
                ]);

                return $sale->fresh('lines', 'suspendedSale');
            }

            $this->captureTenders($cashier, $sale, $tenders);

            return $this->finalize($sale, $cashier);
        });
    }

    /**
     * Revalidate a suspended sale for the resume payment screen without
     * mutating it. Finalization repeats these checks under row locks.
     *
     * @return array{sale: Sale, shift: PosShift, lines: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function suspendedResumePreview(User $cashier, Sale $sale): array
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        $sale = Sale::query()->with(['lines.product', 'suspendedSale', 'store.company'])->findOrFail($sale->id);
        $context = $this->revalidateSuspendedContext($cashier, $sale, false);

        return [
            'sale' => $sale,
            'shift' => $context['shift'],
            'lines' => $context['lines'],
            'totals' => $context['totals'],
        ];
    }

    /** @param array<int, array<string, mixed>> $tenders */
    public function finalizeSuspended(User $cashier, Sale $sale, array $tenders = []): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        $tenders = $this->orderedTenders($tenders);

        return DB::transaction(function () use ($sale, $cashier, $tenders): Sale {
            $sale = Sale::query()->with(['lines.product', 'suspendedSale', 'store.company'])->lockForUpdate()->findOrFail($sale->id);
            $context = $this->revalidateSuspendedContext($cashier, $sale, true);
            $lines = $context['lines'];
            $totals = $context['totals'];
            $tax = $context['tax'];
            $shift = $context['shift'];

            $rounding = $this->hasCashTender($tenders) ? $this->calculator->cashRoundingAdjustment($totals['total']) : '0.00';
            $sale->update([
                'branch_id' => $shift->branch_id,
                'store_id' => $shift->store_id,
                'cash_drawer_id' => $shift->cash_drawer_id,
                'shift_id' => $shift->id,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'tax_applicable' => $tax !== null,
                'tax_setting_id' => $tax?->id,
                'tax_rate_snapshot' => $tax?->rate,
                'tax_inclusive_snapshot' => (bool) ($tax?->is_tax_inclusive ?? false),
                'total' => $totals['total'],
                'cash_rounding_amount' => $rounding,
                'payable_total' => bcadd($totals['total'], $rounding, 2),
            ]);

            foreach ($sale->lines->values() as $index => $saleLine) {
                $line = $lines[$index];
                $computed = $totals['lines'][$index];
                $saleLine->update([
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'reference_price' => $line['reference_price'],
                    'is_open_price' => $line['is_open_price'],
                    'open_price_authorized_by' => $line['is_open_price'] ? $cashier->id : null,
                    'open_price_minimum_snapshot' => $line['open_price_minimum'],
                    'open_price_maximum_snapshot' => $line['open_price_maximum'],
                    'open_price_reason' => $line['open_price_reason'],
                    'gross_amount' => $computed['gross_amount'],
                    'discount_amount' => $computed['discount_amount'],
                    'discount_type' => $line['discount_type'],
                    'discount_reason' => $line['discount_reason'],
                    'discount_applied_by' => $line['discount_type'] ? $cashier->id : null,
                    'allocated_invoice_discount' => '0.00',
                    'net_amount' => $computed['net_amount'],
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'suspended_sale_revalidated',
                source: $sale,
                after: $sale->only(['shift_id', 'cash_drawer_id', 'subtotal', 'discount_total', 'tax_total', 'total', 'payable_total']),
                branchId: (int) $sale->branch_id,
                storeId: (int) $sale->store_id,
                metadata: ['actor_id' => $cashier->id, 'price_revalidated' => true, 'stock_revalidated_at_finalization' => true],
            );

            $this->captureTenders($cashier, $sale, $tenders);

            return $this->finalize($sale, $cashier);
        });
    }

    /** @param array<int, array<string, mixed>> $tenders */
    private function captureTenders(User $cashier, Sale $sale, array $tenders): void
    {
        foreach ($tenders as $index => $tender) {
            $this->payments->execute(
                $cashier,
                $sale,
                $tender['method'],
                (string) ($tender['amount'] ?? '0.00'),
                'SALE:'.$sale->id.':TENDER:'.$index.':'.$tender['method']->code,
                isset($tender['tendered']) ? (string) $tender['tendered'] : null,
                isset($tender['evidence_reference']) ? (string) $tender['evidence_reference'] : null,
                isset($tender['evidence_attachment_id']) ? (string) $tender['evidence_attachment_id'] : null,
            );
        }
    }

    /** @param array<int, array<string, mixed>> $requestedLines @return array<int, array<string, mixed>> */
    private function resolveLines(User $cashier, Store $store, array $requestedLines): array
    {
        if ($requestedLines === []) {
            throw new InvalidArgumentException(__('Add at least one product to the cart.'));
        }

        $resolved = [];
        foreach ($requestedLines as $requested) {
            $quantity = trim((string) ($requested['quantity'] ?? ''));
            if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) {
                throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
            }

            $product = isset($requested['product_id'])
                ? Product::query()->active()->find((int) $requested['product_id'])
                : Barcode::query()->active()->where('barcode', trim((string) ($requested['barcode'] ?? '')))->first()?->product;
            if (! $product instanceof Product) {
                throw new InvalidArgumentException(__('Product was not found or is inactive.'));
            }

            $price = $this->prices->resolve($product->id, $store->id);
            if (! $price instanceof PriceLine) {
                throw new InvalidArgumentException(__('Product has no approved effective price for this store.'));
            }

            $standardPrice = DecimalMoney::normalize((string) $price->amount, 4);
            $referencePrice = DecimalMoney::normalize((string) ($price->reference_amount ?? $price->amount), 4);
            $unitPrice = $standardPrice;
            $openPriceReason = null;
            $isOpenPrice = filled($requested['open_price_amount'] ?? null);
            if ($isOpenPrice) {
                abort_unless($cashier->can('pos_sales.open_price'), 403);
                if (! $price->open_price_allowed) {
                    throw new InvalidArgumentException(__('Open price is not enabled for this product price.'));
                }
                $unitPrice = DecimalMoney::normalize((string) $requested['open_price_amount'], 4);
                $openPriceReason = trim((string) ($requested['open_price_reason'] ?? ''));
                $this->openPrices->validateOrThrow(
                    referenceAmount: $referencePrice,
                    requestedAmount: $unitPrice,
                    minimum: $price->open_price_minimum === null ? null : (string) $price->open_price_minimum,
                    maximum: $price->open_price_maximum === null ? null : (string) $price->open_price_maximum,
                    hasPermission: true,
                    reason: $openPriceReason,
                );
            }

            $discountAmount = filled($requested['discount_amount'] ?? null)
                ? DecimalMoney::round((string) $requested['discount_amount'])
                : '0.00';
            $discountType = bccomp($discountAmount, '0', 2) > 0 ? (string) ($requested['discount_type'] ?? '') : null;
            $discountReason = filled($requested['discount_reason'] ?? null) ? trim((string) $requested['discount_reason']) : null;
            $discountReplaces = filled($requested['discount_replaces'] ?? null) ? (string) $requested['discount_replaces'] : null;
            $discountReplaced = false;
            if ($discountType !== null) {
                abort_unless($cashier->can('pos_sales.apply_discount'), 403);
                $gross = DecimalMoney::round(bcmul($quantity, $unitPrice, 8));
                $payload = $this->discounts->buildLineDiscount(
                    actor: $cashier,
                    discountAmount: $discountAmount,
                    baseAmount: $gross,
                    newType: $discountType,
                    existingType: $discountReplaces,
                    reason: $discountReason,
                );
                $discountAmount = DecimalMoney::round($payload['discount_amount']);
                $discountType = $payload['discount_type'];
                $discountReplaced = $payload['discount_replaced_by'] !== null;
            }

            $resolved[] = [
                'product' => $product,
                'price_line_id' => $price->id,
                'quantity' => DecimalMoney::normalize($quantity, 6),
                'unit_price' => $unitPrice,
                'reference_price' => $referencePrice,
                'is_open_price' => $isOpenPrice,
                'open_price_minimum' => $isOpenPrice ? (string) $price->open_price_minimum : null,
                'open_price_maximum' => $isOpenPrice ? (string) $price->open_price_maximum : null,
                'open_price_reason' => $openPriceReason,
                'discount_amount' => $discountAmount,
                'discount_type' => $discountType,
                'discount_reason' => $discountReason,
                'discount_replaced' => $discountReplaced,
                'discount_previous_type' => $discountReplaced ? $discountReplaces : null,
                'discount_previous_amount' => $discountReplaced ? (string) ($requested['discount_previous_amount'] ?? '0.00') : null,
            ];
        }

        return $resolved;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertPricesRemainCurrent(Store $store, array $lines): void
    {
        foreach ($lines as $line) {
            PriceLine::query()->lockForUpdate()->findOrFail($line['price_line_id']);
            $current = $this->prices->resolve($line['product']->id, $store->id);
            if ($current === null || (int) $current->id !== (int) $line['price_line_id']) {
                throw new InvalidArgumentException(__('A basket price changed before checkout. Review the basket and try again.'));
            }
        }
    }

    private function resolveTax(User $cashier, bool $applicable): ?TaxSetting
    {
        if (! $applicable) {
            return null;
        }
        abort_unless($cashier->can('pos_sales.apply_tax'), 403);

        $settings = TaxSetting::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderByDesc('effective_from')
            ->get();

        if ($settings->count() !== 1 || blank($settings->first()?->rate)) {
            throw new InvalidArgumentException(__('Tax was enabled, but exactly one effective tax policy with a configured rate is required.'));
        }

        return $settings->first();
    }

    /** @param array<int, array<string, mixed>> $tenders @return array<int, array<string, mixed>> */
    private function orderedTenders(array $tenders): array
    {
        if ($tenders === []) {
            return [];
        }

        $cashCount = 0;
        foreach ($tenders as &$tender) {
            if (! ($tender['method'] ?? null) instanceof PaymentMethod) {
                throw new InvalidArgumentException(__('Every tender requires a configured payment method.'));
            }
            $tender['method'] = PaymentMethod::query()->findOrFail($tender['method']->id);
            if ($this->isCashMethod($tender['method'])) {
                $cashCount++;
            }
        }
        unset($tender);

        if ($cashCount > 1) {
            throw new InvalidArgumentException(__('Only one cash tender may settle the residual.'));
        }

        usort($tenders, fn (array $left, array $right): int => (int) $this->isCashMethod($left['method']) <=> (int) $this->isCashMethod($right['method']));

        return array_values($tenders);
    }

    private function openShift(User $cashier, Store $store): PosShift
    {
        $shift = PosShift::query()->open()->where('store_id', $store->id)->where('cashier_id', $cashier->id)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('active_pos_shift_assignments')
                ->whereColumn('active_pos_shift_assignments.shift_id', 'pos_shifts.id'))
            ->with('cashDrawer')->orderByDesc('id')->first();
        if ($shift === null) {
            throw new RuntimeException(__('An active POS shift is required before starting a sale.'));
        }

        return $shift;
    }

    private function finalize(Sale $sale, User $cashier): Sale
    {
        $sale->loadMissing('lines', 'store', 'payments');
        $this->assertSettled($sale);

        foreach ($sale->lines as $line) {
            $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $sale->store_id)->lockForUpdate()->first();
            if ($balance === null || bccomp((string) $balance->on_hand, (string) $line->quantity, 6) < 0) {
                throw new InvalidArgumentException(__('Insufficient stock for one or more sale lines.'));
            }
        }

        $number = app(AllocateDocumentNumber::class)->execute('retail_sale');
        $poster = app(PostInventoryMovement::class);
        foreach ($sale->lines as $line) {
            $movement = $poster->execute(
                (int) $line->product_id,
                (int) $sale->store_id,
                '-'.(string) $line->quantity,
                'sale',
                null,
                'SALE:'.$sale->id.':LINE:'.$line->id,
                Sale::class,
                $sale->id,
                $line->id,
            );
            $line->update(['stock_movement_id' => $movement->id, 'consumed_cost' => $movement->consumed_cost]);
        }

        $paid = $this->payments->paidSoFar($sale);
        $change = $this->payments->changeSoFar($sale);
        $before = $sale->only(['status', 'document_number', 'lock_version']);
        $sale->update([
            'status' => 'approved',
            'document_number' => $number,
            'approved_at' => now(),
            'paid_total' => $paid,
            'change_total' => $change,
            'lock_version' => ((int) $sale->lock_version) + 1,
        ]);

        if ($sale->suspendedSale !== null) {
            $sale->suspendedSale->update(['status' => 'resumed', 'resumed_at' => now()]);
        }

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: 'finalize_sale',
            source: $sale,
            before: $before,
            after: $sale->only(['status', 'document_number', 'subtotal', 'discount_total', 'tax_total', 'total', 'cash_rounding_amount', 'payable_total', 'paid_total', 'change_total', 'lock_version']),
            branchId: (int) $sale->branch_id,
            storeId: (int) $sale->store_id,
            metadata: [
                'line_count' => $sale->lines->count(),
                'cashier_id' => $cashier->id,
                'stock_posted' => true,
                'payment_count' => $sale->payments()->count(),
                'payment_total' => $paid,
                'tax_setting_snapshot' => $sale->tax_setting_id,
                'open_price_line_ids' => $sale->lines->where('is_open_price', true)->pluck('id')->all(),
                'discount_line_ids' => $sale->lines->where('discount_amount', '>', 0)->pluck('id')->all(),
            ],
        );

        return $sale->fresh(['lines', 'store', 'cashier', 'payments.evidenceAttachment']);
    }

    private function assertSettled(Sale $sale): void
    {
        $paid = $this->payments->paidSoFar($sale);
        $payable = (string) $sale->payable_total;
        if (bccomp($paid, $payable, 2) !== 0) {
            throw new InvalidArgumentException(bccomp($paid, $payable, 2) < 0
                ? __('This sale is not fully settled and cannot be approved.')
                : __('Captured payments exceed the payable amount for this sale.'));
        }
    }

    /** @param array<int, array<string, mixed>> $tenders */
    private function hasCashTender(array $tenders): bool
    {
        return collect($tenders)->contains(fn (array $tender): bool => $this->isCashMethod($tender['method']));
    }

    private function isCashMethod(PaymentMethod $method): bool
    {
        return $method->isCash();
    }

    /**
     * @return array{shift: PosShift, lines: array<int, array<string, mixed>>, tax: ?TaxSetting, totals: array<string, mixed>}
     */
    private function revalidateSuspendedContext(User $cashier, Sale $sale, bool $lock): array
    {
        abort_unless($sale->status === 'suspended' && $sale->suspendedSale?->status === 'suspended', 422, __('This suspended sale is no longer available.'));
        abort_unless((int) $sale->cashier_id === (int) $cashier->id || $cashier->is_super_admin, 403);

        $storeQuery = Store::query()->visibleTo($cashier)->whereKey($sale->store_id)->where('status', 'active')->where('type', 'selling')->with('company');
        $store = $lock ? $storeQuery->lockForUpdate()->firstOrFail() : $storeQuery->firstOrFail();
        $shiftQuery = PosShift::query()->with('cashDrawer')->whereKey($sale->shift_id);
        $shift = $lock ? $shiftQuery->lockForUpdate()->firstOrFail() : $shiftQuery->firstOrFail();
        /** @var ShiftState $shiftState */
        $shiftState = $shift->status;
        if (! $shiftState->acceptsActivity()) {
            throw new InvalidArgumentException(__('The original shift is closed or closing; this suspended sale cannot be resumed.'));
        }
        if ((int) $shift->cashier_id !== (int) $sale->cashier_id
            || (int) $shift->branch_id !== (int) $sale->branch_id
            || (int) $shift->store_id !== (int) $sale->store_id
            || (int) $shift->cash_drawer_id !== (int) $sale->cash_drawer_id) {
            throw new InvalidArgumentException(__('The active shift, branch, store, or drawer no longer matches this suspended sale.'));
        }
        if (! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
            ->where('cashier_id', $shift->cashier_id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
            throw new InvalidArgumentException(__('The original shift no longer has an active cashier and drawer assignment.'));
        }

        $currency = strtoupper(trim((string) $store->company?->currency_code));
        if ($currency === '' || $currency === 'TBD' || $currency !== strtoupper((string) $sale->currency_code) || $currency !== strtoupper((string) $shift->currency_code)) {
            throw new InvalidArgumentException(__('The suspended sale, shift, and selling store currencies do not match.'));
        }

        $requestedLines = $sale->lines->sortBy('line_number')->map(static fn (SaleLine $line): array => [
            'product_id' => (int) $line->product_id,
            'quantity' => (string) $line->quantity,
            'open_price_amount' => $line->is_open_price ? (string) $line->unit_price : null,
            'open_price_reason' => $line->open_price_reason,
            'discount_amount' => (string) $line->discount_amount,
            'discount_type' => $line->discount_type,
            'discount_reason' => $line->discount_reason,
        ])->values()->all();

        $lines = $this->resolveLines($cashier, $store, $requestedLines);
        $this->assertPricesRemainCurrent($store, $lines);
        $tax = $this->resolveTax($cashier, (bool) $sale->tax_applicable);
        $totals = $this->calculator->calculate(
            array_map(static fn (array $line): array => [
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
            ], $lines),
            '0.00',
            $tax === null ? ['applicable' => false] : ['applicable' => true, 'rate' => (string) $tax->rate, 'inclusive' => (bool) $tax->is_tax_inclusive],
        );

        return compact('shift', 'lines', 'tax', 'totals');
    }

    /** @param array<int, array<string, mixed>> $lines @param array<int, array<string, mixed>> $tenders */
    private function fingerprint(Store $store, User $cashier, array $lines, ?TaxSetting $tax, array $tenders, bool $suspend): string
    {
        $payload = [
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'suspend' => $suspend,
            'tax_setting_id' => $tax?->id,
            'lines' => array_map(static fn (array $line): array => [
                'product_id' => $line['product']->id,
                'price_line_id' => $line['price_line_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'discount_type' => $line['discount_type'],
                'discount_reason' => $line['discount_reason'],
                'discount_previous_type' => $line['discount_previous_type'],
                'discount_previous_amount' => $line['discount_previous_amount'],
                'open_price_reason' => $line['open_price_reason'],
            ], $lines),
            'tenders' => array_map(static fn (array $tender): array => [
                'method_id' => $tender['method']->id,
                'amount' => (string) ($tender['amount'] ?? ''),
                'tendered' => (string) ($tender['tendered'] ?? ''),
                'evidence_reference' => (string) ($tender['evidence_reference'] ?? ''),
                'evidence_attachment_id' => (string) ($tender['evidence_attachment_id'] ?? ''),
            ], $tenders),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
