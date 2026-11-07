<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

class AllocatePurchaseOrderNumberAction
{
    /**
     * Allocate a concurrency-safe PO number using DocumentSequence or fallback demo sequence.
     */
    public function execute(int $branchId): string
    {
        return app(AllocateDocumentNumber::class)->executeForBranch('purchase_order', $branchId);
    }
}
