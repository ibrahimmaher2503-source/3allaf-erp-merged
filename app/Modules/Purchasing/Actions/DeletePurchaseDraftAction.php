<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeletePurchaseDraftAction
{
    public function execute(int $invoiceId, int $userId): void
    {
        DB::transaction(function () use ($invoiceId, $userId): void {
            $invoice = PurchaseInvoice::query()->with(['lines', 'distributions', 'supplierReturns'])->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'draft' || $invoice->submitted_at !== null || $invoice->approved_at !== null || $invoice->purchase_order_id !== null || $invoice->lines->contains(fn ($line): bool => $line->distributions()->exists()) || $invoice->distributions->isNotEmpty() || $invoice->supplierReturns->isNotEmpty()) {
                throw ValidationException::withMessages(['invoice' => __('This purchase document cannot be deleted after submission or downstream references. Use Cancel or Reject instead.')]);
            }
            $invoice->delete();
            app(RecordAuditEvent::class)->execute(category: 'purchasing', event: 'delete_purchase_draft', source: $invoice, before: $invoice->getRawOriginal(), after: ['deleted_at' => $invoice->deleted_at?->toIso8601String(), 'deleted_by' => $userId]);
        });
    }
}
