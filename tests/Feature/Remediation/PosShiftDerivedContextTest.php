<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\PosShift;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PosShiftDerivedContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pos_uses_the_cashiers_active_shift_for_every_displayed_context_value_when_two_selling_stores_are_visible(): void
    {
        $context = $this->cashierWithTwoVisibleSellingStoresAndActiveSecondShift();

        $this->actingAs($context['cashier'])
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('ALT-POS')
            ->assertSee('ALT-SALES')
            ->assertSee('ALT-DR')
            ->assertSee('Branch')
            ->assertSee('POS selling location')
            ->assertSee('Stock source')
            ->assertSee('Same as POS selling location')
            ->assertSee('Drawer')
            ->assertSee('Shift')
            ->assertDontSee('MAIN-SALES');
    }

    public function test_the_pos_is_disabled_when_the_active_shift_store_does_not_match_the_branch_assignment(): void
    {
        $context = $this->cashierWithTwoVisibleSellingStoresAndActiveSecondShift();
        $replacement = Store::query()->create([
            'company_id' => $context['shift']->store->company_id,
            'branch_id' => $context['shift']->branch_id,
            'code' => 'ALT-ASSIGNED',
            'type' => 'selling',
            'name_ar' => 'موقع البيع المعين',
            'name_en' => 'Assigned POS Location',
            'status' => 'active',
        ]);
        BranchSellingStore::query()->where('branch_id', $context['shift']->branch_id)->update([
            'status' => 'inactive',
            'effective_to' => now(),
        ]);
        BranchSellingStore::query()->create([
            'branch_id' => $context['shift']->branch_id,
            'store_id' => $replacement->id,
            'effective_from' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($context['cashier'])
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('POS is disabled because the active shift uses ALT-SALES')
            ->assertSee('ALT-ASSIGNED')
            ->assertSee('Open a shift from the assigned location.')
            ->assertDontSee('Same as POS selling location');
    }

    public function test_the_pos_is_explicitly_disabled_without_an_active_assigned_shift_and_never_falls_back_to_main(): void
    {
        $context = $this->cashierWithTwoVisibleSellingStoresAndActiveSecondShift();
        DB::table('active_pos_shift_assignments')->where('shift_id', $context['shift']->id)->delete();
        $context['shift']->delete();

        $this->actingAs($context['cashier'])
            ->get(route('pos'))
            ->assertOk()
            ->assertSee('POS is disabled until you open an assigned cashier shift.')
            ->assertSee(route('pos.shift'), escape: false)
            ->assertDontSee('MAIN-SALES');
    }

    /** @return array{cashier: User, shift: PosShift} */
    private function cashierWithTwoVisibleSellingStoresAndActiveSecondShift(): array
    {
        $this->seed(ProductionSeeder::class);

        $company = Company::query()->sole();
        $main = Branch::query()->where('code', 'MAIN')->sole();
        $alternate = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ALT-POS',
            'name_ar' => 'فرع بديل',
            'name_en' => 'Alternate POS Branch',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);
        $mainStore = Store::query()->where('code', 'MAIN-SALES')->sole();
        $alternateStore = Store::query()->create([
            'company_id' => $company->id,
            'branch_id' => $alternate->id,
            'code' => 'ALT-SALES',
            'type' => 'selling',
            'name_ar' => 'متجر بديل',
            'name_en' => 'Alternate Sales Store',
            'status' => 'active',
        ]);
        BranchSellingStore::query()->create([
            'branch_id' => $alternate->id,
            'store_id' => $alternateStore->id,
            'effective_from' => now(),
            'status' => 'active',
        ]);
        $cashier = User::query()->create([
            'name' => 'Shift Context Cashier',
            'username' => 'shift-context-cashier',
            'email' => 'shift-context-cashier@toyjoy.test',
            'password' => 'TestOnly!2026',
            'status' => 'active',
        ]);
        $cashier->roles()->sync([Role::query()->where('code', 'system-administrator')->sole()->id]);
        $cashier->storeScopes()->createMany([
            ['store_id' => $mainStore->id, 'status' => 'active'],
            ['store_id' => $alternateStore->id, 'status' => 'active'],
        ]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $company->id,
            'branch_id' => $alternate->id,
            'store_id' => $alternateStore->id,
            'assigned_user_id' => $cashier->id,
            'code' => 'ALT-DR',
            'name_ar' => 'درج بديل',
            'name_en' => 'Alternate drawer',
            'status' => 'active',
        ]);
        $shift = PosShift::query()->create([
            'branch_id' => $alternate->id,
            'store_id' => $alternateStore->id,
            'cash_drawer_id' => $drawer->id,
            'cashier_id' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0.00',
            'opened_at' => now(),
        ]);
        DB::table('active_pos_shift_assignments')->insert([
            'shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('cashier', 'shift');
    }
}
