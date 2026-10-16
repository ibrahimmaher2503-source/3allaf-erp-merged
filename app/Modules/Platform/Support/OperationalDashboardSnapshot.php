<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\LowStockRule;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Illuminate\Support\Carbon;

final class OperationalDashboardSnapshot
{
    /** @return array<string, mixed> */
    public function for(User $user, array $filters = []): array
    {
        $storeIds = Store::query()->visibleTo($user)->select('id');
        $today = now()->toDateString();
        $from = filled($filters['date_from'] ?? null) ? Carbon::parse((string) $filters['date_from'])->startOfDay() : now()->startOfDay();
        $to = filled($filters['date_to'] ?? null) ? Carbon::parse((string) $filters['date_to'])->endOfDay() : now()->endOfDay();
        $periodDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousFrom = $from->copy()->subDays($periodDays);
        $previousTo = $from->copy()->subDay()->endOfDay();
        if (filled($filters['branch_id'] ?? null)) {
            $storeIds->where('branch_id', (int) $filters['branch_id']);
        }
        if (filled($filters['store_id'] ?? null)) {
            $storeIds->whereKey((int) $filters['store_id']);
        }
        $canDecideApprovals = collect([
            'pricing_labels.approve', 'purchase_orders.approve', 'purchase_invoices_supplier_returns.approve',
            'purchase_returns.approve', 'inventory_stock_card.approve', 'stock_counts.reconcile',
        ])->contains(fn (string $permission): bool => $user->can($permission));
        $sales = Sale::query()->visibleTo($user)->approved();
        $purchases = PurchaseInvoice::query()->whereIn('store_id', clone $storeIds)->where('status', 'approved');
        $trendStart = now()->startOfDay()->subDays(6);
        $salesTrend = $user->can('pos_sales.view') ? (clone $sales)->whereBetween('approved_at', [$from, $to])->selectRaw('DATE(approved_at) as day, SUM(payable_total) as total')->groupBy('day')->pluck('total', 'day') : collect();
        $purchaseTrend = $user->can('purchase_invoices_supplier_returns.view') ? (clone $purchases)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->selectRaw('invoice_date as day, SUM(total_amount) as total')->groupBy('invoice_date')->pluck('total', 'day') : collect();
        $previousSalesTotal = $user->can('pos_sales.view') ? (clone $sales)->whereBetween('approved_at', [$previousFrom, $previousTo])->sum('payable_total') : 0;
        $previousPurchaseTotal = $user->can('purchase_invoices_supplier_returns.view') ? (clone $purchases)->whereBetween('invoice_date', [$previousFrom->toDateString(), $previousTo->toDateString()])->sum('total_amount') : 0;
        $stockBalances = StockBalance::query()->whereIn('store_id', clone $storeIds);

        return [
            'sales' => $user->can('pos_sales.view') ? [
                'total' => (string) (clone $sales)->whereBetween('approved_at', [$from, $to])->sum('payable_total'),
                'count' => (clone $sales)->whereBetween('approved_at', [$from, $to])->count(),
                'recent' => (clone $sales)->with(['store:id,code,name_ar,name_en', 'cashier:id,name'])->latest('approved_at')->latest('id')->limit(5)->get(['id', 'store_id', 'cashier_id', 'document_number', 'payable_total', 'approved_at']),
            ] : null,
            'purchases' => $user->can('purchase_invoices_supplier_returns.view') ? [
                'total' => (string) (clone $purchases)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->sum('total_amount'),
                'count' => (clone $purchases)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->count(),
                'recent' => (clone $purchases)->with(['store:id,code,name_ar,name_en', 'supplier:id,name_ar,name_en'])->latest('invoice_date')->latest('id')->limit(5)->get(['id', 'store_id', 'supplier_id', 'invoice_number', 'total_amount', 'status', 'invoice_date']),
            ] : null,
            'low_stock' => $user->can('inventory_stock_card.view') ? LowStockRule::apply((clone $stockBalances))
                ->with(['product:id,item_code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->orderByRaw('(on_hand - reserved) asc')->limit(6)->get() : collect(),
            'low_stock_count' => $user->can('inventory_stock_card.view') ? LowStockRule::apply((clone $stockBalances))->count() : null,
            'out_of_stock_count' => $user->can('inventory_stock_card.view') ? (clone $stockBalances)->whereRaw('(on_hand - reserved) <= 0')->count() : null,
            'recent_movements' => $user->can('inventory_stock_card.view') ? StockMovement::query()
                ->whereIn('store_id', clone $storeIds)->with(['product:id,item_code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('posted_at')->latest('id')->limit(5)->get() : collect(),
            'trend' => collect(range($periodDays - 1, 0))->map(fn (int $days): array => ['day' => $from->copy()->addDays($periodDays - 1 - $days)->toDateString(), 'sales' => (string) ($salesTrend[$from->copy()->addDays($periodDays - 1 - $days)->toDateString()] ?? '0'), 'purchases' => (string) ($purchaseTrend[$from->copy()->addDays($periodDays - 1 - $days)->toDateString()] ?? '0')]),
            'trend_comparison' => ['from' => $previousFrom->toDateString(), 'to' => $previousTo->toDateString(), 'sales' => (string) $previousSalesTotal, 'purchases' => (string) $previousPurchaseTotal],
            'top_products' => $user->can('pos_sales.view') ? SaleLine::query()->whereIn('sale_id', (clone $sales)->select('id'))->selectRaw('product_id, SUM(quantity) as units')->with('product:id,item_code,name_ar,name_en')->groupBy('product_id')->orderByDesc('units')->limit(5)->get() : collect(),
            'open_shifts' => $user->can('shifts_cash_movements.view') ? PosShift::query()->visibleTo($user)->open()->count() : null,
            'pending_orders' => $user->can('purchase_orders.view') ? PurchaseOrder::query()->whereIn('store_id', clone $storeIds)->whereIn('status', ['submitted', 'approved', 'partially_received'])->count() : null,
            'pending_returns' => $user->can('purchase_returns.view') ? PurchaseReturn::query()->whereIn('store_id', clone $storeIds)->where('status', 'submitted')->count() : null,
            'pending_approvals' => $canDecideApprovals ? ApprovalRecord::query()->visibleTo($user)->where('approval_state', ApprovalState::Pending)->count() : null,
        ];
    }
}
