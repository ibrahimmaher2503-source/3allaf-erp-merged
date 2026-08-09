<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['sale_id', 'product_id', 'line_number', 'item_code', 'name_ar', 'name_en', 'quantity', 'unit_price', 'reference_price', 'is_open_price', 'open_price_authorized_by', 'open_price_minimum_snapshot', 'open_price_maximum_snapshot', 'open_price_reason', 'gross_amount', 'discount_amount', 'discount_type', 'discount_rate', 'discount_reason', 'discount_applied_by', 'discount_replaced_by', 'discount_replaced_at', 'allocated_invoice_discount', 'net_amount', 'consumed_cost', 'stock_movement_id'];

    protected $casts = ['quantity' => 'decimal:6', 'unit_price' => 'decimal:4', 'reference_price' => 'decimal:4', 'open_price_minimum_snapshot' => 'decimal:4', 'open_price_maximum_snapshot' => 'decimal:4', 'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'allocated_invoice_discount' => 'decimal:2', 'discount_rate' => 'decimal:2', 'discount_replaced_at' => 'datetime', 'is_open_price' => 'boolean', 'net_amount' => 'decimal:2', 'consumed_cost' => 'decimal:4'];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->sale()->first();
    }
}
