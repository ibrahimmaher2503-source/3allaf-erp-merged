<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\DashboardApprovalPreview;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\BarcodeLabelService;
use App\Modules\Pricing\Services\BarcodeSellingPriceResolver;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Actions\CreatePurchaseReturnDraftAction;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class OperationalPolishBatchTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->actingAs($this->fixture['admin'])->withoutVite();
        request()->setLaravelSession(app('session')->driver());
    }

    public function test_barcode_preview_uses_the_single_active_company_list_and_distinguishes_missing_prices(): void
    {
        $store = $this->fixture['store'];
        $this->fixture['admin']->branchScopes()->firstOrCreate(['branch_id' => $this->fixture['branch']->id], ['status' => 'active']);
        $store->update(['price_list_id' => null]);
        PriceList::query()->where('company_id', $store->company_id)->where('list_number', 0)->delete();
        $list = PriceList::query()->updateOrCreate(['company_id' => $store->company_id, 'list_number' => 1], [
            'code' => 'PL-0001',
            'name_ar' => 'قائمة نشطة',
            'name_en' => 'Active list',
            'percentage_increase' => 0,
            'status' => 'active',
        ]);
        $otherCompany = Company::query()->create(['code' => 'QA-BAR-OTHER', 'name_ar' => 'شركة أخرى', 'name_en' => 'Other company', 'status' => 'active', 'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo']);
        PriceList::query()->create(['company_id' => $otherCompany->id, 'list_number' => 1, 'code' => 'PL-OTHER', 'name_ar' => 'قائمة أخرى', 'name_en' => 'Other list', 'percentage_increase' => 0, 'status' => 'active']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '125.000']);
        $barcode = $product->barcodes()->create(['barcode' => 'QA-POLISH-'.uniqid(), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $service = app(BarcodeLabelService::class);
        $rows = [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]];
        $document = $service->compose($this->fixture['admin'], $rows, $service->profile($this->fixture['admin']), $store->id);
        $this->assertSame('PL-0001', $document['labels'][0]['list']->code);
        $this->assertSame(app(EffectivePriceResolver::class)->resolveForStore([$product->id], $store->id)->get($product->id)->amount, $document['labels'][0]['price']);

        $product->update(['sale_price' => null]);
        try {
            $service->compose($this->fixture['admin'], $rows, $service->profile($this->fixture['admin']), $store->id);
            $this->fail('Missing product price was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }

        $list->update(['status' => 'inactive']);
        try {
            $service->compose($this->fixture['admin'], $rows, $service->profile($this->fixture['admin']), $store->id);
            $this->fail('Inactive price list was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('price_list', $exception->errors());
        }

        $product->update(['sale_price' => '125.000']);
        $list->update(['status' => 'active']);
        $alternateList = PriceList::query()->create(['company_id' => $store->company_id, 'list_number' => 2, 'code' => 'PL-0002', 'name_ar' => 'قائمة موقع آخر', 'name_en' => 'Second outlet list', 'percentage_increase' => 0, 'status' => 'active']);
        $secondStore = Store::query()->create(['company_id' => $store->company_id, 'branch_id' => $store->branch_id, 'price_list_id' => $alternateList->id, 'code' => 'QA-POLISH-SECOND', 'name_ar' => 'منفذ ثان', 'name_en' => 'Second outlet', 'type' => 'selling', 'status' => 'active', 'allows_negative_stock' => false]);
        $otherDocument = $service->compose($this->fixture['admin'], $rows, $service->profile($this->fixture['admin']), $secondStore->id);
        $this->assertSame('PL-0002', $otherDocument['labels'][0]['list']->code);
        $this->assertSame(app(EffectivePriceResolver::class)->resolveForStore([$product->id], $secondStore->id)->get($product->id)->amount, $otherDocument['labels'][0]['price']);
    }

    public function test_barcode_preview_and_print_apply_selected_outlet_percentage_rule(): void
    {
        $store = $this->fixture['store'];
        $this->fixture['admin']->branchScopes()->firstOrCreate(['branch_id' => $this->fixture['branch']->id], ['status' => 'active']);
        $list = PriceList::query()->updateOrCreate(['company_id' => $store->company_id, 'list_number' => 1], [
            'code' => 'PL-0015', 'name_ar' => 'الساحل', 'name_en' => 'Sahel',
            'percentage_increase' => '15', 'status' => 'active',
        ]);
        $store->update(['price_list_id' => $list->id]);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '300.000']);
        $barcode = $product->barcodes()->create(['barcode' => 'QA-SAH-'.uniqid(), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);

        $preview = app(BarcodeSellingPriceResolver::class)->resolveForStore([$product->id], $store);
        $service = app(BarcodeLabelService::class);
        $document = $service->compose($this->fixture['admin'], [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]], $service->profile($this->fixture['admin']), $store->id);

        $this->assertSame('345.000', $preview->get($product->id));
        $this->assertSame($preview->get($product->id), $document['labels'][0]['price']);
    }

    public function test_supplier_return_search_only_clears_after_successful_add(): void
    {
        $supplier = Supplier::query()->create(['code' => 'QA-POLISH-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'average_cost' => '10.0000']);
        ProductSupplier::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id]);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $this->fixture['receiving']->id, 'on_hand' => 4, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 40, 'version' => 0]);
        $component = Livewire::test('purchasing::returns')
            ->set('selectedSupplierId', $supplier->id)
            ->set('selectedStoreId', $this->fixture['receiving']->id)
            ->set('returnProductEntry', (string) $product->id)
            ->call('selectReturnProduct', $product->id)
            ->assertSet('returnProductEntry', '')
            ->assertCount('returnLines', 1)
            ->assertDispatched('supplier-return-product-added');
        $component->set('returnProductEntry', (string) $product->id)
            ->call('selectReturnProduct', $product->id)
            ->assertHasErrors(['returnProductEntry'])
            ->assertSet('returnProductEntry', (string) $product->id)
            ->assertCount('returnLines', 1);
    }

    public function test_supplier_return_defaults_are_retry_safe_and_other_requires_notes_without_posting_stock(): void
    {
        $migration = require database_path('migrations/2026_10_22_090628_add_default_supplier_return_reasons.php');
        $expectedCodes = ['STALE_STOCK', 'CONSIGNMENT', 'DAMAGED', 'DEFECTIVE', 'SPEC_MISMATCH', 'ORDER_MISMATCH', 'WRONG_DELIVERY', 'EXCESS_QUANTITY', 'QUALITY_REJECTED', 'EXPIRED', 'NEAR_EXPIRY', 'PACKAGING_DAMAGE', 'VARIANT_ERROR', 'ORDER_CANCELLED', 'SUPPLIER_RECALL', 'COMMERCIAL_AGREEMENT', 'DELIVERY_CORRECTION', 'OTHER'];
        $historical = SupplierReturnReason::query()->create(['code' => 'QA-HISTORICAL', 'label_ar' => 'سبب تاريخي', 'label_en' => 'Historical reason', 'is_active' => true]);
        $migration->up();
        $migration->up();
        $this->assertSame(18, SupplierReturnReason::query()->whereIn('code', $expectedCodes)->count());
        $this->assertSame('سبب تاريخي', $historical->fresh()->label_ar);

        $supplier = Supplier::query()->create(['code' => 'QA-OTHER-SUP', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'average_cost' => '10.0000']);
        ProductSupplier::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id]);
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $this->fixture['receiving']->id, 'on_hand' => 4, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => 40, 'version' => 0]);
        $other = SupplierReturnReason::query()->where('code', 'OTHER')->firstOrFail();
        $movementsBefore = StockMovement::query()->count();
        try {
            app(CreatePurchaseReturnDraftAction::class)->executeWithoutInvoice($supplier->id, $this->fixture['receiving']->id, $other->id, [['product_id' => $product->id, 'quantity' => '1']], 'qa-other-empty', '');
            $this->fail('Other without notes was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('returnNotes', $exception->errors());
        }
        $draft = app(CreatePurchaseReturnDraftAction::class)->executeWithoutInvoice($supplier->id, $this->fixture['receiving']->id, $other->id, [['product_id' => $product->id, 'quantity' => '1']], 'qa-other-valid', 'سبب تجاري موضح');
        $this->assertSame('سبب تجاري موضح', $draft->notes);
        $this->assertSame($movementsBefore, StockMovement::query()->count());
    }

    public function test_dashboard_preview_is_bounded_actionable_and_company_scoped(): void
    {
        $admin = $this->fixture['admin'];
        $admin->branchScopes()->firstOrCreate(['branch_id' => $this->fixture['branch']->id], ['status' => 'active']);
        $requester = $this->fixture['user'];
        for ($index = 1; $index <= 12; $index++) {
            $this->approval($requester->id, $this->fixture['branch']->id, $index);
        }
        $otherCompany = Company::query()->create(['code' => 'QA-OTHER-CO', 'name_ar' => 'شركة أخرى', 'name_en' => 'Other company', 'status' => 'active', 'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo']);
        $otherBranch = Branch::query()->create(['company_id' => $otherCompany->id, 'code' => 'QA-OTHER-BR', 'name_ar' => 'فرع آخر', 'name_en' => 'Other branch', 'status' => 'active', 'timezone' => 'Africa/Cairo']);
        $crossCompany = $this->approval($requester->id, $otherBranch->id, 99);
        $items = app(DashboardApprovalPreview::class)->for($admin);
        $this->assertCount(10, $items);
        $this->assertFalse($items->contains('id', $crossCompany->id));
        $this->assertSame(range(12, 3), $items->pluck('source_id')->map(fn ($id): int => (int) $id)->all());

        $role = Role::query()->create(['code' => 'qa-polish-reviewer', 'name_ar' => 'مراجع', 'name_en' => 'Reviewer', 'status' => 'active']);
        $role->permissions()->attach(Permission::query()->whereIn('code', ['dashboard_reports.view', 'purchase_orders.approve'])->pluck('id'));
        $requester->roles()->attach($role->id);
        $requester->branchScopes()->firstOrCreate(['branch_id' => $this->fixture['branch']->id], ['status' => 'active']);
        $this->assertCount(0, app(DashboardApprovalPreview::class)->for($requester));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('data-dashboard-quick-actions', false)->assertSee('data-dashboard-approval-inbox', false)->assertSee(route('admin.approvals', ['approval' => $items->first()->id]), false);
    }

    public function test_quick_actions_use_destination_permissions_for_normal_and_privileged_users(): void
    {
        $normal = $this->fixture['user'];
        $reader = Role::query()->create(['code' => 'qa-polish-dashboard-reader', 'name_ar' => 'قارئ', 'name_en' => 'Dashboard reader', 'status' => 'active']);
        $reader->permissions()->attach(Permission::query()->where('code', 'dashboard_reports.view')->value('id'));
        $normal->roles()->sync([$reader->id]);
        $this->assertFalse(Gate::forUser($normal)->allows('stock_counts.create'));
        $this->assertFalse(Gate::forUser($normal)->allows('purchase_orders.create'));

        foreach (['system-administrator', 'company-owner'] as $roleCode) {
            $privileged = User::query()->create(['name' => 'QA '.$roleCode, 'username' => 'qa-polish-'.$roleCode, 'email' => 'qa-polish-'.$roleCode.'@example.test', 'password' => 'disposable-qa-password', 'status' => 'active']);
            $privileged->roles()->attach(Role::query()->where('code', $roleCode)->firstOrFail()->id);
            $privileged->branchScopes()->create(['branch_id' => $this->fixture['branch']->id, 'status' => 'active']);
            $this->assertTrue(Gate::forUser($privileged)->allows('stock_counts.create'));
            $this->assertTrue(Gate::forUser($privileged)->allows('purchase_orders.create'));
        }
    }

    private function approval(int $requesterId, int $branchId, int $sourceId): ApprovalRecord
    {
        return ApprovalRecord::query()->create([
            'source_type' => 'purchase_orders',
            'source_id' => (string) $sourceId,
            'requested_action' => 'approve',
            'decision_permission' => 'purchase_orders.approve',
            'approval_state' => ApprovalState::Pending,
            'requester_id' => $requesterId,
            'branch_id' => $branchId,
            'requested_at' => now()->addSeconds($sourceId),
        ]);
    }
}
