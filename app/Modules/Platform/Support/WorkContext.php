<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WorkContext
{
    public const SESSION_KEY = 'ui.work_store_id';

    public const BRANCH_SESSION_KEY = 'ui.work_branch_id';

    /** @var array<int, Collection<int, Store>> */
    private array $requestStores = [];

    /** @return Collection<int, Store> */
    public function stores(User $actor): Collection
    {
        return $this->requestStores[$actor->id] ??= Store::query()
            ->with('company:id,name_ar,name_en')
            ->visibleTo($actor)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'company_id', 'branch_id', 'type', 'code', 'name_ar', 'name_en']);
    }

    public function selected(User $actor): ?Store
    {
        $storeId = session(self::SESSION_KEY);
        if (! is_numeric($storeId)) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $store = $this->stores($actor)->firstWhere('id', (int) $storeId);
        if ($store === null) {
            session()->forget(self::SESSION_KEY);
        }

        return $store;
    }

    public function select(User $actor, ?int $storeId): void
    {
        if ($storeId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        if (! Store::query()->visibleTo($actor)->where('status', 'active')->whereKey($storeId)->exists()) {
            throw ValidationException::withMessages(['store_id' => __('The selected work location is unavailable.')]);
        }

        session([self::SESSION_KEY => $storeId]);
        session()->forget(self::BRANCH_SESSION_KEY);
    }

    public function id(User $actor): ?int
    {
        return $this->selected($actor)?->id;
    }

    /** @return Collection<int, Branch> */
    public function branches(User $actor): Collection
    {
        $storeBranchIds = $this->stores($actor)->pluck('branch_id')->filter()->unique()->values();

        return Branch::query()->with('company:id,name_ar,name_en')->where('status', 'active')
            ->where(fn ($query) => $query->whereIn('id', Branch::query()->visibleTo($actor)->select('id'))
                ->orWhereIn('id', $storeBranchIds))
            ->orderBy('code')->get();
    }

    public function branch(User $actor): ?Branch
    {
        $branchId = session(self::BRANCH_SESSION_KEY);
        if (! is_numeric($branchId)) {
            return null;
        }
        $branch = $this->branches($actor)->firstWhere('id', (int) $branchId);
        if ($branch === null) {
            session()->forget(self::BRANCH_SESSION_KEY);
        }

        return $branch;
    }

    public function branchId(User $actor): ?int
    {
        return $this->branch($actor)?->id ?? $this->selected($actor)?->branch_id;
    }

    public function selectBranch(User $actor, ?int $branchId): void
    {
        if ($branchId === null) {
            session()->forget(self::BRANCH_SESSION_KEY);
            session()->forget(self::SESSION_KEY);

            return;
        }
        if (! $this->branches($actor)->contains('id', $branchId)) {
            throw ValidationException::withMessages(['branch_id' => __('The selected branch is unavailable.')]);
        }
        session([self::BRANCH_SESSION_KEY => $branchId]);
        session()->forget(self::SESSION_KEY);
    }
}
