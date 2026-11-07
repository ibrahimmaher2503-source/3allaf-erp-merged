<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

            $branchQuery = Branch::query();
            if (auth()->check()) {
                $branchQuery->visibleTo(auth()->user());
            }
            $branch = $id ? $branchQuery->lockForUpdate()->findOrFail($id) : null;
            $attributes = [
                'company_id' => $company->id,
                'default_price_list_id' => filled($data['default_price_list_id'] ?? null) ? (int) $data['default_price_list_id'] : null,
                'code' => strtoupper(trim($data['code'])),
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'phone' => filled($data['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['phone']) : null,
                'email' => isset($data['email']) && $data['email'] !== '' ? trim($data['email']) : null,
                'address' => isset($data['address']) && $data['address'] !== '' ? trim($data['address']) : null,
                'timezone' => $data['timezone'] ?? $branch?->timezone ?? $company->timezone ?? 'UTC',
                'status' => $data['status'] ?? 'active',
                'policy_notes' => isset($data['policy_notes']) && trim((string) $data['policy_notes']) !== '' ? trim((string) $data['policy_notes']) : null,
            ];
            if ($attributes['default_price_list_id'] !== null && ! PriceList::query()->where('company_id', $company->id)->whereKey($attributes['default_price_list_id'])->where('status', 'active')->exists()) {
                throw new InvalidArgumentException(__('Select an active price list belonging to this company.'));
            }

            if ($id) {
                if ($attributes['status'] === 'inactive' && $branch->status === 'active') {
                    $this->assertDeactivationSafe($branch);
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
            $branchQuery = Branch::query();
            if (auth()->check()) {
                $branchQuery->visibleTo(auth()->user());
            }
            $branch = $branchQuery->lockForUpdate()->findOrFail($id);
            $newStatus = $branch->status === 'active' ? 'inactive' : 'active';

            if ($newStatus === 'inactive') {
                $this->assertDeactivationSafe($branch);
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

    private function assertDeactivationSafe(Branch $branch): void
    {
        $hasActiveStore = $branch->stores()
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['stores.id']) !== null;
        $hasActiveSellingStoreMapping = $branch->sellingStoreMappings()
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['branch_selling_stores.id']) !== null;

        if ($hasActiveStore || $hasActiveSellingStoreMapping) {
            throw ValidationException::withMessages([
                'branch' => __('Cannot deactivate branch while it has active stores or an active selling store mapping.'),
            ]);
        }
    }
}
