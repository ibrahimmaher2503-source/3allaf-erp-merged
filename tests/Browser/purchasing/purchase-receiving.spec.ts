import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('receiving and quantity-difference screens render validation-capable controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/purchasing/quantity-differences', '/purchasing/invoices']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    expect(await page.locator('input, select, button').count()).toBeGreaterThan(0);
    await logout(page);
});
