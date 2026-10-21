<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;

final class OperationalCompanyScope
{
    public function apply(Builder $query, User $actor, string $branchColumn = 'branch_id', string $storeColumn = 'store_id'): Builder
    {
        $companyIds = $actor->operationalCompanyIds();
        $branchIds = Branch::query()->whereIn('company_id', $companyIds)->select('id');
        $storeIds = Store::query()->whereIn('company_id', $companyIds)->select('id');

        return $query
            ->where(fn (Builder $branch): Builder => $branch->whereNull($branchColumn)->orWhereIn($branchColumn, $branchIds))
            ->where(fn (Builder $store): Builder => $store->whereNull($storeColumn)->orWhereIn($storeColumn, $storeIds))
            ->where(fn (Builder $location): Builder => $location->whereNotNull($branchColumn)->orWhereNotNull($storeColumn));
    }
}
