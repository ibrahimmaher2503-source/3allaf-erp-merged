<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RecordCashMovementAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Actions\ReviewShiftVarianceAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\ShiftClosingSubmission;
use App\Modules\Retail\Services\ShiftExpectedTotalsService;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CSH-01–CSH-04, NFR-01, NFR-03, NFR-06. Policy: docs/32 (DEC-066).
 * Test cases: TC-CSH-001..030.
 */
final class ShiftCashLifecycleTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    // ---- Opening and exclusivity (docs/32 §6) -----------------------------

    public function test_opening_a_shift_records_the_float_and_audits_it(): void
    {
        $s = $this->scenario();

        $shift = app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-1');

        self::assertSame(ShiftState::Open, $shift->status);
        self::assertSame('100.00', (string) $shift->opening_cash);
        self::assertSame($s['cashier']->id, (int) $shift->opened_by);
        self::assertSame(1, AuditLog::query()->where('event', 'open_shift')->count());
    }

    public function test_a_drawer_cannot_hold_two_active_shifts(): void
    {
        $s = $this->scenario();
        app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-1');

        $other = $this->userWith('other-cashier', ['cashier'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);
        $this->actingAs($other);

        $this->expectException(InvalidArgumentException::class);
        app(OpenShiftAction::class)->execute($other, $s['drawer'], '50.00', 'OPEN-2');
    }

    public function test_a_cashier_cannot_hold_two_active_shifts(): void
    {
        $s = $this->scenario();
        app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-1');

        $secondDrawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $s['branch']->id, 'store_id' => $s['store']->id,
            'assigned_user_id' => $s['cashier']->id, 'code' => 'SH-DR-2', 'name_ar' => 'درج', 'name_en' => 'Drawer 2', 'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(OpenShiftAction::class)->execute($s['cashier'], $secondDrawer, '50.00', 'OPEN-3');
    }

    public function test_an_inactive_drawer_cannot_open_a_shift(): void
    {
        $s = $this->scenario();
        $s['drawer']->update(['status' => 'inactive']);

        $this->expectException(InvalidArgumentException::class);
        app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-4');
    }

    public function test_a_negative_opening_float_is_rejected(): void
    {
        $s = $this->scenario();

        $this->expectException(InvalidArgumentException::class);
        app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '-1.00', 'OPEN-5');
    }

    public function test_opening_is_idempotent_on_replay(): void
    {
        $s = $this->scenario();
        $action = app(OpenShiftAction::class);

        $first = $action->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-IDEM');
        $replay = $action->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-IDEM');

        self::assertTrue($first->is($replay));
        self::assertSame(1, PosShift::query()->count());
    }

    // ---- Cash movements (docs/32 §8) --------------------------------------

    public function test_cash_out_types_are_signed_negative_regardless_of_input(): void
    {
        $s = $this->openShift();

        $in = app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '50.00', 'Top up', 'MV-1');
        $out = app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_PETTY_DISBURSEMENT, '20.00', 'Taxi', 'MV-2');

        self::assertSame('50.00', (string) $in->amount);
        self::assertSame('-20.00', (string) $out->amount, 'An outflow must reduce the drawer even though the caller passed a positive magnitude.');
    }

    public function test_a_cash_movement_requires_a_reason(): void
    {
        $s = $this->openShift();

        $this->expectException(InvalidArgumentException::class);
        app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '10.00', '   ', 'MV-3');
    }

    public function test_a_cash_movement_is_immutable(): void
    {
        $s = $this->openShift();
        $movement = app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '10.00', 'Top up', 'MV-4');

        $this->expectException(LogicException::class);
        $movement->update(['amount' => '999.00']);
    }

    public function test_a_cash_movement_replay_is_idempotent(): void
    {
        $s = $this->openShift();
        $action = app(RecordCashMovementAction::class);

        $first = $action->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '10.00', 'Top up', 'MV-IDEM');
        $replay = $action->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '10.00', 'Top up', 'MV-IDEM');

        self::assertTrue($first->is($replay));
        self::assertSame(1, CashMovement::query()->count());
    }

    // ---- Expected totals (docs/32 §9) -------------------------------------

    public function test_expected_cash_is_float_plus_cash_sales_plus_movements(): void
    {
        $s = $this->openShift();
        $this->sell($s, '2', '30.00', 'SALE-A');
        app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_CASH_IN, '25.00', 'Top up', 'MV-5');
        app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift'], CashMovement::TYPE_SAFE_DEPOSIT, '40.00', 'Drop', 'MV-6');

        $expected = app(ShiftExpectedTotalsService::class)->derive($s['shift']->fresh());

        // 100 float + 30 cash sale + 25 in - 40 deposit = 115.00
        self::assertSame('115.00', $expected['expected_cash']);
        self::assertSame('30.00', $expected['cash_sales']);
        self::assertSame('-15.00', $expected['cash_movements']);
    }

    public function test_electronic_payments_are_expected_per_method_and_not_as_cash(): void
    {
        $s = $this->openShift();
        $this->sellSplit($s);

        $expected = app(ShiftExpectedTotalsService::class)->derive($s['shift']->fresh());

        self::assertSame('110.00', $expected['expected_cash'], 'Only the cash leg of the split reaches the drawer.');
        self::assertSame(['card' => '20.00'], $expected['expected_by_method']);
    }

    public function test_an_unsettled_sale_does_not_inflate_the_expectation(): void
    {
        $s = $this->openShift();
        // A suspended sale has tendered nothing.
        app(RetailSaleAction::class)->create($s['cashier'], $s['store'], [['product_id' => $s['product']->id, 'quantity' => '1']], 'SALE-SUSP', true);

        $expected = app(ShiftExpectedTotalsService::class)->derive($s['shift']->fresh());

        self::assertSame('100.00', $expected['expected_cash']);
    }

    // ---- Blind close and variance (docs/32 §10-§12) ------------------------

    public function test_an_exact_count_settles_without_variance_review(): void
    {
        $s = $this->openShift();
        $this->sell($s, '2', '30.00', 'SALE-B');

        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '130.00', [], 'CLOSE-1');
        $submission = ShiftClosingSubmission::query()->sole();

        self::assertSame('0.00', (string) $submission->total_variance);
        self::assertSame('130.00', (string) $submission->expected_cash);
        self::assertSame(ShiftState::ClosingSubmitted, $s['shift']->fresh()->status);
    }

    public function test_a_shortage_routes_to_variance_review_with_a_negative_sign(): void
    {
        $s = $this->openShift();
        $this->sell($s, '2', '30.00', 'SALE-C');

        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '120.00', [], 'CLOSE-2');
        $submission = ShiftClosingSubmission::query()->sole();

        // variance = actual - expected (docs/32 §12).
        self::assertSame('-10.00', (string) $submission->cash_variance);
        self::assertSame('-10.00', (string) $submission->total_variance);
        self::assertSame(ShiftState::VarianceReview, $s['shift']->fresh()->status);
    }

    public function test_an_overage_is_positive(): void
    {
        $s = $this->openShift();

        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '105.00', [], 'CLOSE-3');
        $submission = ShiftClosingSubmission::query()->sole();

        self::assertSame('5.00', (string) $submission->cash_variance);
    }

    public function test_a_method_counted_but_not_expected_is_still_a_variance(): void
    {
        $s = $this->openShift();

        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '100.00', ['card' => '15.00'], 'CLOSE-4');
        $submission = ShiftClosingSubmission::query()->sole();

        self::assertSame(['card' => '15.00'], $submission->method_variance);
        self::assertSame('15.00', (string) $submission->total_variance);
    }

    public function test_a_duplicate_submission_is_idempotent(): void
    {
        $s = $this->openShift();
        $action = app(SubmitBlindShiftCloseAction::class);

        $first = $action->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-IDEM');
        $replay = $action->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-IDEM');

        self::assertSame($first->submissionId, $replay->submissionId);
        self::assertSame(1, ShiftClosingSubmission::query()->count());
    }

    public function test_a_submitted_close_is_immutable(): void
    {
        $s = $this->openShift();
        $result = app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-5');
        $submission = ShiftClosingSubmission::query()->findOrFail($result->submissionId);

        $this->expectException(LogicException::class);
        $submission->update(['actual_cash' => '999.00']);
    }

    public function test_a_submitted_shift_accepts_no_further_sales_or_cash_movements(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-6');

        try {
            app(RecordCashMovementAction::class)->execute($s['cashier'], $s['shift']->fresh(), CashMovement::TYPE_CASH_IN, '10.00', 'Late', 'MV-LATE');
            self::fail('A submitted shift must not accept a cash movement.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, CashMovement::query()->count());
        }

        // docs/32 §16 — a sale racing the close must be rejected, not landed
        // after the expected snapshot.
        $this->expectException(\RuntimeException::class);
        $this->sell($s, '1', '15.00', 'SALE-LATE');
    }

    // ---- Review, recount, closure (docs/32 §13-§14) ------------------------

    public function test_a_cashier_cannot_approve_their_own_variance(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '90.00', [], 'CLOSE-7');
        $shift = $s['shift']->fresh();

        // Give the cashier the approve permission to prove separation of duties
        // is enforced by the action and not merely by the permission grant.
        $s['cashier']->roles()->detach();
        $this->grantAll($s['cashier'], ['shifts_cash_movements.approve', 'shifts_cash_movements.view']);

        $this->expectException(AuthorizationException::class);
        app(ReviewShiftVarianceAction::class)->approveAndClose($s['cashier']->fresh(), $shift, $this->pendingApproval($shift), (int) $shift->lock_version);
    }

    public function test_a_manager_approves_and_closes_with_a_document_number(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '90.00', [], 'CLOSE-8');
        $shift = $s['shift']->fresh();
        $manager = $this->manager($s);
        $this->actingAs($manager);

        $closed = app(ReviewShiftVarianceAction::class)->approveAndClose($manager, $shift, $this->pendingApproval($shift), (int) $shift->lock_version, 'Counted twice with supervisor.');

        self::assertSame(ShiftState::Closed, $closed->status);
        self::assertNotNull($closed->closing_document_number);
        self::assertSame($manager->id, (int) $closed->variance_approved_by);
        self::assertNotNull($closed->closed_at);
        self::assertSame(1, AuditLog::query()->where('event', 'close_shift')->count());
    }

    public function test_a_stale_lock_version_is_rejected(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '90.00', [], 'CLOSE-9');
        $shift = $s['shift']->fresh();
        $manager = $this->manager($s);
        $this->actingAs($manager);

        $this->expectException(ValidationException::class);
        app(ReviewShiftVarianceAction::class)->approveAndClose($manager, $shift, $this->pendingApproval($shift), (int) $shift->lock_version - 1);
    }

    public function test_a_recount_returns_the_shift_to_open_and_keeps_the_first_submission(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '90.00', [], 'CLOSE-10');
        $shift = $s['shift']->fresh();
        $manager = $this->manager($s);
        $this->actingAs($manager);
        $approval = $this->pendingApproval($shift);

        $reopened = app(ReviewShiftVarianceAction::class)->requestRecount($manager, $shift, $approval, 'Please recount the 50s.', (int) $shift->lock_version);

        self::assertSame(ShiftState::Open, $reopened->status);
        self::assertSame(1, (int) $reopened->recount_count);
        self::assertNull($reopened->submitted_at);

        // The original count survives as attempt 1; the recount becomes attempt 2.
        $this->actingAs($s['cashier']);
        $second = app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $reopened, '100.00', [], 'CLOSE-11');
        self::assertSame(2, $second->attempt);
        self::assertSame(2, ShiftClosingSubmission::query()->count());
        self::assertSame('90.00', (string) ShiftClosingSubmission::query()->where('attempt', 1)->sole()->actual_cash);
    }

    public function test_a_closed_shift_cannot_be_closed_again(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-12');
        $shift = $s['shift']->fresh();
        $manager = $this->manager($s);
        $this->actingAs($manager);
        $closed = app(ReviewShiftVarianceAction::class)->approveAndClose($manager, $shift, $this->pendingApproval($shift), (int) $shift->lock_version);

        $this->expectException(InvalidArgumentException::class);
        app(ReviewShiftVarianceAction::class)->approveAndClose($manager, $closed, $this->pendingApproval($closed), (int) $closed->lock_version);
    }

    public function test_a_closed_shift_frees_the_drawer_for_a_new_shift(): void
    {
        $s = $this->openShift();
        app(SubmitBlindShiftCloseAction::class)->execute($s['cashier'], $s['shift'], '100.00', [], 'CLOSE-13');
        $shift = $s['shift']->fresh();
        $manager = $this->manager($s);
        $this->actingAs($manager);
        app(ReviewShiftVarianceAction::class)->approveAndClose($manager, $shift, $this->pendingApproval($shift), (int) $shift->lock_version);

        $this->actingAs($s['cashier']);
        $next = app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '75.00', 'OPEN-NEXT');

        self::assertSame(ShiftState::Open, $next->status);
        self::assertSame(2, PosShift::query()->count());
    }

    // ---- Authorization and isolation --------------------------------------

    public function test_a_user_without_shift_permission_cannot_open_one(): void
    {
        $s = $this->scenario();
        $stranger = $this->userWith('no-shift-perms', ['stock-counter'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);
        $this->actingAs($stranger);

        $this->expectException(HttpException::class);
        app(OpenShiftAction::class)->execute($stranger, $s['drawer'], '10.00', 'OPEN-DENY');
    }

    public function test_a_cashier_cannot_open_a_shift_on_an_out_of_scope_drawer(): void
    {
        $s = $this->scenario();
        $otherBranch = $this->branch('SH-OTHER-BR');
        $otherStore = $this->store($otherBranch, 'SH-OTHER-ST');
        $foreignDrawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $otherBranch->id, 'store_id' => $otherStore->id,
            'assigned_user_id' => null, 'code' => 'SH-OTHER-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);

        $this->actingAs($s['cashier']);
        $this->expectException(HttpException::class);
        app(OpenShiftAction::class)->execute($s['cashier'], $foreignDrawer, '10.00', 'OPEN-SCOPE');
    }

    // ---- Fixtures ---------------------------------------------------------

    /** @param list<string> $codes */
    private function grantAll(User $user, array $codes): void
    {
        $role = Role::query()->create([
            'code' => 'tmp-'.uniqid(), 'name_ar' => 'مؤقت', 'name_en' => 'Temp', 'status' => 'active',
        ]);
        $permissionIds = Permission::query()->whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id);
    }

    /** @param array{branch: Branch, store: Store} $s */
    private function manager(array $s): User
    {
        return $this->userWith('shift-manager-'.uniqid(), ['branch-manager'], branchIds: [$s['branch']->id], storeIds: [$s['store']->id]);
    }

    private function pendingApproval(PosShift $shift): ApprovalRecord
    {
        return ApprovalRecord::query()->findOrFail($shift->variance_approval_record_id);
    }

    /**
     * @param  array{cashier: User, store: Store, product: Product, cash: PaymentMethod}  $s
     */
    private function sell(array $s, string $quantity, string $amount, string $key): void
    {
        $this->actingAs($s['cashier']);
        app(RetailSaleAction::class)->create(
            $s['cashier'],
            $s['store'],
            [['product_id' => $s['product']->id, 'quantity' => $quantity]],
            $key,
            false,
            [['method' => $s['cash'], 'amount' => $amount]],
        );
    }

    /** @param array{cashier: User, store: Store, product: Product, cash: PaymentMethod, card: PaymentMethod} $s */
    private function sellSplit(array $s): void
    {
        $this->actingAs($s['cashier']);
        app(RetailSaleAction::class)->create(
            $s['cashier'],
            $s['store'],
            [['product_id' => $s['product']->id, 'quantity' => '2']],
            'SALE-SPLIT',
            false,
            [
                ['method' => $s['card'], 'amount' => '20.00', 'evidence_reference' => 'TERM-1'],
                ['method' => $s['cash'], 'amount' => '10.00'],
            ],
        );
    }

    /** @return array{cashier: User, store: Store, branch: Branch, drawer: CashDrawer, product: Product, cash: PaymentMethod, card: PaymentMethod, shift: PosShift} */
    private function openShift(): array
    {
        $s = $this->scenario();
        $this->actingAs($s['cashier']);
        $s['shift'] = app(OpenShiftAction::class)->execute($s['cashier'], $s['drawer'], '100.00', 'OPEN-BASE');

        return $s;
    }

    /** @return array{cashier: User, store: Store, branch: Branch, drawer: CashDrawer, product: Product, cash: PaymentMethod, card: PaymentMethod} */
    private function scenario(): array
    {
        $this->seedCanonicalAuthorization();
        $this->documentSequence('retail_sale', 'SALE-');
        $this->documentSequence('shift_close', 'SHIFT-');
        $branch = $this->branch('SH-BR');
        $store = $this->store($branch, 'SH-ST');
        $cashier = $this->userWith('shift-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashier->id, 'code' => 'SH-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        $category = Category::query()->create(['code' => 'SH-CAT', 'name_ar' => 'فئة', 'name_en' => 'Category', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => 'SH-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active']);
        StockBalance::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '20', 'reserved' => '0',
            'in_transit' => '0', 'average_cost' => '10', 'total_value' => '200', 'version' => 1,
        ]);
        $priceList = PriceList::query()->create([
            'company_id' => $this->company()->id, 'code' => 'SH-PRICE', 'name_ar' => 'سعر', 'name_en' => 'Price', 'status' => 'active',
        ]);
        $version = PriceVersion::query()->create([
            'price_list_id' => $priceList->id, 'version' => 1, 'state' => 'approved', 'source_type' => 'manual',
            'approved_by' => $cashier->id, 'approved_at' => now(), 'effective_from' => now()->subMinute(), 'lock_version' => 1,
        ]);
        PriceLine::query()->create([
            'price_version_id' => $version->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'branch_id' => $branch->id, 'amount' => '15.000', 'active_key' => $product->id.':'.$store->id,
        ]);
        $cash = PaymentMethod::query()->create([
            'code' => 'cash', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active',
        ]);
        $card = PaymentMethod::query()->create([
            'code' => 'card', 'name_ar' => 'بطاقة', 'name_en' => 'Card', 'type' => 'manual', 'requires_evidence' => false, 'status' => 'active',
        ]);

        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::CASH_ROUNDING_DENOMINATION,
            'value' => '0.05',
            'value_type' => 'decimal',
            'version' => 1,
            'created_by' => $cashier->id,
        ]);

        return compact('cashier', 'store', 'branch', 'drawer', 'product', 'cash', 'card');
    }
}
