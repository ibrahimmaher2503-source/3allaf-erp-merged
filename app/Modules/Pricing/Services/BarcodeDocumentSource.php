<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Support\HumanName;
use Illuminate\Validation\ValidationException;

final class BarcodeDocumentSource
{
    /** @return array{quantities: array<int, int>, prices: array<int, string>, destination: Store} */
    public function prepare(User $actor, string $source, string $number, int $destinationStoreId, int $sellingStoreId): array
    {
        $destination = Store::query()->visibleTo($actor)->where('status', 'active')->findOrFail($destinationStoreId);
        $sellingStore = Store::query()->visibleTo($actor)->where('status', 'active')->where('type', 'selling')->findOrFail($sellingStoreId);
        if ((int) $destination->company_id !== (int) $sellingStore->company_id
            || $destination->branch_id === null
            || (int) $destination->branch_id !== (int) $sellingStore->branch_id) {
            throw ValidationException::withMessages(['store_id' => __('Select a selling outlet in the destination branch.')]);
        }

        $quantities = match ($source) {
            'purchase_invoice' => $this->purchase($actor, $number, $destination),
            'stock_transfer' => $this->transfer($actor, $number, $destination),
            default => throw ValidationException::withMessages(['source' => __('Select a supported document source.')]),
        };
        if ($quantities === []) {
            throw ValidationException::withMessages(['number' => __('This document has no products allocated to the selected destination.')]);
        }
        $resolved = app(BarcodeSellingPriceResolver::class)->resolveForStore(array_keys($quantities), $sellingStore);
        $prices = [];
        foreach ($quantities as $productId => $quantity) {
            $price = $resolved->get($productId);
            if ($price === null || bccomp((string) $price, '0', 3) <= 0) {
                $product = Product::query()->find($productId);
                $productLabel = $product === null ? (string) $productId : HumanName::name($product).' · '.$product->item_code;
                throw ValidationException::withMessages(['number' => __('No selling price for product :product in destination :destination.', ['product' => $productLabel, 'destination' => HumanName::name($sellingStore)])]);
            }
            $prices[$productId] = (string) $price;
        }

        return ['quantities' => $quantities, 'prices' => $prices, 'destination' => $destination];
    }

    /** @return array<int, int> */
    private function purchase(User $actor, string $number, Store $destination): array
    {
        $invoice = PurchaseInvoice::query()->where('invoice_number', $number)->where('status', 'approved')
            ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
            ->with(['store', 'lines', 'distributions'])->firstOrFail();
        if ((int) $invoice->store->company_id !== (int) $destination->company_id
            || (int) $invoice->store->branch_id !== (int) $destination->branch_id) {
            throw ValidationException::withMessages(['destination_store_id' => __('The destination does not belong to the invoice receiving branch.')]);
        }
        if ((int) $invoice->store_id === (int) $destination->id) {
            return $invoice->lines->mapWithKeys(fn ($line): array => [$line->product_id => (int) $line->quantity])->filter()->all();
        }
        $distributions = $invoice->distributions->where('destination_store_id', $destination->id);

        return $distributions->groupBy(fn ($row): int => (int) $invoice->lines->firstWhere('id', $row->purchase_invoice_line_id)?->product_id)
            ->map(fn ($rows): int => (int) $rows->sum('quantity'))->filter()->all();
    }

    /** @return array<int, int> */
    private function transfer(User $actor, string $number, Store $destination): array
    {
        $transfer = StockTransfer::query()->where('transfer_number', $number)
            ->whereIn('status', ['approved', 'in_transit', 'received', 'difference_review'])
            ->with('lines.allocations')->firstOrFail();
        $hasAllocation = $transfer->lines->contains(fn ($line): bool => $line->allocations->isNotEmpty());
        if (! $hasAllocation && (int) $transfer->destination_store_id !== (int) $destination->id) {
            return [];
        }
        $quantities = [];
        foreach ($transfer->lines as $line) {
            $quantity = $hasAllocation
                ? $line->allocations->where('destination_store_id', $destination->id)->sum('quantity')
                : $line->quantity_requested;
            if (bccomp((string) $quantity, '0', 6) > 0) {
                $quantities[(int) $line->product_id] = (int) $quantity;
            }
        }

        return $quantities;
    }
}
