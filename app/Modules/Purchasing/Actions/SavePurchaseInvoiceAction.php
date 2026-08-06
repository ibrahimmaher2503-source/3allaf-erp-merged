<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SavePurchaseInvoiceAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(array $data, array $lines, ?int $id = null, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize($id ? 'purchase_invoices_supplier_returns.edit' : 'purchase_invoices_supplier_returns.create');

        return DB::transaction(function () use ($data, $lines, $id, $expectedVersion): PurchaseInvoice {
            $userId = Auth::id();
            $invoice = $id === null ? null : PurchaseInvoice::query()->lockForUpdate()->findOrFail($id);

            if ($invoice !== null) {
                if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before saving.'));
                }
                if ($invoice->status !== 'draft') {
                    throw new InvalidArgumentException(__('Only draft purchase invoices can be edited.'));
                }
            }

            $supplier = Supplier::query()->findOrFail((int) ($data['supplier_id'] ?? 0));
            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot create an invoice for an inactive supplier.'));
            }

            $store = Store::query()->findOrFail((int) ($data['store_id'] ?? 0));
            if (($data['supplier_reference'] ?? '') !== '') {
                $duplicate = PurchaseInvoice::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('supplier_reference', trim((string) $data['supplier_reference']))
                    ->when($invoice, fn ($query) => $query->whereKeyNot($invoice->id))
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->exists();
                if ($duplicate) {
                    throw new InvalidArgumentException(__('This supplier invoice reference already exists for the selected supplier.'));
                }
            }

            $normalizedLines = [];
            foreach ($lines as $index => $line) {
                $product = Product::query()->findOrFail((int) ($line['product_id'] ?? 0));
                if ($product->status !== 'active') {
                    throw new InvalidArgumentException(__('Product :name is inactive and cannot be invoiced.', ['name' => $product->name_en ?: $product->name_ar]));
                }
                $normalizedLines[] = [...$line, 'product_id' => $product->id, 'line_number' => $index + 1];
            }

            $totals = app(PurchaseInvoiceCalculator::class)->calculateDocument($normalizedLines);
            $attributes = [
                'invoice_number' => $invoice?->invoice_number,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => ! empty($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null,
                'store_id' => $store->id,
                'supplier_reference' => ! empty($data['supplier_reference']) ? trim((string) $data['supplier_reference']) : null,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'currency_code' => ! empty($data['currency_code']) ? strtoupper(trim((string) $data['currency_code'])) : null,
                'status' => 'draft',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['taxAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['totalAmount'],
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'updated_by' => $userId,
            ];

            if ($invoice === null) {
                $attributes['idempotency_key'] = (string) Str::uuid();
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $invoice = PurchaseInvoice::query()->create($attributes);
                $event = 'create_purchase_invoice';
                $before = null;
            } else {
                $before = $invoice->only(['supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'lock_version']);
                $invoice->update([...$attributes, 'lock_version' => $invoice->lock_version + 1]);
                $event = 'update_purchase_invoice';
                $invoice->lines()->delete();
            }

            foreach ($totals['calculated'] as $index => $line) {
                $invoice->lines()->create([
                    ...$line,
                    'product_id' => $normalizedLines[$index]['product_id'],
                    'purchase_order_line_id' => ! empty($normalizedLines[$index]['purchase_order_line_id']) ? (int) $normalizedLines[$index]['purchase_order_line_id'] : null,
                    'quantity_received' => 0,
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: $event,
                source: $invoice,
                before: $before,
                after: $invoice->fresh(['lines'])->only(['id', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'lock_version']),
                storeId: $invoice->store_id,
                metadata: ['line_count' => count($totals['calculated'])],
            );

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }
}
