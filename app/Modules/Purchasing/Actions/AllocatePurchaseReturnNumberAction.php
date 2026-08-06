<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
use Illuminate\Support\Facades\DB;

final class AllocatePurchaseReturnNumberAction
{
    public function execute(): string
    {
        return DB::transaction(function (): string {
            $sequence = DocumentSequence::query()->where('document_type', 'supplier_return')->lockForUpdate()->first();
            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create([
                    'document_type' => 'supplier_return',
                    'prefix' => (app(SupplierReturnPolicy::class)->numberPrefix() ?: 'PRET-'.now()->format('Y').'-'),
                    'padding_length' => 5,
                    'next_value' => 1,
                    'reset_rule' => 'never',
                    'status' => 'active',
                    'lock_version' => 1,
                    'policy_notes' => 'Owner-configurable supplier return sequence; local default only until production policy is approved.',
                ]);
            }
            if ($sequence->status !== 'active') {
                throw new \RuntimeException(__('Supplier return numbering is not active.'));
            }

            $sequence->refresh();
            $number = ($sequence->prefix ?: '')
                .str_pad((string) $sequence->next_value, $sequence->padding_length ?: 5, '0', STR_PAD_LEFT)
                .($sequence->suffix ?: '');
            $sequence->update(['next_value' => $sequence->next_value + 1, 'lock_version' => $sequence->lock_version + 1]);

            return $number;
        });
    }
}
