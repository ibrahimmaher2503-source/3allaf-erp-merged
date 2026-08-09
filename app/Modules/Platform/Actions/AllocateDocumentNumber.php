<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocateDocumentNumber
{
    public function execute(string $documentType): string
    {
        $documentType = trim($documentType);
        if ($documentType === '') {
            throw ValidationException::withMessages(['document_type' => __('A document type is required for number allocation.')]);
        }

        return DB::transaction(function () use ($documentType): string {
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering is not configured for :type.', ['type' => $documentType])]);
            }
            if ($sequence->status !== 'active') {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering is not active for :type.', ['type' => $documentType])]);
            }
            if ($sequence->next_value < 1 || $sequence->padding_length < 1 || $sequence->padding_length > 12) {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document sequence is invalid.')]);
            }

            $before = $sequence->only(['next_value', 'lock_version']);
            $number = (string) ($sequence->prefix ?? '')
                .str_pad((string) $sequence->next_value, $sequence->padding_length, '0', STR_PAD_LEFT)
                .(string) ($sequence->suffix ?? '');
            $sequence->advanceCounter($sequence->next_value + 1);

            app(RecordAuditEvent::class)->execute(
                category: 'numbering',
                event: 'document_number_allocated',
                source: $sequence,
                before: $before,
                after: $sequence->only(['next_value', 'lock_version']),
                metadata: ['document_type' => $documentType, 'allocated_number' => $number],
            );

            return $number;
        });
    }
}
