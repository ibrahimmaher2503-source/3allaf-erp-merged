<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

final class AllocatePurchaseInvoiceNumberAction
{
    public function execute(): string
    {
        return app(AllocateDocumentNumber::class)->execute('purchase_invoice');
    }
}
