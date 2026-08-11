<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetailReturnLine extends Model
{
    protected $fillable = ['retail_return_id', 'sale_line_id', 'product_id', 'line_number', 'quantity', 'unit_value', 'eligible_value', 'condition', 'disposition', 'inspection_notes'];
    protected $casts = ['quantity' => 'decimal:6', 'unit_value' => 'decimal:4', 'eligible_value' => 'decimal:2'];
    public function retailReturn(): BelongsTo { return $this->belongsTo(RetailReturn::class); }
    public function saleLine(): BelongsTo { return $this->belongsTo(SaleLine::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
