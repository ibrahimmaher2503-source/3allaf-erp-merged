<?php

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_unit_id',
        'unit_code_snapshot',
        'entered_quantity',
        'conversion_factor_snapshot',
        'entered_unit_price',
        'line_number',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_code',
        'tax_inclusive',
        'tax_amount',
        'subtotal',
        'line_total',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'entered_quantity' => 'decimal:6',
        'conversion_factor_snapshot' => 'decimal:6',
        'entered_unit_price' => 'decimal:4',
        'quantity_ordered' => 'decimal:6',
        'quantity_received' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_inclusive' => 'boolean',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function approvedParent(): ?Model
    {
        return $this->purchaseOrder()->first();
    }
}
