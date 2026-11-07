<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Platform\Support\OperationalDashboardSnapshot;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class OperationalDashboardSnapshotTest extends TestCase
{
    public function test_today_compares_with_previous_day(): void
    {
        $period = OperationalDashboardSnapshot::periodBounds([], Carbon::parse('2026-10-16 12:00:00', 'Africa/Cairo'));
        self::assertSame('2026-10-16', $period['from']->toDateString());
        self::assertSame('2026-10-15', $period['previous_from']->toDateString());
        self::assertSame('2026-10-15', $period['previous_to']->toDateString());
    }

    public function test_fixed_range_compares_with_equal_length_previous_range(): void
    {
        $period = OperationalDashboardSnapshot::periodBounds(['date_from' => '2026-10-01', 'date_to' => '2026-10-07']);
        self::assertSame('2026-09-24', $period['previous_from']->toDateString());
        self::assertSame('2026-09-30', $period['previous_to']->toDateString());
    }

    public function test_custom_range_compares_with_equal_length_previous_range(): void
    {
        $period = OperationalDashboardSnapshot::periodBounds(['date_from' => '2026-10-01', 'date_to' => '2026-10-10']);
        self::assertSame('2026-09-21', $period['previous_from']->toDateString());
        self::assertSame('2026-09-30', $period['previous_to']->toDateString());
    }

    public function test_dashboard_scope_is_shared_by_current_and_comparison_queries(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Modules/Platform/Support/OperationalDashboardSnapshot.php');
        self::assertGreaterThanOrEqual(2, substr_count($source, 'visibleTo($user)'));
        self::assertStringContainsString("whereIn('store_id', clone \$storeIds)", $source);
        self::assertStringContainsString("whereBetween('approved_at', [\$previousFrom, \$previousTo])", $source);
        self::assertStringContainsString("whereBetween('invoice_date', [\$previousFrom->toDateString(), \$previousTo->toDateString()])", $source);
    }
}
