import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

test.describe('TSK-007 cash drawer masters', () => {
    test.setTimeout(60_000);

    test('administrator can reach the protected cash drawer master', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const response = await page.goto('/admin/cash-drawers');
        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Cash Drawer Masters' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Add Cash Drawer', exact: true })).toBeVisible();
    });

    test('Arabic mobile view is axe-clean and responsive', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/admin/cash-drawers');
        await page.setViewportSize({ width: 390, height: 844 });
        const token = await page.locator('input[name="_token"]').first().inputValue();
        await page.request.post('/locale', { form: { _token: token, locale: 'ar' } });
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        const results = await new AxeBuilder({ page }).exclude('.phpdebugbar').analyze();
        const serious = results.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
        expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk007-cash-drawers-ar-mobile.png', { fullPage: true });
        }
    });
});
