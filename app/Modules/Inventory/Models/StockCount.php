<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockCount extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['count_number', 'count_type', 'scope_type', 'branch_id', 'store_id', 'category_id', 'supplier_id', 'status', 'reference_at', 'submitted_at', 'reconciled_at', 'created_by', 'assigned_to', 'approved_by', 'idempotency_key', 'lock_version', 'notes'];

    protected $casts = ['reference_at' => 'datetime', 'submitted_at' => 'datetime', 'reconciled_at' => 'datetime', 'lock_version' => 'integer'];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
