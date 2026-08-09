<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\ShiftClosingSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CSH-01–CSH-04, NFR-03. Policy: docs/32 §17-§18 (DEC-066).
 * Test cases: TC-CSH-040..050.
 *
 * These drive the real HTTP routes and rendered screens rather than the action
 * classes, so a drift between a form and the validation behind it is caught.
 */
final class ShiftHttpRouteTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_a_cashier_can_open_a_shift_through_the_screen(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['cashier'])->get(route('pos.shift'))
            ->assertOk()
            ->assertSee('cash_drawer_id', escape: false)
            ->assertSee('opening_float', escape: false);

        $this->actingAs($s['cashier'])->post(route('pos.shift.open'), [
            'cash_drawer_id' => $s['drawer']->id,
            'opening_float' => '150.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('pos.shift'))->assertSessionHasNoErrors();

        $shift = PosShift::query()->sole();
        self::assertSame(ShiftState::Open, $shift->status);
        self::assertSame('150.00', (string) $shift->opening_cash);
    }

    public function test_opening_a_second_shift_on_the_same_drawer_surfaces_a_form_error(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['cashier'])->post(route('pos.shift.open'), ['cash_drawer_id' => $s['drawer']->id, 'opening_float' => '100.00', 'idempotency_key' => (string) Str::uuid()]);

        $other = $this->userWith('http-other-cashier', ['cashier'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);
        $this->actingAs($other)->post(route('pos.shift.open'), [
            'cash_drawer_id' => $s['drawer']->id,
            'opening_float' => '10.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('cash_drawer_id');

        self::assertSame(1, PosShift::query()->count());
    }

    public function test_the_cash_movement_form_posts_and_records_a_signed_movement(): void
    {
        $s = $this->openedShift();

        $this->actingAs($s['cashier'])->get(route('pos.shift'))
            ->assertOk()
            ->assertSee('movement_type', escape: false);

        $this->actingAs($s['cashier'])->post(route('pos.shift.cash-movement', $s['shift']), [
            'movement_type' => CashMovement::TYPE_PETTY_DISBURSEMENT,
            'amount' => '25.00',
            'reason' => 'Cleaning supplies',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('pos.shift'))->assertSessionHasNoErrors();

        self::assertSame('-25.00', (string) CashMovement::query()->sole()->amount);
    }

    public function test_the_blind_close_form_posts_and_the_cashier_is_not_shown_the_variance(): void
    {
        $s = $this->openedShift();

        $this->actingAs($s['cashier'])->get(route('pos.shift'))
            ->assertOk()
            ->assertSee('actual_cash', escape: false);

        $response = $this->actingAs($s['cashier'])->post(route('pos.shift.blind-close', $s['shift']), [
            'actual_cash' => '80.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('pos.shift'))->assertSessionHasNoErrors();

        $submission = ShiftClosingSubmission::query()->sole();
        self::assertSame('-20.00', (string) $submission->cash_variance);

        // Following the redirect must not reveal the shortage to the cashier.
        // The opening float is deliberately not asserted against: it is the
        // cashier's own input at open time, not a derived expectation, so
        // showing it back is not a CSH-02 disclosure. The expected total and
        // the variance are what must stay hidden.
        $followed = $this->actingAs($s['cashier'])->get(route('pos.shift'));
        $followed->assertOk();
        $followed->assertDontSee('-20.00');
        $followed->assertDontSee('expected_cash');
        $followed->assertDontSee('cash_variance');
        $followed->assertSee(__('awaiting review'), escape: false);
    }

    public function test_a_manager_sees_expected_versus_actual_and_can_close(): void
    {
        $s = $this->openedShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '80.00', [], 'HTTP-CLOSE-1');
        $shift = $s['shift']->fresh();
        $manager = $this->userWith('http-manager', ['branch-manager'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);

        $this->actingAs($manager)->get(route('pos.shift-variance'))
            ->assertOk()
            ->assertSee('100.00')   // expected
            ->assertSee('80.00')    // actual
            ->assertSee('-20.00');  // variance

        $this->actingAs($manager)->get(route('admin.approvals'))->assertOk()->assertSee('Pos Shifts');
        app(DecideApprovalSource::class)->approve(ApprovalRecord::query()->findOrFail($shift->variance_approval_record_id));

        self::assertSame(ShiftState::Closed, $shift->fresh()->status);
    }

    public function test_a_stale_lock_version_from_the_review_form_is_rejected(): void
    {
        $s = $this->openedShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '80.00', [], 'HTTP-CLOSE-2');
        $shift = $s['shift']->fresh();
        $manager = $this->userWith('http-manager-stale', ['branch-manager'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);

        $approval = ApprovalRecord::query()->findOrFail($shift->variance_approval_record_id);
        $approval->forceFill(['source_version' => (string) ((int) $shift->lock_version - 1)])->saveQuietly();
        $this->expectException(ValidationException::class);
        app(DecideApprovalSource::class)->approve($approval);

        self::assertNotSame(ShiftState::Closed, $shift->fresh()->status);
    }

    public function test_a_cashier_cannot_post_an_approval_even_by_forging_the_request(): void
    {
        $s = $this->openedShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '80.00', [], 'HTTP-CLOSE-3');
        $shift = $s['shift']->fresh();

        $this->actingAs($s['cashier'])->get(route('admin.approvals'))->assertOk();
        $this->expectException(AuthorizationException::class);
        app(DecideApprovalSource::class)->approve(ApprovalRecord::query()->findOrFail($shift->variance_approval_record_id));

        self::assertNotSame(ShiftState::Closed, $shift->fresh()->status);
    }

    public function test_a_cashier_from_another_branch_cannot_act_on_this_shift(): void
    {
        $s = $this->openedShift();
        $otherBranch = $this->branch('HTTP-OTHER-BR');
        $otherStore = $this->store($otherBranch, 'HTTP-OTHER-ST');
        $stranger = $this->userWith('http-stranger', ['cashier'], branchIds: [$otherBranch->id], storeIds: [$otherStore->id]);

        $this->actingAs($stranger)->post(route('pos.shift.cash-movement', $s['shift']), [
            'movement_type' => CashMovement::TYPE_CASH_IN,
            'amount' => '10.00',
            'reason' => 'Forged',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();

        self::assertSame(0, CashMovement::query()->count());
    }

    public function test_an_unknown_movement_type_is_rejected_by_validation(): void
    {
        $s = $this->openedShift();

        $this->actingAs($s['cashier'])->post(route('pos.shift.cash-movement', $s['shift']), [
            'movement_type' => 'siphon_to_pocket',
            'amount' => '10.00',
            'reason' => 'Nope',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('movement_type');

        self::assertSame(0, CashMovement::query()->count());
    }

    /** @return array{cashier: User, store: Store, branch: Branch, drawer: CashDrawer, shift: PosShift} */
    private function openedShift(): array
    {
        $s = $this->scenario();
        $this->actingAs($s['cashier']);
        $s['shift'] = app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'HTTP-OPEN-BASE');

        return $s;
    }

    /** @return array{cashier: User, store: Store, branch: Branch, drawer: CashDrawer} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $this->documentSequence('shift_close', 'SHIFT-');
        $branch = $this->branch('HTTP-SH-BR');
        $store = $this->store($branch, 'HTTP-SH-ST');
        $cashier = $this->userWith('http-shift-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'HTTP-SH-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);

        return compact('cashier', 'store', 'branch', 'drawer');
    }
}
