<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

final class AllocatePurchaseInvoiceNumberAction
{
    public function execute(): string
    {
        return DB::transaction(function (): string {
            $sequence = DocumentSequence::query()->where('document_type', 'purchase_invoice')->lockForUpdate()->first();
            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create([
                    'document_type' => 'purchase_invoice',
                    'prefix' => 'PINV-',
                    'padding_length' => 6,
                    'next_value' => 1,
                    'reset_rule' => 'never',
                    'status' => 'active',
                    'lock_version' => 1,
                    'policy_notes' => 'Local DEC-043 default; replace with owner-approved numbering policy before production.',
                ]);
            }
            if ($sequence->status !== 'active') {
                throw new \RuntimeException(__('Purchase invoice numbering is not active.'));
            }

            $number = ($sequence->prefix ?: 'PINV-')
                .str_pad((string) $sequence->next_value, $sequence->padding_length ?: 6, '0', STR_PAD_LEFT)
                .($sequence->suffix ?: '');
            $sequence->update(['next_value' => $sequence->next_value + 1, 'lock_version' => $sequence->lock_version + 1]);

            return $number;
        });
    }
}
