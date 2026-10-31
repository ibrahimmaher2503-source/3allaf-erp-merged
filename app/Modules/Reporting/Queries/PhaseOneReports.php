<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PhaseOneReports
{
    /** @param array<string, mixed> $filters @return array{rows:LengthAwarePaginator,totals:object,store_ids:list<int>} */
    public function productSales(User $user, array $filters): array
    {
        $stores = $this->storeIds($user, $filters['store_ids'] ?? []);
        $from = $filters['date_from'].' 00:00:00';
        $to = $filters['date_to'].' 23:59:59';
        $sales = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', 'approved')->whereBetween('sales.approved_at', [$from, $to])->whereIn('sales.store_id', $stores)
            ->select('sale_lines.product_id')->selectRaw('SUM(sale_lines.quantity) as quantity, SUM(sale_lines.consumed_cost) as cost_value, SUM(sale_lines.net_amount) as sales_value')
            ->groupBy('sale_lines.product_id');
        $returns = DB::table('retail_return_lines')->join('retail_returns', 'retail_returns.id', '=', 'retail_return_lines.retail_return_id')
            ->leftJoin('sale_lines as original_sale_lines', 'original_sale_lines.id', '=', 'retail_return_lines.sale_line_id')
            ->where('retail_returns.status', 'completed')->whereBetween('retail_returns.completed_at', [$from, $to])->whereIn('retail_returns.store_id', $stores)
            ->select('retail_return_lines.product_id')
            ->selectRaw('-SUM(retail_return_lines.quantity) as quantity, -SUM(COALESCE(original_sale_lines.consumed_cost / NULLIF(original_sale_lines.quantity, 0) * retail_return_lines.quantity, 0)) as cost_value, -SUM(retail_return_lines.eligible_value - retail_return_lines.tax_value) as sales_value')
            ->groupBy('retail_return_lines.product_id');
        $union = $sales->unionAll($returns);
        $aggregated = DB::query()->fromSub($union, 'posted')->select('product_id')
            ->selectRaw('SUM(quantity) as sold_quantity, SUM(cost_value) as total_cost, SUM(sales_value) as total_sales')
            ->groupBy('product_id');
        $query = DB::query()->fromSub($aggregated, 'activity')->join('products', 'products.id', '=', 'activity.product_id')
            ->select('products.id', 'products.item_code', 'products.name_ar', 'products.name_en', 'activity.sold_quantity', 'activity.total_cost', 'activity.total_sales')
            ->when($filters['category_ids'] ?? [], fn ($q, $ids) => $q->whereIn('products.category_id', $ids))
            ->when($filters['supplier_ids'] ?? [], fn ($q, $ids) => $q->whereExists(fn ($supplier) => $supplier->selectRaw('1')->from('product_suppliers')->whereColumn('product_suppliers.product_id', 'products.id')->whereIn('product_suppliers.supplier_id', $ids)));
        $totals = (clone $query)->select([])->selectRaw('COALESCE(SUM(activity.sold_quantity), 0) as quantity, COALESCE(SUM(activity.total_cost), 0) as cost, COALESCE(SUM(activity.total_sales), 0) as sales')->first();

        return ['rows' => $query->orderBy('products.item_code')->paginate(25)->withQueryString(), 'totals' => $totals, 'store_ids' => $stores];
    }

    /** @param array<string, mixed> $filters @return array{rows:LengthAwarePaginator,totals:object,store_ids:list<int>} */
    public function inventory(User $user, array $filters): array
    {
        $stores = $this->storeIds($user, isset($filters['store_id']) ? [(int) $filters['store_id']] : []);
        $balances = DB::table('stock_balances')->whereIn('store_id', $stores)->select('product_id')
            ->selectRaw('SUM(on_hand) as quantity, SUM(total_value) as cost_value')->groupBy('product_id');
        $query = DB::query()->fromSub($balances, 'balance')->join('products', 'products.id', '=', 'balance.product_id')
            ->select('products.id', 'products.item_code', 'products.name_ar', 'products.name_en', 'products.sale_price', 'balance.quantity', 'balance.cost_value')
            ->when($filters['category_ids'] ?? [], fn ($q, $ids) => $q->whereIn('products.category_id', $ids))
            ->when($filters['supplier_ids'] ?? [], fn ($q, $ids) => $q->whereExists(fn ($supplier) => $supplier->selectRaw('1')->from('product_suppliers')->whereColumn('product_suppliers.product_id', 'products.id')->whereIn('product_suppliers.supplier_id', $ids)));
        $totals = (clone $query)->select([])->selectRaw('COALESCE(SUM(balance.quantity), 0) as quantity, COALESCE(SUM(balance.cost_value), 0) as cost')->first();

        $rows = $query->orderBy('products.item_code')->paginate(25)->withQueryString();
        if (isset($filters['store_id'])) {
            $prices = app(EffectivePriceResolver::class)->resolveForStore($rows->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all(), (int) $filters['store_id']);
            $rows->getCollection()->each(function ($row) use ($prices): void {
                $row->current_price = $prices->get($row->id)?->amount;
            });
        }

        return ['rows' => $rows, 'totals' => $totals, 'store_ids' => $stores];
    }

    /** @param array<string, mixed> $filters @return array{rows:LengthAwarePaginator,totals:array<string,float>} */
    public function daily(User $user, array $filters): array
    {
        $storeIds = $this->storeIds($user, []);
        $branchId = (int) $filters['branch_id'];
        $branchStores = Store::query()->visibleTo($user)->where('branch_id', $branchId)->whereIn('id', $storeIds)->pluck('id')->all();
        if ($branchStores === []) {
            throw ValidationException::withMessages(['branch_id' => __('The selected branch is outside your authorized store scope.')]);
        }
        $from = $filters['date_from'].' 00:00:00';
        $to = $filters['date_to'].' 23:59:59';
        $sales = DB::table('sales')->where('status', 'approved')->where('branch_id', $branchId)->whereIn('store_id', $branchStores)
            ->whereBetween('approved_at', [$from, $to])->selectRaw('DATE(approved_at) as day, SUM(subtotal) as gross, SUM(discount_total) as discounts, SUM(total) as sales_total')->groupByRaw('DATE(approved_at)')->get()->keyBy('day');
        $payments = DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'approved')->where('sales.branch_id', $branchId)->whereIn('sales.store_id', $branchStores)->whereBetween('sales.approved_at', [$from, $to])
            ->selectRaw("DATE(sales.approved_at) as day, SUM(CASE WHEN sale_payments.method_type = 'cash' THEN sale_payments.amount ELSE 0 END) as cash, SUM(CASE WHEN sale_payments.method_type IN ('card', 'electronic', 'transfer', 'manual_electronic') THEN sale_payments.amount ELSE 0 END) as electronic")
            ->groupByRaw('DATE(sales.approved_at)')->get()->keyBy('day');
        $returns = DB::table('retail_returns')->where('status', 'completed')->where('branch_id', $branchId)->whereIn('store_id', $branchStores)
            ->whereBetween('completed_at', [$from, $to])->selectRaw('DATE(completed_at) as day, SUM(subtotal_refund) as gross_refund, SUM(discount_refund) as discount_refund, SUM(eligible_value) as refunds')->groupByRaw('DATE(completed_at)')->get()->keyBy('day');
        $refundPayments = DB::table('retail_return_settlements')->join('retail_returns', 'retail_returns.id', '=', 'retail_return_settlements.retail_return_id')
            ->where('retail_returns.status', 'completed')->where('retail_returns.branch_id', $branchId)->whereIn('retail_returns.store_id', $branchStores)->whereBetween('retail_returns.completed_at', [$from, $to])
            ->selectRaw("DATE(retail_returns.completed_at) as day, SUM(CASE WHEN retail_return_settlements.method_type_snapshot = 'cash' THEN retail_return_settlements.amount ELSE 0 END) as cash, SUM(CASE WHEN retail_return_settlements.method_type_snapshot IN ('card', 'electronic', 'transfer', 'manual_electronic') THEN retail_return_settlements.amount ELSE 0 END) as electronic")
            ->groupByRaw('DATE(retail_returns.completed_at)')->get()->keyBy('day');
        $days = collect(array_unique(array_merge($sales->keys()->all(), $returns->keys()->all())))->sort()->values();
        $rows = $days->map(function (string $day) use ($sales, $payments, $returns, $refundPayments): array {
            $sale = $sales->get($day);
            $return = $returns->get($day);

            return [
                'day' => $day,
                'gross' => (float) ($sale->gross ?? 0),
                'cash' => (float) ($payments->get($day)->cash ?? 0) - (float) ($refundPayments->get($day)->cash ?? 0),
                'electronic' => (float) ($payments->get($day)->electronic ?? 0) - (float) ($refundPayments->get($day)->electronic ?? 0),
                'discounts' => (float) ($sale->discounts ?? 0) - (float) ($return->discount_refund ?? 0),
                'refunds' => (float) ($return->refunds ?? 0),
                'net' => (float) ($sale->sales_total ?? 0) - (float) ($return->refunds ?? 0),
            ];
        });
        $totals = [];
        foreach (['gross', 'cash', 'electronic', 'discounts', 'refunds', 'net'] as $column) {
            $totals[$column] = round((float) $rows->sum($column), 2);
        }
        $page = max(1, (int) request('page', 1));

        return ['rows' => new LengthAwarePaginator($rows->forPage($page, 31)->values(), $rows->count(), 31, $page, ['path' => request()->url(), 'query' => request()->query()]), 'totals' => $totals];
    }

    /** @param list<int|string> $selected @return list<int> */
    private function storeIds(User $user, array $selected): array
    {
        $visible = Store::query()->visibleTo($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $selected = array_values(array_unique(array_map('intval', $selected)));
        if (array_diff($selected, $visible) !== []) {
            abort(403);
        }

        return $selected === [] ? $visible : $selected;
    }
}
