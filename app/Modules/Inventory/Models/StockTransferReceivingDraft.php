<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferReceivingDraft extends Model
{
    protected $fillable = ['stock_transfer_id', 'destination_store_id', 'received_quantities', 'difference_type', 'difference_reason', 'finalized_at', 'finalized_by'];

    protected $casts = ['received_quantities' => 'array', 'finalized_at' => 'datetime'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }
}
