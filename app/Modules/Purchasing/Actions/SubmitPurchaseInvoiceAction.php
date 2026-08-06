<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitPurchaseInvoiceAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before submitting.'));
            }
            if ($invoice->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft purchase invoices can be submitted.'));
            }
            if ($invoice->lines()->doesntExist()) {
                throw new InvalidArgumentException(__('A purchase invoice must contain at least one line item.'));
            }

            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'lock_version' => $invoice->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'submit_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'submitted_at', 'lock_version']), storeId: $invoice->store_id);

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }
}
