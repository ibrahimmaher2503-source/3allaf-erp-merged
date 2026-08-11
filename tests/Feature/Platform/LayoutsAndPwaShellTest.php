<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-003 — Application layouts and restricted PWA shell.
 *
 * @group tsk-003
 */
class LayoutsAndPwaShellTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_auth_layout_renders_for_a_guest_with_locale_direction(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('lang="en"', false);
    }

    public function test_the_arabic_locale_switches_the_document_direction_to_rtl(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    public function test_the_admin_layout_renders_for_an_authorized_user(): void
    {
        $this->actingAs($this->administrator('tsk003-admin'));

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee(route('admin.settings'), false);
    }

    public function test_the_pos_layout_renders_its_operational_context_bar(): void
    {
        $this->actingAs($this->userWith('tsk003-cashier', ['cashier']));

        $response = $this->get('/pos');

        $response->assertOk();
        $response->assertSee('Branch Context', false);
        $response->assertSee('Selling Store', false);
        $response->assertSee('Cash Drawer', false);
        $response->assertSee('Offline', false);
    }

    public function test_the_pos_context_indicators_are_not_yet_bound_to_a_resolver(): void
    {
        // Recorded coverage fact for TSK-003: branch/store/drawer context is a
        // static placeholder. No context-switch validation exists to test.
        $this->actingAs($this->userWith('tsk003-cashier-context', ['cashier']));

        $this->get('/pos')->assertSee('Not configured', false);
    }

    public function test_navigation_only_exposes_links_the_user_is_authorized_to_open(): void
    {
        $this->actingAs($this->userWith('tsk003-nav-cashier', ['cashier']));

        $cashierView = $this->get('/pos');
        $cashierView->assertOk();

        $this->actingAs($this->userWith('tsk003-nav-manager', ['branch-manager']));
        $managerView = $this->get('/admin/branches');
        $managerView->assertOk();
        $managerView->assertSee(route('admin.branches'), false);
        $managerView->assertDontSee(route('admin.settings'), false);
        $managerView->assertDontSee(route('admin.cash-drawers'), false);
        $managerView->assertDontSee(route('admin.audit'), false);

        $this->actingAs($this->administrator('tsk003-nav-admin'));
        $adminView = $this->get('/dashboard');
        foreach ([
            route('admin.settings'),
            route('admin.branches'),
            route('admin.stores'),
            route('admin.cash-drawers'),
            route('admin.authorization-baseline'),
            route('admin.audit'),
            route('system.health'),
        ] as $link) {
            $adminView->assertSee($link, false);
        }
    }

    public function test_the_sidebar_keeps_one_canonical_export_and_supplier_return_destination(): void
    {
        $this->actingAs($this->administrator('tsk003-nav-canonical-destinations'));

        $html = (string) $this->get('/dashboard')->assertOk()->getContent();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $navigation = (new \DOMXPath($document))->query('//nav[@aria-label="Workspace"]')->item(0);
        $this->assertNotNull($navigation);
        $xpath = new \DOMXPath($document);

        $this->assertSame(
            2,
            $xpath->query('.//a[normalize-space(.)="PDF / Excel export center"]', $navigation)->length,
            'The one canonical export item is rendered in Flux desktop and collapsed variants only.',
        );
        $this->assertSame(
            2,
            $xpath->query('.//a[normalize-space(.)="Supplier returns"]', $navigation)->length,
            'The one canonical supplier-return item is rendered in Flux desktop and collapsed variants only.',
        );
    }

    public function test_the_sidebar_targets_real_operational_focused_and_report_destinations(): void
    {
        $this->actingAs($this->administrator('tsk003-nav-remediated-destinations'));

        $html = (string) $this->get('/dashboard')->assertOk()->getContent();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $navigation = (new \DOMXPath($document))->query('//nav[@aria-label="Workspace"]')->item(0);
        $this->assertNotNull($navigation);
        $xpath = new \DOMXPath($document);
        $hrefFor = static function (string $label) use ($xpath, $navigation): ?string {
            $literal = str_replace("'", "&apos;", $label);
            $node = $xpath->query(".//a[normalize-space(.)='{$literal}']", $navigation)->item(0);

            return $node?->attributes?->getNamedItem('href')?->nodeValue;
        };

        foreach ([
            'Loyalty & points' => route('customers.index', ['mode' => 'loyalty']),
            'Transaction history' => route('customers.index', ['mode' => 'history']),
            'Supplier invoices & cost history' => route('purchasing.history.suppliers'),
            'Purchase cost history' => route('purchasing.history.costs'),
            'Price lists & versions' => route('pricing.focus', ['mode' => 'versions']),
            'Unpriced products' => route('pricing.focus', ['mode' => 'unpriced']),
            'Price change history' => route('pricing.focus', ['mode' => 'history']),
            'Balances' => route('inventory.balances'),
            'Party bookings' => route('parties.bookings.index'),
            'Working invoice' => route('parties.invoices.index', ['mode' => 'working']),
            'Party payments' => route('parties.invoices.index', ['mode' => 'payments']),
            'Operating orders & consumables' => route('parties.orders.index'),
            'Final close & settlement' => route('parties.invoices.index', ['mode' => 'settlement']),
            'Rental assets & calendar' => route('party.assets.index', ['mode' => 'workspace']),
            'Asset reservations & checkout' => route('party.assets.index', ['mode' => 'reservations']),
            'Return, condition & damages' => route('party.assets.index', ['mode' => 'returns']),
            'Depreciation & asset history' => route('party.assets.index', ['mode' => 'history']),
            'Sales reports' => route('reports.sales'),
            'Customer & loyalty reports' => route('reports.customers'),
            'Cash & shift reports' => route('reports.cash'),
            'Purchasing reports' => route('reports.purchasing'),
            'Inventory reports' => route('reports.inventory'),
            'Party reports' => route('reports.parties'),
            'Rental asset reports' => route('reports.assets'),
            'Override log' => route('admin.audit', ['mode' => 'override']),
            'Print log' => route('admin.audit', ['mode' => 'print']),
        ] as $label => $expectedHref) {
            $this->assertSame($expectedHref, $hrefFor($label), "Unexpected sidebar destination for [{$label}].");
        }

        $sidebarHtml = (string) $document->saveHTML($navigation);
        $this->assertNull($hrefFor('Bookings calendar'));
        $this->assertStringNotContainsString(route('party.readiness'), $sidebarHtml);
    }

    public function test_hidden_navigation_is_not_the_only_control_on_a_denied_route(): void
    {
        $this->actingAs($this->userWith('tsk003-direct', ['branch-manager']));

        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/audit')->assertForbidden();
        $this->get('/admin/cash-drawers')->assertForbidden();
    }

    public function test_authenticated_html_is_not_publicly_cacheable(): void
    {
        $this->actingAs($this->administrator('tsk003-cache'));

        $cacheControl = (string) $this->get('/dashboard')->headers->get('Cache-Control');

        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertTrue(
            str_contains($cacheControl, 'no-store')
            || str_contains($cacheControl, 'no-cache')
            || str_contains($cacheControl, 'private'),
            "Authenticated HTML must not be shared-cacheable. Received: [{$cacheControl}]",
        );
    }

    public function test_the_pwa_manifest_file_is_present_and_valid(): void
    {
        $manifestPath = public_path('manifest.json');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertIsArray($manifest);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_the_service_worker_is_registered_and_caches_no_dynamic_responses(): void
    {
        $serviceWorker = (string) file_get_contents(public_path('sw.js'));
        $entrypoint = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $entrypoint);

        // Only the listed static shell assets may be pre-cached.
        $this->assertStringContainsString('/favicon.ico', $serviceWorker);
        $this->assertStringContainsString('/manifest.json', $serviceWorker);
        $this->assertStringNotContainsString('caches.match', $serviceWorker);
        $this->assertStringNotContainsString('cache.put', $serviceWorker);
        $this->assertStringNotContainsString('respondWith', $serviceWorker);

        foreach (['/dashboard', '/admin', '/pos', '/login'] as $dynamicRoute) {
            $this->assertStringNotContainsString(
                "'{$dynamicRoute}'",
                $serviceWorker,
                'No authenticated or dynamic route may be pre-cached by the service worker.',
            );
        }
    }

    public function test_every_authenticated_layout_route_is_scope_and_permission_guarded(): void
    {
        foreach (['/dashboard', '/pos', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/audit', '/admin/system/health', '/system/app'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }

        $this->actingAs($this->userWith('tsk003-none'));

        foreach (['/dashboard', '/pos', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/audit', '/admin/system/health', '/system/app'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }
}
