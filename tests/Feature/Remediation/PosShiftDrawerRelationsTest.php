<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use App\Models\User;
use App\Modules\Platform\Models\CashDrawer;
use Tests\TestCase;

final class PosShiftDrawerRelationsTest extends TestCase
{
    public function test_shift_page_renders_active_drawer_location_context_without_lazy_loading(): void
    {
        $administrator = User::query()
            ->where('username', 'local.system-administrator')
            ->firstOrFail();
        $drawer = CashDrawer::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('pos.shift'))
            ->assertOk()
            ->assertSee($drawer->code);
    }
}
