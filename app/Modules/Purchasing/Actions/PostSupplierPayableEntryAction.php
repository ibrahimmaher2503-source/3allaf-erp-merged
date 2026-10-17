<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\SupplierPayableEntry;
use InvalidArgumentException;

final class PostSupplierPayableEntryAction
{
    public function execute(int $supplierId, int $storeId, string $sourceType, int $sourceId, string $amount, int $actorId): SupplierPayableEntry
    {
        if (! in_array($sourceType, ['purchase_invoice', 'purchase_invoice_reversal', 'supplier_return', 'supplier_return_reversal'], true)
            || ! preg_match('/^-?\d+(?:\.\d{1,4})?$/', $amount)
            || bccomp($amount, '0', 4) === 0) {
            throw new InvalidArgumentException('Invalid supplier payable posting.');
        }

        $store = Store::query()->findOrFail($storeId);
        $existing = SupplierPayableEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->lockForUpdate()->first();
        if ($existing !== null) {
            if ($existing->company_id !== $store->company_id || $existing->supplier_id !== $supplierId
                || $existing->store_id !== $storeId || bccomp((string) $existing->amount, $amount, 4) !== 0) {
                throw new InvalidArgumentException('Existing supplier payable posting does not match its source.');
            }

            return $existing;
        }

        return SupplierPayableEntry::query()->create([
            'company_id' => $store->company_id,
            'supplier_id' => $supplierId,
            'store_id' => $storeId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'posted_at' => now(),
            'created_by' => $actorId,
        ]);
    }
}
