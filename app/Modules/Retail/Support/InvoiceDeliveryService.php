<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Mail\PosInvoiceReceipt;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Enums\InvoiceDeliveryMethod;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class InvoiceDeliveryService
{
    /** The caller invokes this only after sale posting has committed. */
    public function deliver(Sale $sale): string
    {
        if ($sale->status !== 'approved' || $sale->customer_id === null) {
            return InvoiceDeliveryMethod::Print->value;
        }

        $sale->loadMissing('store');
        $customer = Customer::query()->whereKey($sale->customer_id)->whereHas('createdStore', function ($query) use ($sale): void {
            $query->where('company_id', $sale->store?->company_id);
        })->first();
        $address = trim((string) $customer?->email);
        if ($customer?->invoice_delivery_method !== InvoiceDeliveryMethod::Email->value || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return InvoiceDeliveryMethod::Print->value;
        }

        $inserted = DB::table('sale_invoice_delivery_attempts')->insertOrIgnore([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'method' => InvoiceDeliveryMethod::Email->value,
            'status' => 'attempted',
            'attempted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return 'already_attempted';
        }

        try {
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                throw new RuntimeException('A delivery-capable mail transport is not configured.');
            }
            $sale->loadMissing(['store.company', 'branch', 'customer', 'lines', 'payments.paymentMethod']);
            Mail::to($address)->send(new PosInvoiceReceipt($sale));
            $status = 'sent';
        } catch (Throwable $exception) {
            $status = 'failed';
            Log::error('POS invoice email delivery failed after sale completion.', [
                'sale_id' => $sale->id,
                'customer_id' => $customer->id,
                'exception' => $exception::class,
            ]);
        }

        try {
            DB::table('sale_invoice_delivery_attempts')->where('sale_id', $sale->id)->update([
                'status' => $status,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute(
                category: 'retail', event: 'pos_invoice_email_'.$status, source: $sale,
                branchId: (int) $sale->branch_id, storeId: (int) $sale->store_id,
                metadata: ['customer_id' => $customer->id, 'delivery_method' => 'email'],
            );
        } catch (Throwable $exception) {
            Log::error('POS invoice delivery outcome could not be recorded.', ['sale_id' => $sale->id, 'exception' => $exception::class]);
        }

        return $status === 'sent' ? 'email_sent' : 'email_failed';
    }
}
