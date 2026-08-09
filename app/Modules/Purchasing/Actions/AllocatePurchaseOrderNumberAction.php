<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

class AllocatePurchaseOrderNumberAction
{
    /**
     * Allocate a concurrency-safe PO number using DocumentSequence or fallback demo sequence.
     */
    public function execute(): string
    {
        return app(AllocateDocumentNumber::class)->execute('purchase_order');
    }
}
