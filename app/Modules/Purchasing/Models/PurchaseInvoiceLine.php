<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseInvoiceLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = [
        'purchase_invoice_id', 'purchase_order_line_id', 'product_id', 'quantity', 'original_quantity', 'quantity_received',
        'unit_cost', 'base_consumer_price', 'previous_product_cost', 'previous_base_consumer_price', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_code',
        'tax_inclusive', 'tax_amount', 'subtotal', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'original_quantity' => 'decimal:6',
        'quantity_received' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'base_consumer_price' => 'decimal:3',
        'previous_product_cost' => 'decimal:4',
        'previous_base_consumer_price' => 'decimal:3',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_inclusive' => 'boolean',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $line): void {
            $line->original_quantity ??= $line->quantity;
        });
        self::updating(function (self $line): void {
            if ($line->getRawOriginal('original_quantity') !== null && $line->isDirty('original_quantity')) {
                throw new \LogicException('The original invoice quantity is immutable.');
            }
        });
    }

    public function shortageQuantity(): string
    {
        return bccomp((string) ($this->original_quantity ?? $this->quantity), (string) $this->quantity_received, 6) > 0
            ? bcsub((string) ($this->original_quantity ?? $this->quantity), (string) $this->quantity_received, 6)
            : '0.000000';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithShortage(Builder $query): Builder
    {
        return $query->whereNotNull('original_quantity')->whereColumn('original_quantity', '>', 'quantity_received')
            ->whereHas('invoice', fn ($invoice) => $invoice->where('status', 'approved'));
    }

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function supplierReturnLines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class, 'purchase_invoice_line_id');
    }

    /** @return HasMany<PurchaseInvoiceDistribution, $this> */
    public function distributions(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceDistribution::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->invoice()->first();
    }
}
