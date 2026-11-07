<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Support\ProductQuantity;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

final class PostPosPurchaseDraftFocusedTest extends TestCase
{
    private Store $store;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $this->store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'warehouse']);
        $this->supplier = Supplier::query()->create(['code' => 'QA-'.uniqid(), 'name_en' => 'QA Supplier', 'name_ar' => 'مورد اختبار', 'status' => 'active']);
        $this->product = Product::factory()->create(['status' => 'active', 'sale_price' => '150']);
        $this->product->barcodes()->create(['barcode' => 'QA-'.uniqid(), 'source' => 'local', 'status' => 'active', 'is_primary' => true]);
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $actor->storeScopes()->create(['store_id' => $this->store->id, 'status' => 'active']);
        Auth::login($actor);
        $this->actingAs($actor)->withoutVite();
        foreach (['purchase_invoices.draft', 'purchase_invoices.change_cost', 'purchase_invoices.change_list0_price', 'purchase_invoices_supplier_returns.view', 'purchase_invoices_supplier_returns.edit'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Auth::logout();
        parent::tearDown();
    }

    public function test_draft_quantity_five_to_ten_persists_without_posting_and_keeps_line_identity(): void
    {
        $action = app(SavePurchaseInvoiceAction::class);
        $invoice = $action->execute($this->data(), [$this->line('5')]);
        $lineId = $invoice->lines->sole()->id;
        self::assertSame('500.0000', (string) $invoice->total_amount);
        $beforeMovements = DB::table('stock_movements')->count();
        $beforePayables = DB::table('supplier_payable_entries')->count();

        $updated = $action->execute($this->data(), [$this->line('10')], $invoice->id, $invoice->lock_version);
        self::assertSame('draft', $updated->status);
        self::assertSame($lineId, $updated->lines->sole()->id);
        self::assertSame('10.000000', (string) $updated->lines->sole()->quantity);
        self::assertSame('10.000000', (string) $updated->lines->sole()->original_quantity);
        self::assertSame('1000.0000', (string) $updated->subtotal);
        self::assertSame('1000.0000', (string) $updated->total_amount);
        self::assertSame(1, $updated->lines()->count());
        self::assertSame($beforeMovements, DB::table('stock_movements')->count());
        self::assertSame($beforePayables, DB::table('supplier_payable_entries')->count());
        self::assertSame('10.000000', (string) PurchaseInvoice::query()->findOrFail($invoice->id)->lines()->sole()->quantity);
    }

    public function test_edit_form_uses_canonical_display_quantity_and_livewire_save_persists_change(): void
    {
        $invoice = app(SavePurchaseInvoiceAction::class)->execute($this->data(), [$this->line('5')]);
        $component = Livewire::test('purchasing::invoices')->call('openEditModal', $invoice->id)
            ->assertSet('lineItems.0.quantity', '5');
        $component->set('lineItems.0.quantity', '10')->call('saveInvoice')->assertHasNoErrors()->assertSet('showFormModal', false);
        self::assertSame('10.000000', (string) $invoice->fresh()->lines()->sole()->quantity);
        Livewire::test('purchasing::invoices')->call('openEditModal', $invoice->id)->assertSet('lineItems.0.quantity', '10');
    }

    public function test_draft_quantity_edit_recalculates_percentage_discount_and_document_total(): void
    {
        $discounted = [...$this->line('5'), 'discount_type' => 'percentage', 'discount_value' => '10'];
        $action = app(SavePurchaseInvoiceAction::class);
        $invoice = $action->execute($this->data(), [$discounted]);
        self::assertSame('50.0000', (string) $invoice->discount_amount);
        self::assertSame('450.0000', (string) $invoice->total_amount);

        $updated = $action->execute($this->data(), [[...$discounted, 'quantity' => '10']], $invoice->id, $invoice->lock_version);
        self::assertSame('100.0000', (string) $updated->discount_amount);
        self::assertSame('900.0000', (string) $updated->lines->sole()->line_total);
        self::assertSame('900.0000', (string) $updated->total_amount);
        self::assertSame('draft', $updated->status);
    }

    public function test_quantity_formatter_never_exposes_storage_suffix(): void
    {
        self::assertSame('5', ProductQuantity::format('5.000000'));
        self::assertSame('5.5', ProductQuantity::format('5.500000'));
        self::assertSame('5.25', ProductQuantity::format('5.250000'));
        self::assertSame('53', ProductQuantity::format('53.000000'));
        self::assertSame('53.13', ProductQuantity::format('53.125000'));
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return ['supplier_id' => $this->supplier->id, 'store_id' => $this->store->id, 'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP'];
    }

    /** @return array<string, mixed> */
    private function line(string $quantity): array
    {
        return ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => '100', 'base_consumer_price' => '150', 'discount_type' => null, 'discount_value' => '0'];
    }
}
