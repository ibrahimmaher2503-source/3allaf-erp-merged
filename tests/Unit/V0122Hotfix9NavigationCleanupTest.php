<?php

namespace Tests\Unit;

use App\Modules\Platform\Support\InitialSetupStepRegistry;
use PHPUnit\Framework\TestCase;

final class V0122Hotfix9NavigationCleanupTest extends TestCase
{
    public function test_party_setup_step_is_preserved_but_centrally_deferred(): void
    {
        $all = InitialSetupStepRegistry::steps();
        $active = InitialSetupStepRegistry::activeSteps();

        self::assertArrayHasKey('party-readiness', $all);
        self::assertFalse($all['party-readiness']['enabled']);
        self::assertArrayNotHasKey('party-readiness', $active);
        self::assertCount(20, $active);
        self::assertNotContains('party-readiness', InitialSetupStepRegistry::groups()['operations']);
    }

    public function test_navigation_hides_only_the_requested_duplicate_and_deferred_entries(): void
    {
        $navigation = require dirname(__DIR__, 2).'/config/navigation.php';
        $groups = collect($navigation)->keyBy('key');
        $administrationRoutes = collect($groups['administration']['items'])->pluck('route')->all();

        self::assertFalse($groups['parties']['enabled']);
        self::assertNotContains('admin.branches', $administrationRoutes);
        self::assertNotContains('admin.stores', $administrationRoutes);
        self::assertNotContains('admin.authorization-baseline', $administrationRoutes);
        self::assertNotContains('admin.settings', $administrationRoutes);
        self::assertContains('admin.roles', $administrationRoutes);
        self::assertContains('initial-setup', $administrationRoutes);
    }

    public function test_sidebar_footer_and_duplicate_pricing_summary_are_removed(): void
    {
        $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app/sidebar.blade.php');
        $pricing = file_get_contents(dirname(__DIR__, 2).'/resources/views/pricing/lists.blade.php');

        self::assertStringNotContainsString('app-sidebar__footer', $sidebar);
        self::assertStringNotContainsString('data-status-label', $sidebar);
        self::assertStringNotContainsString('x-desktop-user-menu', $sidebar);
        self::assertStringNotContainsString('ProductPricingReadiness', $pricing);
        self::assertStringNotContainsString('x-setup.product-pricing-readiness', $pricing);
    }
}
