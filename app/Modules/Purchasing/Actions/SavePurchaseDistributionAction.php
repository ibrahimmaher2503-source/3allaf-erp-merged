<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Pricing\Services\PriceListResolver;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SavePurchaseDistributionAction
{
    /** @param array<int, array<int, string|int|float|null>> $matrix */
    public function execute(int $invoiceId, array $matrix, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.distribute');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($invoiceId, $matrix, $expectedVersion, $actor): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->with('lines.product')->lockForUpdate()->findOrFail($invoiceId);
            if (! Store::query()->visibleTo($actor)->whereKey($invoice->store_id)->exists()) {
                abort(403);
            }
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload.'));
            }
            if (! in_array($invoice->status, ['submitted', 'awaiting_distribution'], true)) {
                throw new InvalidArgumentException(__('Only submitted purchase invoices can be allocated.'));
            }
            $before = $invoice->distributions()->get()->map->only(['purchase_invoice_line_id', 'destination_store_id', 'quantity'])->all();
            $savedPrices = $invoice->distributions()->get()->keyBy(fn ($distribution): string => $distribution->purchase_invoice_line_id.':'.$distribution->destination_store_id);
            $invoice->distributions()->delete();
            $lineIds = $invoice->lines->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($matrix as $lineId => $destinations) {
                $line = $invoice->lines->firstWhere('id', (int) $lineId);
                if (! $line || ! in_array((int) $lineId, $lineIds, true)) {
                    throw new InvalidArgumentException(__('The distribution contains a line outside this invoice.'));
                }
                foreach ($destinations as $storeId => $quantity) {
                    $quantity = ProductQuantity::normalize(blank($quantity) ? 0 : $quantity, 'distributionMatrix.'.$line->id.'.'.$storeId, true, decimalPlaces: 6);
                    if (bccomp((string) $quantity, '0', 6) === 0) {
                        continue;
                    }
                    $destination = Store::query()->visibleTo($actor)->where('status', 'active')->where('company_id', $invoice->store->company_id)->whereKey((int) $storeId)->first();
                    if ($destination === null) {
                        throw ValidationException::withMessages(['distributionMatrix.'.$line->id.'.'.$storeId => __('Select an active destination within your company and store scope.')]);
                    }
                    if ((int) $destination->id === (int) $invoice->store_id) {
                        throw new InvalidArgumentException(__('Stock retained in the receiving store needs no distribution entry.'));
                    }
                    $price = null;
                    $fallbackPrice = null;
                    $savedPrice = $savedPrices->get($line->id.':'.$destination->id);
                    if ($destination->type === 'selling' && $savedPrice === null) {
                        try {
                            $list = app(PriceListResolver::class)->listForOutlet($destination);
                            $price = app(PriceListResolver::class)->resolveWithBasePrice($line->product, $list, (string) $line->base_consumer_price);
                        } catch (ValidationException $exception) {
                            if (! isset($exception->errors()['price_list'])) {
                                throw $exception;
                            }
                            $fallbackPrice = app(EffectivePriceResolver::class)->resolve($line->product_id, $destination->id)?->amount;
                            if ($fallbackPrice === null) {
                                throw $exception;
                            }
                        }
                    }
                    $invoice->distributions()->create(['purchase_invoice_line_id' => (int) $lineId, 'destination_store_id' => $destination->id, 'quantity' => bcadd((string) $quantity, '0', 6), 'price_list_id' => $savedPrice?->price_list_id ?? $price?->priceListId, 'effective_selling_price' => $savedPrice?->effective_selling_price ?? $price?->finalPrice ?? $fallbackPrice, 'price_is_override' => $savedPrice?->price_is_override ?? $price->overridden ?? false]);
                }
            }
            foreach ($invoice->lines as $line) {
                $allocated = $invoice->distributions()->where('purchase_invoice_line_id', $line->id)->sum('quantity');
                if (bccomp((string) $allocated, (string) $line->quantity, 6) > 0) {
                    throw ValidationException::withMessages(['distributionMatrix.'.$line->id => 'إجمالي التوزيع لا يمكن أن يتجاوز الكمية المستلمة.']);
                }
            }
            $invoice->update(['status' => 'submitted', 'lock_version' => $invoice->lock_version + 1, 'updated_by' => $actor->id]);
            ApprovalRecord::query()
                ->where('source_type', 'purchase_invoices')
                ->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->update(['source_version' => (string) $invoice->lock_version]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'save_purchase_distribution', source: $invoice, before: $before, after: $invoice->distributions()->get()->map->only(['purchase_invoice_line_id', 'destination_store_id', 'quantity', 'effective_selling_price'])->all(), storeId: $invoice->store_id);

            return $invoice->fresh(['lines.product', 'distributions.destination', 'distributions.priceList']);
        });
    }
}
