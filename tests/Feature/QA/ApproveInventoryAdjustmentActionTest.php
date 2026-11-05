<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\ReverseInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
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

    public function test_open_price_product_can_be_added_directly_without_a_selling_price_cost_link(): void
    {
        $this->grantPermissions('inventory_stock_card.create', 'inventory_stock_card.view', 'inventory_stock_card.approve');
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor)->withoutVite();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $product = Product::factory()->create(['open_price' => true, 'sale_price' => '1.00', 'average_cost' => '1.00']);
        $product->barcodes()->create(['barcode' => 'QA-ENTRY-'.uniqid(), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);

        $this->post(route('inventory.documents.store', ['documentType' => 'entry']), [
            'store_id' => $store->id,
            'reason_code' => 'direct_addition',
            'lines' => [['product_id' => $product->id, 'quantity_delta' => 2, 'unit_cost' => null]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = InventoryAdjustment::query()->latest('id')->firstOrFail();
        self::assertSame('entry', $adjustment->adjustment_type);
        app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
        self::assertSame('2.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));
        self::assertSame('1.00', (string) $product->fresh()->sale_price);
    }

    public function test_inventory_approval_audit_is_visible_to_branch_and_store_scopes(): void
    {
        $this->grantPermissions(
            'inventory_stock_card.create',
            'inventory_stock_card.view',
            'inventory_stock_card.submit',
            'inventory_stock_card.approve',
            'inventory_stock_card.reverse',
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

        $this->withoutVite()->get(route('inventory.additions'))->assertOk()
            ->assertSee('Inventory addition')->assertDontSee('Filter inventory')->assertDontSee('id="adjustment-type"', false);
        $this->get(route('inventory.deductions'))->assertOk()
            ->assertSee('Inventory deduction')->assertDontSee('Filter inventory');

        app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
        self::assertSame('approved', $adjustment->fresh()->status);
        self::assertSame('1.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));
        $deduction = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'exit', 'reason_code' => 'qa_scope'],
            [['product_id' => $product->id, 'quantity_delta' => '1', 'unit_cost' => '10']],
        );
        self::assertStringContainsString('-IAD-', $adjustment->adjustment_number);
        self::assertStringContainsString('-IDD-', $deduction->adjustment_number);
        self::assertStringEndsWith('000001', $adjustment->adjustment_number);
        self::assertStringEndsWith('000001', $deduction->adjustment_number);
        $nextAddition = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'entry', 'reason_code' => 'qa_scope'],
            [['product_id' => $product->id, 'quantity_delta' => '1', 'unit_cost' => '10']],
        );
        $nextDeduction = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'exit', 'reason_code' => 'qa_scope'],
            [['product_id' => $product->id, 'quantity_delta' => '1', 'unit_cost' => '10']],
        );
        self::assertStringEndsWith('000002', $nextAddition->adjustment_number);
        self::assertStringEndsWith('000002', $nextDeduction->adjustment_number);
        self::assertCount(4, array_unique([$adjustment->adjustment_number, $deduction->adjustment_number, $nextAddition->adjustment_number, $nextDeduction->adjustment_number]));
        app(ApproveInventoryAdjustmentAction::class)->execute($deduction->id);
        self::assertSame('0.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));
        $reversal = app(ReverseInventoryAdjustmentAction::class)->execute($deduction->id, 'QA correction');
        self::assertSame($deduction->id, (int) $reversal->reversal_of_id);
        self::assertSame('1.000000', (string) StockBalance::query()->where('store_id', $store->id)->where('product_id', $product->id)->value('on_hand'));

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
