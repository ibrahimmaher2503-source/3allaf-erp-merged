<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class OverrideDocumentSequenceCounter
{
    public function execute(DocumentSequence $sequence, int $nextValue, int $expectedLockVersion, string $reason): DocumentSequence
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.override');
        if ($nextValue < 1) {
            throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The next value must be at least one.')]);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['sequenceOverride.reason' => __('A sequence-counter override reason is required.')]);
        }

        return DB::transaction(function () use ($sequence, $nextValue, $expectedLockVersion, $reason): DocumentSequence {
            $sequence = DocumentSequence::visibleTo(auth()->user())->lockForUpdate()->findOrFail($sequence->id);
            if ($sequence->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The sequence changed in another session. Reload before overriding it.')]);
            }
            if ($sequence->next_value === $nextValue) {
                throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The override must change the current counter.')]);
            }

            $before = $sequence->only(['document_type', 'next_value', 'lock_version']);
            $sequence->advanceCounter($nextValue);
            app(RecordAuditEvent::class)->execute(
                'numbering',
                'document_sequence_counter_overridden',
                $sequence,
                $before,
                $sequence->only(['document_type', 'next_value', 'lock_version']),
                reasonText: trim($reason),
                metadata: ['override' => true],
            );

            return $sequence;
        });
    }
}
