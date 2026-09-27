<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use Illuminate\Support\Carbon;

final class SupplierProductPrice
{
    /** @return array{unit_cost: string|null, price_source: string, price_date: string|null, price_currency: string|null} */
    public function resolve(Product $product, int $supplierId, int $companyId, ?string $currencyCode = null): array
    {
        $currencyCode = filled($currencyCode) ? strtoupper(trim((string) $currencyCode)) : null;
        $lastPurchase = PurchaseInvoiceLine::query()
            ->select([
                'purchase_invoice_lines.unit_cost',
                'purchase_invoices.currency_code',
                'purchase_invoices.invoice_date',
                'purchase_invoices.approved_at',
            ])
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
            ->join('stores', 'stores.id', '=', 'purchase_invoices.store_id')
            ->where('purchase_invoice_lines.product_id', $product->id)
            ->where('stores.company_id', $companyId)
            ->where('purchase_invoices.supplier_id', $supplierId)
            ->where('purchase_invoices.status', 'approved')
            ->orderByDesc('purchase_invoices.approved_at')
            ->orderByDesc('purchase_invoices.id')
            ->first();

        if ($lastPurchase !== null) {
            $priceDate = $lastPurchase->approved_at ?? $lastPurchase->invoice_date;

            return [
                'unit_cost' => (string) $lastPurchase->unit_cost,
                'price_source' => 'last_supplier_price',
                'price_date' => $priceDate === null ? null : Carbon::parse($priceDate)->toDateString(),
                'price_currency' => $lastPurchase->currency_code,
            ];
        }

        return [
            'unit_cost' => '0.0000',
            'price_source' => 'no_supplier_history',
            'price_date' => null,
            'price_currency' => $currencyCode,
        ];
    }
}
