<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterConfiguration extends Model
{
    protected $fillable = [
        'name',
        'printer_type',
        'paper_size',
        'template_name',
        'connection_type',
        'ip_address',
        'port',
        'is_default',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'port' => 'integer',
    ];
}
