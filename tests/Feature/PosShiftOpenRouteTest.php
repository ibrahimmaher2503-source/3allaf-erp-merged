<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PosShiftOpenRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_canonical_cashier_can_get_shift_open_route_and_open_an_assigned_shift(): void
    {
        $this->seedCanonicalCashierRole();
        $cashier = $this->createCashier();
        [$company, $branch, $store] = $this->createSellingOutlet();
        $this->scopeCashierToStore($cashier, $store);
        $drawer = $this->createDrawer($company, $branch, $store, $cashier);

        $this->actingAs($cashier)
            ->get(route('pos.shift.open'))
            ->assertRedirect(route('pos.shift'));

        $this->withoutVite()
            ->actingAs($cashier)
            ->get(route('pos.shift'))
            ->assertOk()
            ->assertSee($drawer->code)
            ->assertSee($store->code);

        $this->actingAs($cashier)
            ->post(route('pos.shift.store'), $this->openPayload($drawer))
            ->assertRedirect(route('pos'));

        $shift = PosShift::query()->sole();

        self::assertSame(ShiftState::Open, $shift->status);
        self::assertSame($cashier->id, (int) $shift->cashier_id);
        self::assertSame($branch->id, (int) $shift->branch_id);
        self::assertSame($store->id, (int) $shift->store_id);
        self::assertSame($drawer->id, (int) $shift->cash_drawer_id);
        self::assertSame('EGP', $shift->currency_code);

        $this->assertDatabaseHas('active_pos_shift_assignments', [
            'shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'open_shift',
            'actor_id' => $cashier->id,
            'source_id' => (string) $shift->id,
        ]);
    }

    public function test_cashier_cannot_open_shift_for_a_drawer_outside_store_scope(): void
    {
        $this->seedCanonicalCashierRole();
        $cashier = $this->createCashier();
        [$company, $branch, $store] = $this->createSellingOutlet();
        $this->scopeCashierToStore($cashier, $store);
        $otherStoreInBranch = $this->createStore($company, $branch);
        $otherBranch = Branch::factory()->for($company)->create();
        $storeInOtherBranch = $this->createStore($company, $otherBranch);
        $sameBranchDrawer = $this->createDrawer($company, $branch, $otherStoreInBranch);
        $otherBranchDrawer = $this->createDrawer($company, $otherBranch, $storeInOtherBranch);

        $this->withoutVite()
            ->actingAs($cashier)
            ->get(route('pos.shift'))
            ->assertOk()
            ->assertDontSee($sameBranchDrawer->code)
            ->assertDontSee($otherBranchDrawer->code);

        $this->actingAs($cashier)
            ->post(route('pos.shift.store'), $this->openPayload($sameBranchDrawer))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->post(route('pos.shift.store'), $this->openPayload($otherBranchDrawer))
            ->assertForbidden();

        $this->assertDatabaseCount('pos_shifts', 0);
        $this->assertDatabaseCount('active_pos_shift_assignments', 0);
    }

    public function test_active_cashier_and_drawer_assignments_remain_exclusive(): void
    {
        $this->seedCanonicalCashierRole();
        $cashier = $this->createCashier();
        $otherCashier = $this->createCashier();
        [$company, $branch, $store] = $this->createSellingOutlet();
        $this->scopeCashierToStore($cashier, $store);
        $this->scopeCashierToStore($otherCashier, $store);
        $assignedDrawer = $this->createDrawer($company, $branch, $store, $cashier);
        $secondDrawer = $this->createDrawer($company, $branch, $store);

        $this->actingAs($cashier)
            ->post(route('pos.shift.store'), $this->openPayload($assignedDrawer))
            ->assertRedirect(route('pos'));

        $this->actingAs($cashier)
            ->from(route('pos.shift'))
            ->post(route('pos.shift.store'), $this->openPayload($secondDrawer))
            ->assertRedirectBackWithErrors('cash_drawer_id');

        $this->actingAs($otherCashier)
            ->from(route('pos.shift'))
            ->post(route('pos.shift.store'), $this->openPayload($assignedDrawer))
            ->assertRedirectBackWithErrors('cash_drawer_id');

        $this->assertDatabaseCount('pos_shifts', 1);
        $this->assertDatabaseCount('active_pos_shift_assignments', 1);
    }

    public function test_shift_open_rejects_drawer_when_store_is_retyped_as_warehouse(): void
    {
        $this->seedCanonicalCashierRole();
        $cashier = $this->createCashier();
        [$company, $branch, $store] = $this->createSellingOutlet();
        $this->scopeCashierToStore($cashier, $store);
        $drawer = $this->createDrawer($company, $branch, $store, $cashier);
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($administrator);
        $warehouse = app(SaveStoreAction::class)->execute([
            'branch_id' => $branch->id,
            'code' => $store->code,
            'name_ar' => $store->name_ar,
            'name_en' => $store->name_en,
            'type' => 'warehouse',
            'status' => 'active',
            'allows_negative_stock' => false,
        ], $store->id);

        self::assertSame('warehouse', $warehouse->type);

        $this->actingAs($cashier)
            ->from(route('pos.shift'))
            ->post(route('pos.shift.store'), $this->openPayload($drawer))
            ->assertRedirectBackWithErrors('cash_drawer_id');

        $this->assertDatabaseCount('pos_shifts', 0);
        $this->assertDatabaseCount('active_pos_shift_assignments', 0);
    }

    public function test_shift_open_rejects_drawer_when_store_is_inactive(): void
    {
        $this->seedCanonicalCashierRole();
        $cashier = $this->createCashier();
        [$company, $branch, $store] = $this->createSellingOutlet();
        $this->scopeCashierToStore($cashier, $store);
        $drawer = $this->createDrawer($company, $branch, $store, $cashier);
        $administrator = User::factory()->create(['status' => 'active']);
        $administrator->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($administrator);
        $inactiveStore = app(SaveStoreAction::class)->toggleStatus($store->id);

        self::assertSame('inactive', $inactiveStore->status);

        $this->actingAs($cashier)
            ->from(route('pos.shift'))
            ->post(route('pos.shift.store'), $this->openPayload($drawer))
            ->assertRedirectBackWithErrors('cash_drawer_id');

        $this->assertDatabaseCount('pos_shifts', 0);
        $this->assertDatabaseCount('active_pos_shift_assignments', 0);
    }

    private function seedCanonicalCashierRole(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();
    }

    private function createCashier(): User
    {
        $cashier = User::factory()->create(['status' => 'active']);
        $cashier->roles()->attach(Role::query()->where('code', 'cashier')->firstOrFail());

        return $cashier;
    }

    /** @return array{Company, Branch, Store} */
    private function createSellingOutlet(): array
    {
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->for($company)->create();
        $store = $this->createStore($company, $branch);

        return [$company, $branch, $store];
    }

    private function createStore(Company $company, Branch $branch): Store
    {
        return Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'selling',
            'status' => 'active',
        ]);
    }

    private function scopeCashierToStore(User $cashier, Store $store): void
    {
        $cashier->storeScopes()->create([
            'store_id' => $store->id,
            'status' => 'active',
        ]);
    }

    private function createDrawer(Company $company, Branch $branch, Store $store, ?User $assignedCashier = null): CashDrawer
    {
        return CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => $assignedCashier?->id,
            'status' => 'active',
        ]);
    }

    /** @return array{cash_drawer_id: int, opening_float: string, idempotency_key: string, confirm_outlet: string} */
    private function openPayload(CashDrawer $drawer): array
    {
        return [
            'cash_drawer_id' => $drawer->id,
            'opening_float' => '100.00',
            'idempotency_key' => (string) Str::uuid(),
            'confirm_outlet' => '1',
        ];
    }
}
