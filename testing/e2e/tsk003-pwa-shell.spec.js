import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth.js';

test.describe('TSK-003 PWA shell', () => {
    test('authenticated user can reach the shell and static manifest/service worker', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        const response = await page.goto('/system/app');
        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', /manifest\.json/);

        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk003-shell-en.png', { fullPage: true });
        }

        const manifest = await page.request.get('/manifest.json');
        expect(manifest.status()).toBe(200);
        expect((await manifest.json()).display).toBe('standalone');

        const serviceWorker = await page.request.get('/sw.js');
        expect(serviceWorker.status()).toBe(200);
        expect(await serviceWorker.text()).toContain('Network-only');
    });

    test('Arabic shell is RTL and remains usable at mobile width', async ({ page }) => {
        await login(page, 'playwright-admin', 'PlaywrightTest!2026');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/system/app');
        const token = await page.locator('input[name="_token"]').first().inputValue();
        await page.request.post('/locale', { form: { _token: token, locale: 'ar' } });
        await page.reload();
        expect(await page.locator('html').getAttribute('dir')).toBe('rtl');
        expect(await page.locator('html').getAttribute('lang')).toBe('ar');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk003-shell-ar-mobile.png', { fullPage: true });
        }
    });
});
