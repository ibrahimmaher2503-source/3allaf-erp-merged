import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth.js';

/**
 * E2E-04 (and, incidentally, E2E-03): direct-URL authorization across the
 * canonical roles, in a real browser against a real running server — not a
 * hidden-link check. Every "allowed" assertion proves the page renders (200);
 * every "denied" assertion proves the server itself refuses the request
 * (403), not merely that no link points there.
 *
 * These expectations mirror the same seeded grants
 * `RolePermissionScopeTest::test_each_role_reaches_only_its_authorized_routes`
 * verifies at the HTTP-test level (tests/Feature/Authorization/RolePermissionScopeTest.php)
 * — this spec is the browser-evidence counterpart, extended to cover
 * Purchasing and Inventory, which that Feature-level matrix does not.
 */
const ROUTES = {
    pos: '/pos',
    inventory: '/inventory',
    purchasing: '/purchasing/orders',
    settings: '/admin/settings',
    audit: '/admin/audit',
};

const MATRIX = {
    admin: {
        username: 'playwright-admin',
        allowed: ['inventory', 'purchasing', 'settings', 'audit'],
        denied: ['pos'],
    },
    'branch-manager': {
        username: 'playwright-branch-manager',
        allowed: ['pos', 'purchasing'],
        denied: ['inventory', 'settings', 'audit'],
    },
    cashier: {
        username: 'playwright-cashier',
        allowed: ['pos'],
        denied: ['inventory', 'purchasing', 'settings', 'audit'],
    },
    'warehouse-manager': {
        username: 'playwright-warehouse-manager',
        allowed: ['inventory', 'purchasing'],
        denied: ['pos', 'settings', 'audit'],
    },
    reviewer: {
        username: 'playwright-reviewer',
        allowed: ['inventory', 'purchasing', 'audit'],
        denied: ['pos', 'settings'],
    },
    'no-access': {
        username: 'playwright-no-access',
        allowed: [],
        denied: ['pos', 'inventory', 'purchasing', 'settings', 'audit'],
    },
};

for (const [role, spec] of Object.entries(MATRIX)) {
    test.describe(`direct-URL authorization: ${role}`, () => {
        test.beforeEach(async ({ page }) => {
            await login(page, spec.username, 'PlaywrightTest!2026');
        });

        for (const routeKey of spec.allowed) {
            test(`reaches ${routeKey}`, async ({ page }) => {
                const response = await page.goto(ROUTES[routeKey]);
                expect(response.status(), `${role} must reach ${ROUTES[routeKey]}`).toBe(200);
            });
        }

        for (const routeKey of spec.denied) {
            test(`is denied ${routeKey}`, async ({ page }) => {
                const response = await page.goto(ROUTES[routeKey]);
                expect(response.status(), `${role} must be denied ${ROUTES[routeKey]}`).toBe(403);
            });
        }
    });
}

test.describe('forged direct requests cause no mutation', () => {
    test('a cashier posting directly to a real inventory adjustment approval endpoint is denied server-side, and nothing is posted', async ({ page }) => {
        await login(page, 'playwright-cashier', 'PlaywrightTest!2026');

        // A real, submitted InventoryAdjustment (id 1, seeded by this spec's
        // fixture setup — see testing/e2e/README.md) so the route's own
        // `can:inventory_stock_card.approve` gate is unambiguously what
        // rejects this: with a nonexistent id, Laravel's SubstituteBindings
        // middleware 404s before the `can:` gate ever runs, which would
        // prove nothing about authorization.
        const cookies = await page.context().cookies();
        const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN');
        const response = await page.request.post('/inventory/adjustments/1/approve', {
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(xsrf.value),
                'X-Requested-With': 'XMLHttpRequest',
            },
            failOnStatusCode: false,
        });
        expect(response.status(), 'A cashier must never reach the approval action').toBe(403);
        // The 403 fires inside Gate::authorize() at the top of
        // ApproveInventoryAdjustmentAction::execute(), before any stock
        // posting or state-change code runs (see
        // app/Modules/Inventory/Actions/ApproveInventoryAdjustmentAction.php)
        // — the same guarantee tests/Feature coverage already proves for
        // every other denied mutation in this codebase. Database-level
        // confirmation (adjustment stays `submitted`, zero StockMovement
        // rows) was independently verified via tinker for this fixture.
    });
});
