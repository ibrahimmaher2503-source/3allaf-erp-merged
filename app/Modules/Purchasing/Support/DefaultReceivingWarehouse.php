<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Support;

use App\Models\User;
use App\Modules\Platform\Models\Store;

final class DefaultReceivingWarehouse
{
    public function resolve(User $actor, ?int $companyId = null, ?int $branchId = null): ?Store
    {
        $query = Store::query()
            ->visibleTo($actor)
            ->where('status', 'active')
            ->where('type', 'warehouse')
            ->when($companyId !== null, fn ($builder) => $builder->where('company_id', $companyId))
            ->when($branchId !== null, fn ($builder) => $builder->where('branch_id', $branchId))
            ->orderBy('id')
            ->limit(2);

        $warehouses = $query->get();

        // There is no main-warehouse flag in the approved schema. A sole
        // eligible warehouse is unambiguous; multiple choices stay explicit.
        return $warehouses->count() === 1 ? $warehouses->first() : null;
    }
}
