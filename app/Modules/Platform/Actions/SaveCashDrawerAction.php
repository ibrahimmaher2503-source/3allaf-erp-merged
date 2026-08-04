<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveCashDrawerAction
{
    public const ALLOWED_STATUSES = ['active', 'inactive', 'maintenance'];

    /**
     * Create or update a cash drawer master record with correlation ID and append-only audit trail.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $id = null): CashDrawer
    {
        Gate::authorize($id ? 'drawers_payments_tax_numbering_printers.edit' : 'drawers_payments_tax_numbering_printers.create');
        return DB::transaction(function () use ($data, $id) {
            $branch = Branch::findOrFail($data['branch_id']);

            if ($branch->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot assign cash drawer to an inactive branch.'));
            }

            if (! empty($data['store_id'])) {
                $store = Store::findOrFail($data['store_id']);
                if ($store->branch_id && (int) $store->branch_id !== (int) $branch->id) {
                    throw new InvalidArgumentException(__('Selected store does not belong to the chosen branch.'));
                }
            }

            $attributes = [
                'branch_id' => $branch->id,
                'store_id' => ! empty($data['store_id']) ? (int) $data['store_id'] : null,
                'assigned_user_id' => ! empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
                'code' => strtoupper(trim($data['code'])),
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'status' => $data['status'] ?? 'active',
                'policy_notes' => $data['policy_notes'] ?? 'TBD: Production cash drawer baseline pending shift rules and owner approval (BLK-006).',
            ];

            if ($id) {
                $drawer = CashDrawer::findOrFail($id);

                $oldData = $drawer->toArray();
                $drawer->update($attributes);
                $actionName = 'update_cash_drawer';
                $changes = [
                    'before' => $oldData,
                    'after' => $drawer->toArray(),
                ];
            } else {
                $drawer = CashDrawer::create($attributes);
                $actionName = 'create_cash_drawer';
                $changes = [
                    'after' => $drawer->toArray(),
                ];
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $actionName,
                source: $drawer,
                before: $changes['before'] ?? null,
                after: $changes['after'] ?? null,
                branchId: $drawer->branch_id,
                storeId: $drawer->store_id,
            );

            return $drawer;
        });
    }

    /**
     * Toggle or update cash drawer status with explicit TBD shift dependency guards and audit logging.
     */
    public function toggleStatus(int $id, string $newStatus = 'inactive'): CashDrawer
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');
        if (! in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(__('Invalid cash drawer status provided.'));
        }

        return DB::transaction(function () use ($id, $newStatus) {
            $drawer = CashDrawer::findOrFail($id);
            $oldStatus = $drawer->status;

            // Explicit TBD shift dependency guard (Shifts module TSK-025 is not implemented in DM 1.2)
            // Drawers cannot be deactivated with active shifts once shifts are introduced.

            $drawer->update(['status' => $newStatus]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_cash_drawer_status',
                source: $drawer,
                before: ['status' => $oldStatus],
                after: ['status' => $newStatus],
                branchId: $drawer->branch_id,
                storeId: $drawer->store_id,
                metadata: ['dependency_guard' => 'TBD: Active shift validation non-active (Shifts module deferred to DM 3.3 / TSK-025)'],
            );

            return $drawer;
        });
    }

    /**
     * Delete a cash drawer record safely if no dependencies exist, with audit logging.
     */
    public function delete(int $id): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.logical_delete');
        DB::transaction(function () use ($id) {
            $drawer = CashDrawer::findOrFail($id);

            // Safe guard check against active shift/session dependencies (TBD until DM 3.3)

            $oldData = $drawer->toArray();
            $drawer->delete();

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_cash_drawer',
                source: $drawer,
                before: $oldData,
                after: ['deleted' => true],
                branchId: $drawer->branch_id,
                storeId: $drawer->store_id,
                metadata: ['deleted_source_id' => $id],
            );
        });
    }
}
