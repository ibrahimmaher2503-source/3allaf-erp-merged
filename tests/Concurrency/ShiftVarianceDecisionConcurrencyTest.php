<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Support\Str;

/**
 * Real MariaDB, separate-process proof that the sole canonical ApprovalRecord
 * decision cannot produce conflicting shift outcomes under contention.
 *
 * @group tsk-025
 */
final class ShiftVarianceDecisionConcurrencyTest extends ConcurrencyTestCase
{
    public function test_approve_vs_approve_allows_one_terminal_decision(): void
    {
        $this->assertRace(['approve', 'approve']);
    }

    public function test_approve_vs_recount_allows_one_terminal_decision(): void
    {
        $this->assertRace(['approve', 'recount']);
    }

    public function test_recount_vs_recount_allows_one_terminal_decision(): void
    {
        $this->assertRace(['recount', 'recount']);
    }

    public function test_a_stale_decision_after_a_winner_is_safely_rejected(): void
    {
        [$shift, $approval, $manager] = $this->pendingVariance();
        $first = $this->race([['shift_decision', $this->params($approval, $manager, 'approve')]])[0];
        self::assertTrue($first['ok'] ?? false, json_encode($first));

        $stale = $this->race([['shift_decision', $this->params($approval, $manager, 'recount')]])[0];
        self::assertFalse($stale['ok'] ?? true, json_encode($stale));
        self::assertDoesNotMatchRegularExpression('/SQLSTATE|deadlock/i', (string) ($stale['message'] ?? ''));
        $this->assertReconciled($shift, $approval, 'approved');
    }

    /** @param list<'approve'|'recount'> $decisions */
    private function assertRace(array $decisions): void
    {
        [$shift, $approval, $manager] = $this->pendingVariance();
        $results = $this->race(array_map(fn (string $decision): array => ['shift_decision', $this->params($approval, $manager, $decision)], $decisions));
        self::assertSame(1, collect($results)->filter(fn (array $result): bool => (bool) ($result['ok'] ?? false))->count(), json_encode($results));
        foreach ($results as $result) {
            if (! ($result['ok'] ?? false)) {
                self::assertDoesNotMatchRegularExpression('/SQLSTATE|deadlock/i', (string) ($result['message'] ?? ''));
            }
        }
        $state = $approval->fresh()->approval_state->value;
        self::assertContains($state, ['approved', 'rejected']);
        $this->assertReconciled($shift, $approval, $state);
    }

    /** @return array{PosShift, ApprovalRecord, User} */
    private function pendingVariance(): array
    {
        $this->seedCanonicalAuthorization();
        $this->company()->update(['currency_code' => 'EGP', 'currency_symbol' => 'EGP']);
        $suffix = Str::lower(Str::random(8));
        $branch = $this->branch('RACE-BR-'.$suffix);
        $store = $this->store($branch, 'RACE-ST-'.$suffix);
        $cashier = $this->userWith('race-cashier-'.$suffix, ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $manager = $this->userWith('race-manager-'.$suffix, ['branch-manager'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'RACE-DR-'.$suffix, 'name_ar' => 'درج', 'name_en' => 'Race drawer', 'status' => 'active',
        ]);
        $this->documentSequence('shift_close', 'RACE-');
        PosFinancialSettingVersion::query()->firstOrCreate([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION, 'version' => 1,
        ], ['value' => '0.05', 'value_type' => 'decimal', 'created_by' => $cashier->id]);
        $this->actingAs($cashier);
        $shift = app(OpenShiftAction::class)->execute($cashier, $drawer, '100.00', 'RACE-OPEN-'.$suffix);
        app(SubmitBlindShiftCloseAction::class)->execute($cashier, $shift, '90.00', [], 'RACE-CLOSE-'.$suffix);
        $shift = $shift->fresh();

        return [$shift, ApprovalRecord::query()->findOrFail($shift->variance_approval_record_id), $manager];
    }

    /** @return array<string, mixed> */
    private function params(ApprovalRecord $approval, User $manager, string $decision): array
    {
        return ['approval_id' => $approval->id, 'user_id' => $manager->id, 'decision' => $decision, 'reason' => 'Concurrent recount verification.'];
    }

    private function assertReconciled(PosShift $shift, ApprovalRecord $approval, string $approvalState): void
    {
        $shift = $shift->fresh();
        self::assertSame($approval->id, (int) $shift->variance_approval_record_id);
        self::assertSame('pos_shifts', $approval->fresh()->source_type);
        self::assertSame((string) $shift->id, (string) $approval->fresh()->source_id);
        self::assertSame($approvalState === 'approved' ? ShiftState::Closed : ShiftState::Open, $shift->status);
        self::assertSame(1, AuditLog::query()->where('source_type', PosShift::class)->where('source_id', (string) $shift->id)
            ->whereIn('event', ['close_shift', 'request_shift_recount'])->count());
        self::assertSame(1, AuditLog::query()->where('source_type', ApprovalRecord::class)->where('source_id', (string) $approval->id)
            ->whereIn('event', ['approval_approved', 'approval_rejected'])->count());
    }
}
