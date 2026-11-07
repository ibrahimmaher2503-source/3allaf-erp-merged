import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('supplier returns list, create, settings, and history routes render', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/purchasing/returns', '/purchasing/returns/create', '/purchasing/returns/settings', '/purchasing/supplier-history']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await logout(page);
});
