import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

async function setLocale(page, locale) {
    const token = await page.locator('input[name="_token"]').first().inputValue();
    await page.request.post('/locale', { form: { _token: token, locale } });
    await page.reload();
}

test.describe('TSK-005 settings', () => {
    test.setTimeout(60_000);

    test('Administration and Master Data: Preview company settings before saving', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/admin/settings');
        await setLocale(page, 'en');

        await page.getByLabel('Company Code', { exact: true }).fill('TOY-JOY-PREVIEW');
        await page.getByLabel('Tax Identification Number (TIN)', { exact: true }).fill('300000000000099');
        await page.getByRole('button', { name: 'Save Company Baseline', exact: true }).click();

        const preview = page.getByRole('dialog').filter({ hasText: 'Review company baseline' });
        await expect(preview).toBeVisible();
        await expect(preview.getByText('TOY-JOY-PREVIEW', { exact: true })).toBeVisible();
        await expect(preview.getByText('300000000000099', { exact: true })).toBeVisible();
        await preview.getByRole('button', { name: 'Confirm and save', exact: true }).click();
        await expect(preview).not.toBeVisible();
        await expect(page.getByText('Company settings saved successfully.', { exact: true })).toBeVisible();

        await page.reload();
        await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue('TOY-JOY-PREVIEW');
        await expect(page.getByLabel('Tax Identification Number (TIN)', { exact: true })).toHaveValue('300000000000099');
    });

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
