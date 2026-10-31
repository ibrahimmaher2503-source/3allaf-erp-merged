<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Inventory\Actions\AllocateStockTransferNumberAction;
use App\Modules\Platform\Models\Store;

final class AllocatePurchaseReturnNumberAction
{
    public function execute(Store $sourceStore): string
    {
        return app(AllocateStockTransferNumberAction::class)->executeFor($sourceStore, 'supplier_return');
    }
}
