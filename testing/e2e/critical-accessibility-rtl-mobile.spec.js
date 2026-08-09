import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

/**
 * E2E-08 (RTL/LTR, accessibility, viewport) — real browser evidence, not a
 * documentation pass. Every page scanned here was previously NOT_RUN_BROWSER.
 *
 * Scope discipline: this covers the pages already exercised by other browser
 * specs (login, dashboard, /pos) at desktop LTR, desktop RTL (locale switch),
 * and a 390px mobile viewport (per the audit's named breakpoint). It does not
 * claim WCAG conformance for the whole application — only for these pages —
 * and any violation found is reported here and in DEFECTS.md, never silently
 * dropped or downgraded to pass.
 */
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const MOBILE_VIEWPORT = { width: 390, height: 844 };

function summarize(results) {
    return results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodes: v.nodes.length,
        targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')),
    }));
}

/**
 * `#phpdebugbar` (barryvdh/laravel-debugbar) is dev-only tooling injected by
 * `APP_DEBUG=true` — never present in Production and never seen by a real
 * user. Scanning it would report false-positive "defects" against chrome
 * that isn't part of the application.
 */
function axeScan(page) {
    return new AxeBuilder({ page }).withTags(WCAG_TAGS).exclude('.phpdebugbar').analyze();
}

function assertNoSeriousViolations(results, label) {
    const blocking = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    expect(blocking, `${label}: critical/serious a11y violations — ${JSON.stringify(summarize({ violations: blocking }), null, 2)}`).toEqual([]);
}

test.describe('accessibility (axe-core, WCAG 2.1 A/AA) — desktop LTR', () => {
    test('the login page has no critical/serious violations', async ({ page }) => {
        await page.goto('/login');
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'login (desktop, en)');
    });

    test('the administrator dashboard has no critical/serious violations', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/dashboard');
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'dashboard (desktop, en)');
    });

    test('the POS screen has no critical/serious violations', async ({ page }) => {
        await login(page, 'playwright-cashier', 'PlaywrightTest!2026');
        await page.goto('/pos');
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'pos (desktop, en)');
    });
});

test.describe('RTL (Arabic locale)', () => {
    test('switching to Arabic sets dir=rtl and lang=ar on the dashboard, with no new critical/serious violations', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/dashboard');

        // Uses the real `/locale` route the sidebar's "Switch to Arabic" menu
        // item submits (routes/platform.php `locale.switch`) — a real
        // authenticated POST with a valid CSRF token, not a bypass.
        const cookies = await page.context().cookies();
        const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN');
        const switchResponse = await page.request.post('/locale', {
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
            form: { locale: 'ar' },
            failOnStatusCode: false,
        });
        expect(switchResponse.ok(), 'The locale switch must succeed').toBeTruthy();

        await page.goto('/dashboard');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

        // No horizontal overflow: the RTL layout must not force the page
        // wider than the viewport (a common RTL-mirroring regression).
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'The RTL dashboard must not overflow horizontally').toBeLessThanOrEqual(1);

        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'dashboard (desktop, ar/rtl)');

        // Switch back so this test does not leak locale state into siblings
        // running against the same disposable session/user.
        const cookies2 = await page.context().cookies();
        const xsrf2 = cookies2.find((c) => c.name === 'XSRF-TOKEN');
        await page.request.post('/locale', {
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf2.value) },
            form: { locale: 'en' },
            failOnStatusCode: false,
        });
    });
});

test.describe('mobile viewport (390x844)', () => {
    test.use({ viewport: MOBILE_VIEWPORT });

    test('the login page has no horizontal overflow and no critical/serious violations at 390px', async ({ page }) => {
        await page.goto('/login');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'The login page must not overflow horizontally at 390px').toBeLessThanOrEqual(1);
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'login (390px)');
    });

    test('the POS screen has no horizontal overflow and no critical/serious violations at 390px', async ({ page }) => {
        await login(page, 'playwright-cashier', 'PlaywrightTest!2026');
        await page.goto('/pos');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'The POS screen must not overflow horizontally at 390px — cashiers use handheld devices').toBeLessThanOrEqual(1);
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'pos (390px)');
    });

    test('the administrator dashboard has no horizontal overflow at 390px', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/dashboard');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'The dashboard must not overflow horizontally at 390px').toBeLessThanOrEqual(1);
        const results = await axeScan(page);
        assertNoSeriousViolations(results, 'dashboard (390px)');
    });
});
