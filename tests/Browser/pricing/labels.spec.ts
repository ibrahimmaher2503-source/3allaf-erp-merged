import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('price labels search and preview screens render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/pricing/labels', '/pricing/labels/search', '/pricing/labels/prices']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await logout(page);
});
