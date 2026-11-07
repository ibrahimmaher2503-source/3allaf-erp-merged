import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('stocktake list and create routes expose count controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/inventory/counts', '/inventory/counts/create']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    expect(await page.locator('input, select, button').count()).toBeGreaterThan(0);
    await logout(page);
});
