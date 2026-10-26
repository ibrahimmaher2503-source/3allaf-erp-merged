<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductOptionGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ProductOptionGroupFactory::new();
    }

    protected $fillable = ['code', 'name_ar', 'name_en', 'status', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
