<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class ScaleConfiguration extends Model
{
    protected $fillable = ['company_id', 'store_id', 'name', 'ip_address', 'port', 'status', 'pos_receive_enabled', 'barcode_settings'];

    protected $casts = ['port' => 'integer', 'pos_receive_enabled' => 'boolean', 'barcode_settings' => 'array'];
}
