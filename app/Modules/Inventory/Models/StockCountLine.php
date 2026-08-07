<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockCountLine extends Model
{
    protected $fillable = ['stock_count_id', 'product_id', 'reference_on_hand', 'movement_quantity_after_reference', 'expected_quantity', 'counted_quantity', 'variance_quantity', 'is_counted', 'input_method', 'recount_number', 'counted_at', 'notes'];

    protected $casts = ['reference_on_hand' => 'decimal:6', 'movement_quantity_after_reference' => 'decimal:6', 'expected_quantity' => 'decimal:6', 'counted_quantity' => 'decimal:6', 'variance_quantity' => 'decimal:6', 'is_counted' => 'boolean', 'recount_number' => 'integer', 'counted_at' => 'datetime'];

    /** @return BelongsTo<StockCount, $this> */
    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
