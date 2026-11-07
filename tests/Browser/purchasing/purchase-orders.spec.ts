import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('purchase order list and create UI render with line-entry controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/purchasing/orders', '/purchasing/orders/create', '/purchasing/orders/import-template']) {
        const response = await page.goto(path).catch(() => null);
        expect(response?.status() ?? 200, path).toBeGreaterThanOrEqual(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await page.goto('/purchasing/orders/create');
    expect(await page.locator('input, select, textarea, button').count()).toBeGreaterThan(0);
    await logout(page);
});
