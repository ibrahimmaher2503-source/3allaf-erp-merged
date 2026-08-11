<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class Customer extends Model
{
    private bool $namedMutation = false;

    protected $fillable = [
        'public_id', 'phone_normalized', 'phone_display', 'name_ar', 'name_en', 'email',
        'secondary_phone', 'address_ar', 'address_en', 'status', 'merged_into_id',
        'created_by', 'updated_by', 'created_branch_id', 'created_store_id', 'idempotency_key', 'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $customer): void {
            $customer->public_id ??= (string) Str::uuid();
        });
        self::updating(function (self $customer): void {
            if ($customer->getOriginal('status') === 'merged' && ! $customer->namedMutation) {
                throw new LogicException('Merged customer profiles are immutable.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Customer history is preserved; use a named logical state action.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Branch, $this> */
    public function createdBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'created_branch_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function createdStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'created_store_id');
    }

    /** @return HasMany<CustomerScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(CustomerScope::class);
    }

    /** @return HasMany<CustomerConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    /** @return HasMany<CustomerChild, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(CustomerChild::class);
    }

    /** @return HasMany<LoyaltyLedger, $this> */
    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(LoyaltyLedger::class);
    }

    /** @return HasMany<LoyaltyAdjustment, $this> */
    public function loyaltyAdjustments(): HasMany
    {
        return $this->hasMany(LoyaltyAdjustment::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<ProductWalletLedger, $this> */
    public function productWalletLedger(): HasMany
    {
        return $this->hasMany(ProductWalletLedger::class);
    }

    /** @return HasMany<PartyWalletLedger, $this> */
    public function partyWalletLedger(): HasMany
    {
        return $this->hasMany(PartyWalletLedger::class);
    }

    /** @return HasMany<ProductWalletAdjustment, $this> */
    public function productWalletAdjustments(): HasMany
    {
        return $this->hasMany(ProductWalletAdjustment::class);
    }

    /** @return HasMany<PartyWalletAdjustment, $this> */
    public function partyWalletAdjustments(): HasMany
    {
        return $this->hasMany(PartyWalletAdjustment::class);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $branchIds = $user->branchScopes()->where('status', 'active')->select('branch_id');
        $storeIds = $user->storeScopes()->where('status', 'active')->select('store_id');

        return $query->whereHas('scopes', function (Builder $scope) use ($branchIds, $storeIds): void {
            $scope->where(function (Builder $visible) use ($branchIds, $storeIds): void {
                $visible->whereIn('branch_id', $branchIds)
                    ->orWhereIn('store_id', $storeIds);
            });
        });
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleFrom(Builder $query, User $user, int $branchId, ?int $storeId = null): Builder
    {
        if ($user->is_super_admin) {
            return $query->whereHas('scopes', function (Builder $scope) use ($branchId, $storeId): void {
                $scope->where(function (Builder $visible) use ($branchId, $storeId): void {
                    $visible->where('branch_id', $branchId)->orWhere('store_id', $storeId);
                });
            });
        }

        abort_unless($user->canAccessBranch($branchId) || ($storeId !== null && $user->canAccessStore($storeId)), 403);

        return $query->whereHas('scopes', function (Builder $scope) use ($branchId, $storeId): void {
            $scope->where(function (Builder $visible) use ($branchId, $storeId): void {
                $visible->where('branch_id', $branchId)->orWhere('store_id', $storeId);
            });
        });
    }

    /** @param array<string, mixed> $attributes */
    public function mutateMaster(array $attributes): void
    {
        $this->namedMutation = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedMutation = false;
        }
    }
}
