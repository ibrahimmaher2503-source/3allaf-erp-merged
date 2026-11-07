<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Queries;

use App\Modules\Catalog\Models\Product;

final class SupplierProductPrice
{
    /** @return array{unit_cost: string|null, price_source: string, price_date: string|null, price_currency: string|null} */
    public function resolve(Product $product, int $supplierId, int $companyId, ?string $currencyCode = null): array
    {
        return [
            'unit_cost' => $product->average_cost === null ? null : (string) $product->average_cost,
            'price_source' => $product->average_cost === null ? 'no_supplier_history' : 'fallback_cost',
            'price_date' => null,
            'price_currency' => filled($currencyCode) ? strtoupper(trim((string) $currencyCode)) : null,
        ];
    }
}
