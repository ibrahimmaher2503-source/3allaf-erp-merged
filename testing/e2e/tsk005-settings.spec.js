import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

async function setLocale(page, locale) {
    const token = await page.locator('input[name="_token"]').first().inputValue();
    await page.request.post('/locale', { form: { _token: token, locale } });
    await page.reload();
}

test.describe('TSK-005 settings', () => {
    test.setTimeout(60_000);

    test('administrator can inspect settings, effective tax fields and printer preview', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const response = await page.goto('/admin/settings');
        expect(response?.status()).toBe(200);
        await setLocale(page, 'en');
        await expect(page.getByRole('heading', { name: 'System Settings' })).toBeVisible();

        await page.getByRole('tab', { name: /Tax/i }).click();
        await expect(page.getByLabel('Effective From')).toBeVisible();
        await expect(page.getByLabel('Effective To')).toBeVisible();

        await page.getByRole('tab', { name: /Printer/i }).click();
        await expect(page.getByText('Configured Printer Profiles')).toBeVisible();
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk005-settings-en-desktop.png', { fullPage: true });
        }
        const preview = page.getByRole('link', { name: 'Preview' }).first();
        if (await preview.count()) {
            const previewPagePromise = page.waitForEvent('popup');
            await preview.click();
            const previewPage = await previewPagePromise;
            await expect(previewPage.getByText('Configuration preview', { exact: true })).toBeVisible();
        }
    });

    test('settings page is accessible and responsive in Arabic RTL', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/admin/settings');
        await setLocale(page, 'ar');
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        const results = await new AxeBuilder({ page }).exclude('.phpdebugbar').analyze();
        const serious = results.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact));
        expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
    });
});
