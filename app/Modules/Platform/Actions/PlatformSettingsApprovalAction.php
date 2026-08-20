<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformSettingsApprovalAction
{
    /** @param array<string, mixed> $proposed @param array<string, mixed>|null $before */
    public function request(string $resource, ?int $id, array $proposed, ?array $before = null, ?int $branchId = null, ?int $storeId = null, ?string $reason = null): ApprovalRecord
    {
        $requestPermission = match ($resource) {
            'tax_setting', 'document_sequence' => 'company_settings.edit',
            'document_sequence_override' => 'drawers_payments_tax_numbering_printers.override',
            'branch_delete', 'store_delete', 'store_archive' => 'branches_stores.logical_delete',
            'cash_drawer_delete' => 'drawers_payments_tax_numbering_printers.logical_delete',
            default => throw ValidationException::withMessages(['resource' => __('This platform setting is not approval-enabled.')]),
        };
        Gate::authorize($requestPermission);
        /** @var User $requester */
        $requester = auth()->user() ?? throw new \LogicException('An authenticated requester is required.');

        if ($resource === 'document_sequence') {
            $branchId = ($proposed['scope_type'] ?? 'company') === 'branch'
                ? Branch::visibleTo($requester)
                    ->whereKey((int) ($proposed['scope_id'] ?? 0))
                    ->where('status', 'active')
                    ->firstOrFail()
                    ->id
                : null;
        } elseif ($resource === 'document_sequence_override') {
            $sequence = DocumentSequence::visibleTo($requester)
                ->whereKey((int) ($proposed['sequence_id'] ?? $id ?? 0))
                ->firstOrFail();
            $branchId = $sequence->scope_type === 'branch'
                ? Branch::visibleTo($requester)
                    ->whereKey($sequence->scope_id)
                    ->where('status', 'active')
                    ->firstOrFail()
                    ->id
                : null;
        }

        if (in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true)) {
            [$branchId, $storeId] = $this->masterDeleteScope($resource, $id, $requester, $branchId, $storeId);
        }

        if (in_array($resource, ['store_delete', 'store_archive'], true)) {
            if ($id === null) {
                throw ValidationException::withMessages(['source' => __('An existing location is required for archive approval.')]);
            }
            app(SaveStoreAction::class)->assertStoreDependencyFree($id, 'archive', false);
        }

        $sourceId = $resource.':'.($id === null ? 'new:'.Str::lower(Str::random(16)) : $id);
        $payload = ['resource' => $resource, 'id' => $id, 'proposed' => $proposed, 'before' => $before];
        $sourceHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'platform_settings',
            sourceId: $sourceId,
            sourceVersion: $id === null ? 'new' : (string) $id,
            requestedAction: $resource,
            requestPermission: $requestPermission,
            branchId: $branchId,
            storeId: $storeId,
            reasonCode: in_array($resource, ['branch_delete', 'store_delete', 'store_archive', 'cash_drawer_delete'], true) ? 'MASTER_DELETE' : null,
            reasonText: $reason,
            limitContext: $payload,
            sourceHash: $sourceHash,
            idempotencyKey: 'platform-settings-'.$sourceHash,
            decisionPermission: 'company_settings.approve',
        ));

        if ($requester instanceof User && $requester->canBypassApproval()) {
            $this->approve($approval);
        }

        return $approval->fresh();
    }

    public function approve(ApprovalRecord $record): void
    {
        $context = $record->limit_context ?? [];
        $resource = (string) ($context['resource'] ?? '');

        DB::transaction(function () use ($record, $context, $resource): void {
            $this->assertSourceUnchanged($context, $resource);
            if (in_array($resource, ['store_delete', 'store_archive'], true)) {
                $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
                if ($id === null) {
                    throw ValidationException::withMessages(['approval' => __('The archive source location is missing.')]);
                }
                // Re-check while the source row and every known dependency
                // row are locked, immediately before the approval transition.
                app(SaveStoreAction::class)->assertStoreDependencyFree($id, 'archive', true);
            }
            $approved = app(ApproveRequest::class)->execute($record, $record->source_version, $record->source_hash, __('Platform master change approved.'));
            $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
            $proposed = is_array($context['proposed'] ?? null) ? $context['proposed'] : [];

            match ($resource) {
                'tax_setting' => app(SaveLocalSettingsAction::class)->saveTaxSetting($proposed, $id),
                'document_sequence' => app(SaveLocalSettingsAction::class)->saveDocumentSequence($proposed, $id),
                'document_sequence_override' => $this->applySequenceOverride($proposed),
                'branch_delete' => app(SaveBranchAction::class)->applyApprovedLogicalDelete($id, $approved),
                'store_delete', 'store_archive' => app(SaveStoreAction::class)->applyApprovedLogicalDelete($id, $approved),
                'cash_drawer_delete' => app(SaveCashDrawerAction::class)->applyApprovedLogicalDelete($id, $approved),
                default => throw ValidationException::withMessages(['approval' => __('This platform setting approval source is not supported.')]),
            };
        });
    }

    /** @return array{0: int, 1: int|null} */
    private function masterDeleteScope(string $resource, ?int $id, User $requester, ?int $branchId, ?int $storeId): array
    {
        if ($id === null) {
            throw ValidationException::withMessages(['source' => __('An existing platform master is required for deletion approval.')]);
        }

        $target = match ($resource) {
            'branch_delete' => Branch::visibleTo($requester)->findOrFail($id),
            'store_delete', 'store_archive' => Store::visibleTo($requester)->findOrFail($id),
            'cash_drawer_delete' => CashDrawer::visibleTo($requester)->findOrFail($id),
        };
        $expectedBranchId = $resource === 'branch_delete' ? $target->id : $target->branch_id;
        $expectedStoreId = $resource === 'store_delete' || $resource === 'store_archive' ? $target->id : $target->store_id;

        if ($branchId !== $expectedBranchId || $storeId !== $expectedStoreId) {
            throw ValidationException::withMessages(['scope' => __('Approval scope must match the platform master being changed.')]);
        }

        return [$expectedBranchId, $expectedStoreId];
    }

    public function reject(ApprovalRecord $record, string $reason): void
    {
        app(RejectRequest::class)->execute($record, $record->source_version, $reason, $record->source_hash);
    }

    /** @param array<string, mixed> $data */
    private function applySequenceOverride(array $data): void
    {
        $sequence = DocumentSequence::query()->findOrFail((int) ($data['sequence_id'] ?? 0));
        app(OverrideDocumentSequenceCounter::class)->execute($sequence, (int) $data['next_value'], (int) $data['expected_lock_version'], (string) $data['reason']);
    }

    /** @param array<string, mixed> $context */
    private function assertSourceUnchanged(array $context, string $resource): void
    {
        $before = is_array($context['before'] ?? null) ? $context['before'] : null;
        $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
        if ($before === null || $id === null) {
            return;
        }

        $model = match ($resource) {
            'tax_setting' => TaxSetting::class,
            'document_sequence', 'document_sequence_override' => DocumentSequence::class,
            'branch_delete' => Branch::class,
            'store_delete', 'store_archive' => Store::class,
            'cash_drawer_delete' => CashDrawer::class,
            default => null,
        };
        if ($model === null) {
            return;
        }

        $record = $model::query()->lockForUpdate()->find($id);
        $sourceChanged = $record === null;
        if (! $sourceChanged) {
            foreach ($before as $key => $expected) {
                $actual = $record->getAttribute($key);
                if (($actual === null || $expected === null) ? $actual !== $expected : (string) $actual !== (string) $expected) {
                    $sourceChanged = true;
                    break;
                }
            }
        }
        if ($sourceChanged) {
            throw ValidationException::withMessages(['approval' => __('The source changed after this approval request. Reload it and submit a new request.')]);
        }
    }
}
