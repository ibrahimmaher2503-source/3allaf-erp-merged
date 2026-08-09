<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: CSH-01..CSH-04, POS-01..POS-05 offline boundary, NFR-04.
 * These assertions cover the implemented Local/Dev readiness boundary only;
 * shift mutation and offline queue/sync remain intentionally unimplemented.
 */
final class CashShiftOfflineBoundaryTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_shift_readiness_does_not_expose_expected_or_actual_amounts(): void
    {
        $administrator = $this->administrator('shift-boundary-admin');
        $branch = $this->branch('SHIFT-BOUNDARY-BR');
        $store = $this->store($branch, 'SHIFT-BOUNDARY-ST');
        $drawer = CashDrawer::query()->create([
            'company_id' => $this->company()->id, 'branch_id' => $branch->id, 'store_id' => $store->id,
            'assigned_user_id' => $administrator->id, 'code' => 'SHIFT-BOUNDARY-DR', 'name_ar' => 'درج', 'name_en' => 'Drawer', 'status' => 'active',
        ]);
        PosShift::query()->create([
            'branch_id' => $branch->id, 'store_id' => $store->id, 'cash_drawer_id' => $drawer->id,
            'cashier_id' => $administrator->id, 'status' => 'open', 'opening_cash' => '1234.56', 'opened_at' => now(),
        ]);

        $response = $this->actingAs($administrator)->get(route('pos.shift-readiness'));
        $response->assertOk()
            ->assertSee('Blind close is preserved')
            ->assertSee('Expected amounts are not rendered')
            ->assertSee('Your open local shifts')
            ->assertDontSee('1234.56')
            ->assertDontSee('opening_cash')
            ->assertDontSee('expected_cash');
    }

    public function test_offline_readiness_is_explicitly_pending_and_has_no_transactional_surface(): void
    {
        $administrator = $this->administrator('offline-boundary-admin');

        $response = $this->actingAs($administrator)->get(route('pos.offline-readiness'));
        $response->assertOk()
            ->assertSee('TSK-026 Offline Readiness')
            ->assertSee('Transactional offline POS is disabled by default')
            ->assertSee('OFF-01 / PENDING')
            ->assertSee('OFF-05 / PENDING')
            ->assertSee('No offline queue, sync, replay, conflict, or transaction is enabled here.')
            ->assertDontSee('offline/sync')
            ->assertDontSee('offline/queue/approve');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
