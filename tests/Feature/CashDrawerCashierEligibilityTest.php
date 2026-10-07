<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\SaveCashDrawerAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class CashDrawerCashierEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_drawer_assignment_filters_and_rejects_ineligible_cashiers_without_writing(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();
        $cashierRole = Role::query()->where('code', 'cashier')->where('status', 'active')->firstOrFail();

        $suffix = Str::upper(Str::random(8));
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['status' => 'active']);
        $store = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'status' => 'active',
            'type' => 'selling',
        ]);
        $otherStore = Store::factory()->for($branch)->create([
            'company_id' => $company->id,
            'status' => 'active',
            'type' => 'selling',
        ]);
        $drawer = CashDrawer::factory()->for($store)->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'name_en' => 'BUG04 '.$suffix,
        ]);

        $eligibleCashier = $this->createCashier('Eligible '.$suffix, $cashierRole);
        $eligibleCashier->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        $inactiveCashier = $this->createCashier('Inactive '.$suffix, $cashierRole, 'inactive');
        $inactiveCashier->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        $rolelessUser = User::factory()->create(['name' => 'Roleless '.$suffix, 'status' => 'active']);
        $rolelessUser->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);

        $outOfScopeCashier = $this->createCashier('Out of scope '.$suffix, $cashierRole);
        $outOfScopeCashier->storeScopes()->create(['store_id' => $otherStore->id, 'status' => 'active']);

        $this->actingAs($actor)->withoutVite();
        Livewire::test('platform::admin.drawers')
            ->call('openCreateDrawerModal')
            ->set('drawerForm.branch_id', (string) $branch->id)
            ->set('drawerForm.store_id', (string) $store->id)
            ->assertSee($eligibleCashier->name)
            ->assertDontSee($inactiveCashier->name)
            ->assertDontSee($rolelessUser->name)
            ->assertDontSee($outOfScopeCashier->name);

        $action = app(SaveCashDrawerAction::class);
        $baseline = $this->drawerPayload($drawer, $branch, $store);
        $auditCount = AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->where('event', 'update_cash_drawer')
            ->count();

        Livewire::test('platform::admin.drawers')
            ->call('openEditDrawerModal', $drawer->id)
            ->set('drawerForm.assigned_user_id', (string) $inactiveCashier->id)
            ->call('saveDrawer')
            ->assertHasErrors(['drawerForm.assigned_user_id']);

        foreach ([$inactiveCashier, $rolelessUser, $outOfScopeCashier] as $ineligibleCashier) {
            try {
                $action->execute([...$baseline, 'assigned_user_id' => $ineligibleCashier->id], $drawer->id);
                self::fail('An inactive, roleless, or out-of-scope user must not be assigned as the drawer cashier.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('assigned_user_id', $exception->errors());
            }
        }

        self::assertNull($drawer->fresh()->assigned_user_id);
        self::assertSame((int) $drawer->lock_version, (int) $drawer->fresh()->lock_version);
        self::assertSame($auditCount, AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->where('event', 'update_cash_drawer')
            ->count());

        $saved = $action->execute([...$baseline, 'assigned_user_id' => $eligibleCashier->id], $drawer->id);

        self::assertSame($eligibleCashier->id, (int) $saved->assigned_user_id);
        self::assertSame((int) $drawer->lock_version + 1, (int) $saved->lock_version);
        self::assertSame($auditCount + 1, AuditLog::query()
            ->where('source_type', CashDrawer::class)
            ->where('source_id', (string) $drawer->id)
            ->where('event', 'update_cash_drawer')
            ->count());
    }

    private function createCashier(string $name, Role $role, string $status = 'active'): User
    {
        $cashier = User::factory()->create(['name' => $name, 'status' => $status]);
        $cashier->roles()->attach($role);

        return $cashier;
    }

    /** @return array<string, mixed> */
    private function drawerPayload(CashDrawer $drawer, Branch $branch, Store $store): array
    {
        return [
            'branch_id' => $branch->id,
            'store_id' => $store->id,
            'assigned_user_id' => '',
            'code' => $drawer->code,
            'name_ar' => $drawer->name_ar,
            'name_en' => $drawer->name_en,
            'status' => $drawer->status,
            'policy_notes' => $drawer->policy_notes,
            'lock_version' => $drawer->lock_version,
        ];
    }
}
