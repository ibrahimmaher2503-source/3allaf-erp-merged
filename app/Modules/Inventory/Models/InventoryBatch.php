<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class InventoryBatch extends Model
{
    protected $fillable = ['product_id', 'batch_number', 'production_date', 'expiry_date', 'status'];

    protected $casts = ['production_date' => 'immutable_date', 'expiry_date' => 'immutable_date'];

    protected static function booted(): void
    {
        self::saving(function (self $batch): void {
            $batch->batch_number = trim((string) $batch->batch_number);
            if ($batch->batch_number === '') {
                throw new InvalidArgumentException(__('A batch number is required.'));
            }
            if ($batch->production_date !== null && $batch->expiry_date !== null && $batch->production_date->isAfter($batch->expiry_date)) {
                throw new InvalidArgumentException(__('Batch production date cannot be after its expiry date.'));
            }
        });
    }

    /**
     * Owner decision 2026-11-25: POS stock for a batch- or expiry-tracked product comes from the
     * active batch with the nearest unexpired expiry date (first expiry, first out). Returns null
     * for an untracked product, which posts without a batch exactly as before.
     */
    public static function firstExpiringIdFor(int $productId): ?int
    {
        $product = Product::query()->select(['id', 'track_batches', 'track_expiry'])->findOrFail($productId);
        if (! $product->track_batches && ! $product->track_expiry) {
            return null;
        }
        $batchId = self::query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->when($product->track_expiry, fn ($query) => $query->whereNotNull('expiry_date'))
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->value('id');
        if ($batchId === null) {
            throw new InvalidArgumentException(__('No active, unexpired batch is available for this product.'));
        }

        return (int) $batchId;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }
}
