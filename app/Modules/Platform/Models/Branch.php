<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name_ar',
        'name_en',
        'phone',
        'email',
        'address',
        'timezone',
        'status',
        'policy_notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function sellingStoreMappings(): HasMany
    {
        return $this->hasMany(BranchSellingStore::class);
    }

    public function activeSellingStoreMapping(): HasOne
    {
        return $this->hasOne(BranchSellingStore::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->is_super_admin
            ? $query
            : $query->whereIn('id', $user->branchScopes()->where('status', 'active')->select('branch_id'));
    }
}
