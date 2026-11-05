<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Support\HumanName;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BarcodeDocumentSource
{
    /** @return Collection<int, Store> */
    public function transferDestinations(User $actor, string $number): Collection
    {
        $transfer = StockTransfer::query()->where('transfer_number', $number)
            ->whereIn('status', ['approved', 'in_transit', 'received', 'difference_review'])
            ->where(fn ($query) => $query
                ->whereIn('source_store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereIn('destination_store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereHas('allocations', fn ($allocations) => $allocations->whereIn('destination_store_id', Store::query()->visibleTo($actor)->select('id'))))
            ->with('allocations')->firstOrFail();
        $ids = $transfer->allocations->pluck('destination_store_id')->unique();
        if ($ids->isEmpty()) {
            $ids = collect([$transfer->destination_store_id]);
        }
        if (! Store::query()->visibleTo($actor)->whereKey($transfer->source_store_id)->exists()) {
            $ids = $ids->intersect(Store::query()->visibleTo($actor)->pluck('id'));
        }

        return Store::query()->whereIn('id', $ids)->where('status', 'active')->orderBy('code')->get();
    }

    /** @return array{quantities: array<int, int>, prices: array<int, string>, destination: Store, selling_store_id: int} */
    public function prepare(User $actor, string $source, string $number, ?int $destinationStoreId = null, ?int $sellingStoreId = null): array
    {
        $document = null;
        if ($destinationStoreId === null) {
            if ($source === 'purchase_invoice') {
                $document = PurchaseInvoice::query()->where('invoice_number', $number)->where('status', 'approved')
                    ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))->firstOrFail();
                $destinationStoreId = (int) $document->store_id;
            } elseif ($source === 'stock_transfer') {
                $destinationIds = $this->transferDestinations($actor, $number)->pluck('id');
                if ($destinationIds->count() > 1) {
                    throw ValidationException::withMessages(['number' => __('This transfer has multiple destinations. Print labels from each destination receipt.')]);
                }
                $destinationStoreId = (int) $destinationIds->first();
            }
        }
        if ($destinationStoreId === null) {
            throw ValidationException::withMessages(['source' => __('Select a supported document source.')]);
        }
        $destinationQuery = Store::query()->where('status', 'active');
        if ($source !== 'stock_transfer' || ! $this->transferDestinations($actor, $number)->contains('id', $destinationStoreId)) {
            $destinationQuery->visibleTo($actor);
        }
        $destination = $destinationQuery->findOrFail($destinationStoreId);
        if ($sellingStoreId === null) {
            $mappedStoreId = $destination->branch?->activeSellingStoreMapping?->store_id;
            $sellingStoreId = (int) ($mappedStoreId ?: Store::query()->where('company_id', $destination->company_id)
                ->where('branch_id', $destination->branch_id)->where('type', 'selling')->where('status', 'active')->value('id'));
        }
        $sellingStore = Store::query()->where('status', 'active')->where('type', 'selling')->findOrFail($sellingStoreId);
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

        return ['quantities' => $quantities, 'prices' => $prices, 'destination' => $destination, 'selling_store_id' => $sellingStore->id];
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
            ->where(fn ($query) => $query
                ->whereIn('source_store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereIn('destination_store_id', Store::query()->visibleTo($actor)->select('id'))
                ->orWhereHas('allocations', fn ($allocations) => $allocations->whereIn('destination_store_id', Store::query()->visibleTo($actor)->select('id'))))
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
