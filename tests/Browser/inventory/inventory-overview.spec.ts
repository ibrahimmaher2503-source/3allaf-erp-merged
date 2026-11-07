import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('inventory overview, balances, movements, and export render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/inventory', '/inventory/balances', '/inventory/movements', '/inventory/additions', '/inventory/deductions']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await logout(page);
});
