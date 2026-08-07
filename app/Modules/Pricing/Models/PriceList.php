<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $fillable = ['company_id', 'code', 'name_ar', 'name_en', 'status', 'notes', 'created_by', 'updated_by'];

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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
