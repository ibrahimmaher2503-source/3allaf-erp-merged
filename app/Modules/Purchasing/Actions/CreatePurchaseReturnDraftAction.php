<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Support\ProductQuantity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreatePurchaseReturnDraftAction
{
    /** @param array<int, array<string, mixed>> $lines */
    public function executeWithoutInvoice(int $supplierId, int $storeId, int $reasonId, array $lines, ?string $idempotencyKey = null, ?string $notes = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($actor, $supplierId, $storeId, $reasonId, $lines, $idempotencyKey, $notes): PurchaseReturn {
            $supplier = Supplier::query()->active()->with('supplierGroup:id,company_id')->findOrFail($supplierId);
            $store = Store::query()->visibleTo($actor)->where('status', 'active')->find($storeId);
            if ($store === null) {
                throw new InvalidArgumentException('مخزن الإرجاع غير نشط أو خارج نطاق صلاحيتك.');
            }
            if ($supplier->supplierGroup !== null && (int) $supplier->supplierGroup->company_id !== (int) $store->company_id) {
                throw new InvalidArgumentException('المورد لا ينتمي إلى شركة مخزن الإرجاع.');
            }
            $reason = SupplierReturnReason::query()->where('is_active', true)->find($reasonId);
            if ($reason === null) {
                throw new InvalidArgumentException('لا يوجد سبب مرتجع نشط محدد. أضف سببًا نشطًا من الإعدادات.');
            }
            if ($lines === []) {
                throw new InvalidArgumentException('أضف صنفًا واحدًا على الأقل إلى مرتجع المورد.');
            }
            $key = $idempotencyKey ?: (string) Str::uuid();
            $existing = PurchaseReturn::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->created_by !== $actor->id || $existing->supplier_id !== $supplier->id
                    || $existing->store_id !== $store->id || $existing->reason_id !== $reason->id
                    || $existing->purchase_invoice_id !== null || (string) $existing->notes !== (string) $notes
                    || ! $this->linesMatch($existing->lines, $lines)) {
                    throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
                }

                return $existing->fresh(['supplier', 'store', 'reason', 'lines.product']);
            }
            $normalized = [];
            $subtotal = '0';
            $seen = [];
            foreach ($lines as $lineData) {
                $productId = (int) ($lineData['product_id'] ?? 0);
                if ($productId <= 0 || isset($seen[$productId])) {
                    throw new InvalidArgumentException('يجب اختيار كل صنف مرة واحدة فقط.');
                }
                $seen[$productId] = true;
                $quantity = bcadd((string) ProductQuantity::normalize($lineData['quantity'] ?? null), '0', 6);
                if (bccomp($quantity, '0', 6) <= 0) {
                    throw new InvalidArgumentException('يجب أن تكون كمية المرتجع أكبر من صفر.');
                }
                if (! ProductSupplier::query()->where('supplier_id', $supplierId)->where('product_id', $productId)->exists()) {
                    throw new InvalidArgumentException('الصنف غير مرتبط بالمورد المحدد. اختر موردًا مرتبطًا بالصنف.');
                }
                $product = Product::query()->lockForUpdate()->findOrFail($productId);
                if ($product->average_cost === null || bccomp((string) $product->average_cost, '0', 4) <= 0) {
                    throw new InvalidArgumentException('التكلفة الحالية للصنف غير متاحة في بطاقة الصنف.');
                }
                $balance = StockBalance::query()->where('store_id', $storeId)->where('product_id', $productId)->lockForUpdate()->first();
                $available = $balance === null ? '0' : bcsub((string) $balance->on_hand, (string) $balance->reserved, 6);
                if (bccomp($quantity, $available, 6) > 0) {
                    throw new InvalidArgumentException('كمية المرتجع تتجاوز المخزون المتاح في مخزن الإرجاع.');
                }
                $unitCost = bcadd((string) $product->average_cost, '0', 4);
                $total = bcmul($quantity, $unitCost, 4);
                $subtotal = bcadd($subtotal, $total, 4);
                $normalized[] = ['purchase_invoice_line_id' => null, 'product_id' => $productId, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $total, 'cost_source' => 'product_current_cost'];
            }
            $return = PurchaseReturn::query()->create([
                'supplier_id' => $supplierId, 'purchase_invoice_id' => null, 'store_id' => $storeId,
                'reason_id' => $reasonId, 'return_date' => now()->toDateString(), 'status' => 'draft',
                'subtotal' => $subtotal, 'total_amount' => $subtotal, 'idempotency_key' => $key,
                'lock_version' => 0, 'created_by' => $actor->id, 'updated_by' => $actor->id, 'notes' => $notes,
            ]);
            $return->lines()->createMany($normalized);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'create_supplier_return_draft', source: $return, after: $return->only(['id', 'supplier_id', 'purchase_invoice_id', 'store_id', 'reason_id', 'subtotal']), storeId: $storeId, reasonCode: $reason->code, metadata: ['cost_source' => 'product_current_cost', 'stock_posted' => false]);

            return $return->fresh(['supplier', 'store', 'reason', 'lines.product']);
        });
    }

    /**
     * Create a draft supplier return without posting stock.
     *
     * Every line must point to an approved purchase-invoice line. The unit cost
     * is copied from that source line; there is deliberately no fallback cost.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(int $purchaseInvoiceId, int $reasonId, array $lines, ?string $idempotencyKey = null, ?string $notes = null, ?int $sourceStoreId = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->id;

        return DB::transaction(function () use ($actor, $actorId, $purchaseInvoiceId, $reasonId, $lines, $idempotencyKey, $notes, $sourceStoreId): PurchaseReturn {
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($purchaseInvoiceId);

            if ($invoice->status !== 'approved') {
                throw new InvalidArgumentException(__('Supplier returns can only reference approved purchase invoices.'));
            }
            $storeId = $sourceStoreId ?? $invoice->store_id;
            if (! Store::query()->visibleTo($actor)->where('status', 'active')->where('company_id', $invoice->store->company_id)->whereKey($storeId)->exists()) {
                throw new InvalidArgumentException('مخزن الإرجاع غير نشط أو خارج نطاق صلاحيتك.');
            }

            $reason = SupplierReturnReason::query()
                ->whereKey($reasonId)
                ->where('is_active', true)
                ->first();
            if ($reason === null) {
                throw new InvalidArgumentException(__('An active supplier return reason is required.'));
            }

            if ($lines === []) {
                throw new InvalidArgumentException(__('A supplier return must contain at least one invoice line.'));
            }

            $aggregated = [];
            foreach ($lines as $lineData) {
                $sourceLineId = (int) ($lineData['purchase_invoice_line_id'] ?? 0);
                $quantity = bcadd((string) ProductQuantity::normalize($lineData['quantity'] ?? null), '0', 6);
                if ($this->compare($quantity, '0') <= 0) {
                    throw new InvalidArgumentException(__('Return quantity must be greater than zero.'));
                }
                if (isset($aggregated[$sourceLineId])) {
                    throw new InvalidArgumentException(__('A source invoice line can appear only once.'));
                }
                $aggregated[$sourceLineId] = ['input' => $lineData, 'quantity' => $quantity];
            }
            ksort($aggregated);

            $key = $idempotencyKey !== null && trim($idempotencyKey) !== '' ? trim($idempotencyKey) : (string) Str::uuid();
            $existing = PurchaseReturn::query()
                ->where('created_by', $actorId)
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->where('idempotency_key', $key)
                ->with('lines')
                ->first();
            if ($existing !== null) {
                $replaySafe = $existing->purchase_invoice_id === $purchaseInvoiceId
                    && $existing->reason_id === $reasonId
                    && $existing->store_id === $storeId
                    && (string) $existing->notes === (string) $notes
                    && $this->linesMatch($existing->lines, $lines);

                if (! $replaySafe) {
                    throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
                }

                return $existing->load(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
            }

            $normalized = [];
            $subtotal = '0';
            foreach ($aggregated as $sourceLineId => $aggregate) {
                $lineData = $aggregate['input'];
                $quantity = $aggregate['quantity'];
                $sourceLine = $invoice->lines->firstWhere('id', $sourceLineId);
                if ($sourceLine === null) {
                    throw new InvalidArgumentException(__('Every supplier return line must reference a line from the selected approved invoice.'));
                }
                if (isset($lineData['product_id']) && (int) $lineData['product_id'] !== (int) $sourceLine->product_id) {
                    throw new InvalidArgumentException(__('Every supplier return line must reference a line from the selected approved invoice.'));
                }

                $received = $this->decimal($sourceLine->quantity_received);
                $alreadyReturned = $this->decimal((string) PurchaseReturnLine::query()
                    ->where('purchase_invoice_line_id', $sourceLine->id)
                    ->whereHas('purchaseReturn', static fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled', 'reversed']))
                    ->sum('quantity'));
                $remaining = bcsub($received, $alreadyReturned, 6);
                if ($this->compare($quantity, $remaining) > 0) {
                    throw new InvalidArgumentException(__('Return quantity exceeds the eligible quantity from the original invoice line.'));
                }

                $balance = StockBalance::query()
                    ->where('product_id', $sourceLine->product_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();
                if ($balance === null || $this->compare($quantity, bcsub((string) $balance->on_hand, (string) $balance->reserved, 6)) > 0) {
                    throw new InvalidArgumentException(__('Return quantity exceeds current on-hand stock for the selected product and store.'));
                }

                $unitCost = $this->decimal($sourceLine->unit_cost);
                $totalCost = bcmul($quantity, $unitCost, 4);
                $subtotal = bcadd($subtotal, $totalCost, 4);
                $normalized[] = [
                    'purchase_invoice_line_id' => $sourceLine->id,
                    'product_id' => $sourceLine->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'cost_source' => 'purchase_invoice',
                ];
            }

            $return = PurchaseReturn::query()->create([
                'supplier_id' => $invoice->supplier_id,
                'purchase_invoice_id' => $invoice->id,
                'store_id' => $storeId,
                'reason_id' => $reason->id,
                'return_date' => now()->toDateString(),
                'status' => 'draft',
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'idempotency_key' => $key,
                'lock_version' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'notes' => $notes,
            ]);

            foreach ($normalized as $line) {
                $return->lines()->create($line);
                app(SyncSupplierProductAssociation::class)->associate($invoice->supplier_id, (int) $line['product_id'], $actorId);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'create_supplier_return_draft',
                source: $return,
                after: $return->fresh(['lines'])->only(['id', 'supplier_id', 'purchase_invoice_id', 'store_id', 'reason_id', 'status', 'subtotal', 'lock_version']),
                storeId: $return->store_id,
                reasonCode: $reason->code,
                metadata: [
                    'stock_posted' => false,
                    'cost_source' => 'original_purchase_invoice_line_unit_cost',
                    'line_count' => count($normalized),
                ],
            );

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }

    /**
     * @param  Collection<int, PurchaseReturnLine>  $existingLines
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function linesMatch($existingLines, array $lines): bool
    {
        if ($existingLines->count() !== count($lines)) {
            return false;
        }

        $remaining = $existingLines->all();
        foreach ($lines as $lineData) {
            $sourceLineId = (int) ($lineData['purchase_invoice_line_id'] ?? 0);
            try {
                $quantity = bcadd((string) ProductQuantity::normalize($lineData['quantity'] ?? null), '0', 6);
            } catch (InvalidArgumentException|ValidationException) {
                return false;
            }

            $matchIndex = null;
            foreach ($remaining as $index => $existingLine) {
                if ((int) $existingLine->purchase_invoice_line_id === $sourceLineId
                    && ($sourceLineId !== 0 || (int) $existingLine->product_id === (int) ($lineData['product_id'] ?? 0))
                    && bccomp((string) $existingLine->quantity, $quantity, 6) === 0) {
                    $matchIndex = $index;
                    break;
                }
            }
            if ($matchIndex === null) {
                return false;
            }
            unset($remaining[$matchIndex]);
        }

        return true;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal value.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }
}
