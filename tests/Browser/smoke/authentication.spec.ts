import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

test('valid and invalid credentials are handled through the UI', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'en-desktop', 'Invalid credential rejection is covered in Arabic. English locale has a separate valid-login smoke.');
    await page.goto('/login');
    await page.locator('input[name="username"]').fill('local.system-administrator');
    await page.locator('input[name="password"]').fill('wrong-password');
    await page.getByRole('button', { name: /تسجيل الدخول|log in|login/i }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/credentials|invalid|incorrect|خطأ|بيانات الدخول/i);

    await loginAs(page, 'system-administrator');
    await expect(page).not.toHaveURL(/\/login/);
    await logout(page);
    await expect(page).toHaveURL(/\/$|\/login/);
});

test('authentication works in English UI', async ({ page }) => {
    await page.goto('/login?lang=en');
    await page.locator('input[name="username"]').fill('local.system-administrator');
    const password = process.env.UI_AUTH_SYSTEM_ADMINISTRATOR_PASSWORD;
    if (!password) throw new Error('UI_AUTH_SYSTEM_ADMINISTRATOR_PASSWORD is required');
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: /log in|login|تسجيل الدخول/i }).click();
    await expect(page).not.toHaveURL(/\/login/);
    await logout(page);
});
