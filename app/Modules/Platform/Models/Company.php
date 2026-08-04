<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'legal_name',
        'tax_number',
        'commercial_registration',
        'currency_code',
        'currency_symbol',
        'timezone',
        'locale_default',
        'phone',
        'email',
        'address',
        'status',
        'policy_notes',
    ];

    public function branches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function stores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Store::class);
    }
}
