<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ApproveStockTransferAction;
use App\Modules\Inventory\Actions\ReconcileStockCountAction;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\RejectPriceProposalAction;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Modules\Purchasing\Actions\ApprovePurchaseReturnAction;
use App\Modules\Purchasing\Actions\RejectPurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\RejectPurchaseReturnAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Validation\ValidationException;

/**
 * Focused routing from the shared inbox to source-owned domain actions.
 * Source actions retain authorization, locks, posting, and audit ownership.
 */
final class DecideApprovalSource
{
    public function approve(ApprovalRecord $record): void
    {
        match ($record->source_type) {
            'pricing_labels' => app(ApprovePriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($record->source_id)),
            'purchase_orders' => app(ApprovePurchaseOrderAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'purchase_invoices' => app(ApprovePurchaseInvoiceAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'purchase_returns' => app(ApprovePurchaseReturnAction::class)->execute((int) $record->source_id, (int) $record->source_version),
            'inventory_adjustments' => app(ApproveInventoryAdjustmentAction::class)->execute((int) $record->source_id),
            'stock_counts' => app(ReconcileStockCountAction::class)->execute((int) $record->source_id),
            'stock_transfers' => app(ApproveStockTransferAction::class)->execute((int) $record->source_id),
            'pos_shifts' => app(ReviewShiftVarianceAction::class)->approveAndClose(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                PosShift::query()->findOrFail($record->source_id),
                $record,
                (int) $record->source_version,
            ),
            default => throw ValidationException::withMessages(['approval' => __('This approval source is not supported by the central inbox.')]),
        };
    }

    public function reject(ApprovalRecord $record, string $reason): void
    {
        match ($record->source_type) {
            'pricing_labels' => app(RejectPriceProposalAction::class)->execute(PriceVersion::query()->findOrFail($record->source_id), $reason),
            'purchase_invoices' => app(RejectPurchaseInvoiceAction::class)->execute((int) $record->source_id, $reason, (int) $record->source_version),
            'purchase_returns' => app(RejectPurchaseReturnAction::class)->execute((int) $record->source_id, $reason, (int) $record->source_version),
            'pos_shifts' => app(ReviewShiftVarianceAction::class)->requestRecount(
                auth()->user() ?? throw new \LogicException('An authenticated approver is required.'),
                PosShift::query()->findOrFail($record->source_id),
                $record,
                $reason,
                (int) $record->source_version,
            ),
            default => throw ValidationException::withMessages(['approval' => __('This source does not expose a rejection transition.')]),
        };
    }

    public function canReject(ApprovalRecord $record): bool
    {
        return in_array($record->source_type, ['pricing_labels', 'purchase_invoices', 'purchase_returns', 'pos_shifts'], true);
    }

    public function sourceRoute(ApprovalRecord $record): string
    {
        return match ($record->source_type) {
            'pricing_labels' => route('pricing.approvals'),
            'purchase_orders' => route('purchasing.orders'),
            'purchase_invoices' => route('purchasing.invoices'),
            'purchase_returns' => route('purchasing.returns.show', $record->source_id),
            'inventory_adjustments' => route('inventory.adjustments'),
            'stock_counts' => route('inventory.counts.reconcile-page', $record->source_id),
            'stock_transfers' => route('inventory.transfers'),
            'pos_shifts' => route('pos.shift-variance'),
            default => route('admin.approvals'),
        };
    }
}
