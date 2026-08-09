<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Models\SuspendedSale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class RetailSaleAction
{
    public function __construct(private readonly EffectivePriceResolver $prices) {}

    /** @param array<int, array{product_id?: int|string, barcode?: string, quantity: int|float|string}> $requestedLines */
    public function create(User $cashier, Store $store, array $requestedLines, string $idempotencyKey, bool $suspend = false): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        abort_unless($store->visibleTo($cashier)->whereKey($store->id)->exists(), 403);

        $lines = $this->resolveLines($store, $requestedLines);

        $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines')->first();
        if ($existing !== null) {
            return $this->assertReplaySafe($existing, $store, $cashier, $lines, $suspend);
        }

        try {
            $shift = $this->openShift($cashier, $store);

            return $this->createSale($cashier, $store, $shift, $lines, $idempotencyKey, $suspend);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_key')) {
                throw $e;
            }

            // Two concurrent submissions with the same idempotency key can
            // both pass the pre-insert check above before either commits.
            // The unique index is the real guard against a duplicate Sale;
            // this recovers the loser's request as an idempotent replay
            // instead of surfacing a raw DB error.
            $existing = Sale::query()->where('idempotency_key', $idempotencyKey)->with('lines')->first();
            if ($existing === null) {
                throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
            }

            return $this->assertReplaySafe($existing, $store, $cashier, $lines, $suspend);
        }
    }

    /** @param array<int, array{product: Product, quantity: numeric-string, unit_price: numeric-string}> $lines */
    private function assertReplaySafe(Sale $existing, Store $store, User $cashier, array $lines, bool $suspend): Sale
    {
        $replaySafe = $existing->store_id === $store->id
            && $existing->cashier_id === $cashier->id
            && (($existing->suspended_at !== null)) === $suspend
            && $this->linesMatch($existing->lines, $lines);

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    /** @param array<int, array{product: Product, quantity: numeric-string, unit_price: numeric-string}> $lines */
    private function createSale(User $cashier, Store $store, PosShift $shift, array $lines, string $idempotencyKey, bool $suspend): Sale
    {
        return DB::transaction(function () use ($cashier, $store, $shift, $lines, $idempotencyKey, $suspend): Sale {
            $sale = Sale::query()->create([
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'cash_drawer_id' => $shift->cash_drawer_id,
                'shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'status' => $suspend ? 'suspended' : 'draft',
                'idempotency_key' => $idempotencyKey,
                'currency_code' => $store->company?->getAttribute('currency_code') ?? 'EGP',
                'suspended_at' => $suspend ? now() : null,
                'notes' => 'LOCAL DEMO ONLY. POS financial and receipt policy remains PENDING.',
            ]);

            $subtotal = '0.00';
            foreach ($lines as $index => $line) {
                $gross = bcmul($line['quantity'], $line['unit_price'], 2);
                $subtotal = bcadd($subtotal, $gross, 2);
                SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'line_number' => $index + 1,
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'gross_amount' => $gross,
                    'discount_amount' => '0.00',
                    'net_amount' => $gross,
                ]);
            }

            $sale->update(['subtotal' => $subtotal, 'total' => $subtotal, 'paid_total' => $suspend ? '0.00' : $subtotal]);

            if ($suspend) {
                SuspendedSale::query()->create([
                    'sale_id' => $sale->id,
                    'resume_code' => 'S-'.strtoupper(Str::random(10)),
                    'created_by' => $cashier->id,
                    'status' => 'suspended',
                ]);

                return $sale->fresh('lines', 'suspendedSale');
            }

            return $this->finalize($sale, $cashier);
        });
    }

    public function finalizeSuspended(User $cashier, Sale $sale): Sale
    {
        abort_unless($cashier->can('pos_sales.create'), 403);
        $sale = Sale::query()->with('lines', 'suspendedSale')->lockForUpdate()->findOrFail($sale->id);
        abort_unless($sale->getAttribute('status') === 'suspended' && $sale->suspendedSale?->getAttribute('status') === 'suspended', 422, __('This suspended sale is no longer available.'));
        abort_unless($sale->cashier_id === $cashier->id || $cashier->is_super_admin, 403);

        return DB::transaction(fn (): Sale => $this->finalize($sale, $cashier));
    }

    /**
     * @param  array<int, array{product_id?: int|string, barcode?: string, quantity: int|float|string}>  $requestedLines
     * @return array<int, array{product: Product, quantity: numeric-string, unit_price: numeric-string}>
     */
    private function resolveLines(Store $store, array $requestedLines): array
    {
        if ($requestedLines === []) {
            throw new InvalidArgumentException(__('Add at least one product to the cart.'));
        }

        $resolved = [];
        foreach ($requestedLines as $requested) {
            /** @var numeric-string $quantity */
            $quantity = trim((string) $requested['quantity']);
            if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $quantity) || bccomp($quantity, '0', 6) <= 0) {
                throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
            }

            $product = null;
            if (isset($requested['product_id'])) {
                $product = Product::query()->active()->find((int) $requested['product_id']);
            } elseif (isset($requested['barcode'])) {
                $product = Barcode::query()->active()->where('barcode', trim((string) $requested['barcode']))->first()?->product;
            }
            if (! $product instanceof Product) {
                throw new InvalidArgumentException(__('Product was not found or is inactive.'));
            }

            $price = $this->prices->resolve($product->id, $store->id);
            if ($price === null) {
                throw new InvalidArgumentException(__('Product has no approved effective price for this store.'));
            }

            /** @var numeric-string $priceAmount */
            $priceAmount = (string) $price->getAttribute('amount');
            $resolved[] = ['product' => $product, 'quantity' => bcadd($quantity, '0', 6), 'unit_price' => bcadd($priceAmount, '0', 4)];
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, SaleLine>  $existingLines
     * @param  array<int, array{product: Product, quantity: numeric-string, unit_price: numeric-string}>  $lines
     */
    private function linesMatch($existingLines, array $lines): bool
    {
        $existingLines = $existingLines->sortBy('line_number')->values();
        if ($existingLines->count() !== count($lines)) {
            return false;
        }

        foreach (array_values($lines) as $index => $line) {
            $existingLine = $existingLines->get($index);
            if ($existingLine === null
                || (int) $existingLine->product_id !== $line['product']->id
                || bccomp((string) $existingLine->quantity, $line['quantity'], 6) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function openShift(User $cashier, Store $store): PosShift
    {
        $shift = PosShift::query()->open()
            ->where('store_id', $store->id)
            ->where('cashier_id', $cashier->id)
            ->with('cashDrawer')
            ->latest('opened_at')
            ->first();

        if ($shift === null) {
            throw new RuntimeException(__('An active POS shift is required before starting a sale.'));
        }

        return $shift;
    }

    private function finalize(Sale $sale, User $cashier): Sale
    {
        $sale->loadMissing('lines', 'store');
        foreach ($sale->lines as $line) {
            /** @var int $productId */
            $productId = (int) $line->getAttribute('product_id');
            /** @var numeric-string $lineQuantity */
            $lineQuantity = (string) $line->getAttribute('quantity');
            $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $sale->getAttribute('store_id'))->lockForUpdate()->first();
            if ($balance === null) {
                throw new InvalidArgumentException(__('Insufficient stock for one or more sale lines.'));
            }
            /** @var numeric-string $onHand */
            $onHand = (string) $balance->getAttribute('on_hand');
            if (bccomp($onHand, $lineQuantity, 6) < 0) {
                throw new InvalidArgumentException(__('Insufficient stock for one or more sale lines.'));
            }
        }

        $number = $this->allocateNumber();
        $poster = app(PostInventoryMovement::class);
        foreach ($sale->lines as $line) {
            /** @var int $productId */
            $productId = (int) $line->getAttribute('product_id');
            /** @var numeric-string $lineQuantity */
            $lineQuantity = (string) $line->getAttribute('quantity');
            $movement = $poster->execute(
                $productId,
                (int) $sale->getAttribute('store_id'),
                '-'.$lineQuantity,
                'sale',
                null,
                'SALE:'.$sale->id.':LINE:'.$line->id,
                Sale::class,
                $sale->id,
                $line->id,
            );
            $line->update(['stock_movement_id' => $movement->id, 'consumed_cost' => $movement->consumed_cost]);
        }

        $before = $sale->only(['status', 'document_number', 'lock_version']);
        $sale->update([
            'status' => 'approved',
            'document_number' => $number,
            'approved_at' => now(),
            'lock_version' => $sale->lock_version + 1,
        ]);

        if ($sale->suspendedSale !== null) {
            $sale->suspendedSale->update(['status' => 'resumed', 'resumed_at' => now()]);
        }

        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: 'finalize_sale',
            source: $sale,
            before: $before,
            after: $sale->only(['status', 'document_number', 'total', 'lock_version']),
            branchId: $sale->branch_id,
            storeId: $sale->store_id,
            metadata: ['line_count' => $sale->lines->count(), 'cashier_id' => $cashier->id, 'stock_posted' => true],
        );

        return $sale->fresh('lines', 'store', 'cashier');
    }

    private function allocateNumber(): string
    {
        $sequence = DocumentSequence::query()->where('document_type', 'retail_sale')->lockForUpdate()->first();
        if ($sequence === null) {
            $sequence = DocumentSequence::query()->create([
                'document_type' => 'retail_sale',
                'prefix' => 'SALE-'.now()->format('Y').'-',
                'padding_length' => 6,
                'next_value' => 1,
                'reset_rule' => 'never',
                'status' => 'active',
                'lock_version' => 1,
                'policy_notes' => 'LOCAL DEMO ONLY. Production numbering policy remains PENDING.',
            ]);
        }
        abort_unless($sequence->status === 'active', 422, __('Retail sale numbering is not active.'));
        $number = ($sequence->prefix ?: 'SALE-'.now()->format('Y').'-').str_pad((string) $sequence->next_value, $sequence->padding_length ?: 6, '0', STR_PAD_LEFT).($sequence->suffix ?: '');
        $sequence->update(['next_value' => $sequence->next_value + 1, 'lock_version' => $sequence->lock_version + 1]);

        return $number;
    }
}
