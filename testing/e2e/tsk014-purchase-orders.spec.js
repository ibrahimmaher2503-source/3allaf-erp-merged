import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

test.describe('TSK-014 purchase orders', () => {
    test('renders the real order workflow, print view, bilingual directions and mobile layout', async ({ page }) => {
        test.setTimeout(60000);
        const consoleErrors = [];
        page.on('console', (message) => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        page.on('pageerror', (error) => consoleErrors.push(error.message));

        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.setViewportSize({ width: 1280, height: 900 });
        const initialCsrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const arabicResponse = await page.request.post('/locale', { form: { _token: initialCsrf, locale: 'ar' } });
        expect(arabicResponse.status()).toBe(200);
        const response = await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await expect(page.locator('body')).toContainText('PO-DEMO-000001');
        expect(await page.locator('html').getAttribute('dir')).toBe('rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        const accessibility = await new AxeBuilder({ page }).include('main').analyze();
        expect(accessibility.violations, JSON.stringify(accessibility.violations, null, 2)).toEqual([]);

        const printLink = page.locator('a[href*="/purchasing/orders/1/print"]');
        await expect(printLink).toHaveAttribute('title', /(?:Print A4|طباعة A4)/i);
        const printPagePromise = page.waitForEvent('popup');
        await printLink.click();
        const printPage = await printPagePromise;
        await printPage.waitForLoadState('domcontentloaded');
        expect(new URL(printPage.url()).pathname).toMatch(/\/purchasing\/orders\/\d+\/print$/);
        await expect(printPage.locator('body')).toContainText('PO-DEMO-000001');
        await printPage.close();

        const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const localeResponse = await page.request.post('/locale', { form: { _token: csrf, locale: 'en' } });
        expect(localeResponse.status()).toBe(200);
        await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        expect(await page.locator('html').getAttribute('dir')).toBe('ltr');
        await expect(page.locator('body')).toContainText('Purchase Orders');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.screenshot({ path: 'artifacts/tsk-014-browser/purchase-orders-mobile-en.png', fullPage: true });
        expect(consoleErrors).toEqual([]);
    });

    test('denies direct purchasing navigation to actors without the purchase-order permission', async ({ page }) => {
        await login(page, 'playwright-cashier', 'PlaywrightTest!2026');
        const cashierResponse = await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        expect(cashierResponse?.status()).toBe(403);

        // Logout is a POST-only endpoint; clearing the isolated browser context
        // keeps this security check deterministic without issuing an invalid GET.
        await page.context().clearCookies();
        await login(page, 'playwright-no-access', 'PlaywrightTest!2026');
        const noAccessResponse = await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        expect(noAccessResponse?.status()).toBe(403);
    });
});
