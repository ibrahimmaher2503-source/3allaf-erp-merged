<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveStoreAction
{
    /**
     * Allowed location types.
     */
    public const ALLOWED_TYPES = [
        'selling',
        'warehouse',
        'party',
        'damaged',
        'transit',
    ];

    /**
     * Create or update a store record with correlation ID and audit logging.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $id = null): Store
    {
        Gate::authorize($id ? 'branches_stores.edit' : 'branches_stores.create');
        $type = $data['type'] ?? 'selling';
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(__('Invalid location type specified.'));
        }

        return DB::transaction(function () use ($data, $id, $type) {
            $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' ? (int) $data['branch_id'] : null;
            $branch = $branchId === null ? null : Branch::query()->whereKey($branchId)->where('status', 'active')->first();
            if ($branchId !== null && $branch === null) {
                throw new InvalidArgumentException(__('Select an active branch for this store.'));
            }

            $company = $branch === null
                ? Company::query()->where('status', 'active')->first()
                : Company::query()->whereKey($branch->company_id)->where('status', 'active')->first();
            if ($company === null) {
                throw new InvalidArgumentException(__('Complete active Company Settings before creating stores.'));
            }

            $attributes = [
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'code' => strtoupper(trim($data['code'])),
                'type' => $type,
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'status' => $data['status'] ?? 'active',
                'allows_negative_stock' => (bool) ($data['allows_negative_stock'] ?? false),
                'policy_notes' => isset($data['policy_notes']) && trim((string) $data['policy_notes']) !== '' ? trim((string) $data['policy_notes']) : null,
            ];

            if ($id) {
                $store = Store::findOrFail($id);

                // If deactivating, guard against active selling store mapping
                if ($attributes['status'] === 'inactive' && $store->status === 'active') {
                    if (BranchSellingStore::where('store_id', $store->id)->where('status', 'active')->exists()) {
                        throw new InvalidArgumentException(__('Cannot deactivate store while it is actively mapped to a branch as POS selling store.'));
                    }
                }

                $oldData = $store->toArray();
                $store->update($attributes);
                $actionName = 'update_store';
                $changes = [
                    'before' => $oldData,
                    'after' => $store->toArray(),
                ];
            } else {
                $store = Store::create($attributes);
                $actionName = 'create_store';
                $changes = [
                    'after' => $store->toArray(),
                ];
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $actionName,
                source: $store,
                before: $changes['before'] ?? null,
                after: $changes['after'] ?? null,
                branchId: $store->branch_id,
                storeId: $store->id,
            );

            return $store;
        });
    }

    /**
     * Toggle store status (active / inactive) with dependency checks and audit logging.
     */
    public function toggleStatus(int $id): Store
    {
        Gate::authorize('branches_stores.edit');

        return DB::transaction(function () use ($id) {
            $store = Store::findOrFail($id);
            $newStatus = $store->status === 'active' ? 'inactive' : 'active';

            if ($newStatus === 'inactive') {
                if (BranchSellingStore::where('store_id', $store->id)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate store while it is actively mapped to a branch as POS selling store.'));
                }
            }

            $oldStatus = $store->status;
            $store->update(['status' => $newStatus]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_store_status',
                source: $store,
                before: ['status' => $oldStatus],
                after: ['status' => $newStatus],
                branchId: $store->branch_id,
                storeId: $store->id,
            );

            return $store;
        });
    }

    /**
     * Delete store record safely if no selling mappings exist.
     */
    public function delete(int $id): void
    {
        Gate::authorize('branches_stores.logical_delete');
        DB::transaction(function () use ($id) {
            $store = Store::findOrFail($id);

            if ($store->sellingStoreMappings()->exists()) {
                throw new InvalidArgumentException(__('Cannot delete store with active or historical branch mapping records. Deactivate the record instead.'));
            }

            $oldData = $store->toArray();
            $store->delete();

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_store',
                source: $store,
                before: $oldData,
                after: ['deleted' => true],
                branchId: $store->branch_id,
                storeId: $id,
                metadata: ['deleted_source_id' => $id],
            );
        });
    }

    /** Apply an approved logical delete while preserving approval foreign keys and master history. */
    public function logicalDeleteAfterApproval(int $id): void
    {
        Gate::authorize('branches_stores.logical_delete');

        DB::transaction(function () use ($id): void {
            $store = Store::query()->lockForUpdate()->findOrFail($id);
            if ($store->sellingStoreMappings()->exists()) {
                throw new InvalidArgumentException(__('Cannot delete store while it has active or historical branch mappings.'));
            }

            $before = $store->getAttributes();
            $store->update(['status' => 'inactive']);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_store',
                source: $store,
                before: $before,
                after: ['deleted' => true, 'status' => 'inactive'],
                branchId: $store->branch_id,
                storeId: $store->id,
                metadata: ['logical_delete' => true, 'approval_required' => true],
            );
        });
    }
}
