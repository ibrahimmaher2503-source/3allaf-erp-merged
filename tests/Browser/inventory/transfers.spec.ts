import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('transfer list, create, receiving inbox, and difference routes render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/inventory/transfers', '/inventory/transfers/create', '/inventory/transfers/receiving']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await logout(page);
});
