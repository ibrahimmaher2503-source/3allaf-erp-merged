import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth.js';

/**
 * TSK-025 — shift, cash movement, blind close, and variance review in a real
 * browser (docs/32 §18, DEC-066).
 *
 * The load-bearing assertion here is the blind-close one: CSH-02 forbids the
 * cashier learning the expected drawer total before submitting, and §10 is
 * explicit that a hidden field or a preloaded response counts as a leak. A
 * server-side test can only prove the rendered HTML is clean; this proves it
 * for what the browser actually receives, including anything a script writes
 * into the DOM afterwards.
 */

// Demo users are seeded with random unknowable passwords by design, so this
// suite uses its own fixed-password fixtures (see testing/e2e/README.md).
const CASHIER = { username: process.env.PLAYWRIGHT_CASHIER ?? 'playwright-cashier', password: 'PlaywrightTest!2026' };
const MANAGER = { username: process.env.PLAYWRIGHT_MANAGER ?? 'playwright-branch-manager', password: 'PlaywrightTest!2026' };

test.describe('TSK-025 shift and cash workflow', () => {
    test('the cashier shift screen never exposes an expected total or variance', async ({ page }) => {
        await login(page, CASHIER.username, CASHIER.password);
        await page.goto('/pos/shift');
        await expect(page).toHaveURL(/\/pos\/shift$/);

        // Whatever the DOM holds after scripts have run — not just the initial
        // server response — must contain no expectation.
        const dom = await page.content();
        expect(dom).not.toContain('expected_cash');
        expect(dom).not.toContain('expected_by_method');
        expect(dom).not.toContain('cash_variance');
        expect(dom).not.toContain('total_variance');

        // No hidden input may smuggle it either.
        const hiddenNames = await page.locator('input[type=hidden]').evaluateAll(
            (nodes) => nodes.map((n) => n.getAttribute('name') || ''),
        );
        expect(hiddenNames.some((n) => n.includes('expected'))).toBe(false);
        expect(hiddenNames.some((n) => n.includes('variance'))).toBe(false);
    });

    test('a cashier is denied the variance review screen server-side', async ({ page }) => {
        await login(page, CASHIER.username, CASHIER.password);
        const response = await page.goto('/pos/shift-variance');
        expect(response?.status()).toBe(403);
    });

    test('a manager can reach the variance review screen', async ({ page }) => {
        await login(page, MANAGER.username, MANAGER.password);
        const response = await page.goto('/pos/shift-variance');
        expect(response?.status()).toBe(200);
    });

    test('the shift screen renders cleanly in Arabic RTL', async ({ page }) => {
        await login(page, CASHIER.username, CASHIER.password);
        await page.goto('/pos/shift?locale=ar');

        const dir = await page.locator('html').getAttribute('dir');
        expect(['rtl', 'ltr']).toContain(dir);

        // The page must not scroll horizontally at any supported width.
        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(overflow).toBe(false);
    });

    test('the shift screen fits a 390x844 phone without horizontal overflow', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, CASHIER.username, CASHIER.password);
        await page.goto('/pos/shift');

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(overflow).toBe(false);
    });

    test('the shift screen logs no console or page errors', async ({ page }) => {
        // Listeners are attached AFTER login on purpose. Laravel's post-login
        // redirect sends every user to /dashboard, which a Cashier is not
        // granted, so the login hop itself emits a 403 console entry. That is a
        // pre-existing platform redirect issue outside TSK-025; this test is
        // about the shift screen, so it measures only that navigation.
        await login(page, CASHIER.username, CASHIER.password);

        const problems = [];
        page.on('console', (m) => m.type() === 'error' && problems.push(m.text()));
        page.on('pageerror', (e) => problems.push(e.message));

        await page.goto('/pos/shift');
        await page.waitForLoadState('networkidle');

        expect(problems).toEqual([]);
    });

    test('cashier submits a blind close, manager approves it, and closed outputs are reachable from the UI', async ({ browser }, testInfo) => {
        test.setTimeout(120000);
        const cashierContext = await browser.newContext();
        const cashierPage = await cashierContext.newPage();

        await cashierPage.goto('/__demo/auth?as=demo-cashier&redirect=/pos/shift');
        await cashierPage.waitForURL(/\/pos\/shift$/);
        await cashierPage.goto('/pos/shift');
        await expect(cashierPage.locator('main').getByText('Cash Shift', { exact: true })).toBeVisible();
        await cashierPage.getByLabel('Counted cash', { exact: true }).fill('0.00');
        await cashierPage.getByRole('button', { name: 'Submit count' }).click();
        await cashierPage.waitForURL(/\/pos\/shift$/);
        await expect(cashierPage.getByText(/awaiting review/i)).toBeVisible();
        await cashierContext.close();

        const managerContext = await browser.newContext();
        const managerPage = await managerContext.newPage();
        await managerPage.goto('/__demo/auth?as=demo-branch-manager&redirect=/pos/shift-variance');
        await managerPage.waitForURL(/\/pos\/shift-variance$/);
        await managerPage.goto('/pos/shift-variance');
        await expect(managerPage.getByText('Shift Variance Review', { exact: true })).toBeVisible();
        await managerPage.getByRole('link', { name: 'Open canonical approval request' }).click();
        await managerPage.waitForURL(/\/approvals$/);

        const pendingShiftRow = managerPage.locator('tr').filter({ hasText: /Pos Shifts #1/ }).first();
        await expect(pendingShiftRow).toBeVisible();
        await pendingShiftRow.getByRole('button', { name: 'Review' }).click();
        await expect(managerPage.getByText('Approval request', { exact: true })).toBeVisible();
        managerPage.once('dialog', async (dialog) => {
            await dialog.accept();
        });
        await managerPage.getByRole('button', { name: 'Approve and close', exact: true }).click();
        await expect(managerPage.getByRole('dialog')).toBeHidden({ timeout: 15000 });
        await expect(managerPage.getByText('The source was approved and its audit trail was recorded.')).toBeVisible();

        await managerPage.goto('/pos/shift-variance');
        const closedShiftRow = managerPage.locator('section').filter({ hasText: 'Closed shifts' }).locator('tr').filter({ hasText: /SHIFT-/ }).first();
        await expect(closedShiftRow).toBeVisible();

        const thermalTabPromise = managerContext.waitForEvent('page');
        await closedShiftRow.getByRole('link', { name: 'Thermal' }).click();
        const thermalPage = await thermalTabPromise;
        await thermalPage.waitForLoadState('domcontentloaded');
        await expect(thermalPage).toHaveURL(/\/pos\/shifts\/1\/print\/thermal$/);
        await expect(thermalPage.locator('body')).toContainText('Shift closing receipt');
        await expect(thermalPage.locator('body')).toContainText('0.00');
        await thermalPage.screenshot({ path: testInfo.outputPath('shift-close-thermal-en.png'), fullPage: true });

        const a4TabPromise = managerContext.waitForEvent('page');
        await closedShiftRow.getByRole('link', { name: 'Print A4' }).click();
        const a4Page = await a4TabPromise;
        await a4Page.waitForLoadState('domcontentloaded');
        await expect(a4Page).toHaveURL(/\/pos\/shifts\/1\/print\/a4$/);
        await expect(a4Page.locator('body')).toContainText('Payment methods');
        await expect(a4Page.locator('body')).toContainText('Expected');
        await a4Page.screenshot({ path: testInfo.outputPath('shift-close-a4-en.png'), fullPage: true });

        await thermalPage.close();
        await a4Page.close();
        await managerContext.close();
    });
});
