<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use App\Modules\Retail\Services\PosCalculationService;
use App\Support\ProductQuantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Saves an ordinary invoice draft without payment capture or stock posting. */
final class SaveSalesInvoiceDraftAction
{
    public function __construct(
        private readonly EffectivePriceResolver $prices,
        private readonly PosCalculationService $calculator,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: string}>  $requestedLines
     */
    public function execute(User $actor, Store $store, array $requestedLines, string $token, ?string $notes = null): Sale
    {
        abort_unless($actor->can('sales_invoices.create'), 403);
        abort_unless(Store::query()->visibleTo($actor)->whereKey($store->id)->where('status', 'active')->where('type', 'selling')->exists(), 403);
        if ($requestedLines === []) {
            throw new InvalidArgumentException(__('Add at least one product to the invoice.'));
        }

        $store->loadMissing('company');
        $currency = strtoupper(trim((string) $store->company?->currency_code));
        if ($currency === '' || $currency === 'TBD') {
            throw new InvalidArgumentException(__('The company currency must be configured before an invoice can be saved.'));
        }

        $productIds = array_column($requestedLines, 'product_id');
        if (count($productIds) !== count(array_unique($productIds))) {
            throw new InvalidArgumentException(__('Each product may appear only once on an invoice.'));
        }

        $products = Product::query()->sellable()
            ->availableToCompany((int) $store->company_id, (int) $store->branch_id, (int) $store->id)
            ->whereKey($productIds)->get()->keyBy('id');
        if ($products->count() !== count($productIds)) {
            throw new InvalidArgumentException(__('One or more products are unavailable in the selected company or store.'));
        }

        $lines = [];
        foreach ($requestedLines as $requested) {
            $product = $products->get($requested['product_id']);
            $price = $this->prices->resolve((int) $product->id, (int) $store->id);
            if ($price === null) {
                throw new InvalidArgumentException(__('One or more products have no valid selling price for this store.'));
            }
            $lines[] = [
                'product' => $product,
                'quantity' => (string) ProductQuantity::normalize($requested['quantity'], decimalPlaces: 6),
                'unit_price' => (string) $price->amount,
                'reference_price' => (string) ($price->reference_amount ?? $price->amount),
            ];
        }

        $totals = $this->calculator->calculate(array_map(static fn (array $line): array => [
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discount_amount' => '0.00',
        ], $lines));
        $fingerprint = hash('sha256', json_encode([
            'channel' => 'ordinary_invoice_draft',
            'store_id' => $store->id,
            'actor_id' => $actor->id,
            'lines' => array_map(static fn (array $line): array => [(int) $line['product']->id, $line['quantity'], $line['unit_price']], $lines),
            'notes' => trim((string) $notes),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($actor, $store, $lines, $token, $notes, $totals, $currency, $fingerprint): Sale {
            $existing = Sale::query()->where('idempotency_key', $token)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless((int) $existing->cashier_id === (int) $actor->id
                    && (int) $existing->store_id === (int) $store->id
                    && $existing->status === 'draft'
                    && $existing->shift_id === null
                    && hash_equals((string) $existing->request_fingerprint, $fingerprint), 409);

                return $existing;
            }

            $sale = Sale::query()->create([
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'cashier_id' => $actor->id,
                'status' => 'draft',
                'idempotency_key' => $token,
                'request_fingerprint' => $fingerprint,
                'currency_code' => $currency,
                'subtotal' => $totals['subtotal'],
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'tax_applicable' => false,
                'total' => $totals['total'],
                'payable_total' => $totals['total'],
                'notes' => $notes,
            ]);

            foreach ($lines as $index => $line) {
                SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'line_number' => $index + 1,
                    'item_code' => $line['product']->item_code,
                    'name_ar' => $line['product']->name_ar,
                    'name_en' => $line['product']->name_en,
                    'variant_snapshot' => $line['product']->variantSnapshot(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'reference_price' => $line['reference_price'],
                    'gross_amount' => $totals['lines'][$index]['gross_amount'],
                    'discount_amount' => '0.00',
                    'allocated_invoice_discount' => '0.00',
                    'net_amount' => $totals['lines'][$index]['net_amount'],
                ]);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'create_sales_invoice_draft',
                source: $sale,
                after: $sale->only(['status', 'store_id', 'subtotal', 'total', 'currency_code']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'line_count' => count($lines), 'stock_posted' => false],
            );

            return $sale->load('lines');
        });
    }
}
