<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SaveStockCountAction
{
    /** @param array<string, mixed> $data @param array<int, int> $productIds */
    public function execute(array $data, array $productIds, ?int $id = null, ?int $expectedVersion = null): StockCount
    {
        Gate::authorize($id === null ? 'stock_counts.create' : 'stock_counts.edit');
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            throw new InvalidArgumentException(__('Select at least one product for the count.'));
        }

        return DB::transaction(function () use ($data, $productIds, $id, $expectedVersion): StockCount {
            $count = $id === null ? null : StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($count !== null) {
                if (! in_array($count->status, ['draft', 'in_progress'], true)) {
                    throw new InvalidArgumentException(__('Only open stock counts can be edited.'));
                }
                if ($expectedVersion !== null && $count->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This stock count changed in another session. Please reload before saving.'));
                }
            }
            $store = Store::query()->whereKey((int) ($data['store_id'] ?? 0))->where('status', 'active')->firstOrFail();
            app(AssertInventoryStoreScope::class)->execute($store->id);
            $assignedTo = (int) ($data['assigned_to'] ?? Auth::id());
            $assigned = User::query()->findOrFail($assignedTo);
            if (! $assigned->is_super_admin && ! $assigned->hasPermission('stock_counts.submit')) {
                throw new InvalidArgumentException(__('The assigned user cannot submit stock counts.'));
            }
            if (! $assigned->is_super_admin && ! Store::query()->visibleTo($assigned)->whereKey($store->id)->exists()) {
                throw new InvalidArgumentException(__('The assigned counter is outside the selected store scope.'));
            }
            $products = Product::query()->whereIn('id', $productIds)->where('status', 'active')->get()->keyBy('id');
            if ($products->count() !== count($productIds)) {
                throw new InvalidArgumentException(__('Every selected count product must be active.'));
            }
            $scopeType = trim((string) ($data['scope_type'] ?? 'store'));
            if (! in_array($scopeType, ['store', 'category', 'supplier', 'partial'], true)) {
                throw new InvalidArgumentException(__('Select a supported count scope.'));
            }
            $categoryId = ($data['category_id'] ?? null) === null || ($data['category_id'] ?? '') === '' ? null : (int) $data['category_id'];
            $supplierId = ($data['supplier_id'] ?? null) === null || ($data['supplier_id'] ?? '') === '' ? null : (int) $data['supplier_id'];
            if ($scopeType === 'category') {
                if ($categoryId === null || ! Category::query()->whereKey($categoryId)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('A valid category is required for a category count.'));
                }
                if ($products->contains(fn (Product $product): bool => (int) $product->category_id !== $categoryId)) {
                    throw new InvalidArgumentException(__('Every selected product must belong to the selected category.'));
                }
            }
            if ($scopeType === 'supplier') {
                if ($supplierId === null || ! Supplier::query()->whereKey($supplierId)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('A valid supplier is required for a supplier count.'));
                }
                $supplierProductIds = Product::query()->whereIn('id', $productIds)->whereHas('productSuppliers', static fn ($query) => $query->where('supplier_id', $supplierId))->pluck('id')->all();
                if (count($supplierProductIds) !== count($productIds)) {
                    throw new InvalidArgumentException(__('Every selected product must belong to the selected supplier.'));
                }
            }
            if ($count === null) {
                $count = StockCount::query()->create([
                    'count_number' => app(AllocateDocumentNumber::class)->execute('stock_count'),
                    'count_type' => trim((string) ($data['count_type'] ?? 'partial')) === 'full' ? 'full' : 'partial',
                    'scope_type' => $scopeType,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'category_id' => $categoryId,
                    'supplier_id' => $supplierId,
                    'status' => 'in_progress',
                    'reference_at' => now(),
                    'created_by' => Auth::id(),
                    'assigned_to' => $assigned->id,
                    'idempotency_key' => (string) ($data['idempotency_key'] ?? 'stock-count:'.Str::uuid()),
                    'lock_version' => 1,
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ]);
                $before = null;
                $event = 'create_stock_count';
            } else {
                $before = $count->only(['store_id', 'scope_type', 'assigned_to', 'status', 'lock_version']);
                $count->update(['count_type' => trim((string) ($data['count_type'] ?? $count->count_type)) === 'full' ? 'full' : 'partial', 'scope_type' => $scopeType, 'store_id' => $store->id, 'branch_id' => $store->branch_id, 'category_id' => $categoryId, 'supplier_id' => $supplierId, 'assigned_to' => $assigned->id, 'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'lock_version' => $count->lock_version + 1]);
                $event = 'update_stock_count';
            }
            $existingProductIds = $count->lines()->pluck('product_id')->all();
            foreach ($productIds as $productId) {
                if (in_array($productId, $existingProductIds, true)) {
                    continue;
                }
                $reference = StockBalance::query()->where('product_id', $productId)->where('store_id', $store->id)->value('on_hand') ?? '0';
                $count->lines()->create(['product_id' => $productId, 'reference_on_hand' => $reference, 'is_counted' => false, 'recount_number' => 0]);
            }
            $count->lines()->whereNotIn('product_id', $productIds)->delete();
            app(RecordAuditEvent::class)->execute('inventory', $event, $count, $before, $count->fresh(['lines'])->only(['count_number', 'store_id', 'scope_type', 'assigned_to', 'status', 'lock_version']), storeId: $store->id, metadata: ['line_count' => count($productIds), 'assigned_to' => $assigned->id]);

            return $count->fresh(['store', 'lines.product']);
        });
    }
}
