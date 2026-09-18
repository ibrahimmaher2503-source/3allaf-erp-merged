<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocateDocumentNumber
{
    public function executeForBranch(string $documentType, int $branchId): string
    {
        return $this->execute($documentType, 'branch', $branchId);
    }

    public function execute(string $documentType, string $scopeType = 'company', ?int $scopeId = null): string
    {
        $documentType = trim($documentType);
        if ($documentType === '') {
            throw ValidationException::withMessages(['document_type' => __('A document type is required for number allocation.')]);
        }
        $scopeType = strtolower(trim($scopeType));
        if ($scopeType !== 'branch' || $scopeId === null) {
            throw ValidationException::withMessages(['scope' => __('A branch-specific document-numbering scope is required.')]);
        }
        if (! Branch::query()->whereKey($scopeId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['scope' => __('The selected branch is not active or does not exist.')]);
        }
        $scopeKey = DocumentSequence::scopeKeyFor($scopeType, $scopeId);

        return DB::transaction(function () use ($documentType, $scopeType, $scopeId, $scopeKey): string {
            $branch = Branch::query()->whereKey($scopeId)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create([
                    'document_type' => $documentType,
                    'scope_type' => 'branch',
                    'scope_id' => $branch->id,
                    'prefix' => DocumentTypeCatalog::prefix((string) $branch->code, $documentType),
                    'suffix' => null,
                    'padding_length' => 6,
                    'next_value' => 1,
                    'reset_rule' => 'never',
                    'status' => 'active',
                    'lock_version' => 1,
                    'policy_notes' => __('Automatically initialized for independent continuous branch numbering; legacy company counters and document references remain unchanged.'),
                ]);
                app(RecordAuditEvent::class)->execute(
                    category: 'numbering',
                    event: 'branch_document_sequence_initialized',
                    source: $sequence,
                    after: $sequence->only(['document_type', 'scope_type', 'scope_id', 'next_value', 'reset_rule', 'status']),
                    branchId: $branch->id,
                    metadata: ['company_id' => $branch->company_id, 'legacy_company_sequence_preserved' => true],
                );
            }
            if ($sequence->scope_type !== $scopeType || (int) ($sequence->scope_id ?? 0) !== (int) ($scopeId ?? 0)) {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document-numbering scope does not match the allocation context.')]);
            }
            if ($sequence->status !== 'active') {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering is not active for :type.', ['type' => $documentType])]);
            }
            if ($sequence->next_value < 1) {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document sequence is invalid.')]);
            }
            if ($sequence->reset_rule !== 'never') {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering must use a continuous sequence without periodic resets.')]);
            }

            $allocatedValue = (int) $sequence->next_value;
            $nextValue = $allocatedValue + 1;
            $before = $sequence->only(['next_value', 'lock_version', 'last_reset_period']);
            $number = DocumentTypeCatalog::prefix((string) $branch->code, $documentType)
                .str_pad((string) $allocatedValue, 6, '0', STR_PAD_LEFT);
            $sequence->advanceCounter($nextValue);

            app(RecordAuditEvent::class)->execute(
                category: 'numbering',
                event: 'document_number_allocated',
                source: $sequence,
                before: $before,
                after: $sequence->only(['next_value', 'lock_version', 'last_reset_period']),
                metadata: [
                    'document_type' => $documentType,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'company_id' => $branch->company_id,
                    'branch_code' => $branch->code,
                    'allocated_number' => $number,
                    'reset_applied' => false,
                ],
            );

            return $number;
        });
    }
}
