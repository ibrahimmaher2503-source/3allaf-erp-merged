import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login } from '../helpers/auth.js';

async function setLocale(page, locale) {
    const token = await page.locator('input[name="_token"]').first().inputValue();
    await page.request.post('/locale', { form: { _token: token, locale } });
    await page.reload();
}

test.describe('TSK-004 shared UI foundation', () => {
    test.setTimeout(60_000);
    test('authorized user can exercise shared states, controls, dialog, and print pattern', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const response = await page.goto('/admin/system/ui-showcase');
        await setLocale(page, 'en');
        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'UI Pattern Showcase' })).toBeVisible();

        await page.getByRole('tab', { name: 'Forms', exact: true }).click();
        await expect(page.getByText('Grouped form fields')).toBeVisible();
        await page.getByRole('button', { name: 'Open dialog', exact: true }).click();
        await expect(page.getByText('Confirmation pattern', { exact: true })).toBeVisible();
        await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click();

        await page.getByRole('button', { name: 'Show loading', exact: true }).click();
        await expect(page.getByRole('status').filter({ hasText: 'Refreshing the showcase' })).toBeVisible();
        await page.getByRole('button', { name: 'Clear loading', exact: true }).click();

        await page.getByRole('tab', { name: 'Data', exact: true }).click();
        await expect(page.getByText('Server-driven data panel')).toBeVisible();
        await page.getByRole('tab', { name: 'States', exact: true }).click();
        await expect(page.getByText('No matching locations')).toBeVisible();
        await expect(page.getByText('REQ-DEMO-403')).toBeVisible();
        await page.getByRole('tab', { name: 'Print', exact: true }).click();
        await expect(page.getByText('Thermal')).toBeVisible();
    });

    test('shared UI is accessible and has no mobile overflow in Arabic RTL', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/admin/system/ui-showcase');
        await setLocale(page, 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

        const results = await new AxeBuilder({ page }).exclude('.phpdebugbar').analyze();
        const serious = results.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact));
        expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);

        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk004-shared-ui-ar-mobile.png', { fullPage: true });
        }
    });

    test('English desktop shared UI visual baseline is stable', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.goto('/admin/system/ui-showcase');
        await setLocale(page, 'en');
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk004-shared-ui-en-desktop.png', { fullPage: true });
        }
    });
});
