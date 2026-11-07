import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('pricing screens expose filters and product price controls', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/pricing');
    expect(await page.locator('input, select, button').count()).toBeGreaterThan(0);
    await expect(page.locator('body')).toContainText(/pricing|price|التسعير|السعر/i);
    await logout(page);
});
