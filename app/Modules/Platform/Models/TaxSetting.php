<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'rate',
        'is_tax_inclusive',
        'tax_number',
        'effective_from',
        'effective_to',
        'status',
        'policy_notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];
}
