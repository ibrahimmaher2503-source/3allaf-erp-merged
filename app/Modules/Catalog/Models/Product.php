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
use Illuminate\Support\Collection;

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
        'has_variations',
        'parent_product_id',
        'variant_signature',
        'variant_sort_order',
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
        'has_variations' => 'boolean',
        'variant_sort_order' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id')
            ->orderBy('variant_sort_order')
            ->orderBy('id');
    }

    public function familyOptionGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionGroup::class, 'product_family_option_groups')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function familyOptionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_family_option_values')
            ->withTimestamps();
    }

    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class)->orderBy('sort_order');
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

    public function scopeFamiliesAndSimple(Builder $query): Builder
    {
        return $query->whereNull('parent_product_id');
    }

    /**
     * Transaction-facing products only. A family is descriptive and can never be
     * priced, stocked, scanned, purchased, quoted, or sold directly.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('products.status', 'active')->where(function (Builder $scope): void {
            $scope->where(function (Builder $simple): void {
                $simple->whereNull('products.parent_product_id')->where('products.has_variations', false);
            })->orWhere(function (Builder $variant): void {
                $variant->whereNotNull('products.parent_product_id')
                    ->whereHas('parent', fn (Builder $family): Builder => $family->where('status', 'active')->where('has_variations', true));
            });
        });
    }

    public function isFamily(): bool
    {
        return $this->parent_product_id === null && (bool) $this->has_variations;
    }

    public function isVariant(): bool
    {
        return $this->parent_product_id !== null;
    }

    public function isSellable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->isVariant()) {
            $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

            return $parent?->status === 'active' && (bool) $parent?->has_variations;
        }

        return ! $this->has_variations;
    }

    public function family(): self
    {
        return $this->isVariant() ? ($this->parent ?? $this->parent()->firstOrFail()) : $this;
    }

    /** @return Collection<int, ProductImage> */
    public function effectiveImages(): Collection
    {
        $own = $this->relationLoaded('images') ? $this->images : $this->images()->with('attachment')->get();
        if ($own->isNotEmpty() || ! $this->isVariant()) {
            return $own;
        }

        $family = $this->family();

        return $family->relationLoaded('images') ? $family->images : $family->images()->with('attachment')->get();
    }

    public function effectiveMainImage(): ?ProductImage
    {
        $images = $this->effectiveImages();

        return $images->firstWhere('role', 'main') ?? $images->first();
    }

    public function localizedVariationLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $values = $this->relationLoaded('variantValues')
            ? $this->variantValues
            : $this->variantValues()->with(['group', 'value'])->get();

        return $values->map(function (ProductVariantValue $selection) use ($locale): string {
            $group = $locale === 'ar' ? $selection->group?->name_ar : $selection->group?->name_en;
            $value = $locale === 'ar' ? $selection->value?->name_ar : $selection->value?->name_en;

            return trim((string) $group).': '.trim((string) $value);
        })->implode(' · ');
    }

    /** @return array<int, array<string, int|string|null>>|null */
    public function variantSnapshot(): ?array
    {
        if (! $this->isVariant()) {
            return null;
        }

        $values = $this->relationLoaded('variantValues')
            ? $this->variantValues
            : $this->variantValues()->with(['group', 'value'])->get();

        return $values->map(fn (ProductVariantValue $selection): array => [
            'group_code' => (string) $selection->group?->code,
            'group_ar' => (string) $selection->group?->name_ar,
            'group_en' => (string) $selection->group?->name_en,
            'value_code' => (string) $selection->value?->code,
            'value_ar' => (string) $selection->value?->name_ar,
            'value_en' => (string) $selection->value?->name_en,
            'colour_swatch' => $selection->value?->colour_swatch,
        ])->values()->all();
    }
}
