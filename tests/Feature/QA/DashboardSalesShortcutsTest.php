<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Retail\Actions\SaveSalesInvoiceDraftAction;
use App\Modules\Retail\Models\Sale;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class DashboardSalesShortcutsTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('scripts/qa/fixture.php');
        $this->fixture = qaFixture();
        $this->withoutVite();
        request()->setLaravelSession(app('session')->driver());
    }

    public function test_pos_and_ordinary_invoice_shortcuts_have_independent_permissions_and_routes(): void
    {
        $this->withSession(['locale' => 'ar']);
        $cases = [
            'pos-only' => [['pos_sales.view', 'pos_sales.create'], true, false],
            'invoice-only' => [['sales_invoices.create'], false, true],
            'both' => [['pos_sales.view', 'pos_sales.create', 'sales_invoices.create'], true, true],
            'neither' => [[], false, false],
        ];

        foreach ($cases as $label => [$abilities, $posVisible, $invoiceVisible]) {
            $user = $this->userWith($label, ['dashboard_reports.view', ...$abilities]);
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();
            $html = $response->getContent();
            $this->assertSame($posVisible, str_contains($html, 'نقطة بيع جديدة'), $label.' POS label');
            $this->assertSame($invoiceVisible, str_contains($html, 'فاتورة مبيعات جديدة'), $label.' invoice label');
            $this->assertSame($invoiceVisible, str_contains($html, route('sales.invoices.create')), $label.' invoice URL');
            $this->actingAs($user)->get(route('sales.invoices.create'))->assertStatus($invoiceVisible ? 200 : 403);
        }

        $this->assertSame('/pos', parse_url(route('pos'), PHP_URL_PATH));
        $this->assertSame('/sales/invoices/create', parse_url(route('sales.invoices.create'), PHP_URL_PATH));
    }

    public function test_ordinary_invoice_draft_is_scoped_idempotent_and_does_not_post_stock(): void
    {
        $user = $this->userWith('invoice-draft', ['sales_invoices.create']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '125.00']);
        $product->barcodes()->create(['barcode' => 'QA-INVOICE-'.Str::random(10), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $token = (string) Str::uuid();
        $beforeMovements = StockMovement::query()->count();
        $this->actingAs($user);
        $drafts = app(SaveSalesInvoiceDraftAction::class);

        $first = $drafts->execute($user, $this->fixture['store'], [['product_id' => $product->id, 'quantity' => '2']], $token);
        $retry = $drafts->execute($user, $this->fixture['store'], [['product_id' => $product->id, 'quantity' => '2']], $token);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, Sale::query()->where('idempotency_key', $token)->count());
        $this->assertSame('draft', $first->status);
        $this->assertNull($first->shift_id);
        $this->assertNull($first->document_number);
        $this->assertSame(1, $first->lines()->count());
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->actingAs($user)->get(route('sales.invoices.drafts.show', $first))->assertOk();

        $other = $this->userWith('invoice-other', ['sales_invoices.create']);
        $this->actingAs($other)->get(route('sales.invoices.drafts.show', $first))->assertForbidden();
        $this->expectException(HttpException::class);
        $drafts->execute($other, $this->fixture['store'], [['product_id' => $product->id, 'quantity' => '2']], $token);
    }

    public function test_invoice_create_route_saves_only_a_draft_and_denies_pos_only_user(): void
    {
        $invoiceUser = $this->userWith('invoice-post', ['sales_invoices.create']);
        $posUser = $this->userWith('pos-post', ['pos_sales.view', 'pos_sales.create']);
        $product = Product::factory()->create(['category_id' => $this->fixture['category']->id, 'sale_price' => '45.00']);
        $product->barcodes()->create(['barcode' => 'QA-INVOICE-POST-'.Str::random(8), 'source' => 'manual', 'status' => 'active', 'is_primary' => true]);
        $payload = [
            'store_id' => $this->fixture['store']->id,
            'creation_token' => (string) Str::uuid(),
            'lines' => [['identifier' => $product->item_code, 'quantity' => '2']],
        ];
        $beforeMovements = StockMovement::query()->count();

        $this->actingAs($posUser)->post(route('sales.invoices.drafts.store'), $payload)->assertForbidden();
        $response = $this->actingAs($invoiceUser)->post(route('sales.invoices.drafts.store'), $payload);
        $draft = Sale::query()->where('idempotency_key', $payload['creation_token'])->firstOrFail();
        $response->assertRedirect(route('sales.invoices.drafts.show', $draft));
        $this->assertSame('draft', $draft->status);
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->actingAs($invoiceUser)->post(route('sales.invoices.drafts.store'), $payload)
            ->assertRedirect(route('sales.invoices.drafts.show', $draft));
        $this->assertSame(1, Sale::query()->where('idempotency_key', $payload['creation_token'])->count());
        $this->assertSame(1, $draft->lines()->count());

        $this->actingAs($invoiceUser)->post(route('sales.invoices.drafts.store'), [
            ...$payload,
            'creation_token' => (string) Str::uuid(),
            'lines' => [['identifier' => 'UNKNOWN-CODE', 'quantity' => '1']],
        ])->assertSessionHasErrors('lines');
        $this->assertSame($beforeMovements, StockMovement::query()->count());
    }

    /** @param list<string> $abilities */
    private function userWith(string $suffix, array $abilities): User
    {
        $role = Role::query()->create(['code' => 'qa-shortcut-'.$suffix, 'name_ar' => 'اختبار', 'name_en' => 'QA '.$suffix, 'status' => 'active']);
        $role->permissions()->attach(Permission::query()->whereIn('code', $abilities)->pluck('id'));
        $user = User::query()->create(['name' => 'QA '.$suffix, 'username' => 'qa-shortcut-'.$suffix, 'email' => 'qa-shortcut-'.$suffix.'@example.test', 'password' => 'disposable-password', 'status' => 'active', 'email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        $user->branchScopes()->create(['branch_id' => $this->fixture['branch']->id, 'status' => 'active']);
        $user->storeScopes()->create(['store_id' => $this->fixture['store']->id, 'status' => 'active']);

        return $user;
    }
}
