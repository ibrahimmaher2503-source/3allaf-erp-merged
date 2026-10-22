<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class DashboardApprovalPreview
{
    /** @return Collection<int, ApprovalRecord> */
    public function for(User $user): Collection
    {
        if (! Gate::forUser($user)->allows('view-approval-inbox')) {
            return collect();
        }

        $query = ApprovalRecord::query()
            ->visibleTo($user)
            ->where('approval_state', ApprovalState::Pending->value)
            ->with('requester:id,name');

        if (! $user->canBypassApproval()) {
            $permissionCodes = array_keys(app(RequestPermissionLookup::class)->for($user));
            $defaultApprovalTypes = collect($permissionCodes)
                ->filter(fn (string $permission): bool => str_ends_with($permission, '.approve'))
                ->map(fn (string $permission): string => substr($permission, 0, -8))
                ->all();
            $query->where('requester_id', '!=', $user->id)
                ->where(function (Builder $visible) use ($permissionCodes, $defaultApprovalTypes): void {
                    $visible->whereIn('decision_permission', $permissionCodes)
                        ->orWhere(fn (Builder $defaults): Builder => $defaults
                            ->whereNull('decision_permission')
                            ->whereIn('source_type', $defaultApprovalTypes));
                });
        }

        return $query->latest('requested_at')->latest('id')->limit(10)->get()
            ->filter(fn (ApprovalRecord $approval): bool => Gate::forUser($user)->allows('decide', $approval))
            ->values();
    }
}
