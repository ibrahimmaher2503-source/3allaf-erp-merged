<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Enums\TaxTreatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class TaxSetting extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $tax): void {
            if ($tax->isDirty('company_id')) {
                throw new LogicException('Tax-rule company ownership is immutable.');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'code',
        'name_ar',
        'name_en',
        'rate',
        'treatment',
        'is_default',
        'is_tax_inclusive',
        'tax_number',
        'effective_from',
        'effective_to',
        'status',
        'policy_notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_tax_inclusive' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function treatmentLabel(): string
    {
        return TaxTreatment::tryFrom((string) $this->treatment)?->label() ?? __('Standard');
    }

    public function products(): HasMany
    {
        return $this->hasMany(\App\Modules\Catalog\Models\Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
