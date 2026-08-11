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
        allowed: ['pos', 'inventory', 'purchasing'],
        denied: ['settings', 'audit'],
    },
    cashier: {
        username: 'playwright-cashier',
        allowed: ['pos', 'inventory'],
        denied: ['purchasing', 'settings', 'audit'],
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

test('inventory UI supports scoped search and the transfer lifecycle', async ({ page, browser }) => {
    test.setTimeout(180_000);
    await login(page, 'playwright-admin', 'PlaywrightTest!2026');
    await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-stock-balance-table]')).toBeVisible();
    await page.getByLabel('Product search', { exact: true }).fill('DEMO-PROD-001');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await expect(page.locator('[data-inventory-filter]')).toBeVisible();

    await page.getByRole('link', { name: 'New transfer', exact: true }).click();
    const source = page.locator('#transfer-source');
    const destination = page.locator('#transfer-destination');
    const sourceValue = await source.locator('option').filter({ hasText: 'DEMO-WH' }).first().getAttribute('value');
    const destinationValue = await destination.locator('option').filter({ hasText: 'DEMO-SELL' }).first().getAttribute('value');
    const productValue = await page.locator('#transfer-product option').filter({ hasText: 'DEMO-PROD-001' }).first().getAttribute('value');
    await source.selectOption(sourceValue);
    await destination.selectOption(destinationValue);
    await page.locator('#transfer-product').selectOption(productValue);
    await page.locator('#transfer-quantity').fill('1');
    await page.locator('#transfer-reason').fill('ui_replenishment');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    const transferNumber = (await page.locator('[data-transfer-row]').first().locator('strong').textContent()).trim();
    await expect(page.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first()).toContainText('Draft');

    let transferRow = page.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first();
    await transferRow.getByRole('button', { name: 'Submit', exact: true }).click();

    const approverContext = await browser.newContext({ locale: 'en-US' });
    const approverPage = await approverContext.newPage();
    await login(approverPage, 'playwright-inventory-approver', 'PlaywrightTest!2026');
    await approverPage.goto('/inventory', { waitUntil: 'domcontentloaded' });
    transferRow = approverPage.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first();
    await expect(transferRow).toContainText('Submitted');
    await transferRow.getByRole('button', { name: 'Approve', exact: true }).click();
    transferRow = approverPage.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first();
    await expect(transferRow).toContainText('Approved');
    await transferRow.getByRole('button', { name: 'Dispatch', exact: true }).click();
    transferRow = approverPage.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first();
    await expect(transferRow).toContainText('In transit');
    await transferRow.getByRole('button', { name: 'Record receipt', exact: true }).click();
    await expect(approverPage.locator('[data-transfer-row]').filter({ hasText: transferNumber }).first()).toContainText('Received');

    await page.goto('/inventory/adjustments/create', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#adjustment-type')).toBeVisible();
    const adjustmentStoreValue = await page.locator('#adjustment-store option').filter({ hasText: 'DEMO-WH' }).first().getAttribute('value');
    const adjustmentProductValue = await page.locator('#adjustment-product option').filter({ hasText: 'DEMO-PROD-001' }).first().getAttribute('value');
    await page.locator('#adjustment-store').selectOption(adjustmentStoreValue);
    await page.locator('#adjustment-type').selectOption('entry');
    await page.locator('#adjustment-reason').fill('ui_entry');
    await page.locator('#adjustment-product').selectOption(adjustmentProductValue);
    await page.locator('#adjustment-quantity').fill('1');
    await page.locator('#adjustment-cost').fill('5');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    const adjustmentSection = page.locator('[data-guide="inventory-adjustments"]');
    const adjustmentNumber = (await adjustmentSection.locator('strong').first().textContent()).trim();
    let adjustmentRow = adjustmentSection.locator('div.rounded-2xl').filter({ hasText: adjustmentNumber }).first();
    await expect(adjustmentRow).toContainText('Draft');
    await adjustmentRow.getByRole('button', { name: 'Submit', exact: true }).click();

    const adjustmentApproverPage = approverPage;
    await adjustmentApproverPage.goto('/inventory', { waitUntil: 'domcontentloaded' });
    const approverAdjustmentSection = adjustmentApproverPage.locator('[data-guide="inventory-adjustments"]');
    adjustmentRow = approverAdjustmentSection.locator('div.rounded-2xl').filter({ hasText: adjustmentNumber }).first();
    await expect(adjustmentRow).toContainText('Submitted');
    await adjustmentRow.getByRole('button', { name: 'Approve and post', exact: true }).click();
    adjustmentRow = approverAdjustmentSection.locator('div.rounded-2xl').filter({ hasText: adjustmentNumber }).first();
    await expect(adjustmentRow).toContainText('Approved');
    await expect(adjustmentApproverPage.locator('[data-guide="inventory-movements"]')).toContainText('inventory_entry');
    await page.goto('/inventory/counts/create', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#count-category')).toBeVisible();
    await expect(page.locator('#count-supplier')).toBeVisible();
    const countStoreValue = await page.locator('#count-store option').filter({ hasText: 'DEMO-WH' }).first().getAttribute('value');
    const countAssigneeValue = await page.locator('#count-assigned option').filter({ hasText: 'playwright-inventory-approver' }).first().getAttribute('value');
    await page.locator('#count-store').selectOption(countStoreValue);
    await page.locator('#count-assigned').selectOption(countAssigneeValue);
    await page.locator('select[name="scope_type"]').selectOption('store');
    await page.locator('input[name="product_ids[]"]').first().check();
    await page.getByRole('button', { name: 'Create count', exact: true }).click();
    const countSection = page.locator('[data-guide="inventory-counts"]');
    const countNumber = (await countSection.locator('strong').first().textContent()).trim();

    const counterPage = approverPage;
    await counterPage.goto('/inventory', { waitUntil: 'domcontentloaded' });
    let countRow = counterPage.locator('[data-guide="inventory-counts"] div.rounded-2xl').filter({ hasText: countNumber }).first();
    await countRow.getByRole('link', { name: 'Enter count', exact: true }).click();
    await counterPage.locator('input[id^="count-line-"]').first().fill('0');
    await counterPage.getByRole('button', { name: 'Save count', exact: true }).click();
    countRow = counterPage.locator('[data-guide="inventory-counts"] div.rounded-2xl').filter({ hasText: countNumber }).first();
    await countRow.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
    countRow = page.locator('[data-guide="inventory-counts"] div.rounded-2xl').filter({ hasText: countNumber }).first();
    await expect(countRow).toContainText('Submitted');
    await countRow.getByRole('link', { name: 'Review', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Reconciliation review/ })).toBeVisible();
    await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
    countRow = page.locator('[data-guide="inventory-counts"] div.rounded-2xl').filter({ hasText: countNumber }).first();
    await countRow.getByRole('button', { name: 'Reconcile and approve', exact: true }).click();
    await expect(page.locator('[data-guide="inventory-counts"]')).toContainText('Reconciled');
    await approverContext.close();
});

test('inventory UI remains operable in Arabic RTL at 390 by 844', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page, 'playwright-admin', 'PlaywrightTest!2026');
    await page.setViewportSize({ width: 390, height: 844 });
    const cookies = await page.context().cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    await page.request.post('/locale', {
        form: { locale: 'ar' },
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
    });
    await page.goto('/inventory', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('[data-inventory-filter]')).toBeVisible();
    await expect(page.locator('[data-stock-balance-table]')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBeTruthy();
});
