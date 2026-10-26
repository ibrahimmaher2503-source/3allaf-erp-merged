<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PriceList extends Model
{
    protected static function newFactory(): Factory
    {
        return PriceListFactory::new();
    }

    use HasFactory;

    protected $fillable = ['company_id', 'list_number', 'code', 'name_ar', 'name_en', 'percentage_increase', 'status', 'effective_from', 'effective_to', 'notes', 'created_by', 'updated_by'];

    protected $casts = ['list_number' => 'integer', 'percentage_increase' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<PriceVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PriceVersion::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(ProductPriceOverride::class);
    }

    public function isBase(): bool
    {
        return $this->list_number === 0;
    }

    public function isEffective(?\DateTimeInterface $at = null): bool
    {
        $at = Carbon::instance($at ?? now())->startOfDay();

        return $this->status === 'active' && ($this->effective_from === null || $this->effective_from->lte($at)) && ($this->effective_to === null || $this->effective_to->gte($at));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
