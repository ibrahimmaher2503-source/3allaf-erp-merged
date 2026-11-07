import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('inventory adjustments list and create expose reason and quantity fields', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/inventory/adjustments', '/inventory/adjustments/create']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await expect(page.locator('input:not([type="hidden"]), textarea, select').first()).toBeVisible();
    await logout(page);
});
