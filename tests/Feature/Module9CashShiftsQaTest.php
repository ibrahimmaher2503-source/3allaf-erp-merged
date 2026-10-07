<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Queries\ReportSnapshot;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
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
        if (getenv('DB_DATABASE') !== 'qa_rajh_m9_cash_shifts_20261007' || ! getenv('QA_STORAGE_PATH')) {
            throw new \RuntimeException('Module 9 checks require the dedicated disposable MySQL schema and storage.');
        }
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useStoragePath(getenv('QA_STORAGE_PATH'));
        $app->make(Kernel::class)->bootstrap();
        $app->setLocale('zz');
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
        foreach (['pos_sales.view', 'shifts_cash_movements.view', 'shifts_cash_movements.create', 'shifts_cash_movements.submit', 'shifts_cash_movements.approve', 'shifts_cash_movements.recount', 'dashboard_reports.view', 'company_settings.view', 'company_settings.edit'] as $code) {
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

    private function reviewer(): User
    {
        return User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
    }

    private function openShift(): PosShift
    {
        $cashier = $this->reviewer();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $drawer = CashDrawer::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'store_id' => $store->id]);
        $this->actingAs($cashier);

        return app(OpenShiftAction::class)->execute($cashier, $drawer, '1000', (string) Str::uuid());
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
