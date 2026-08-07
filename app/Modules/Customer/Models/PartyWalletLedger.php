<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property string $public_id
 * @property string $entry_type
 */
final class PartyWalletLedger extends Model
{
    protected $table = 'party_wallet_ledger';

    public $timestamps = false;

    protected $fillable = [
        'public_id', 'entry_type', 'amount', 'currency_code', 'source_type', 'source_id',
        'source_line_id', 'idempotency_key', 'reference', 'reason', 'created_by', 'metadata', 'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $entry): void {
            $entry->public_id ??= (string) Str::uuid();
        });
        self::updating(fn (): never => throw new LogicException('Party Wallet ledger entries are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Party Wallet ledger entries are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<PartyWalletLedger>  $query
     * @return Builder<PartyWalletLedger>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
