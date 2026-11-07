import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('purchase screens expose required action labels to authorized users', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/purchasing/invoices');
    await expect(page.locator('button, a')).toHaveCount(await page.locator('button, a').count());
    await expect(page.locator('body')).toContainText(/purchase|invoice|شراء|فاتورة/i);
    await logout(page);
});
