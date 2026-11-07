import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('customer directory, create, groups, import, and readiness screens render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/customers', '/customers/create', '/customers/groups', '/customers/import', '/customers/loyalty-readiness']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await page.goto('/customers/create');
    expect(await page.locator('input, select, textarea').count()).toBeGreaterThan(0);
    await logout(page);
});
