<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CancelPurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.cancel');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A cancellation reason is required.'));
        }

        return DB::transaction(function () use ($id, $reason, $expectedVersion): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session.'));
            }
            if (! in_array($invoice->status, ['draft', 'submitted'], true)) {
                throw new InvalidArgumentException(__('Only draft or submitted invoices can be cancelled.'));
            }
            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => Auth::id(), 'cancel_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'cancel_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'lock_version']), storeId: $invoice->store_id);

            return $invoice->fresh(['lines']);
        });
    }
}
