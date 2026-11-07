<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductOptionValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionValue extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ProductOptionValueFactory::new();
    }

    protected $fillable = ['product_option_group_id', 'code', 'name_ar', 'name_en', 'colour_swatch', 'status', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
