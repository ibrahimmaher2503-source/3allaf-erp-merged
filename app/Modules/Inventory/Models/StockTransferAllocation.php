<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockTransferAllocation extends Model
{
    protected $fillable = ['stock_transfer_line_id', 'destination_store_id', 'quantity', 'quantity_received'];

    protected $casts = ['quantity' => 'decimal:6', 'quantity_received' => 'decimal:6'];

    /** @return BelongsTo<StockTransferLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class, 'stock_transfer_line_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }
}
