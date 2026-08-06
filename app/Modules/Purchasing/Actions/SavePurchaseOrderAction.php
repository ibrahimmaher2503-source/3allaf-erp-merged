<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SavePurchaseOrderAction
{
    /**
     * Create or update a draft Purchase Order with line items.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(array $data, array $lines, ?int $id = null, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize($id ? 'purchase_orders.edit' : 'purchase_orders.create');

        if (empty($lines)) {
            throw new InvalidArgumentException(__('A purchase order must contain at least one line item.'));
        }

        return DB::transaction(function () use ($data, $lines, $id, $expectedVersion): PurchaseOrder {
            $userId = Auth::id();
            $po = $id === null ? null : PurchaseOrder::query()->lockForUpdate()->findOrFail($id);

            if ($po !== null) {
                if ($expectedVersion !== null && $po->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before saving.'));
                }

                if ($po->status !== 'draft') {
                    throw new InvalidArgumentException(__('Only draft purchase orders can be edited.'));
                }
            }

            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $supplier = Supplier::query()->findOrFail($supplierId);
            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot create or update a purchase order for an inactive supplier.'));
            }

            $storeId = ! empty($data['store_id']) ? (int) $data['store_id'] : null;
            $branchId = null;
            if ($storeId !== null) {
                $store = Store::query()->findOrFail($storeId);
                $branchId = $store->branch_id;
            }

            $validatedLines = [];
            $lineSubtotalSum = '0.0000';

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $product = Product::query()->findOrFail($productId);
                if ($product->status !== 'active') {
                    throw new InvalidArgumentException(__('Product :name is inactive and cannot be ordered.', ['name' => $product->name_en ?: $product->name_ar]));
                }

                $qty = (float) ($line['quantity_ordered'] ?? 0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException(__('Quantity ordered must be greater than zero.'));
                }

                $unitCost = (float) ($line['unit_cost'] ?? 0);
                if ($unitCost < 0) {
                    throw new InvalidArgumentException(__('Unit cost cannot be negative.'));
                }

                $lineSubtotal = round($qty * $unitCost, 4);
                $lineSubtotalSum = bcadd((string) $lineSubtotalSum, (string) $lineSubtotal, 4);

                $validatedLines[] = [
                    'product_id' => $product->id,
                    'line_number' => $index + 1,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $unitCost,
                    'subtotal' => $lineSubtotal,
                    'notes' => ! empty($line['notes']) ? trim((string) $line['notes']) : null,
                    'updated_by' => $userId,
                ];
            }

            $taxAmount = '0.0000'; // Explicit local zero or TBD tax
            $totalAmount = bcadd((string) $lineSubtotalSum, (string) $taxAmount, 4);

            $poNumber = $po !== null ? $po->po_number : app(AllocatePurchaseOrderNumberAction::class)->execute();

            $attributes = [
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'branch_id' => $branchId,
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => ! empty($data['expected_delivery_date']) ? $data['expected_delivery_date'] : null,
                'payment_terms' => ! empty($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'subtotal' => $lineSubtotalSum,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'updated_by' => $userId,
            ];

            if ($po === null) {
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $po = PurchaseOrder::query()->create($attributes);
                $event = 'create_purchase_order';
                $before = null;
            } else {
                $before = $po->only(['po_number', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'total_amount', 'lock_version']);
                $po->update([
                    ...$attributes,
                    'lock_version' => $po->lock_version + 1,
                ]);
                $event = 'update_purchase_order';

                $po->lines()->delete();
            }

            foreach ($validatedLines as $lineData) {
                $po->lines()->create([
                    ...$lineData,
                    'created_by' => $userId,
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: $event,
                source: $po,
                before: $before,
                after: $po->fresh(['lines'])->only(['po_number', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'total_amount', 'lock_version']),
                branchId: $po->branch_id,
                storeId: $po->store_id,
                metadata: ['line_count' => count($validatedLines)],
            );

            return $po->fresh(['supplier', 'store', 'lines.product']);
        });
    }
}
