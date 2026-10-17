<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class SupplierPayableEntry extends Model
{
    protected $fillable = ['company_id', 'supplier_id', 'store_id', 'source_type', 'source_id', 'amount', 'posted_at', 'created_by'];

    protected $casts = ['amount' => 'decimal:4', 'posted_at' => 'datetime'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Posted supplier payable entries are immutable.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Posted supplier payable entries are immutable.');
        });
    }
}
