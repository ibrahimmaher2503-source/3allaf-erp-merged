<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\ApplicationNavigation;
use App\Modules\Platform\Support\WorkContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PostPosNavigationContextFocusedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cashier_and_branch_manager_grants_show_scoped_incoming_transfers(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $cashier = $fixture['user'];
        $cashier->roles()->attach(Role::query()->where('code', 'cashier')->firstOrFail());
        $cashier->storeScopes()->create(['store_id' => $fixture['receiving']->id, 'status' => 'active']);
        $this->actingAs($cashier)->withoutVite();

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('inventory.transfers.receiving-inbox'))->assertOk();
        $cashierItems = collect(app(ApplicationNavigation::class)->for($cashier, 'en'))->flatMap(fn (array $group): array => $group['items'] ?? []);
        self::assertNotNull($cashierItems->firstWhere('url', route('inventory.transfers.receiving-inbox')));

        $manager = User::factory()->create(['status' => 'active']);
        $manager->roles()->attach(Role::query()->where('code', 'branch-manager')->firstOrFail());
        $manager->branchScopes()->create(['branch_id' => $fixture['branch']->id, 'status' => 'active']);
        $this->actingAs($manager);
        $this->get(route('inventory.transfers'))->assertOk();
        $this->get(route('inventory.transfers.receiving-inbox'))->assertOk();
    }

    public function test_transfer_navigation_uses_canonical_add_and_deduct_routes_with_permission_filtering(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $navigation = app(ApplicationNavigation::class);
        $adminItems = collect($navigation->for($fixture['admin'], 'ar-EG'))->flatMap(fn (array $group): array => $group['items'] ?? []);
        $addition = $adminItems->firstWhere('label', 'إذن إضافة');
        $deduction = $adminItems->firstWhere('label', 'إذن خصم');
        self::assertNotNull($addition);
        self::assertNotNull($deduction);
        self::assertSame(route('inventory.adjustments.create', ['type' => 'entry']), $addition['url']);
        self::assertSame(route('inventory.adjustments.create', ['type' => 'exit']), $deduction['url']);

        $restrictedItems = collect($navigation->for($fixture['user'], 'ar-EG'))->flatMap(fn (array $group): array => $group['items'] ?? []);
        self::assertNull($restrictedItems->firstWhere('label', 'إذن إضافة'));
        self::assertNull($restrictedItems->firstWhere('label', 'إذن خصم'));
    }

    public function test_entry_and_exit_routes_render_dedicated_permits_without_inventory_dashboard_controls(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $this->actingAs($fixture['admin'])->withoutVite();

        foreach (['entry' => 'Stock Addition / Entry Permit', 'exit' => 'Stock Deduction / Exit Permit'] as $type => $title) {
            $this->get(route('inventory.adjustments.create', ['type' => $type]))
                ->assertOk()
                ->assertSee($title)
                ->assertDontSee('Filter inventory')
                ->assertDontSee('New transfer')
                ->assertDontSee('Opening inventory entry')
                ->assertDontSee('id="adjustment-type"', false);
        }
    }

    public function test_selected_work_store_inherits_pos_shift_drawer_while_general_context_keeps_choice(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $otherBranch = Branch::factory()->create(['company_id' => $fixture['company']->id]);
        $otherStore = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id, 'type' => 'selling']);
        $otherDrawer = CashDrawer::factory()->create(['branch_id' => $otherBranch->id, 'store_id' => $otherStore->id, 'status' => 'active']);
        $this->actingAs($fixture['admin'])->withoutVite();
        $context = app(WorkContext::class);
        $context->select($fixture['admin'], $fixture['store']->id);

        $this->get(route('pos.shift'))->assertOk()->assertSee($fixture['store']->code)->assertDontSee($otherStore->code);
        $this->get(route('dashboard'))->assertOk()->assertSee(route('pos.shift', ['store_id' => $fixture['store']->id]), false);

        $context->select($fixture['admin'], null);
        $this->get(route('pos.shift'))->assertOk()->assertSee($fixture['store']->code)->assertSee($otherStore->code);
        $this->get(route('dashboard'))->assertOk()->assertSee('href="'.route('pos.shift').'"', false);
    }

    public function test_out_of_scope_work_store_cannot_be_inherited(): void
    {
        require_once base_path('scripts/qa/fixture.php');
        $fixture = qaFixture();
        $fixture['user']->storeScopes()->create(['store_id' => $fixture['store']->id, 'status' => 'active']);
        $otherBranch = Branch::factory()->create(['company_id' => $fixture['company']->id]);
        $otherStore = Store::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $otherBranch->id, 'type' => 'selling']);
        $this->actingAs($fixture['user']);
        $this->withSession([WorkContext::SESSION_KEY => $otherStore->id]);
        self::assertNull(app(WorkContext::class)->selected($fixture['user']));
        self::assertNull(session(WorkContext::SESSION_KEY));
    }
}
