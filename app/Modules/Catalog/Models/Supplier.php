<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }

    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $supplier): void {
            $supplier->name_en = trim((string) $supplier->name_en);
            if ($supplier->name_en === '') {
                throw new \InvalidArgumentException(__('Supplier English name is required.'));
            }
        });
    }

    protected $fillable = [
        'code',
        'barcode_prefix',
        'name_ar',
        'name_en',
        'contact_name',
        'email',
        'phone',
        'tax_number',
        'country_code',
        'payment_terms',
        'credit_limit',
        'lead_time_days',
        'preferred_payment_method_id',
        'settlement_method',
        'settlement_other_description',
        'address',
        'status',
        'supplier_group_id',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'credit_limit' => 'decimal:4',
        'lead_time_days' => 'integer',
    ];

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    /** @return BelongsTo<SupplierGroup, $this> */
    public function supplierGroup(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function preferredPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'preferred_payment_method_id');
    }

    /** @return HasMany<SupplierContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /** @return HasMany<SupplierCommunicationDestination, $this> */
    public function communicationDestinations(): HasMany
    {
        return $this->hasMany(SupplierCommunicationDestination::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_suppliers')
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
