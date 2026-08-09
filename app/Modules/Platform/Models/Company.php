<?php

namespace App\Modules\Platform\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return CompanyFactory::new();
    }

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

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
