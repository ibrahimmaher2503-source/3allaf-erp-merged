<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
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

        $blockedMutation = null;

        try {
            return DB::transaction(function () use ($data, $id, &$blockedMutation) {
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
                    $drawer = CashDrawer::query()->lockForUpdate()->findOrFail($id);

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
    public function toggleStatus(int $id, string $newStatus = 'inactive'): CashDrawer
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.edit');
        if (! in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException(__('Invalid cash drawer status provided.'));
        }

        $blockedMutation = null;

        try {
            return DB::transaction(function () use ($id, $newStatus, &$blockedMutation) {
                $drawer = CashDrawer::query()->lockForUpdate()->findOrFail($id);
                $oldStatus = $drawer->status;

                if ($newStatus !== 'active') {
                    $blockedMutation = $this->blockedMutationContext(
                        drawer: $drawer,
                        operation: 'deactivate',
                        requested: ['status' => $newStatus],
                    );
                    $this->assertNoActiveShift($drawer);
                }

                $drawer->update(['status' => $newStatus]);

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

    /** Apply an approved logical delete while preserving scoped approval history. */
    public function logicalDeleteAfterApproval(int $id): void
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.logical_delete');

        DB::transaction(function () use ($id): void {
            $drawer = CashDrawer::query()->lockForUpdate()->findOrFail($id);
            $this->assertNoActiveShift($drawer);
            $before = $drawer->getAttributes();
            $drawer->update(['status' => 'inactive']);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'delete_cash_drawer',
                source: $drawer,
                before: $before,
                after: ['deleted' => true, 'status' => 'inactive'],
                branchId: $drawer->branch_id,
                storeId: $drawer->store_id,
                metadata: ['logical_delete' => true, 'approval_required' => true],
            );
        });
    }
}
