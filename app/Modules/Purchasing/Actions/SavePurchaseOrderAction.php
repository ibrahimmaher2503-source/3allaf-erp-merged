<?php

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Support\ProductQuantity;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrderCalculator;
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
        if (collect($lines)->pluck('product_id')->filter()->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException(__('A product may appear only once; scan it again to increase quantity.'));
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
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot create or update a purchase order for an inactive supplier.'));
            }

            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);
            $branchId = (int) ($data['branch_id'] ?? 0);
            $branch = Branch::query()->where('status', 'active')
                ->when(! $actor->hasGlobalApplicationAccess() || $actor->canBypassApproval(), fn ($query) => $query->whereIn('id', Store::query()->visibleTo($actor)->whereNotNull('branch_id')->select('branch_id')))
                ->findOrFail($branchId);
            $storeId = (int) ($data['store_id'] ?? 0);
            if ($storeId < 1) {
                throw new InvalidArgumentException(__('A receiving store or warehouse is required so the purchase order has an explicit branch.'));
            }
            $store = Store::query()->findOrFail($storeId);
            $this->assertStoreScope($storeId);
            if ($store->branch_id === null || (int) $store->branch_id !== $branch->id) {
                throw new InvalidArgumentException(__('The receiving store must belong to the selected creating branch.'));
            }
            if ($po !== null && $po->branch_id !== null && (int) $po->branch_id !== (int) $branchId) {
                throw new InvalidArgumentException(__('A numbered draft cannot move to another branch; create a new purchase order for that branch.'));
            }

            $validatedLines = [];
            $existingLines = $po?->lines()->lockForUpdate()->get()->keyBy('product_id') ?? collect();
            // Migration 000115 deliberately does not invent line tax snapshots for
            // legacy orders. Retain any pre-existing header tax that cannot be
            // explained by stored line snapshots as an undistributed legacy amount.
            $storedLineTax = $existingLines->reduce(
                fn (string $carry, $line): string => bcadd($carry, (string) $line->tax_amount, 4),
                '0.0000',
            );
            $legacyHeaderTax = $po === null
                ? '0.0000'
                : bcsub((string) $po->tax_amount, $storedLineTax, 4);
            if (bccomp($legacyHeaderTax, '0', 4) < 0) {
                $legacyHeaderTax = '0.0000';
            }

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $product = Product::query()->sellable()->availableToCompany((int) $branch->company_id)->with('baseProductUnit.unit')->findOrFail($productId);
                if ($product->status !== 'active') {
                    throw new InvalidArgumentException(__('Product :name is inactive and cannot be ordered.', ['name' => $product->name_en ?: $product->name_ar]));
                }

                $productUnit = filled($line['product_unit_id'] ?? null)
                    ? $product->productUnits()->with('unit')->find((int) $line['product_unit_id'])
                    : $product->baseProductUnit;
                if (filled($line['product_unit_id'] ?? null) && ! $productUnit instanceof ProductUnit) {
                    throw new InvalidArgumentException(__('The selected unit does not belong to the product.'));
                }
                if ($productUnit !== null && (! $productUnit->is_purchase_unit || $productUnit->unit?->status !== 'active')) {
                    throw new InvalidArgumentException(__('Select an active purchase unit for this product.'));
                }
                $precision = $product->fractional_quantity ? min(6, (int) ($productUnit?->unit?->decimal_places ?? 6)) : 0;
                $enteredQuantity = (string) ProductQuantity::normalize($line['quantity_ordered'] ?? null, "lineItems.$index.quantity_ordered", decimalPlaces: $precision);
                $enteredPrice = $this->decimal($line['unit_cost'] ?? null, 4, 'Unit cost', true);
                $factor = $productUnit?->conversion_factor ?? '1.000000';
                // Base-unit quantity and cost feed rajeh's PurchaseOrderCalculator (discount/tax snapshots).
                $qty = $productUnit ? app(ConvertProductQuantity::class)->execute($product, $productUnit, $enteredQuantity) : $enteredQuantity;
                $unitCost = bcdiv((string) $enteredPrice, (string) $factor, 4);

                $existingLine = $existingLines->get($product->id);
                $legacyCommercialSnapshot = $existingLine === null
                    ? ['discount_type' => null, 'discount_value' => '0.0000', 'tax_rate' => '0.0000', 'tax_code' => null, 'tax_inclusive' => false]
                    : ['discount_type' => $existingLine->discount_type, 'discount_value' => (string) $existingLine->discount_value, 'tax_rate' => (string) $existingLine->tax_rate, 'tax_code' => $existingLine->tax_code, 'tax_inclusive' => (bool) $existingLine->tax_inclusive];

                $validatedLines[] = [
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit?->id,
                    'unit_code_snapshot' => $productUnit?->unit?->code,
                    'entered_quantity' => $enteredQuantity,
                    'conversion_factor_snapshot' => (string) $factor,
                    'entered_unit_price' => (string) $enteredPrice,
                    'line_number' => $index + 1,
                    'quantity_ordered' => (string) $qty,
                    'quantity_received' => '0.000000',
                    'unit_cost' => (string) $unitCost,
                    ...$legacyCommercialSnapshot,
                    'notes' => ! empty($line['notes']) ? trim((string) $line['notes']) : null,
                    'updated_by' => $userId,
                ];
            }

            $totals = app(PurchaseOrderCalculator::class)->calculateDocument($validatedLines);
            $headerTax = bcadd($totals['taxAmount'], $legacyHeaderTax, 4);
            $headerTotal = bcadd($totals['totalAmount'], $legacyHeaderTax, 4);

            $poNumber = $po !== null ? $po->po_number : app(AllocatePurchaseOrderNumberAction::class)->execute((int) $branchId);

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
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $headerTax,
                'total_amount' => $headerTotal,
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

            foreach ($totals['calculated'] as $index => $calculated) {
                $lineData = $validatedLines[$index];
                $po->lines()->create([
                    ...$lineData,
                    ...$calculated,
                    'created_by' => $userId,
                ]);
                app(SyncSupplierProductAssociation::class)->associate($supplier->id, (int) $lineData['product_id'], (int) $userId);
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

    private function assertStoreScope(int $storeId): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ($user->hasGlobalApplicationAccess() && ! $user->canBypassApproval())) {
            return;
        }

        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected purchase-order store.'));
        }
    }

    /**
     * [merge 3allaf] Entered unit price validation used by the unit-aware line builder.
     *
     * @return numeric-string
     */
    private function decimal(mixed $value, int $scale, string $label, bool $allowZero): string
    {
        $value = trim((string) $value);
        $pattern = $scale === 0 ? '/^\d+$/D' : '/^\d+(?:\.\d{1,'.$scale.'})?$/D';
        if ($value === '' || preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException(__(':label must be a valid non-negative number.', ['label' => __($label)]));
        }
        if (! $allowZero && bccomp($value, '0', $scale) <= 0) {
            throw new InvalidArgumentException(__(':label must be greater than zero.', ['label' => __($label)]));
        }

        return bcadd($value, '0', $scale);
    }
}
