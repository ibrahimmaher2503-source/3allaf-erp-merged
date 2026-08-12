<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveBranchAction
{
    /**
     * Create or update a branch record with correlation ID and append-only audit trail.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $id = null): Branch
    {
        Gate::authorize($id ? 'branches_stores.edit' : 'branches_stores.create');

        return DB::transaction(function () use ($data, $id) {
            $company = Company::query()->where('status', 'active')->first();
            if ($company === null) {
                throw new InvalidArgumentException(__('Complete active Company Settings before creating branches.'));
            }

            $attributes = [
                'company_id' => $company->id,
                'code' => strtoupper(trim($data['code'])),
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null,
                'email' => isset($data['email']) && $data['email'] !== '' ? trim($data['email']) : null,
                'address' => isset($data['address']) && $data['address'] !== '' ? trim($data['address']) : null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => $data['status'] ?? 'active',
                'policy_notes' => isset($data['policy_notes']) && trim((string) $data['policy_notes']) !== '' ? trim((string) $data['policy_notes']) : null,
            ];

            if ($id) {
                $branch = Branch::findOrFail($id);

                if ($attributes['status'] === 'inactive' && $branch->status === 'active') {
                    if ($branch->stores()->where('status', 'active')->exists() || $branch->activeSellingStoreMapping()->exists()) {
                        throw new InvalidArgumentException(__('Cannot deactivate branch while it has active stores or an active selling store mapping.'));
                    }
                }

                $oldData = $branch->toArray();
                $branch->update($attributes);
                $actionName = 'update_branch';
                $changes = [
                    'before' => $oldData,
                    'after' => $branch->toArray(),
                ];
            } else {
                $branch = Branch::create($attributes);
                $actionName = 'create_branch';
                $changes = [
                    'after' => $branch->toArray(),
                ];
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $actionName,
                source: $branch,
                before: $changes['before'] ?? null,
                after: $changes['after'] ?? null,
                branchId: $branch->id,
            );

            return $branch;
        });
    }

    /**
     * Toggle branch status (active / inactive) with dependency checks and audit logging.
     */
    public function toggleStatus(int $id): Branch
    {
        Gate::authorize('branches_stores.edit');

        return DB::transaction(function () use ($id) {
            $branch = Branch::findOrFail($id);
            $newStatus = $branch->status === 'active' ? 'inactive' : 'active';

            if ($newStatus === 'inactive') {
                if ($branch->stores()->where('status', 'active')->exists() || $branch->activeSellingStoreMapping()->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate branch while it has active stores or an active selling store mapping.'));
                }
            }

            $oldStatus = $branch->status;
            $branch->update(['status' => $newStatus]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_branch_status',
                source: $branch,
                before: ['status' => $oldStatus],
                after: ['status' => $newStatus],
                branchId: $branch->id,
            );

            return $branch;
        });
    }

    /**
     * Delete a branch record safely if no dependencies exist, with audit logging.
     */
    public function delete(int $id): void
    {
        Gate::authorize('branches_stores.logical_delete');
        DB::transaction(function () use ($id) {
            $branch = Branch::findOrFail($id);

            if ($branch->stores()->exists() || $branch->sellingStoreMappings()->exists()) {
                throw new InvalidArgumentException(__('Cannot delete branch with existing stores or mapping history. Deactivate the record instead.'));
            }

            $oldData = $branch->toArray();
            $branch->delete();

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_branch',
                source: $branch,
                before: $oldData,
                after: ['deleted' => true],
                branchId: $id,
                metadata: ['deleted_source_id' => $id],
            );
        });
    }

    /** Apply an approved logical delete while preserving approval foreign keys and master history. */
    public function logicalDeleteAfterApproval(int $id): void
    {
        Gate::authorize('branches_stores.logical_delete');

        DB::transaction(function () use ($id): void {
            $branch = Branch::query()->lockForUpdate()->findOrFail($id);
            if ($branch->stores()->where('status', 'active')->exists() || $branch->activeSellingStoreMapping()->exists()) {
                throw new InvalidArgumentException(__('Cannot delete branch while it has active stores or an active selling store mapping.'));
            }

            $before = $branch->getAttributes();
            $branch->update(['status' => 'inactive']);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_branch',
                source: $branch,
                before: $before,
                after: ['deleted' => true, 'status' => 'inactive'],
                branchId: $branch->id,
                metadata: ['logical_delete' => true, 'approval_required' => true],
            );
        });
    }
}
