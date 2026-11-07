import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('authorized inventory user sees action controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/inventory/balances');
    expect(await page.locator('button, a').count()).toBeGreaterThan(0);
    await expect(page.locator('body')).toContainText(/inventory|stock|المخزون|الرصيد/i);
    await logout(page);
});
