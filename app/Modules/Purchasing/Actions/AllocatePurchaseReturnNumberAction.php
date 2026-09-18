<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Platform\Actions\AllocateDocumentNumber;

final class AllocatePurchaseReturnNumberAction
{
    public function execute(int $branchId): string
    {
        return app(AllocateDocumentNumber::class)->executeForBranch('supplier_return', $branchId);
    }
}
