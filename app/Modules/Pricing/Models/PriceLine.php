<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceLine extends Model
{
    protected $fillable = ['price_version_id', 'product_id', 'store_id', 'branch_id', 'amount', 'reference_amount', 'open_price_allowed', 'active_key', 'notes'];

    protected $casts = ['amount' => 'decimal:3', 'reference_amount' => 'decimal:3', 'open_price_allowed' => 'boolean'];

    /** @return BelongsTo<PriceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceVersion::class, 'price_version_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
