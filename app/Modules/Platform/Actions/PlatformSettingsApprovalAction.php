<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\ApprovalRecord;
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
            'branch_delete', 'store_delete' => 'branches_stores.logical_delete',
            'cash_drawer_delete' => 'drawers_payments_tax_numbering_printers.logical_delete',
            default => throw ValidationException::withMessages(['resource' => __('This platform setting is not approval-enabled.')]),
        };
        Gate::authorize($requestPermission);

        $sourceId = $resource.':'.($id === null ? 'new:'.Str::lower(Str::random(16)) : $id);
        $payload = ['resource' => $resource, 'id' => $id, 'proposed' => $proposed, 'before' => $before];
        $sourceHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return app(RequestApproval::class)->execute(new ApprovalRequestData(
            sourceType: 'platform_settings',
            sourceId: $sourceId,
            sourceVersion: $id === null ? 'new' : (string) $id,
            requestedAction: $resource,
            requestPermission: $requestPermission,
            branchId: $branchId,
            storeId: $storeId,
            reasonCode: str_ends_with($resource, '_delete') ? 'MASTER_DELETE' : null,
            reasonText: $reason,
            limitContext: $payload,
            sourceHash: $sourceHash,
            idempotencyKey: 'platform-settings-'.$sourceHash,
            decisionPermission: 'company_settings.approve',
        ));
    }

    public function approve(ApprovalRecord $record): void
    {
        $context = $record->limit_context ?? [];
        $resource = (string) ($context['resource'] ?? '');

        DB::transaction(function () use ($record, $context, $resource): void {
            $this->assertSourceUnchanged($context, $resource);
            app(ApproveRequest::class)->execute($record, $record->source_version, $record->source_hash, __('Platform master change approved.'));
            $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
            $proposed = is_array($context['proposed'] ?? null) ? $context['proposed'] : [];

            match ($resource) {
                'tax_setting' => app(SaveLocalSettingsAction::class)->saveTaxSetting($proposed, $id),
                'document_sequence' => app(SaveLocalSettingsAction::class)->saveDocumentSequence($proposed, $id),
                'document_sequence_override' => $this->applySequenceOverride($proposed),
                'branch_delete' => app(SaveBranchAction::class)->logicalDeleteAfterApproval($id),
                'store_delete' => app(SaveStoreAction::class)->logicalDeleteAfterApproval($id),
                'cash_drawer_delete' => app(SaveCashDrawerAction::class)->logicalDeleteAfterApproval($id),
                default => throw ValidationException::withMessages(['approval' => __('This platform setting approval source is not supported.')]),
            };
        });
    }

    public function reject(ApprovalRecord $record, string $reason): void
    {
        app(RejectRequest::class)->execute($record, $record->source_version, $reason, $record->source_hash);
    }

    /** @param array<string, mixed> $data */
    private function applySequenceOverride(array $data): void
    {
        $sequence = \App\Modules\Platform\Models\DocumentSequence::query()->findOrFail((int) ($data['sequence_id'] ?? 0));
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
            'tax_setting' => \App\Modules\Platform\Models\TaxSetting::class,
            'document_sequence', 'document_sequence_override' => \App\Modules\Platform\Models\DocumentSequence::class,
            'branch_delete' => \App\Modules\Platform\Models\Branch::class,
            'store_delete' => \App\Modules\Platform\Models\Store::class,
            'cash_drawer_delete' => \App\Modules\Platform\Models\CashDrawer::class,
            default => null,
        };
        if ($model === null) {
            return;
        }

        $record = $model::query()->lockForUpdate()->find($id);
        if ($record === null || array_intersect_key($record->getAttributes(), $before) !== $before) {
            throw ValidationException::withMessages(['approval' => __('The source changed after this approval request. Reload it and submit a new request.')]);
        }
    }
}
