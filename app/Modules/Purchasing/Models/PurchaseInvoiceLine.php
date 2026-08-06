<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseInvoiceLine extends Model
{
    protected $fillable = [
        'purchase_invoice_id', 'purchase_order_line_id', 'product_id', 'quantity', 'quantity_received',
        'unit_cost', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_code',
        'tax_amount', 'subtotal', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'quantity_received' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
