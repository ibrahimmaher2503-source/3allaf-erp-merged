<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ApproveInventoryAdjustmentActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_approval_audit_is_visible_to_branch_and_store_scopes(): void
    {
        $this->grantPermissions(
            'inventory_stock_card.create',
            'inventory_stock_card.submit',
            'inventory_stock_card.approve',
        );
        $actor = User::factory()->create();
        $actor->forceFill(['status' => 'active', 'is_super_admin' => true])->save();
        $this->actingAs($actor);

        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'warehouse',
        ]);
        $product = Product::factory()->create([
            'product_type' => 'service',
            'item_code' => 'QA-APPROVAL-SCOPE',
            'model_number' => 'QA-APPROVAL-SCOPE',
            'sale_price' => '10.00',
        ]);
        $adjustment = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'entry', 'reason_code' => 'qa_scope'],
            [['product_id' => $product->id, 'quantity_delta' => '1', 'unit_cost' => '10']],
        );

        app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);

        $event = AuditLog::query()
            ->where('event', 'approve_inventory_adjustment')
            ->where('source_id', (string) $adjustment->id)
            ->firstOrFail();
        $branchViewer = User::factory()->create(['status' => 'active']);
        $branchViewer->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $storeViewer = User::factory()->create(['status' => 'active']);
        $storeViewer->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        self::assertTrue(AuditLog::query()->visibleTo($branchViewer)->whereKey($event->id)->exists());
        self::assertTrue(AuditLog::query()->visibleTo($storeViewer)->whereKey($event->id)->exists());
        self::assertSame($branch->id, (int) $event->branch_id);
        self::assertSame($store->id, (int) $event->store_id);
    }

    private function grantPermissions(string ...$codes): void
    {
        foreach ($codes as $code) {
            Permission::query()->firstOrCreate(['code' => $code], [
                'module' => 'inventory_stock_card',
                'action' => substr(strstr($code, '.'), 1),
                'status' => 'active',
            ]);
        }
    }
}
