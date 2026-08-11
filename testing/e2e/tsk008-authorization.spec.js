import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

test.describe('TSK-008 authorization baseline', () => {
    test.setTimeout(60_000);

    test('administrator can inspect the authorization baseline', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const response = await page.goto('/admin/authorization-baseline');
        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Authorization Baseline' })).toBeVisible();
        await expect(page.getByText('Access management', { exact: true })).toBeVisible();
        await expect(page.getByText(/Current access is role-based and scope-aware/)).toBeVisible();
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
            await expect(page).toHaveScreenshot('tsk008-authorization-ar-mobile.png', { fullPage: true });
        }
    });
});
