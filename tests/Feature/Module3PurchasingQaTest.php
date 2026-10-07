<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

final class Module3PurchasingQaTest extends TestCase
{
    use DatabaseTransactions;

    private User $buyer;

    private Store $store;

    private Supplier $supplier;

    private Product $product;

    public function createApplication(): Application
    {
        if (getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_DATABASE') !== 'qa_rajh_m3_purchasing_20261007' || ! getenv('QA_STORAGE_PATH')) {
            throw new \RuntimeException('Module 3 requires its disposable XAMPP MySQL schema and storage.');
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useStoragePath(getenv('QA_STORAGE_PATH'));
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('en');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $company = Company::factory()->create();
        TaxSetting::query()->create(['company_id' => $company->id, 'code' => 'M3-ZERO', 'name_en' => 'Zero tax', 'name_ar' => 'ضريبة صفر', 'rate' => 0, 'treatment' => 'standard', 'is_default' => true, 'status' => 'active']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $this->store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $this->supplier = Supplier::query()->create(['code' => 'M3-SUP', 'name_en' => 'M3 Supplier', 'name_ar' => 'مورد اختبار', 'status' => 'active']);
        $this->product = Product::factory()->create(['name_en' => 'Spider Man', 'name_ar' => 'منتج اختبار', 'model_number' => 'M3-MODEL', 'sale_price' => '150']);
        $this->product->barcodes()->create(['barcode' => 'M3-BARCODE', 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        ProductSupplier::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $this->product->id]);
        $this->buyer = $this->user(['purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create', 'purchase_invoices.draft', 'purchase_invoices.change_cost', 'purchase_invoices.change_list0_price', 'purchase_invoices.view_cost', 'purchase_orders.view', 'suppliers.view']);
        $this->actingAs($this->buyer)->withoutVite();
        $this->get(route('purchasing.invoices'))->assertOk();
        request()->setLaravelSession(app('session')->driver());
    }

    public function test_m3_01_m3_02_authorized_cost_entry_saves_audited_draft_without_stock_effect(): void
    {
        foreach ([false, true] as $admin) {
            $buyer = $admin ? User::factory()->create(['status' => 'active', 'is_super_admin' => true]) : $this->buyer;
            $this->actingAs($buyer);
            $component = Livewire::test('purchasing::invoices')->call('openCreateModal')
                ->set('invoiceForm.supplier_id', (string) $this->supplier->id)
                ->set('invoiceForm.store_id', (string) $this->store->id)
                ->call('selectInvoiceProduct', 0, $this->product->id);
            self::assertDoesNotMatchRegularExpression('/data-purchase-cost-input[^>]*disabled/', $component->html());
            $component->set('lineItems.0.unit_cost', '100')->set('lineItems.0.quantity', '2')
                ->set('invoiceForm.supplier_reference', $admin ? 'M3-ADMIN' : 'M3-BUYER')
                ->call('saveInvoice')->assertHasNoErrors()->assertSet('showFormModal', false);
            $invoice = PurchaseInvoice::query()->latest('id')->firstOrFail();
            self::assertSame('draft', $invoice->status);
            self::assertSame('200.0000', $invoice->total_amount);
            $this->assertDatabaseHas('audit_logs', ['event' => 'create_purchase_invoice', 'source_id' => (string) $invoice->id]);
        }
        $this->assertDatabaseCount('purchase_invoices', 2);
        $this->assertDatabaseCount('stock_movements', 0);
        self::assertSame('10.00', $this->product->fresh()->average_cost);
        self::assertNull(ProductSupplier::query()->firstOrFail()->last_purchase_price);
    }

    public function test_m3_02_create_entry_uses_the_same_draft_permission_as_saving(): void
    {
        $this->actingAs($this->user(['purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.create']));
        Livewire::test('purchasing::invoices')->call('openCreateModal')->assertForbidden();
        Livewire::test('purchasing::invoices')->call('saveInvoice')->assertForbidden();
        $this->assertDatabaseCount('purchase_invoices', 0);
    }

    public function test_m3_06_po_store_is_locked_and_mismatch_rejected_while_direct_selection_works(): void
    {
        $order = $this->order();
        $component = Livewire::test('purchasing::invoices')->call('openReceivingModal', $order->id)
            ->assertSet('invoiceForm.store_id', (string) $this->store->id);
        self::assertMatchesRegularExpression('/wire:model.live="invoiceForm.store_id"[^>]*disabled/', $component->html());
        $component->call('saveInvoice')->assertHasNoErrors()->assertSet('showFormModal', false);
        $other = Store::factory()->create(['company_id' => $this->store->company_id, 'branch_id' => $this->store->branch_id, 'type' => 'warehouse']);
        DB::table('user_store_scopes')->insert(['user_id' => $this->buyer->id, 'store_id' => $other->id, 'status' => 'active']);
        try {
            app(SavePurchaseInvoiceAction::class)->execute($this->data($other->id, $order->id), $this->lines($order));
            self::fail('PO receipt accepted a mismatched store.');
        } catch (ModelNotFoundException) {
            self::assertSame(1, PurchaseInvoice::query()->count());
        }
        $direct = app(SavePurchaseInvoiceAction::class)->execute($this->data($other->id), $this->lines());
        self::assertSame($other->id, $direct->store_id);
        self::assertNull($direct->purchase_order_id);
    }

    public function test_m3_04_eligible_supplier_products_match_name_code_barcode_and_model(): void
    {
        foreach (['Spider', $this->product->item_code, 'M3-BARCODE', 'M3-MODEL'] as $term) {
            $this->getJson(route('transaction-products.search', ['q' => $term, 'context' => 'purchasing', 'supplier_id' => $this->supplier->id, 'company_id' => $this->store->company_id]))
                ->assertOk()->assertJsonPath('data.0.id', $this->product->id);
        }
        $this->product->update(['status' => 'inactive']);
        $this->getJson(route('transaction-products.search', ['q' => 'Spider', 'context' => 'purchasing', 'supplier_id' => $this->supplier->id, 'company_id' => $this->store->company_id]))->assertJsonCount(0, 'data');
    }

    public function test_m3_06_po_create_waits_for_branch_before_showing_scoped_stores(): void
    {
        $user = $this->user(['purchase_orders.view', 'purchase_orders.create']);
        $branch = Branch::factory()->create(['company_id' => $this->store->company_id]);
        $otherStore = Store::factory()->create(['company_id' => $this->store->company_id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        DB::table('user_store_scopes')->insert(['user_id' => $user->id, 'store_id' => $otherStore->id, 'status' => 'active']);
        $this->actingAs($user);
        $component = Livewire::test('purchasing::orders')->set('createPage', true)
            ->assertSet('orderForm.branch_id', '');
        $selectOptions = static function (string $html): string {
            self::assertSame(1, preg_match('/<select\b[^>]*wire:model="orderForm.store_id"[^>]*>(.*?)<\/select>/s', $html, $matches));

            return $matches[1];
        };
        self::assertStringNotContainsString($this->store->code, $selectOptions($component->html()));
        self::assertStringNotContainsString($otherStore->code, $selectOptions($component->html()));
        $component->set('orderForm.branch_id', (string) $this->store->branch_id);
        self::assertStringContainsString($this->store->code, $selectOptions($component->html()));
        self::assertStringNotContainsString($otherStore->code, $selectOptions($component->html()));
        $component->set('orderForm.branch_id', (string) $branch->id);
        self::assertStringContainsString($otherStore->code, $selectOptions($component->html()));
        self::assertStringNotContainsString($this->store->code, $selectOptions($component->html()));
    }

    public function test_m3_05_preview_shows_existing_subtotal_discount_tax_and_final(): void
    {
        $component = Livewire::test('purchasing::invoices')->call('openCreateModal')
            ->set('lineItems', [[...$this->lines()[0], 'discount_type' => 'percentage', 'discount_value' => '10', 'tax_rate' => '14']])
            ->assertSee('Subtotal')->assertSee('Discount total')->assertSee('Tax total')
            ->assertSee('180.00')->assertSee('20.00')->assertSee('25.20')->assertSee('205.20');
        $component->set('lineItems.0.tax_inclusive', true)->assertSee('157.89')->assertSee('22.11')->assertSee('180.00');
        $component->set('lineItems.0.quantity', '')->assertSee('0.00');
    }

    public function test_m3_03_supplier_details_link_to_scoped_purchase_history_with_empty_states(): void
    {
        Livewire::test('catalog::suppliers')->call('openSupplierDetailModal', $this->supplier->id)
            ->assertSee(route('purchasing.history.suppliers', ['supplier_id' => $this->supplier->id]), false);
        $url = route('purchasing.history.suppliers', ['supplier_id' => $this->supplier->id]);
        $this->get($url)->assertOk()->assertSee('No approved purchase orders found.')
            ->assertSee('No approved supplier invoices found.')->assertSee('No approved supplier returns found.')
            ->assertSee('Select a supplier with recorded last prices.');
        $this->order();
        $outsideCompany = Company::factory()->create();
        $outsideBranch = Branch::factory()->create(['company_id' => $outsideCompany->id]);
        $outside = Store::factory()->create(['company_id' => $outsideCompany->id, 'branch_id' => $outsideBranch->id, 'type' => 'warehouse']);
        PurchaseOrder::query()->create(['po_number' => 'M3-HIDDEN-PO', 'supplier_id' => $this->supplier->id, 'store_id' => $outside->id, 'branch_id' => $outside->branch_id, 'status' => 'approved', 'order_date' => now()->toDateString()]);
        $invoice = PurchaseInvoice::query()->create([...$this->data(), 'invoice_number' => 'M3-HISTORY-INV', 'status' => 'approved', 'idempotency_key' => Str::uuid()->toString(), 'total_amount' => '200']);
        $reasonId = DB::table('supplier_return_reasons')->insertGetId(['code' => 'M3-REASON', 'label_en' => 'Damaged', 'label_ar' => 'تالف']);
        PurchaseReturn::query()->create(['return_number' => 'M3-HISTORY-RET', 'supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $invoice->id, 'store_id' => $this->store->id, 'reason_id' => $reasonId, 'return_date' => now()->toDateString(), 'status' => 'approved', 'idempotency_key' => Str::uuid()->toString()]);
        ProductSupplier::query()->firstOrFail()->update(['last_purchase_price' => '100', 'last_purchase_date' => now()->toDateString()]);
        $this->get($url)->assertOk()->assertSee('M3-PO')->assertDontSee('M3-HIDDEN-PO')
            ->assertSee('M3-HISTORY-INV')->assertSee('M3-HISTORY-RET')->assertSee('100.0000');
        $this->actingAs($this->user(['purchase_invoices_supplier_returns.view']));
        $this->get($url)->assertOk()->assertDontSee('M3-PO')->assertSee('M3-HISTORY-INV');
        $this->actingAs($this->user(['suppliers.view']));
        Livewire::test('catalog::suppliers')->call('openSupplierDetailModal', $this->supplier->id)->assertDontSee($url, false);
        $this->get($url)->assertForbidden();
    }

    public function test_m3_07_linked_product_names_fall_back_to_alternate_language_then_code(): void
    {
        $this->product->update(['name_en' => '']);
        Livewire::test('catalog::suppliers')->call('openSupplierDetailModal', $this->supplier->id)
            ->set('detailTab', 'products')->assertSee('منتج اختبار');
        $this->product->update(['name_ar' => '']);
        Livewire::test('catalog::suppliers')->call('openSupplierDetailModal', $this->supplier->id)
            ->set('detailTab', 'products')->assertSeeHtml('font-medium">'.$this->product->item_code.'</td>');
    }

    private function user(array $codes): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['code' => 'M3-'.Str::random(10), 'name_ar' => 'مشتريات', 'name_en' => 'Purchasing', 'status' => 'active']);
        foreach ($codes as $code) {
            [$module, $action] = explode('.', $code);
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'status' => 'active']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
        DB::table('user_store_scopes')->insert(['user_id' => $user->id, 'store_id' => $this->store->id, 'status' => 'active']);

        return $user;
    }

    private function order(): PurchaseOrder
    {
        $order = PurchaseOrder::query()->create(['po_number' => 'M3-PO', 'supplier_id' => $this->supplier->id, 'store_id' => $this->store->id, 'branch_id' => $this->store->branch_id, 'status' => 'approved', 'order_date' => now()->toDateString(), 'created_by' => $this->buyer->id]);
        $order->lines()->create(['product_id' => $this->product->id, 'line_number' => 1, 'quantity_ordered' => 2, 'quantity_received' => 0, 'unit_cost' => 100]);

        return $order;
    }

    private function data(?int $storeId = null, ?int $orderId = null): array
    {
        return ['supplier_id' => $this->supplier->id, 'store_id' => $storeId ?? $this->store->id, 'purchase_order_id' => $orderId, 'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP'];
    }

    private function lines(?PurchaseOrder $order = null): array
    {
        return [['product_id' => $this->product->id, 'purchase_order_line_id' => $order?->lines()->first()->id, 'quantity' => 2, 'unit_cost' => '100', 'base_consumer_price' => '150', 'tax_rate' => '0', 'discount_type' => null, 'discount_value' => '0']];
    }
}
