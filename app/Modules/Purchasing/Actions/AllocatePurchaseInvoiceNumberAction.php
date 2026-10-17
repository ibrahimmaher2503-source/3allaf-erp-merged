<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\DocumentTypeCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocatePurchaseInvoiceNumberAction
{
    public function execute(Store $receivingStore): string
    {
        if ($receivingStore->branch_id !== null) {
            return app(AllocateDocumentNumber::class)->executeForBranch('purchase_invoice', (int) $receivingStore->branch_id);
        }

        return DB::transaction(function () use ($receivingStore): string {
            $company = Company::query()->whereKey($receivingStore->company_id)->lockForUpdate()->firstOrFail();
            $sequence = DocumentSequence::query()
                ->where('document_type', 'purchase_invoice')
                ->where('scope_key', DocumentSequence::scopeKeyFor('company'))
                ->lockForUpdate()
                ->first();
            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create([
                    'document_type' => 'purchase_invoice',
                    'scope_type' => 'company',
                    'scope_id' => null,
                    'prefix' => DocumentTypeCatalog::prefix('CENTRAL', 'purchase_invoice'),
                    'padding_length' => 6,
                    'next_value' => 1,
                    'reset_rule' => 'never',
                    'status' => 'active',
                    'lock_version' => 1,
                    'policy_notes' => __('Automatically initialized for central receiving warehouses without a branch.'),
                ]);
                app(RecordAuditEvent::class)->execute(
                    category: 'numbering',
                    event: 'company_document_sequence_initialized',
                    source: $sequence,
                    after: $sequence->only(['document_type', 'scope_type', 'scope_id', 'next_value', 'reset_rule', 'status']),
                    metadata: ['company_id' => $company->id, 'receiving_store_id' => $receivingStore->id],
                );
            }
            if ($sequence->scope_type !== 'company' || $sequence->scope_id !== null || $sequence->status !== 'active'
                || $sequence->next_value < 1 || $sequence->reset_rule !== 'never') {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document sequence is invalid.')]);
            }

            $allocatedValue = (int) $sequence->next_value;
            $before = $sequence->only(['next_value', 'lock_version', 'last_reset_period']);
            $number = $sequence->formatValue($allocatedValue);
            $sequence->advanceCounter($allocatedValue + 1);
            app(RecordAuditEvent::class)->execute(
                category: 'numbering',
                event: 'document_number_allocated',
                source: $sequence,
                before: $before,
                after: $sequence->only(['next_value', 'lock_version', 'last_reset_period']),
                metadata: [
                    'document_type' => 'purchase_invoice',
                    'scope_type' => 'company',
                    'scope_id' => null,
                    'company_id' => $company->id,
                    'receiving_store_id' => $receivingStore->id,
                    'allocated_number' => $number,
                    'reset_applied' => false,
                ],
            );

            return $number;
        });
    }
}
