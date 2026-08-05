<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ToggleSupplierStatusAction
{
    public function execute(int $id): Supplier
    {
        Gate::authorize('suppliers.edit');

        return DB::transaction(function () use ($id): Supplier {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($id);
            $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';

            if ($supplier->status === 'active' && $newStatus === 'inactive') {
                if ($supplier->productSuppliers()->where('is_preferred', true)->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate a supplier that is set as preferred for active products.'));
                }
            }

            $before = $supplier->only(['code', 'name_ar', 'name_en', 'status', 'lock_version']);

            $supplier->update([
                'status' => $newStatus,
                'updated_by' => Auth::id(),
                'lock_version' => $supplier->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $newStatus === 'active' ? 'activate_supplier' : 'deactivate_supplier',
                source: $supplier,
                before: $before,
                after: $supplier->fresh()->only(['code', 'name_ar', 'name_en', 'status', 'lock_version']),
            );

            return $supplier->fresh();
        });
    }
}
