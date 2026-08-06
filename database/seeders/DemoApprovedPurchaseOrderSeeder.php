<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Seeder;

final class DemoApprovedPurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $approverId = User::query()
            ->where('email', 'demo.branch.manager@toyjoy.local')
            ->value('id');

        PurchaseOrder::query()
            ->where('po_number', 'PO-DEMO-000001')
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approverId,
                'updated_at' => now(),
            ]);
    }
}
