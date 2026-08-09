<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    protected $fillable = [
        'item_code',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'model_number',
        'product_type',
        'unit_of_measure',
        'category_id',
        'brand_id',
        'status',
        'barcode_mode',
        'average_cost',
        'reorder_threshold',
        'dimension_length',
        'dimension_width',
        'dimension_height',
        'dimension_unit',
        'weight',
        'target_age',
        'suitable_gender',
        'colour',
        'size',
        'character',
        'key_points_ar',
        'key_points_en',
        'keywords_ar',
        'keywords_en',
        'fractional_quantity',
        'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'average_cost' => 'decimal:2',
        'reorder_threshold' => 'decimal:3',
        'dimension_length' => 'decimal:3',
        'dimension_width' => 'decimal:3',
        'dimension_height' => 'decimal:3',
        'weight' => 'decimal:3',
        'fractional_quantity' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN role = 'main' THEN 0 ELSE 1 END")
            ->orderBy('sort_order');
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function preferredProductSupplier(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_preferred', true);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
            ->withPivot([
                'supplier_item_code',
                'is_preferred',
                'last_purchase_price',
                'last_purchase_date',
                'notes',
                'created_by',
                'updated_by',
            ])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
