import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('purchase invoice screens expose receiving, readiness, settings, and cost history', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    for (const path of ['/purchasing/invoices', '/purchasing/invoices/readiness', '/purchasing/invoices/settings', '/purchasing/cost-history']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await expect(page.locator('body')).toBeVisible();
    }
    await logout(page);
});
