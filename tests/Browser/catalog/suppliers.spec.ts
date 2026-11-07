import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('supplier list, create, import, template, and export routes are reachable', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/catalog/suppliers', '/catalog/suppliers/import']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    const downloadPromise = page.waitForEvent('download');
    await page.goto('/catalog/suppliers/import/template').catch(() => undefined);
    const download = await downloadPromise;
    expect(await download.suggestedFilename()).toMatch(/\.(xlsx|csv)$/i);
    await logout(page);
});
