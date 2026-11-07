import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('top-level browser destinations render through direct and visible navigation', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    const destinations = ['/dashboard', '/alerts', '/approvals', '/notifications', '/catalog/products', '/inventory/balances'];
    for (const destination of destinations) {
        const response = await page.goto(destination);
        expect(response?.status(), destination).toBe(200);
        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('h1, [role="heading"]').first()).toBeVisible();
    }
    await logout(page);
});
