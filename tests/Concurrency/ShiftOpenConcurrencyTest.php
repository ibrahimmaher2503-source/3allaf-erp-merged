<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;

/**
 * Requirements: CSH-01, NFR-01. Policy: docs/32 §6, §16 (DEC-066).
 *
 * Drawer active-shift uniqueness is checked in application code under a row
 * lock rather than by a database constraint, because MySQL/MariaDB does not
 * expresses "at most one row whose status is in this set" as a partial unique
 * index portably. That makes it exactly the kind of guard a single-process
 * test cannot prove: two overlapping transactions are required.
 */
final class ShiftOpenConcurrencyTest extends ConcurrencyTestCase
{
    public function test_two_cashiers_racing_the_same_drawer_produce_exactly_one_shift(): void
    {
        $branch = $this->branch('RACE-SH-BR');
        $store = $this->store($branch, 'RACE-SH-ST');
        $cashierA = $this->userWith('race-cashier-a', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $cashierB = $this->userWith('race-cashier-b', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $cashierA->id, 'code' => 'RACE-SH-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);

        $results = $this->race([
            ['shift_open', ['user_id' => $cashierA->id, 'cash_drawer_id' => $drawer->id, 'opening_float' => '100.00', 'idempotency_key' => 'RACE-SH-A']],
            ['shift_open', ['user_id' => $cashierB->id, 'cash_drawer_id' => $drawer->id, 'opening_float' => '200.00', 'idempotency_key' => 'RACE-SH-B']],
        ]);

        $winners = array_filter($results, static fn (array $r): bool => $r['ok'] === true);
        $losers = array_filter($results, static fn (array $r): bool => $r['ok'] === false);

        self::assertCount(1, $winners, 'Exactly one racer may open the shift.');
        self::assertCount(1, $losers, 'The other racer must be rejected, not silently succeed.');

        $active = PosShift::query()
            ->where('cash_drawer_id', $drawer->id)
            ->where('status', ShiftState::Open->value)
            ->get();

        self::assertCount(1, $active, 'The drawer must hold exactly one open shift after the race.');
    }
}
