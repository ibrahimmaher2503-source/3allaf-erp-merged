<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Catalog\Models\Product;
use InvalidArgumentException;

final class ResolveStockCountProduct
{
    public function resolve(string $identifier): Product
    {
        $identifier = trim($identifier);
        if ($identifier === '' || mb_strlen($identifier) > 255) {
            throw new InvalidArgumentException(__('Enter a valid product code or barcode.'));
        }

        $product = Product::query()->sellable()->where(function ($query) use ($identifier): void {
            $query->where('item_code', $identifier)->orWhere('model_number', $identifier)
                ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', $identifier)->where('status', 'active'));
        })->first();

        if ($product === null) {
            throw new InvalidArgumentException(__('Unknown barcode or product identifier. Search by product name, code, or model number.'));
        }

        return $product;
    }
}
