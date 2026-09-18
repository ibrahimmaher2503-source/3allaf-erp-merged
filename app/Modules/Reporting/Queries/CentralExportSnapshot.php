<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CentralExportSnapshot
{
    public const DATASETS = ['products_barcodes', 'customers_groups', 'suppliers_groups', 'purchase_orders', 'purchase_invoices_receiving', 'opening_inventory', 'inventory_movements'];
    public const MAX_ROWS = 50000;

    private const PERMISSIONS = [
        'products_barcodes' => 'products_categories_brands.view',
        'customers_groups' => 'customers.view',
        'suppliers_groups' => 'suppliers.view',
        'purchase_orders' => 'purchase_orders.view',
        'purchase_invoices_receiving' => 'purchase_invoices_supplier_returns.view',
        'opening_inventory' => 'inventory_stock_card.view',
        'inventory_movements' => 'inventory_stock_card.view',
    ];

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function execute(User $user, string $dataset, array $filters): array
    {
        abort_unless(in_array($dataset, self::DATASETS, true), 422);
        Gate::forUser($user)->authorize(self::PERMISSIONS[$dataset]);
        $branchId = filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;
        $storeId = filled($filters['store_id'] ?? null) ? (int) $filters['store_id'] : null;
        if ($branchId !== null) abort_unless($user->is_super_admin || $user->canAccessBranch($branchId), 403);
        if ($storeId !== null) abort_unless($user->is_super_admin || $user->canAccessStore($storeId), 403);

        $branchIds = Branch::query()->visibleTo($user)->when($branchId !== null, fn (Builder $q): Builder => $q->whereKey($branchId))->pluck('id');
        $storeIds = Store::query()->visibleTo($user)
            ->when($branchId !== null, fn (Builder $q): Builder => $q->where('branch_id', $branchId))
            ->when($storeId !== null, fn (Builder $q): Builder => $q->whereKey($storeId))->pluck('id');
        $companyIds = $storeId !== null
            ? Store::query()->whereIn('id', $storeIds)->pluck('company_id')->filter()->unique()->values()
            : ($branchId !== null
                ? Branch::query()->whereIn('id', $branchIds)->pluck('company_id')->filter()->unique()->values()
                : Branch::query()->whereIn('id', $branchIds)->pluck('company_id')
                    ->merge(Store::query()->whereIn('id', $storeIds)->pluck('company_id'))->filter()->unique()->values());
        abort_if($companyIds->isEmpty(), 403, __('No company is available in the authorized export scope.'));

        $from = filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : now()->subDays(30)->toDateString();
        $to = filled($filters['date_to'] ?? null) ? (string) $filters['date_to'] : now()->toDateString();
        $status = filled($filters['document_status'] ?? null) ? (string) $filters['document_status'] : null;
        $supplierId = filled($filters['supplier_id'] ?? null) ? (int) $filters['supplier_id'] : null;
        $customerId = filled($filters['customer_id'] ?? null) ? (int) $filters['customer_id'] : null;
        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;

        [$columns, $rows] = match ($dataset) {
            'products_barcodes' => $this->products($companyIds, $branchId, $storeId, $categoryId),
            'customers_groups' => $this->customers($user, $companyIds, $branchId, $storeId, $customerId),
            'suppliers_groups' => $this->suppliers($companyIds, $supplierId),
            'purchase_orders' => $this->purchaseOrders($branchIds, $branchId, $storeId, $from, $to, $status, $supplierId),
            'purchase_invoices_receiving' => $this->purchaseInvoices($storeIds, $from, $to, $status, $supplierId),
            'opening_inventory' => $this->openingInventory($companyIds, $branchId, $storeId, $from, $to, $status),
            'inventory_movements' => $this->inventoryMovements($storeIds, $from, $to),
        };
        $normalized = ['dataset' => $dataset, 'date_from' => $from, 'date_to' => $to, 'company_ids' => $companyIds->all(), 'branch_id' => $branchId, 'store_id' => $storeId, 'document_status' => $status, 'supplier_id' => $supplierId, 'customer_id' => $customerId, 'category_id' => $categoryId, 'maximum_rows' => self::MAX_ROWS, 'is_complete' => true];

        return ['dataset' => $dataset, 'filters' => $normalized, 'columns' => $columns, 'rows' => $rows, 'row_count' => count($rows), 'fresh_at' => now()->toIso8601String()];
    }

    private function products(Collection $companies, ?int $branch, ?int $store, ?int $category): array
    {
        $query = Product::query()->with(['category', 'barcodes', 'productSuppliers'])
            ->availableToCompanies($companies->map(fn ($id): int => (int) $id)->values()->all(), $branch, $store)
            ->when($category !== null, fn (Builder $q): Builder => $q->where('category_id', $category))->orderBy('item_code')->orderBy('id');
        $rows = $this->bounded($query, 'products_barcodes')->map(fn (Product $p): array => [$p->item_code, $p->name_ar, $p->name_en, $p->category?->code, $p->model_number, $p->productSuppliers->pluck('supplier_item_code')->filter()->join(' | '), $p->barcodes->pluck('barcode')->filter()->join(' | '), $p->status])->all();
        return [['internal_code', 'name_ar', 'name_en', 'category', 'model', 'supplier_codes', 'barcodes', 'status'], $rows];
    }

    private function customers(User $user, Collection $companies, ?int $branch, ?int $store, ?int $customer): array
    {
        $memberQuery = Customer::query()->visibleTo($user)->with('group')
            ->whereHas('createdStore', fn (Builder $q): Builder => $q->whereIn('company_id', $companies))
            ->when($branch !== null, fn (Builder $q): Builder => $q->where('created_branch_id', $branch))
            ->when($store !== null, fn (Builder $q): Builder => $q->where('created_store_id', $store))
            ->when($customer !== null, fn (Builder $q): Builder => $q->whereKey($customer))->orderBy('id');
        if ($customer !== null) {
            $members = $this->bounded($memberQuery, 'customers_groups');
            $groups = $this->bounded(CustomerGroup::query()->whereIn('company_id', $companies)->whereIn('id', $members->pluck('customer_group_id')->filter())->orderBy('code')->orderBy('id'), 'customers_groups', $members->count());
        } else {
            $groups = $this->bounded(CustomerGroup::query()->whereIn('company_id', $companies)->orderBy('code')->orderBy('id'), 'customers_groups');
            $members = $this->bounded($memberQuery, 'customers_groups', $groups->count());
        }
        $rows = $groups->map(fn (CustomerGroup $g): array => ['group', $g->code, $g->name_ar, $g->name_en, null, null, null, $g->status])
            ->concat($members->map(fn (Customer $c): array => ['customer', $c->group?->code, $c->group?->name_ar, $c->group?->name_en, $c->customer_code, $c->name_ar, $c->name_en, $c->status]))->values()->all();
        return [['record_type', 'group_code', 'group_name_ar', 'group_name_en', 'customer_code', 'customer_name_ar', 'customer_name_en', 'status'], $rows];
    }

    private function suppliers(Collection $companies, ?int $supplier): array
    {
        $memberQuery = Supplier::query()->with('supplierGroup')
            ->where(fn (Builder $q): Builder => $q->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn (Builder $g): Builder => $g->whereIn('company_id', $companies)))
            ->when($supplier !== null, fn (Builder $q): Builder => $q->whereKey($supplier))->orderBy('code')->orderBy('id');
        if ($supplier !== null) {
            $members = $this->bounded($memberQuery, 'suppliers_groups');
            $groups = $this->bounded(SupplierGroup::query()->whereIn('company_id', $companies)->whereIn('id', $members->pluck('supplier_group_id')->filter())->orderBy('code')->orderBy('id'), 'suppliers_groups', $members->count());
        } else {
            $groups = $this->bounded(SupplierGroup::query()->whereIn('company_id', $companies)->orderBy('code')->orderBy('id'), 'suppliers_groups');
            $members = $this->bounded($memberQuery, 'suppliers_groups', $groups->count());
        }
        $rows = $groups->map(fn (SupplierGroup $g): array => ['group', $g->code, $g->name_ar, $g->name_en, null, null, null, $g->status])
            ->concat($members->map(fn (Supplier $s): array => ['supplier', $s->supplierGroup?->code, $s->supplierGroup?->name_ar, $s->supplierGroup?->name_en, $s->code, $s->name_ar, $s->name_en, $s->status]))->values()->all();
        return [['record_type', 'group_code', 'group_name_ar', 'group_name_en', 'supplier_code', 'supplier_name_ar', 'supplier_name_en', 'status'], $rows];
    }

    private function purchaseOrders(Collection $branches, ?int $branch, ?int $store, string $from, string $to, ?string $status, ?int $supplier): array
    {
        $query = PurchaseOrder::query()->with(['branch', 'store', 'supplier'])->whereIn('branch_id', $branches)
            ->when($branch !== null, fn (Builder $q): Builder => $q->where('branch_id', $branch))->when($store !== null, fn (Builder $q): Builder => $q->where('store_id', $store))
            ->whereBetween('order_date', [$from, $to])->when($status !== null, fn (Builder $q): Builder => $q->where('status', $status))->when($supplier !== null, fn (Builder $q): Builder => $q->where('supplier_id', $supplier))->orderByDesc('id');
        $rows = $this->bounded($query, 'purchase_orders')->map(fn (PurchaseOrder $o): array => [$o->po_number, $o->branch?->code, $o->store?->code, $o->supplier?->code, $o->order_date?->toDateString(), $o->status, $o->receivingState(), $o->total_amount])->all();
        return [['document', 'creating_branch', 'receiving_store', 'supplier', 'date', 'approval_status', 'receiving_status', 'total'], $rows];
    }

    private function purchaseInvoices(Collection $stores, string $from, string $to, ?string $status, ?int $supplier): array
    {
        $query = PurchaseInvoice::query()->with(['purchaseOrder', 'store', 'supplier'])->whereIn('store_id', $stores)->whereBetween('invoice_date', [$from, $to])
            ->when($status !== null, fn (Builder $q): Builder => $q->where('status', $status))->when($supplier !== null, fn (Builder $q): Builder => $q->where('supplier_id', $supplier))->orderByDesc('id');
        $rows = $this->bounded($query, 'purchase_invoices_receiving')->map(fn (PurchaseInvoice $i): array => [$i->invoice_number, $i->purchaseOrder?->po_number, $i->store?->code, $i->supplier?->code, $i->invoice_date?->toDateString(), $i->status, $i->total_amount])->all();
        return [['document', 'purchase_order', 'receiving_store', 'supplier', 'date', 'status', 'total'], $rows];
    }

    private function openingInventory(Collection $companies, ?int $branch, ?int $store, string $from, string $to, ?string $status): array
    {
        $query = OpeningInventoryDocument::query()->with(['branch', 'store'])->withCount('lines')->whereIn('company_id', $companies)
            ->when($branch !== null, fn (Builder $q): Builder => $q->where('branch_id', $branch))->when($store !== null, fn (Builder $q): Builder => $q->where('store_id', $store))
            ->whereBetween('document_date', [$from, $to])->when($status !== null, fn (Builder $q): Builder => $q->where('status', $status))->orderByDesc('id');
        $rows = $this->bounded($query, 'opening_inventory')->map(fn (OpeningInventoryDocument $d): array => [$d->document_number, $d->branch?->code, $d->store?->code, $d->document_date?->toDateString(), $d->status, $d->lines_count])->all();
        return [['document', 'branch', 'store', 'date', 'status', 'lines'], $rows];
    }

    private function inventoryMovements(Collection $stores, string $from, string $to): array
    {
        $query = StockMovement::query()->with('store')->whereIn('store_id', $stores)->whereBetween('posted_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderByDesc('id');
        $rows = $this->bounded($query, 'inventory_movements')->map(fn (StockMovement $m): array => [$m->id, $m->store?->code, $m->product_id, $m->movement_type, $m->quantity, $m->unit_cost, $m->posted_at?->toIso8601String()])->all();
        return [['id', 'store', 'product_id', 'movement_type', 'quantity', 'unit_cost', 'posted_at'], $rows];
    }

    private function bounded(Builder $query, string $dataset, int $included = 0): Collection
    {
        $count = (clone $query)->count();
        if ($included + $count > self::MAX_ROWS) {
            throw ValidationException::withMessages(['export' => __('The :dataset export contains :count rows, above the explicit :limit-row limit. Narrow the filters and request the export again.', ['dataset' => __($dataset), 'count' => $included + $count, 'limit' => self::MAX_ROWS])]);
        }
        return $query->get();
    }

    public function fingerprint(array $snapshot): string
    {
        unset($snapshot['fresh_at']);
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
