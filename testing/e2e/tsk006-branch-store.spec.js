import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

test.describe('TSK-006 branches and stores', () => {
    test.setTimeout(60_000);

    test('authorized administrator can reach branch and store masters', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const branches = await page.goto('/admin/branches');
        expect(branches?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Branch Masters' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Add Branch', exact: true })).toBeVisible();

        const stores = await page.goto('/admin/stores');
        expect(stores?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Store Masters' })).toBeVisible();
    });

    test('branch master is RTL, responsive and axe-clean on mobile', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/admin/branches');
        await page.setViewportSize({ width: 390, height: 844 });
        const localeToken = await page.locator('input[name="_token"]').first().inputValue();
        await page.request.post('/locale', { form: { _token: localeToken, locale: 'ar' } });
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        const results = await new AxeBuilder({ page }).exclude('.phpdebugbar').analyze();
        const serious = results.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
        expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk006-branches-ar-mobile.png', { fullPage: true });
        }
    });
});
