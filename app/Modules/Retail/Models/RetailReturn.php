<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RetailReturn extends Model
{
    protected $fillable = ['branch_id', 'store_id', 'cashier_id', 'approved_by', 'customer_id', 'source_sale_id', 'source_gift_receipt_id', 'approval_record_id', 'return_number', 'status', 'settlement_type', 'reason', 'eligible_value', 'settlement_value', 'currency_code', 'idempotency_key', 'payload_hash', 'submitted_at', 'approved_at', 'completed_at', 'lock_version'];
    protected $casts = ['eligible_value' => 'decimal:2', 'settlement_value' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function sourceSale(): BelongsTo { return $this->belongsTo(Sale::class, 'source_sale_id'); }
    public function sourceGiftReceipt(): BelongsTo { return $this->belongsTo(GiftReceipt::class, 'source_gift_receipt_id'); }
    public function lines(): HasMany { return $this->hasMany(RetailReturnLine::class); }
    public function exchange(): HasOne { return $this->hasOne(Exchange::class); }
    public function settlements(): HasMany { return $this->hasMany(RetailReturnSettlement::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
