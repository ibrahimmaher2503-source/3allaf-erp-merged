import { test, expect } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

test.describe.configure({ mode: 'serial' });

test.use({
    locale: 'en-US',
    viewport: { width: 1440, height: 1000 },
    launchOptions: { slowMo: 220 },
    trace: 'on',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
});

test('KS-001 and KS-002 visible browser feasibility', async ({ browser, page }, testInfo) => {
    test.setTimeout(180_000);

    const evidenceDirectory = path.resolve('artifacts/ks-001-002-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const results = { browser: 'Chromium headed', locale: 'en-US', scenarios: [], consoleErrors: [], pageErrors: [], failedRequests: [] };

    const attachDiagnostics = (candidatePage) => {
        candidatePage.on('console', (message) => {
            if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) results.consoleErrors.push(message.text());
        });
        candidatePage.on('pageerror', (error) => results.pageErrors.push(error.message));
        candidatePage.on('requestfailed', (request) => results.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    };
    attachDiagnostics(page);

    const capture = async (candidatePage, id) => {
        const file = path.join(evidenceDirectory, id + '.png');
        await candidatePage.screenshot({ path: file, fullPage: true });
        await testInfo.attach(id, { path: file, contentType: 'image/png' });
        return file;
    };

    await test.step('KS-001 Login + scoped navigation', async () => {
        const startedAt = Date.now();
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        const pos = await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        const ltrText = await page.locator('body').innerText();

        const localeForm = page.locator('form').filter({ has: page.locator('input[name="locale"][value="ar"]') }).first();
        const localeAvailable = await localeForm.isVisible().catch(() => false);
        let localeVariant = false;
        if (localeAvailable) {
            await localeForm.getByRole('button').click();
            await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            const restoreForm = page.locator('form').filter({ has: page.locator('input[name="locale"][value="en"]') }).first();
            await restoreForm.getByRole('button').click();
            await expect(page.locator('html')).toHaveAttribute('lang', 'en');
            await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
            localeVariant = true;
        }

        const branches = await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
        await capture(page, 'KS-001-branch-route-denial-en-ltr');
        results.scenarios.push({
            id: 'KS-001', priority: 'P0', actor: 'Cashier Branch A', route: '/login; /pos; /admin/branches',
            expected: 'Branch A POS is allowed; Branch B and expired-session variants are denied without data leakage.',
            actual: `POS=${pos?.status()}; English POS content=${ltrText.length > 0}; locale variant=${localeVariant}; admin branches direct route=${branches?.status()}. No Branch B/cross-scope actor or legitimate expiry mechanism exists in the running fixture.`,
            result: 'BLOCKED', classification: 'TEST DATA / FIXTURE GAP', durationMs: Date.now() - startedAt,
        });
    });

    await test.step('KS-002 Password reset single-use', async () => {
        const startedAt = Date.now();
        const context = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
        const resetPage = await context.newPage();
        attachDiagnostics(resetPage);
        await resetPage.goto('/forgot-password', { waitUntil: 'domcontentloaded' });
        await expect(resetPage.locator('html')).toHaveAttribute('lang', 'en');
        await expect(resetPage.locator('html')).toHaveAttribute('dir', 'ltr');
        await resetPage.locator('input[name="email"]').fill('ks-reset-unavailable@toyjoy.local');
        await resetPage.locator('button[data-test="email-password-reset-link-button"]').click();
        await resetPage.waitForTimeout(900);
        const text = await resetPage.locator('body').innerText();
        const safe = !/(APP_KEY|DB_PASSWORD|reset-password\/[^\s]+|token=)/i.test(text);
        await capture(resetPage, 'KS-002-generic-request-en-ltr');
        await context.close();
        results.scenarios.push({
            id: 'KS-002', priority: 'P0', actor: 'Guest / local recovery identity', route: '/forgot-password; /reset-password/{token}',
            expected: 'A valid reset completes once, then reuse/expiry are denied without token or password leakage.',
            actual: `Visible recovery request submitted with safe rendered feedback=${safe}. The local mailer exposes no browser inbox/reset link, so a valid completion, reuse, expiry, old-session invalidation, and reset audit cannot be exercised honestly through UI.`,
            result: 'NOT TESTABLE THROUGH UI', classification: 'NO BROWSER-ACCESSIBLE RESET LINK', durationMs: Date.now() - startedAt,
        });
    });

    const resultFile = path.join(evidenceDirectory, 'results.json');
    await writeFile(resultFile, JSON.stringify(results, null, 2), 'utf8');
    await testInfo.attach('KS-001/002 results', { path: resultFile, contentType: 'application/json' });
    expect(results.scenarios.map((scenario) => scenario.id).sort()).toEqual(['KS-001', 'KS-002']);
    expect(results.consoleErrors).toEqual([]);
    expect(results.pageErrors).toEqual([]);
});
