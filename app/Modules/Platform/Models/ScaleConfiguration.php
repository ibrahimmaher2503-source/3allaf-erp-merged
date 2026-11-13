<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class ScaleConfiguration extends Model
{
    protected $fillable = ['company_id', 'store_id', 'name', 'ip_address', 'port', 'status', 'barcode_settings'];

    protected $casts = ['port' => 'integer', 'barcode_settings' => 'array'];
}
