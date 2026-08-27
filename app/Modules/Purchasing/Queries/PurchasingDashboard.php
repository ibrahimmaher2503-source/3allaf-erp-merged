<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Queries;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Builder;

final class PurchasingDashboard
{
    /** @return array<string, mixed> */
    public function for(User $actor): array
    {
        $orders = $this->visibleOrders($actor);
        $invoices = $this->visibleInvoices($actor);
        $returns = $this->visibleReturns($actor);
        $periodStart = today()->subDays(6);

        $approvedPeriodInvoices = (clone $invoices)
            ->where('status', 'approved')
            ->whereDate('invoice_date', '>=', $periodStart);

        $trendRows = (clone $approvedPeriodInvoices)
            ->selectRaw('DATE(invoice_date) as purchase_day, COUNT(*) as invoice_count, COALESCE(SUM(total_amount), 0) as total')
            ->groupByRaw('DATE(invoice_date)')
            ->pluck('total', 'purchase_day');

        return [
            'currency_code' => (clone $approvedPeriodInvoices)->value('currency_code') ?: 'EGP',
            'today_total' => (string) (clone $invoices)->where('status', 'approved')->whereDate('invoice_date', today())->sum('total_amount'),
            'today_count' => (clone $invoices)->where('status', 'approved')->whereDate('invoice_date', today())->count(),
            'period_total' => (string) (clone $approvedPeriodInvoices)->sum('total_amount'),
            'period_count' => (clone $approvedPeriodInvoices)->count(),
            'open_orders' => (clone $orders)->whereIn('status', ['draft', 'submitted', 'approved', 'partially_received'])->count(),
            'receiving' => [
                'pending' => (clone $orders)->where('status', 'approved')->count(),
                'partial' => (clone $orders)->where('status', 'partially_received')->count(),
                'completed' => (clone $orders)->whereIn('status', ['received', 'closed'])->count(),
            ],
            'returns_requiring_action' => (clone $returns)->whereIn('status', ['draft', 'submitted'])->count(),
            'pending_approvals' => ApprovalRecord::query()->visibleTo($actor)
                ->whereIn('source_type', ['purchase_orders', 'purchase_invoices', 'purchase_returns'])
                ->where('approval_state', ApprovalState::Pending->value)
                ->count(),
            'trend' => collect(range(6, 0))->map(function (int $offset) use ($trendRows): array {
                $date = today()->subDays($offset);

                return ['date' => $date, 'total' => (float) ($trendRows[$date->toDateString()] ?? 0)];
            }),
            'top_suppliers' => (clone $approvedPeriodInvoices)
                ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
                ->select(['suppliers.id', 'suppliers.code', 'suppliers.name_ar', 'suppliers.name_en'])
                ->selectRaw('COUNT(purchase_invoices.id) as invoice_count, COALESCE(SUM(purchase_invoices.total_amount), 0) as purchase_total')
                ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name_ar', 'suppliers.name_en')
                ->orderByDesc('purchase_total')->limit(5)->get(),
            'top_products' => PurchaseInvoiceLine::query()
                ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
                ->join('products', 'products.id', '=', 'purchase_invoice_lines.product_id')
                ->whereIn('purchase_invoices.store_id', Store::query()->visibleTo($actor)->select('id'))
                ->where('purchase_invoices.status', 'approved')
                ->whereDate('purchase_invoices.invoice_date', '>=', $periodStart)
                ->select(['products.id', 'products.item_code', 'products.name_ar', 'products.name_en'])
                ->selectRaw('COALESCE(SUM(purchase_invoice_lines.quantity), 0) as units, COALESCE(SUM(purchase_invoice_lines.line_total), 0) as purchase_total')
                ->groupBy('products.id', 'products.item_code', 'products.name_ar', 'products.name_en')
                ->orderByDesc('purchase_total')->limit(5)->get(),
            'recent_invoices' => (clone $invoices)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('invoice_date')->latest('id')->limit(5)->get(),
            'recent_orders' => (clone $orders)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('order_date')->latest('id')->limit(5)->get(),
            'recent_returns' => (clone $returns)->with(['supplier:id,code,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('return_date')->latest('id')->limit(5)->get(),
        ];
    }

    private function visibleOrders(User $actor): Builder
    {
        return PurchaseOrder::query()->where(function (Builder $query) use ($actor): void {
            $query->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereIn('branch_id', Branch::query()->visibleTo($actor)->select('id'));
        });
    }

    private function visibleInvoices(User $actor): Builder
    {
        return PurchaseInvoice::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'));
    }

    private function visibleReturns(User $actor): Builder
    {
        return PurchaseReturn::query()->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'));
    }
}
