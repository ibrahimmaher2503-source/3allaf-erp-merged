<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleLine extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'line_number', 'item_code', 'name_ar', 'name_en', 'quantity', 'unit_price', 'gross_amount', 'discount_amount', 'net_amount', 'consumed_cost', 'stock_movement_id'];

    protected $casts = ['quantity' => 'decimal:6', 'unit_price' => 'decimal:4', 'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'consumed_cost' => 'decimal:4'];

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
}
