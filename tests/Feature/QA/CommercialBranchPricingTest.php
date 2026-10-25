<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\WorkContext;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\BarcodeLabelService;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Support\PosContextResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class CommercialBranchPricingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_price_and_draft_line_labels_follow_explicit_outlet_without_posting(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $company = $fixture['company'];
        $branch = $fixture['branch'];
        $warehouse = $fixture['receiving'];
        $outlet = $fixture['store'];
        $base = PriceList::query()->where('company_id', $company->id)->where('list_number', 0)->firstOrFail();
        $sahil = PriceList::query()->firstOrCreate(['company_id' => $company->id, 'list_number' => 2], ['code' => 'QA-S2', 'name_ar' => 'ساحل', 'name_en' => 'Sahil', 'percentage_increase' => '15', 'status' => 'active']);
        $outlet->update(['price_list_id' => $sahil->id]);
        $product = Product::factory()->create(['status' => 'active', 'sale_price' => '400']);
        $barcode = $product->barcodes()->create(['barcode' => 'QA-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $actor = $fixture['admin'];
        $version = $sahil->versions()->create(['version' => 100, 'state' => 'approved', 'source_type' => 'qa_fixture', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $version->lines()->create(['product_id' => $product->id, 'store_id' => $outlet->id, 'branch_id' => $branch->id, 'amount' => '460', 'reference_amount' => '400']);
        $this->actingAs($actor)->withoutVite();
        app(WorkContext::class)->selectBranch($actor, $branch->id);
        self::assertSame($branch->id, app(WorkContext::class)->branch($actor)?->id);
        $component = Livewire::test('purchasing::invoices')->call('openCreateModal');
        $component->assertSet('invoiceForm.store_id', (string) $warehouse->id);
        $component->set('lineItems.0.product_id', (string) $product->id)
            ->set('lineItems.0.quantity', '20')
            ->set('lineItems.0.unit_cost', '100')
            ->set('lineItems.0.base_consumer_price', '400');
        self::assertSame('460.000', $component->instance()->contextualSalePrice($product->id, '400'));
        self::assertSame('100', (string) $component->get('lineItems.0.unit_cost'));
        $beforeInvoices = PurchaseInvoice::query()->count();
        $beforeMovements = DB::table('stock_movements')->count();
        $component->call('printLineLabels', 0)->assertHasNoErrors();
        $preview = session('barcode_label_preview');
        self::assertSame(20, $preview['rows'][0]['copies']);
        self::assertSame($barcode->id, $preview['rows'][0]['barcode_id']);
        self::assertSame('460.000', $preview['prices'][$product->id]);
        $labels = app(BarcodeLabelService::class)->compose($actor, [['product_id' => $product->id, 'barcode_id' => $barcode->id, 'copies' => 1]], app(BarcodeLabelService::class)->profile($actor), $outlet->id);
        self::assertCount(1, $labels['labels']);
        self::assertSame('460.000', (string) $labels['labels'][0]['price']);
        self::assertSame($beforeInvoices, PurchaseInvoice::query()->count());
        self::assertSame($beforeMovements, DB::table('stock_movements')->count());

        $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
        $otherWarehouse = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'type' => 'warehouse']);
        Store::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'type' => 'selling', 'price_list_id' => $base->id]);
        $component->set('invoiceForm.store_id', (string) $otherWarehouse->id);
        self::assertSame('400', $component->instance()->contextualSalePrice($product->id, '400'));
        self::assertSame('100', (string) $component->get('lineItems.0.unit_cost'));
        self::assertSame($branch->id, app(WorkContext::class)->branch($actor)?->id);
        app(WorkContext::class)->selectBranch($actor, null);
        self::assertNull(app(WorkContext::class)->branch($actor));
    }

    public function test_multiple_outlets_require_a_valid_active_mapping(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $company = $fixture['company'];
        $branch = $fixture['branch'];
        $warehouse = $fixture['receiving'];
        $first = $fixture['store'];
        Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'selling']);
        $product = Product::factory()->create(['status' => 'active', 'sale_price' => '400']);
        $product->barcodes()->create(['barcode' => 'QA-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $actor = $fixture['admin'];
        $this->actingAs($actor)->withoutVite();
        $component = Livewire::test('purchasing::invoices')->call('openCreateModal')->set('invoiceForm.store_id', (string) $warehouse->id);
        self::assertNull($component->instance()->contextualSalePrice($product->id, '400'));
        BranchSellingStore::query()->create(['branch_id' => $branch->id, 'store_id' => $first->id, 'status' => 'active', 'effective_from' => now()->subDay()]);
        $component->set('invoiceForm.store_id', (string) $warehouse->id);
        self::assertNotNull($component->instance()->contextualSalePrice($product->id, '400'));
        BranchSellingStore::query()->create(['branch_id' => $branch->id, 'store_id' => $first->id, 'status' => 'active', 'effective_from' => now()->subDay()]);
        $component->set('invoiceForm.store_id', (string) $warehouse->id);
        self::assertNull($component->instance()->contextualSalePrice($product->id, '400'));
    }

    public function test_unauthorized_branch_is_rejected_and_branchless_warehouse_keeps_nullable_context(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $user = $fixture['user'];
        $user->branchScopes()->create(['branch_id' => $fixture['branch']->id, 'status' => 'active']);
        $otherBranch = Branch::factory()->create(['company_id' => $fixture['company']->id]);
        $this->actingAs($user)->withoutVite();
        $this->expectException(ValidationException::class);
        app(WorkContext::class)->selectBranch($user, $otherBranch->id);
    }

    public function test_branchless_warehouse_does_not_acquire_a_fabricated_price_context(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $warehouse = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => null, 'type' => 'warehouse']);
        $product = Product::factory()->create(['status' => 'active', 'sale_price' => '400']);
        $product->barcodes()->create(['barcode' => 'QA-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $this->actingAs($fixture['admin'])->withoutVite();
        $component = Livewire::test('purchasing::invoices')->call('openCreateModal')->set('invoiceForm.store_id', (string) $warehouse->id);
        $component->assertSet('invoiceForm.store_id', (string) $warehouse->id);
        self::assertNull($component->instance()->contextualSalePrice($product->id, '400'));
        self::assertNull($warehouse->fresh()->branch_id);
    }

    public function test_sales_and_reports_follow_branch_default_while_open_pos_shift_keeps_its_own_branch(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $actor = $fixture['admin'];
        $otherBranch = Branch::factory()->create(['company_id' => $fixture['company']->id]);
        $otherStore = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id, 'type' => 'selling']);
        foreach ([[$fixture['branch'], $fixture['store'], 'QA-COM-SAHIL'], [$otherBranch, $otherStore, 'QA-COM-OTHER']] as [$branch, $store, $number]) {
            Sale::query()->create([
                'branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $actor->id,
                'document_number' => $number, 'status' => 'approved', 'idempotency_key' => (string) Str::uuid(),
                'subtotal' => '10', 'total' => '10', 'paid_total' => '10', 'payable_total' => '10',
                'currency_code' => 'EGP', 'approved_at' => now(),
            ]);
        }
        $shift = PosShift::query()->create([
            'branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id,
            'cash_drawer_id' => $fixture['drawer']->id, 'cashier_id' => $actor->id, 'opened_by' => $actor->id,
            'status' => 'open', 'opening_cash' => '0', 'currency_code' => 'EGP',
            'idempotency_key' => (string) Str::uuid(), 'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert(['shift_id' => $shift->id, 'cashier_id' => $actor->id, 'cash_drawer_id' => $fixture['drawer']->id]);
        $this->actingAs($actor)->withoutVite();
        $context = app(WorkContext::class);
        $context->selectBranch($actor, $fixture['branch']->id);
        $this->get(route('sales.invoices'))->assertOk()->assertSee('QA-COM-SAHIL')->assertDontSee('QA-COM-OTHER');
        $report = $this->get(route('reports.sales'))->assertOk();
        self::assertSame($fixture['branch']->id, (int) $report->viewData('report')['filters']['branch_id']);
        $context->selectBranch($actor, $otherBranch->id);
        self::assertSame($fixture['branch']->id, app(PosContextResolver::class)->resolve($actor)->branch?->id);
        $context->selectBranch($actor, null);
        $this->get(route('sales.invoices'))->assertOk()->assertSee('QA-COM-SAHIL')->assertSee('QA-COM-OTHER');
        $globalReport = $this->get(route('reports.sales'))->assertOk();
        self::assertEmpty($globalReport->viewData('report')['filters']['branch_id']);
    }
}
