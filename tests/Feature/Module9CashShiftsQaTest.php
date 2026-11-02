<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Services\ShiftExpectedTotalsService;
use App\Modules\Retail\Support\PosContextResolver;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Livewire\Livewire;

final class Module9CashShiftsQaTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        if (getenv('DB_CONNECTION') !== 'mysql' || ! str_starts_with((string) getenv('DB_DATABASE'), 'qa_rajh_') || ! getenv('QA_STORAGE_PATH')) {
            throw new \RuntimeException('Module 9 checks require the dedicated disposable MySQL schema and storage.');
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useStoragePath(getenv('QA_STORAGE_PATH'));
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('en');
        $app->forgetInstance('translation.loader');
        $app->forgetInstance('translator');
        $app->singleton('translation.loader', fn ($app) => new FileLoader(
            $app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']],
        ));

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['pos_sales.view', 'shifts_cash_movements.view', 'shifts_cash_movements.create', 'shifts_cash_movements.submit', 'shifts_cash_movements.approve', 'shifts_cash_movements.print', 'shifts_cash_movements.recount', 'dashboard_reports.view', 'company_settings.view', 'company_settings.edit'] as $code) {
            [$module, $action] = explode('.', $code);
            Permission::query()->firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'status' => 'active', 'sensitivity' => 'normal']);
        }
    }

    public function test_m9_05_approval_modal_displays_stored_counts(): void
    {
        $shift = $this->submittedShift();
        $this->actingAs($this->reviewer());
        $this->get(route('admin.approvals'))->assertOk();
        request()->setLaravelSession(app('session')->driver());

        Livewire::test('platform::system.approval-inbox')
            ->call('showApproval', $shift->variance_approval_record_id)
            ->assertSee('Expected')->assertSee('Actual')->assertSee('Variance')
            ->assertSee('1,000.00')->assertSee('950.00')->assertSee('-50.00')
            ->assertSee('Source')->assertSee('Requester')->assertSee('Requested action')
            ->assertSee('Counted shortage');
    }

    public function test_m9_04_approved_variance_excludes_pending_and_recount_history(): void
    {
        $shift = $this->submittedShift();
        $cashier = $shift->cashier;
        $reviewer = $this->reviewer();
        $this->actingAs($reviewer);
        self::assertSame(0.0, $this->cashReport($reviewer)['kpis']['shift_variance']);
        $shift = app(ReviewShiftVarianceAction::class)->requestRecount($reviewer, $shift, $shift->varianceApprovalRecord, 'Recount requested', $shift->lock_version);
        $this->actingAs($cashier);
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, '980', [], (string) Str::uuid());
        $shift->refresh();
        $this->actingAs($reviewer);
        app(ReviewShiftVarianceAction::class)->approveAndClose($reviewer, $shift, $shift->varianceApprovalRecord, $shift->lock_version);
        self::assertSame(ShiftState::Closed, $shift->fresh()->status);
        $report = $this->cashReport($reviewer);
        self::assertSame(-20.0, $report['kpis']['shift_variance']);
        self::assertSame([-20.0], collect($report['visuals'])->firstWhere('key', 'cash_variance')['series'][0]['data']);
    }

    public function test_m9_08_settings_exposes_the_existing_cash_rounding_control(): void
    {
        $shift = $this->openShift();
        $url = route('pos.financial-readiness', ['company_id' => $shift->store->company_id]);
        $this->get(route('admin.settings', ['tab' => 'payments']))
            ->assertOk()->assertSee($url, false)->assertSee('Cash rounding denomination');
        $this->get($url)->assertOk()->assertSee('Cash rounding denomination')
            ->assertSee(route('admin.settings.pos-financial.save'), false);
        $this->from($url)->post(route('admin.settings.pos-financial.save'), [
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '5', 'notes' => 'Existing approved denomination',
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        self::assertSame('5.0000', PosFinancialSettingRegistry::value(PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION));
    }

    public function test_m9_01_m9_02_header_and_open_link_explain_closing_states(): void
    {
        $shift = $this->openShift();
        foreach ([ShiftState::Open->value => 'Open', ShiftState::ClosingSubmitted->value => 'Not ready', ShiftState::VarianceReview->value => 'Not ready'] as $state => $label) {
            $shift->update(['status' => $state]);
            foreach (['pos', 'pos.shift'] as $route) {
                $response = $this->get(route($route))->assertOk();
                $dom = new \DOMDocument;
                @$dom->loadHTML($response->getContent());
                $expectedLabel = $route === 'pos' && $state === ShiftState::ClosingSubmitted->value ? 'Closing submitted' : ($route === 'pos' && $state === ShiftState::VarianceReview->value ? 'In Review' : $label);
                self::assertSame($expectedLabel, (new \DOMXPath($dom))->evaluate('normalize-space(//header//span[normalize-space(.)="Shift"]/following-sibling::*[1])'));
            }
            if ($state !== ShiftState::Open->value) {
                $this->followingRedirects()->get(route('pos.shift.open'))->assertOk()
                    ->assertSee('Open a shift');
            }
        }
        $shift->update(['status' => ShiftState::Closed]);
        $html = view('layouts.pos', ['title' => 'Closed shift', 'store' => $shift->store, 'shift' => $shift, 'slot' => ''])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        self::assertSame('Closed', (new \DOMXPath($dom))->evaluate('normalize-space(//header//span[normalize-space(.)="Shift"]/following-sibling::*[1])'));
    }

    public function test_m9_03_opening_reports_occupied_drawer_and_confirmation_errors(): void
    {
        $shift = $this->openShift();
        $cashier = $this->reviewer();
        $this->actingAs($cashier);
        $data = ['cash_drawer_id' => $shift->cash_drawer_id, 'opening_float' => '1000', 'idempotency_key' => (string) Str::uuid(), 'confirm_outlet' => '1'];
        $this->from(route('pos.shift'))->post(route('pos.shift.store'), $data)
            ->assertRedirect(route('pos.shift'))
            ->assertSessionHasErrors(['cash_drawer_id' => 'This drawer already has an active shift.']);
        self::assertSame(1, PosShift::query()->where('cash_drawer_id', $shift->cash_drawer_id)->count());
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('pos.shift'))->assertOk()->assertViewHas('shift', null)
            ->assertSee('Every available cash drawer is occupied by an active shift.');
        unset($data['confirm_outlet']);
        $this->from(route('pos.shift'))->post(route('pos.shift.store'), $data)
            ->assertSessionHasErrors(['confirm_outlet' => 'Confirm the sales outlet before opening a shift.']);
        $this->get(route('pos.shift'))->assertOk()->assertSee('Every available cash drawer is occupied by an active shift.');
    }

    public function test_m9_06_movement_screen_and_report_show_existing_actor_and_reference(): void
    {
        $shift = $this->openShift();
        $actor = $shift->cashier;
        app(RecordCashMovementAction::class)->execute($actor, $shift, 'cash_in', '125', 'Additional float', (string) Str::uuid(), 'M9-REF-125');
        app(RecordCashMovementAction::class)->execute($actor, $shift, 'cash_out', '25', 'Petty expense', (string) Str::uuid());
        $expected = app(ShiftExpectedTotalsService::class)->derive($shift);
        self::assertSame('100.00', $expected['cash_movements']);
        self::assertSame(bcadd(bcadd($expected['opening_float'], $expected['cash_sales'], 2), '100.00', 2), $expected['expected_cash']);
        $this->get(route('pos.shift'))->assertOk()->assertSee(route('pos.shift.cash-movement.form', $shift))
            ->assertDontSee('name="movement_type"', false)->assertDontSee('name="amount"', false)
            ->assertDontSee('125.00');
        $this->get(route('pos.shift.cash-movement.form', $shift))->assertOk()->assertSee('Actor')->assertSee('Reference')
            ->assertSee($actor->name)->assertSee('M9-REF-125')->assertSee('Additional float')->assertSee('125.00')->assertSee('—');
        $movements = collect($this->cashReport($actor)['detail_sections'])->firstWhere('key', 'cash_movements');
        self::assertArrayHasKey('actor', $movements['columns']);
        self::assertArrayHasKey('reason', $movements['columns']);
        $row = collect($movements['rows'])->firstWhere('reference', 'M9-REF-125');
        self::assertSame($actor->name, $row['actor']);
        self::assertSame('Additional float', $row['reason']);
        self::assertSame('cash_in', $row['type']);
        self::assertSame(125.0, $row['amount']);
        self::assertNull(collect($movements['rows'])->firstWhere('reason', 'Petty expense')['reference']);
    }

    public function test_cash_expected_uses_signed_movements_and_cash_refunds_but_excludes_card(): void
    {
        $cashier = $this->reviewer();
        $this->actingAs($cashier)->withoutVite();
        $drawer = $this->drawerFor($cashier);
        $cashier->branchScopes()->create(['branch_id' => $drawer->branch_id, 'status' => 'active']);
        $shift = app(OpenShiftAction::class)->execute($cashier, $drawer, '500', (string) Str::uuid());
        $cash = PaymentMethod::factory()->create(['type' => 'cash']);
        $card = PaymentMethod::factory()->create(['type' => 'card']);

        $sale = Sale::query()->create([
            'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id,
            'cash_drawer_id' => $shift->cash_drawer_id, 'shift_id' => $shift->id, 'cashier_id' => $cashier->id,
            'document_number' => 'QA-CASH-'.Str::random(8), 'status' => 'approved',
            'idempotency_key' => (string) Str::uuid(), 'subtotal' => '300', 'total' => '300',
            'paid_total' => '300', 'payable_total' => '300', 'currency_code' => $shift->currency_code,
            'approved_at' => now(),
        ]);
        $sale->payments()->create([
            'payment_method_id' => $cash->id, 'method_code' => $cash->code,
            'method_type' => 'cash', 'amount' => '300', 'tendered_amount' => '300',
            'change_amount' => '0', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $cashier->id,
        ]);
        self::assertSame('300.00', app(ShiftExpectedTotalsService::class)->derive($shift)['cash_sales']);

        $deposit = app(RecordCashMovementAction::class)->execute($cashier, $shift, 'cash_in', '100', 'Deposit', (string) Str::uuid(), 'DEP-100');
        $withdrawal = app(RecordCashMovementAction::class)->execute($cashier, $shift, 'cash_out', '50', 'Withdrawal', (string) Str::uuid(), 'WD-50');
        foreach ([[$deposit, 'cash_in', '100.00', 'Deposit', 'DEP-100'], [$withdrawal, 'cash_out', '-50.00', 'Withdrawal', 'WD-50']] as [$movement, $type, $amount, $reason, $reference]) {
            $movement->refresh();
            self::assertSame($shift->id, $movement->shift_id);
            self::assertSame($shift->cash_drawer_id, $movement->cash_drawer_id);
            self::assertSame($cashier->id, $movement->created_by);
            self::assertSame($type, $movement->movement_type);
            self::assertSame($amount, $movement->amount);
            self::assertSame($reason, $movement->reason);
            self::assertSame($reference, $movement->reference);
            self::assertNotNull($movement->created_at);
        }
        $totals = app(ShiftExpectedTotalsService::class)->derive($shift);
        self::assertSame('850.00', $totals['expected_cash']);
        $cardSale = Sale::query()->create([
            'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id,
            'cash_drawer_id' => $shift->cash_drawer_id, 'shift_id' => $shift->id, 'cashier_id' => $cashier->id,
            'document_number' => 'QA-CARD-'.Str::random(8), 'status' => 'approved',
            'idempotency_key' => (string) Str::uuid(), 'subtotal' => '200', 'total' => '200',
            'paid_total' => '200', 'payable_total' => '200', 'currency_code' => $shift->currency_code,
            'approved_at' => now(),
        ]);
        $cardSale->payments()->create([
            'payment_method_id' => $card->id, 'method_code' => $card->code,
            'method_type' => 'card', 'amount' => '200', 'tendered_amount' => '200',
            'change_amount' => '0', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $cashier->id,
        ]);
        $totals = app(ShiftExpectedTotalsService::class)->derive($shift);
        self::assertSame('850.00', $totals['expected_cash']);
        self::assertSame('200.00', $totals['expected_by_method'][$card->code]);

        $return = RetailReturn::query()->create([
            'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id, 'shift_id' => $shift->id,
            'cash_drawer_id' => $shift->cash_drawer_id, 'cashier_id' => $cashier->id,
            'source_sale_id' => $sale->id, 'return_number' => 'QA-REF-'.Str::random(8),
            'status' => 'completed', 'settlement_type' => 'original_tender', 'reason' => 'Refund',
            'settlement_value' => '40', 'currency_code' => $shift->currency_code,
            'idempotency_key' => (string) Str::uuid(), 'completed_at' => now(),
        ]);
        $return->settlements()->create([
            'payment_method_id' => $cash->id, 'method_code_snapshot' => $cash->code,
            'method_type_snapshot' => 'cash', 'direction' => 'refund', 'amount' => '40',
            'settlement_type' => 'original_tender', 'idempotency_key' => (string) Str::uuid(),
            'created_by' => $cashier->id, 'reason' => 'Refund',
        ]);
        self::assertSame('810.00', app(ShiftExpectedTotalsService::class)->derive($shift)['expected_cash']);

        $this->get(route('pos.shift'))->assertOk()->assertDontSee('850.00')->assertDontSee('Opening float')
            ->assertDontSee('name="movement_type"', false)->assertDontSee('DEP-100');
        $this->get(route('pos.shift.cash-movement.form', $shift))->assertOk()->assertSee('DEP-100');
        $this->followingRedirects()->from(route('pos.shift.cash-movement.form', $shift))
            ->post(route('pos.shift.cash-movement', $shift), [
                'movement_type' => 'cash_in', 'amount' => '0', 'reason' => 'Invalid amount',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertOk()->assertSee('greater than 0');
        $this->actingAs(User::factory()->create(['status' => 'active', 'is_super_admin' => false]))
            ->get(route('pos.shift.cash-movement.form', $shift))->assertForbidden();
    }

    public function test_m9_10_cashier_submits_only_cash_and_can_print_pending_close_sheet(): void
    {
        $shift = $this->openShift();
        $cashier = $shift->cashier;

        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, '1000', [], (string) Str::uuid());
        $shift = $shift->fresh();

        self::assertSame(ShiftState::ClosingSubmitted, $shift->status);
        self::assertSame(0, \DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)->count());
        self::assertSame([], $shift->closingSubmissions()->latest('id')->firstOrFail()->actual_by_method);

        $this->actingAs($cashier);
        $this->get(route('pos.shift.print.thermal', $shift))
            ->assertOk()
            ->assertSee('Pending approval')
            ->assertSee('Physical cash was counted by the cashier')
            ->assertSee('System expected cash');
        $this->get(route('pos.shift.print.a4', $shift))->assertOk()->assertSee('Overall total');
    }

    public function test_m9_11_two_pending_closes_are_allowed_and_third_open_is_blocked(): void
    {
        $cashier = $this->reviewer();
        $first = $this->openShiftForCashier($cashier);
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $first, '1000', [], (string) Str::uuid());
        $second = $this->openShiftForCashier($cashier);
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $second, '1000', [], (string) Str::uuid());

        $thirdDrawer = $this->drawerFor($cashier);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('two previous shifts awaiting approval');
        app(OpenShiftAction::class)->execute($cashier, $thirdDrawer, '1000', (string) Str::uuid());
    }

    public function test_two_cashiers_can_open_separate_drawers_and_pos_resolves_each_own_shift(): void
    {
        $cashierA = $this->reviewer();
        $cashierB = $this->reviewer();
        $drawerA = $this->drawerFor($cashierA);
        $drawerB = CashDrawer::factory()->create([
            'company_id' => $drawerA->company_id, 'branch_id' => $drawerA->branch_id,
            'store_id' => $drawerA->store_id, 'assigned_user_id' => $cashierB->id,
        ]);

        $this->actingAs($cashierA);
        $shiftA = app(OpenShiftAction::class)->execute($cashierA, $drawerA, '100.00', (string) Str::uuid());
        self::assertSame($shiftA->id, app(PosContextResolver::class)->resolve($cashierA)->shift?->id);

        $this->actingAs($cashierB);
        try {
            app(OpenShiftAction::class)->execute($cashierB, $drawerA, '300.00', (string) Str::uuid());
            self::fail('An occupied physical drawer was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        $shiftB = app(OpenShiftAction::class)->execute($cashierB, $drawerB, '200.00', (string) Str::uuid());
        self::assertSame($shiftB->id, app(PosContextResolver::class)->resolve($cashierB)->shift?->id);
        self::assertSame($shiftA->id, app(PosContextResolver::class)->resolve($cashierA)->shift?->id);
        self::assertSame('100.00', app(ShiftExpectedTotalsService::class)->derive($shiftA)['expected_cash']);
        self::assertSame('200.00', app(ShiftExpectedTotalsService::class)->derive($shiftB)['expected_cash']);

    }

    public function test_shift_receipts_include_completed_return_and_original_receipt(): void
    {
        $shift = $this->openShift();
        $sale = Sale::query()->create([
            'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id, 'cash_drawer_id' => $shift->cash_drawer_id,
            'shift_id' => $shift->id, 'cashier_id' => $shift->cashier_id, 'status' => 'approved',
            'document_number' => 'QA-SHIFT-SALE', 'idempotency_key' => (string) Str::uuid(),
            'subtotal' => '50.00', 'discount_total' => '0.00', 'total' => '50.00', 'paid_total' => '50.00',
            'payable_total' => '50.00', 'currency_code' => $shift->currency_code, 'approved_at' => now(),
        ]);
        RetailReturn::query()->create([
            'branch_id' => $shift->branch_id, 'store_id' => $shift->store_id, 'cash_drawer_id' => $shift->cash_drawer_id,
            'shift_id' => $shift->id, 'cashier_id' => $shift->cashier_id, 'source_sale_id' => $sale->id,
            'status' => 'completed', 'return_number' => 'QA-SHIFT-RETURN', 'settlement_type' => 'cash_refund', 'reason' => 'QA return',
            'settlement_value' => '10.00', 'currency_code' => $shift->currency_code,
            'idempotency_key' => (string) Str::uuid(), 'completed_at' => now(),
        ]);

        $this->get(route('pos.shift.receipts', $shift))->assertOk()
            ->assertSee('QA-SHIFT-SALE')->assertSee('QA-SHIFT-RETURN')->assertSee('Return');
    }

    public function test_m9_12_final_approval_print_shows_approved_status_and_actor(): void
    {
        $shift = $this->openShift();
        $cashier = $shift->cashier;
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, '1000', [], (string) Str::uuid());
        $shift->refresh();
        $reviewer = $this->reviewer();
        $this->actingAs($reviewer);
        $closed = app(ReviewShiftVarianceAction::class)->approveAndClose($reviewer, $shift, $shift->varianceApprovalRecord, $shift->lock_version);

        $this->get(route('pos.shift.print.a4', $closed))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee($reviewer->name)
            ->assertSee('Final approval recorded in the system.');
    }

    private function reviewer(): User
    {
        return User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
    }

    private function openShift(): PosShift
    {
        return $this->openShiftForCashier($this->reviewer());
    }

    private function openShiftForCashier(User $cashier): PosShift
    {
        $drawer = $this->drawerFor($cashier);
        $this->actingAs($cashier);

        return app(OpenShiftAction::class)->execute($cashier, $drawer, '1000', (string) Str::uuid());
    }

    private function drawerFor(User $cashier): CashDrawer
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);

        return CashDrawer::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'store_id' => $store->id]);
    }

    private function submittedShift(): PosShift
    {
        $shift = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($shift->cashier, $shift, '950', [], (string) Str::uuid(), 'Counted shortage');

        return $shift->fresh();
    }

    /** @return array<string, mixed> */
    private function cashReport(User $user): array
    {
        return app(ReportSnapshot::class)->execute($user, ['module' => 'cash', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]);
    }
}
