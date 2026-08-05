<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class AllocatePurchaseOrderNumberAction
{
    /**
     * Allocate a concurrency-safe PO number using DocumentSequence or fallback demo sequence.
     */
    public function execute(): string
    {
        return DB::transaction(function (): string {
            $seq = DocumentSequence::query()
                ->where('document_type', 'purchase_order')
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                throw new \RuntimeException('Purchase-order document sequence is not configured for this environment.');
            }

            $currentVal = $seq->next_value;
            $prefix = $seq->prefix ?: 'PO-';
            $padding = $seq->padding_length ?: 6;
            $number = $prefix . str_pad((string) $currentVal, $padding, '0', STR_PAD_LEFT);

            $seq->update([
                'next_value' => $currentVal + 1,
                'lock_version' => $seq->lock_version + 1,
            ]);

            return $number;
        });
    }
}
