<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'type',
        'requires_evidence',
        'offline_eligible',
        'status',
        'policy_notes',
    ];

    protected $casts = [
        'requires_evidence' => 'boolean',
        'offline_eligible' => 'boolean',
    ];
}
