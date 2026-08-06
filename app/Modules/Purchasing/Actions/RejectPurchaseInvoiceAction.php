<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RejectPurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.approve');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A rejection reason is required.'));
        }

        return DB::transaction(function () use ($id, $reason, $expectedVersion): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session.'));
            }
            if ($invoice->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted invoices can be rejected.'));
            }
            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => Auth::id(), 'rejection_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reject_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'rejected_at', 'rejected_by', 'rejection_reason', 'lock_version']), storeId: $invoice->store_id);

            return $invoice->fresh(['lines']);
        });
    }
}
