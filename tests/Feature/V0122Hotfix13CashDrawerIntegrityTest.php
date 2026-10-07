<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class V0122Hotfix13CashDrawerIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_valid_drawer_opening_keeps_transaction_scope_and_duplicate_guards(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();
        $cashier = $this->cashier();
        $otherCashier = $this->cashier();
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->for($company)->create();
        $store = $this->store($branch);
        $cashier->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $otherCashier->branchScopes()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $drawer = $this->drawer($store);
        $otherDrawer = $this->drawer($store);
        $payload = $this->openingPayload($drawer);
        $beforeShifts = PosShift::query()->count();
        $beforeAssignments = DB::table('active_pos_shift_assignments')->count();
        $beforeAudits = AuditLog::query()->where('event', 'open_shift')->count();
        $safeMessage = 'Unable to complete the request. Please review the data and try again.';

        $this->actingAs($cashier)->post(route('pos.shift.store'), $payload)->assertRedirect(route('pos'));

        $shift = PosShift::query()->where('idempotency_key', 'SHIFT-OPEN:'.$payload['idempotency_key'])->sole();
        self::assertSame(ShiftState::Open, $shift->status);
        self::assertSame('100.00', $shift->opening_cash);
        self::assertSame('EGP', $shift->currency_code);
        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shift->id,
            'cashier_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'cash_drawer_id' => $drawer->id,
            'branch_id' => $store->branch_id,
            'store_id' => $store->id,
        ]);
        $this->assertDatabaseHas('active_pos_shift_assignments', [
            'shift_id' => $shift->id, 'cashier_id' => $cashier->id, 'cash_drawer_id' => $drawer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'open_shift', 'actor_id' => $cashier->id,
            'source_type' => PosShift::class, 'source_id' => (string) $shift->id,
            'branch_id' => $store->branch_id, 'store_id' => $store->id,
        ]);

        $this->post(route('pos.shift.store'), $payload)->assertRedirect(route('pos'));
        $this->from(route('pos.shift'))
            ->post(route('pos.shift.store'), [...$payload, 'opening_float' => '99.00'])
            ->assertRedirectBackWithErrors(['cash_drawer_id' => $safeMessage]);
        $this->post(route('pos.shift.store'), $this->openingPayload($otherDrawer))
            ->assertRedirectBackWithErrors(['cash_drawer_id' => $safeMessage]);
        $this->actingAs($otherCashier)->post(route('pos.shift.store'), $this->openingPayload($drawer))
            ->assertRedirectBackWithErrors(['cash_drawer_id' => $safeMessage]);

        $dispatcher = AuditLog::getEventDispatcher();
        $transactionLevel = DB::transactionLevel();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::created(static function (AuditLog $audit) use ($transactionLevel): void {
            if ($audit->event === 'open_shift') {
                self::assertGreaterThan($transactionLevel, DB::transactionLevel());
                throw new RuntimeException('Injected audit failure');
            }
        });
        try {
            app(OpenShiftAction::class)->execute($otherCashier, $otherDrawer, '100.00', 'ROLLBACK:'.Str::uuid());
            self::fail('An audit failure must roll back the shift and active assignment.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected audit failure', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('pos_shifts', $beforeShifts + 1);
        $this->assertDatabaseCount('active_pos_shift_assignments', $beforeAssignments + 1);
        self::assertSame($beforeAudits + 1, AuditLog::query()->where('event', 'open_shift')->count());
        $this->assertDatabaseMissing('pos_shifts', ['cashier_id' => $otherCashier->id]);
        $this->assertDatabaseMissing('active_pos_shift_assignments', ['cash_drawer_id' => $otherDrawer->id]);
    }

    public function test_every_application_write_derives_company_from_store_and_rejects_mismatch(): void
    {
        $model = $this->source('app/Modules/Platform/Models/CashDrawer.php');
        $save = $this->source('app/Modules/Platform/Actions/SaveCashDrawerAction.php');
        $uat = $this->source('app/Modules/Platform/Services/UatDataset.php');

        self::assertStringContainsString('static::saving(function (CashDrawer $drawer)', $model);
        self::assertStringContainsString("(int) \$store->getAttribute('branch_id') !== \$branchId", $model);
        self::assertStringContainsString("(int) \$store->getAttribute('company_id') !== (int) \$branch->getAttribute('company_id')", $model);
        self::assertStringContainsString("\$drawer->setAttribute('company_id', (int) \$store->getAttribute('company_id'))", $model);
        self::assertStringContainsString("'company_id' => \$store?->company_id ?? \$branch->company_id", $save);
        self::assertStringNotContainsString("'company_id' => \$data", $save);
        self::assertStringContainsString("['company_id'=>(int)\$outlet->company_id,'branch_id'=>(int)\$outlet->branch_id", $uat);
    }

    public function test_legacy_migration_repairs_only_unambiguous_store_ownership_and_fails_closed(): void
    {
        $migration = $this->source('database/migrations/2026_09_04_000107_enforce_cash_drawer_context_integrity.php');

        self::assertStringContainsString("DB::table('cash_drawers')->orderBy('id')", $migration);
        self::assertStringContainsString('$updates[$drawerId] = $storeCompanyId;', $migration);
        self::assertStringContainsString("'drawer_store_branch_mismatch'", $migration);
        self::assertStringContainsString("'store_branch_company_mismatch'", $migration);
        self::assertStringContainsString('Cash drawer integrity audit failed; no drawers were changed.', $migration);
        self::assertLessThan(strpos($migration, 'DB::transaction(function () use ($updates)'), strpos($migration, "if (\$issues !== [])"));
        self::assertStringContainsString("CHECK (company_id > 0)", $migration);
        self::assertStringContainsString("->foreign('company_id')->references('id')->on('companies')->restrictOnDelete()", $migration);
        self::assertStringContainsString("->unsignedBigInteger('company_id')->nullable(false)->change()", $migration);
    }

    public function test_invalid_or_cross_scope_drawers_are_not_selectable_and_return_safe_validation(): void
    {
        app(ProductionSeeder::class)->seedAuthorizationOnly();
        $cashier = $this->cashier();
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->for($company)->create();
        $store = $this->store($branch);
        $cashier->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $drawer = $this->drawer($store);
        $otherStore = $this->store($branch);
        $otherCompany = Company::factory()->create(['currency_code' => 'EGP']);
        $foreignBranch = Branch::factory()->for($otherCompany)->create();
        $foreignStore = $this->store($foreignBranch);
        $crossScopeDrawers = [
            $this->drawer($otherStore),
            $this->drawer($foreignStore),
        ];
        $invalidDrawer = $this->drawer($store);
        DB::table('cash_drawers')->where('id', $invalidDrawer->id)->update(['company_id' => $otherCompany->id]);
        $invalidBranchDrawer = $this->drawer($store);
        DB::table('cash_drawers')->where('id', $invalidBranchDrawer->id)->update(['branch_id' => $foreignBranch->id]);
        $invalidStore = $this->store($branch);
        $invalidStoreDrawer = $this->drawer($invalidStore);
        $cashier->storeScopes()->create(['store_id' => $invalidStore->id, 'status' => 'active']);
        DB::table('stores')->where('id', $invalidStore->id)->update(['company_id' => $otherCompany->id]);
        $sameCompanyOtherBranch = Branch::factory()->for($company)->create();
        $otherBranchStore = $this->store($sameCompanyOtherBranch);
        $storeBranchMismatchDrawer = $this->drawer($store);
        $cashier->storeScopes()->create(['store_id' => $otherBranchStore->id, 'status' => 'active']);
        DB::table('cash_drawers')->where('id', $storeBranchMismatchDrawer->id)->update(['store_id' => $otherBranchStore->id]);
        $inactiveDrawer = $this->drawer($store);
        $inactiveDrawer->update(['status' => 'inactive']);
        $warehouse = $this->store($branch);
        $warehouse->update(['type' => 'warehouse']);
        $cashier->storeScopes()->create(['store_id' => $warehouse->id, 'status' => 'active']);
        $warehouseDrawer = $this->drawer($warehouse);
        $message = 'Unable to complete the request. Please review the data and try again.';
        $beforeShifts = PosShift::query()->count();
        $beforeAssignments = DB::table('active_pos_shift_assignments')->count();
        $beforeAudits = AuditLog::query()->where('event', 'open_shift')->count();

        $this->withoutVite()->actingAs($cashier)->get(route('pos.shift'))
            ->assertOk()->assertViewHas('drawers', fn ($drawers): bool => $drawers->modelKeys() === [$drawer->id]);

        foreach ($crossScopeDrawers as $crossScopeDrawer) {
            $this->post(route('pos.shift.store'), $this->openingPayload($crossScopeDrawer))->assertForbidden();
        }
        foreach ([$invalidDrawer, $invalidBranchDrawer, $invalidStoreDrawer, $storeBranchMismatchDrawer, $warehouseDrawer] as $invalid) {
            $this->from(route('pos.shift'))->post(route('pos.shift.store'), $this->openingPayload($invalid))
                ->assertRedirectBackWithErrors(['cash_drawer_id' => $message]);
        }
        $this->post(route('pos.shift.store'), $this->openingPayload($inactiveDrawer))
            ->assertRedirectBackWithErrors(['cash_drawer_id' => $message]);

        $unprivileged = User::factory()->create(['status' => 'active']);
        $unprivileged->storeScopes()->create(['store_id' => $store->id, 'status' => 'active']);
        $this->actingAs($unprivileged)->post(route('pos.shift.store'), $this->openingPayload($drawer))->assertForbidden();

        $this->assertDatabaseCount('pos_shifts', $beforeShifts);
        $this->assertDatabaseCount('active_pos_shift_assignments', $beforeAssignments);
        self::assertSame($beforeAudits, AuditLog::query()->where('event', 'open_shift')->count());
    }

    private function cashier(): User
    {
        $cashier = User::factory()->create(['status' => 'active']);
        $cashier->roles()->attach(Role::query()->where('code', 'cashier')->firstOrFail());

        return $cashier;
    }

    private function store(Branch $branch): Store
    {
        return Store::factory()->create(['company_id' => $branch->company_id, 'branch_id' => $branch->id]);
    }

    private function drawer(Store $store): CashDrawer
    {
        return CashDrawer::factory()->create([
            'company_id' => $store->company_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
        ]);
    }

    /** @return array{cash_drawer_id: int, opening_float: string, idempotency_key: string, confirm_outlet: string} */
    private function openingPayload(CashDrawer $drawer): array
    {
        return [
            'cash_drawer_id' => $drawer->id,
            'opening_float' => '100.00',
            'idempotency_key' => (string) Str::uuid(),
            'confirm_outlet' => '1',
        ];
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        self::assertIsString($source);

        return $source;
    }
}
