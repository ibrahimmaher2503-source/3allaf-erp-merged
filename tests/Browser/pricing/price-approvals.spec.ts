import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('pricing approval inbox is reachable and exposes action controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    const response = await page.goto('/pricing/approvals');
    expect(response?.status()).toBe(200);
    expect(await page.locator('button, a').count()).toBeGreaterThan(0);
    await logout(page);
});
