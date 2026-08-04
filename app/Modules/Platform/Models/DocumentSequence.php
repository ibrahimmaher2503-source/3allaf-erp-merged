<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'prefix',
        'suffix',
        'padding_length',
        'next_value',
        'reset_rule',
        'status',
        'lock_version',
        'policy_notes',
    ];

    protected $casts = [
        'padding_length' => 'integer',
        'next_value' => 'integer',
        'lock_version' => 'integer',
    ];
}
