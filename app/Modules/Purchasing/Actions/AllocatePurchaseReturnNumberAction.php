<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

final class AllocatePurchaseReturnNumberAction
{
    public function execute(): string
    {
        return app(AllocateDocumentNumber::class)->execute('supplier_return');
    }
}
