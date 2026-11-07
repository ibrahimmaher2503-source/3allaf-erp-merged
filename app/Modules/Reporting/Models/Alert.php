<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\OperationalCompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Alert extends Model
{
    protected $fillable = ['alert_key', 'alert_type', 'severity', 'title', 'description', 'source_type', 'source_id', 'branch_id', 'store_id', 'assigned_to', 'status', 'acknowledged_by', 'acknowledged_at', 'resolved_at', 'due_at', 'metadata'];

    protected $casts = ['metadata' => 'array', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime', 'due_at' => 'datetime'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canBypassApproval()) {
            return app(OperationalCompanyScope::class)->apply($query, $user, 'branch_id', 'store_id');
        }

        if ($user->hasGlobalApplicationAccess()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
