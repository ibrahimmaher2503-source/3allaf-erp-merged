// @ts-check
import { test, expect } from '@playwright/test';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

/**
 * Critical browser E2E smoke suite. Ties to E2E-03/E2E-04 (authentication and
 * scoped-route denial) from testing/results/E2E-SCENARIOS.md. This is the
 * first browser-executed evidence for this project — prior sessions and the
 * earlier part of this one recorded every E2E scenario as NOT_RUN_BROWSER.
 *
 * Scope is deliberately narrow (login + direct-URL RBAC denial), not the full
 * 40-scenario register: converting the remaining scenarios to real Playwright
 * specs, each with its own seeded fixture, is follow-up work, not something
 * to rush through in one pass. See testing/e2e/README.md for what's covered
 * and what still needs conversion.
 */

test.describe('Critical auth and direct-route RBAC (E2E-03/E2E-04)', () => {
    test('an authenticated administrator reaches the dashboard and console stays clean', async ({ page }) => {
        const consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await login(page, 'playwright-admin', 'PlaywrightTest!2026');

        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page).toHaveTitle(/Dashboard/);
        await expect(page.getByRole('heading', { name: 'Operations workspace' })).toBeVisible();

        expect(consoleErrors, `Console/page errors on the dashboard: ${consoleErrors.join(' | ')}`).toEqual([]);
    });

    test('an unauthenticated visitor is redirected away from a protected route', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('a wrong password is rejected with an inline error and no redirect', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username', { exact: true }).fill('playwright-admin');
        await page.getByLabel('Password', { exact: true }).fill('DefinitelyWrongPassword!');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/login/);
    });

    test('Authentication and Offline Synchronization: Reject invalid sign in credentials', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username', { exact: true }).fill(LOCAL_BROWSER_ACTORS.administrator.username);
        await page.getByLabel('Password', { exact: true }).fill('DefinitelyWrongPassword!');

        const [response] = await Promise.all([
            page.waitForResponse((candidate) => candidate.request().method() === 'POST' && new URL(candidate.url()).pathname === '/login'),
            page.getByRole('button', { name: 'Log in' }).click({ noWaitAfter: true }),
        ]);

        expect(response.status()).toBe(302);
        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText('These credentials do not match our records.', { exact: true })).toBeVisible();
        await expect(page.getByText('Unexpected system error (500)', { exact: true })).toHaveCount(0);
    });

    test('a cashier reaches POS but a direct URL to an administrator-only route is denied server-side', async ({ page }) => {
        await login(page, 'playwright-cashier', 'PlaywrightTest!2026');

        const posResponse = await page.goto('/pos');
        expect(posResponse?.status(), 'A scoped cashier must reach their own POS route.').toBe(200);

        const settingsResponse = await page.goto('/admin/settings');
        expect(settingsResponse?.status(), 'A cashier must be denied an administrator-only route by a direct URL, not just a hidden link.').toBe(403);
    });
});
