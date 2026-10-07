<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\PermissionPresentation;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

final class Bug121PurchaseCostPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        if (getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_HOST') !== '127.0.0.1'
            || ! str_starts_with((string) getenv('DB_DATABASE'), 'qa_rajh_bug121_')) {
            throw new \RuntimeException('BUG-121 requires its own loopback MySQL database.');
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.bug121-does-not-exist');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_upgrade_unlocks_first_cost_for_system_administrator_and_saves_only_a_draft(): void
    {
        $actor = $this->actor('system-administrator');
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $supplier = Supplier::query()->create(['code' => 'BUG121', 'name_ar' => 'مورد الاختبار', 'name_en' => 'BUG121 supplier', 'status' => 'active']);
        $product = Product::factory()->create(['average_cost' => '10.0000', 'sale_price' => '50.0000', 'model_number' => 'BUG121']);
        Barcode::query()->create(['product_id' => $product->id, 'barcode' => '6221210000001', 'source' => 'supplier', 'status' => 'active', 'is_primary' => true]);
        $this->actingAs($actor)->withoutVite();

        Artisan::call('migrate', ['--force' => true]);

        self::assertTrue(Gate::allows('purchase_invoices.change_cost'));
        $component = Livewire::test('purchasing::invoices')->call('openCreateModal')
            ->set('invoiceForm.supplier_id', (string) $supplier->id)
            ->set('invoiceForm.store_id', (string) $store->id)
            ->set('invoiceForm.invoice_date', '2026-10-07')
            ->set('lineItems.0.product_id', (string) $product->id)
            ->set('lineItems.0.quantity', '2')
            ->set('lineItems.0.unit_cost', '37.50')
            ->set('lineItems.0.base_consumer_price', '50.00');
        self::assertStringNotContainsString('disabled', $this->costInput($component->html()));
        $component->call('saveInvoice')->assertHasNoErrors();
        self::assertFalse($component->get('showFormModal'));
        $invoice = PurchaseInvoice::query()->sole();
        self::assertSame('draft', $invoice->status);
        self::assertSame('75.0000', $invoice->total_amount);
        self::assertSame('37.5000', $invoice->lines()->sole()->unit_cost);
        self::assertSame('50.000', $invoice->lines()->sole()->base_consumer_price);
        self::assertSame('10.00', $product->fresh()->average_cost);
        self::assertSame('50.00', $product->fresh()->sale_price);
        self::assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_upgrade_preserves_disabled_permissions_and_existing_grants_on_replay(): void
    {
        $role = $this->actor('restricted-buyer')->roles()->firstOrFail();
        $permission = Permission::query()->create(['code' => 'purchase_invoices.change_cost', 'module' => 'purchase_invoices', 'action' => 'change_cost', 'sensitivity' => 'sensitive', 'status' => 'inactive']);
        $role->permissions()->attach($permission);

        Artisan::call('migrate', ['--force' => true]);
        $migration = require database_path('migrations/2026_10_07_000120_ensure_purchase_invoice_permissions.php');
        $migration->up();

        self::assertSame('inactive', $permission->fresh()->status);
        self::assertSame(8, Permission::query()->where('module', 'purchase_invoices')->count());
        self::assertTrue($role->permissions()->whereKey($permission->id)->exists());
    }

    public function test_ungranted_buyer_gets_a_clear_reason_and_cannot_submit_a_cost(): void
    {
        $actor = $this->actor('restricted-buyer');
        Artisan::call('migrate', ['--force' => true]);
        $actor->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('code', 'purchase_invoices.draft')->sole());
        $this->actingAs($actor)->withoutVite();

        $component = Livewire::test('purchasing::invoices')->call('openCreateModal');
        self::assertStringContainsString('disabled', $this->costInput($component->html()));
        $component->assertSee('Ask a System Administrator to grant purchase cost editing in role permissions.');
        $permission = Permission::query()->where('code', 'purchase_invoices.change_cost')->sole();
        self::assertSame('Edit purchase unit cost', PermissionPresentation::permission($permission, 'en')['label']);
        try {
            app(SavePurchaseInvoiceAction::class)->execute([], [['product_id' => 1, 'quantity' => 2, 'unit_cost' => '37.50']]);
            self::fail('An ungranted buyer must not write a purchase cost.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('You are not allowed to change purchase cost.', $exception->getMessage());
        }
        self::assertSame(0, PurchaseInvoice::query()->count());
    }

    private function actor(string $code): User
    {
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'status' => 'active']);
        $actor->roles()->attach($role);
        foreach (['view', 'create'] as $action) {
            $permission = Permission::query()->firstOrCreate(['code' => 'purchase_invoices_supplier_returns.'.$action], ['module' => 'purchase_invoices_supplier_returns', 'action' => $action, 'status' => 'active']);
            $role->permissions()->attach($permission);
        }

        return $actor;
    }

    private function costInput(string $html): string
    {
        self::assertSame(1, preg_match('/<input[^>]*data-purchase-cost-input[^>]*>/', $html, $matches));

        return $matches[0];
    }
}
