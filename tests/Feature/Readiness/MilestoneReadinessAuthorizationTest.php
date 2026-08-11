<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: NFR-03 and readiness-only boundaries for TSK-026 through TSK-044.
 */
final class MilestoneReadinessAuthorizationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    /** @var list<string> */
    private array $readinessRoutes = [
        'returns.readiness',
        'party.readiness',
        'reports.readiness',
        'alerts.readiness',
        'exports.audit.readiness',
        'master-data.migration.readiness',
        'operations.readiness',
        'uat.readiness',
        'release.readiness',
        'quotations.readiness',
        'party.final-close.readiness',
        'party.asset-events.readiness',
        'party.assets.readiness',
        'party.operating.readiness',
        'party.payments.readiness',
        'gift.receipts',
        'gift.cards',
        'pos.offline-readiness',
        'customers.loyalty-readiness',
        'pos.financial-readiness',
    ];

    public function test_an_authenticated_user_without_permissions_is_denied_every_readiness_direct_url(): void
    {
        $user = $this->userWith('readiness-no-access');

        foreach ($this->readinessRoutes as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_super_administrator_can_render_every_current_readiness_boundary(): void
    {
        $administrator = $this->administrator('readiness-admin');

        foreach ($this->readinessRoutes as $routeName) {
            $response = $this->actingAs($administrator)->get(route($routeName));
            if ($routeName === 'customers.loyalty-readiness') {
                $response->assertRedirect(route('customers.index'));
            } else {
                $response->assertOk();
            }
        }
    }

    public function test_readiness_pages_do_not_mutate_protected_business_tables(): void
    {
        $administrator = $this->administrator('readiness-no-mutation');
        $protectedTables = [
            'sales', 'stock_movements', 'stock_transfers', 'stock_counts', 'purchase_invoices',
            'purchase_returns', 'product_wallet_ledger', 'party_wallet_ledger', 'label_print_events',
        ];

        foreach ($this->readinessRoutes as $routeName) {
            $response = $this->actingAs($administrator)->get(route($routeName));
            if ($routeName === 'customers.loyalty-readiness') {
                $response->assertRedirect(route('customers.index'));
            } else {
                $response->assertOk();
            }
        }

        foreach ($protectedTables as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
