<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $blockedMutation = null;

        try {
            return DB::transaction(function () use ($data, $id, &$blockedMutation) {
                $user = auth()->user();
                $branch = Branch::query()
                    ->visibleTo($user)
                    ->where('status', 'active')
                    ->find($data['branch_id']);

                if ($branch === null) {
                    throw new InvalidArgumentException(__('The selected branch is an inactive branch, does not exist, or is outside your authorized scope.'));
                }

                $status = $data['status'] ?? 'active';
                $store = null;
                if (! empty($data['store_id'])) {
                    $store = Store::query()->find((int) $data['store_id']);
                    if ($store === null) {
                        throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
                    }
                    if ((int) $store->branch_id !== (int) $branch->id) {
                        throw new InvalidArgumentException(__('Selected store does not belong to the chosen branch.'));
                    }
                    if ((int) $store->company_id !== (int) $branch->company_id) {
                        throw new InvalidArgumentException(__('Selected store does not belong to the chosen company.'));
                    }
                    if (! $user->hasGlobalApplicationAccess() && ! $store->visibleTo($user)->whereKey($store->getKey())->exists()) {
                        throw new InvalidArgumentException(__('The selected POS selling location is outside your authorized scope.'));
                    }
                }

                if ($status === 'active') {
                    $this->assertActiveSellingStore($store);

                    // The drawer's explicit selling outlet is authoritative. A
                    // historical branch-wide primary mapping is never a fallback.
                }

                $assignedUserId = $data['assigned_user_id'] ?? null;
                if ($assignedUserId !== null && $assignedUserId !== '') {
                    if ((! is_int($assignedUserId) && (! is_string($assignedUserId) || ctype_digit($assignedUserId) !== true))
                        || (int) $assignedUserId < 1) {
                        throw ValidationException::withMessages([
                            'assigned_user_id' => __('The selected cashier must be active and assigned to this branch or sales outlet.'),
                        ]);
                    }

                    $assignedCashier = User::query()
                        ->assignableToCashDrawer((int) $branch->id, $store?->id)
                        ->sharedLock()
                        ->find((int) $assignedUserId);

                    if ($assignedCashier === null) {
                        throw ValidationException::withMessages([
                            'assigned_user_id' => __('The selected cashier must be active and assigned to this branch or sales outlet.'),
                        ]);
                    }

                    $assignedUserId = (int) $assignedCashier->id;
                } else {
                    $assignedUserId = null;
                }

                $attributes = [
                    // Prefer the selected store as the authoritative owner. The
                    // model repeats this derivation so Eloquent seed/update paths
                    // cannot persist an independently supplied company ID.
                    'company_id' => $store?->company_id ?? $branch->company_id,
                    'branch_id' => $branch->id,
                    'store_id' => $store?->id,
                    'assigned_user_id' => $assignedUserId,
                    'code' => strtoupper(trim($data['code'])),
                    'name_ar' => trim($data['name_ar']),
                    'name_en' => trim($data['name_en']),
                    'status' => $status,
                    'policy_notes' => $data['policy_notes'] ?? 'TBD: Production cash drawer baseline pending shift rules and owner approval (BLK-006).',
                ];

                if ($id) {
                    $drawerQuery = CashDrawer::query()->lockForUpdate();
                    if ($user !== null) {
                        $drawerQuery->visibleTo($user);
                    }
                    $drawer = $drawerQuery->findOrFail($id);

                    $expectedLockVersion = $this->requirePositiveLockVersion($data['lock_version'] ?? null);
                    if ($expectedLockVersion !== (int) $drawer->lock_version) {
                        throw ValidationException::withMessages(['lock_version' => __('This cash drawer changed in another session. Reload it before saving.')]);
                    }

                    $assignmentChanged = (int) $drawer->branch_id !== (int) $attributes['branch_id']
                        || (int) $drawer->store_id !== (int) $attributes['store_id'];
                    $statusChangedToNonActive = $drawer->status !== $attributes['status']
                        && $attributes['status'] !== 'active';
                    if ($assignmentChanged || $statusChangedToNonActive) {
                        $blockedMutation = $this->blockedMutationContext(
                            drawer: $drawer,
                            operation: $assignmentChanged ? 'reassign' : 'deactivate',
                            requested: [
                                'branch_id' => $attributes['branch_id'],
                                'store_id' => $attributes['store_id'],
                                'status' => $attributes['status'],
                            ],
                        );
                        $this->assertNoActiveShift($drawer);
                    }

                    $oldData = $drawer->toArray();
                    $drawer->fill($attributes);
                    $drawer->lock_version = $expectedLockVersion + 1;
                    $drawer->save();
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
        } catch (InvalidArgumentException $exception) {
            if ($blockedMutation !== null) {
                $this->recordBlockedMutation($blockedMutation);
            }

            throw $exception;
        }
    }

    /**
     * Toggle or update cash drawer status with explicit TBD shift dependency guards and audit logging.
     */
    public function toggleStatus(int $id, string $newStatus = 'inactive', mixed $expectedLockVersion = null): CashDrawer
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');
        if (! in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(__('Invalid cash drawer status provided.'));
        }

        $blockedMutation = null;

        try {
            return DB::transaction(function () use ($id, $newStatus, $expectedLockVersion, &$blockedMutation) {
                $user = auth()->user();
                $drawerSnapshot = null;
                $branch = null;
                $store = null;

                if ($newStatus === 'active') {
                    $snapshotQuery = CashDrawer::query();
                    if ($user !== null) {
                        $snapshotQuery->visibleTo($user);
                    }
                    $drawerSnapshot = $snapshotQuery->findOrFail($id);

                    $branch = Branch::query()->lockForUpdate()->find($drawerSnapshot->branch_id);
                    $storeQuery = Store::query();
                    if ($user !== null) {
                        $storeQuery->visibleTo($user);
                    }
                    $store = $drawerSnapshot->store_id === null
                        ? null
                        : $storeQuery->lockForUpdate()->find((int) $drawerSnapshot->store_id);
                }

                $drawerQuery = CashDrawer::query()->lockForUpdate();
                if ($user !== null) {
                    $drawerQuery->visibleTo($user);
                }
                $drawer = $drawerQuery->findOrFail($id);
                $version = $this->requirePositiveLockVersion($expectedLockVersion);
                if ($version !== (int) $drawer->lock_version) {
                    throw ValidationException::withMessages(['lock_version' => __('This cash drawer changed in another session. Reload it before saving.')]);
                }

                if ($newStatus === 'active') {
                    if ((int) $drawer->branch_id !== (int) $drawerSnapshot->branch_id
                        || (int) $drawer->store_id !== (int) $drawerSnapshot->store_id) {
                        throw ValidationException::withMessages(['lock_version' => __('This cash drawer changed in another session. Reload it before saving.')]);
                    }
                    if ($branch === null || $branch->status !== 'active') {
                        throw new InvalidArgumentException(__('The selected branch is an inactive branch, does not exist, or is outside your authorized scope.'));
                    }
                    if ($drawer->store_id !== null && $store === null) {
                        throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
                    }
                    if ($store !== null
                        && ((int) $store->branch_id !== (int) $drawer->branch_id
                            || (int) $store->company_id !== (int) $branch->company_id)) {
                        throw new InvalidArgumentException(__('The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.'));
                    }

                    $this->assertActiveSellingStore($store);
                }

                $oldStatus = $drawer->status;

                if ($newStatus !== 'active') {
                    $blockedMutation = $this->blockedMutationContext(
                        drawer: $drawer,
                        operation: 'deactivate',
                        requested: ['status' => $newStatus],
                    );
                    $this->assertNoActiveShift($drawer);
                }

                $drawer->status = $newStatus;
                $drawer->lock_version = $version + 1;
                $drawer->save();

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: 'toggle_cash_drawer_status',
                    source: $drawer,
                    before: ['status' => $oldStatus],
                    after: ['status' => $newStatus],
                    branchId: $drawer->branch_id,
                    storeId: $drawer->store_id,
                    metadata: ['dependency_guard' => 'Active POS shift dependency enforced server-side'],
                );

                return $drawer;
            });
        } catch (InvalidArgumentException $exception) {
            if ($blockedMutation !== null) {
                $this->recordBlockedMutation($blockedMutation);
            }

            throw $exception;
        }
    }

    private function assertNoActiveShift(CashDrawer $drawer): void
    {
        $hasActiveShift = PosShift::query()
            ->active()
            ->where('cash_drawer_id', $drawer->getKey())
            ->lockForUpdate()
            ->exists();

        if ($hasActiveShift) {
            throw new InvalidArgumentException(__('Cannot deactivate or reassign a cash drawer while it has an active POS shift. Close the shift before trying again.'));
        }
    }

    private function requirePositiveLockVersion(mixed $value): int
    {
        if (! is_int($value) && (! is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1)) {
            throw ValidationException::withMessages(['lock_version' => __('This cash drawer changed in another session. Reload it before saving.')]);
        }

        $version = (int) $value;

        if ($version < 1) {
            throw ValidationException::withMessages(['lock_version' => __('This cash drawer changed in another session. Reload it before saving.')]);
        }

        return $version;
    }

    private function assertActiveSellingStore(?Store $store): void
    {
        if ($store === null) {
            throw new InvalidArgumentException(__('An active cash drawer requires a POS selling location / stock source. Assign the branch\'s active POS location before saving.'));
        }

        if ($store->status !== 'active' || $store->type !== 'selling') {
            throw new InvalidArgumentException(__('The POS selling location / stock source must be an active selling location.'));
        }
    }

    /**
     * @param  array<string, mixed>  $requested
     * @return array<string, mixed>
     */
    private function blockedMutationContext(CashDrawer $drawer, string $operation, array $requested): array
    {
        return [
            'drawer_id' => $drawer->getKey(),
            'operation' => $operation,
            'branch_id' => $drawer->branch_id,
            'store_id' => $drawer->store_id,
            'before' => [
                'status' => $drawer->status,
                'branch_id' => $drawer->branch_id,
                'store_id' => $drawer->store_id,
            ],
            'requested' => $requested,
        ];
    }

    /**
     * Persist a blocked state transition after the business transaction has rolled back.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordBlockedMutation(array $context): void
    {
        app(RecordAuditEvent::class)->execute(
            category: 'master_data',
            event: 'cash_drawer_mutation_blocked',
            source: CashDrawer::class,
            before: $context['before'],
            after: $context['before'],
            branchId: (int) $context['branch_id'],
            storeId: $context['store_id'] === null ? null : (int) $context['store_id'],
            reasonCode: 'active_shift',
            reasonText: __('Cannot deactivate or reassign a cash drawer while it has an active POS shift. Close the shift before trying again.'),
            metadata: [
                'outcome' => 'blocked',
                'operation' => $context['operation'],
                'dependency' => 'pos_shifts.active',
                'requested' => $context['requested'],
            ],
            explicitSourceId: (string) $context['drawer_id'],
        );
    }
}
