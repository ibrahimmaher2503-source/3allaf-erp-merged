import type { Page } from '@playwright/test';
import { roleCredentials } from './env';

export async function loginAs(page: Page, role: string) {
    const credentials = roleCredentials(role);
    if (!credentials.password) throw new Error(`Missing UI_AUTH password for role ${role}`);
    const loginIp = `10.0.${Math.floor(Math.random() * 200) + 1}.${Math.floor(Math.random() * 200) + 1}`;
    await page.context().setExtraHTTPHeaders({ 'X-Forwarded-For': loginIp });
    await page.context().clearCookies();
    await page.goto('/login');
    await page.locator('input[name="username"]').fill(credentials.username);
    await page.locator('input[name="password"]').fill(credentials.password);
    await page.getByRole('button', { name: /تسجيل الدخول|log in|login/i }).click();
    await page.waitForLoadState('domcontentloaded');
    await assertAuthenticated(page);
}

export async function logout(page: Page) {
    const logoutButton = page.locator('form[action$="/logout"] button:visible');
    if (!(await logoutButton.isVisible().catch(() => false))) {
        if (!(await page.getByRole('button', { name: /Local System Administrator|مدير النظام|LA /i }).isVisible().catch(() => false))) {
            await page.goto('/dashboard');
        }
        await page.getByRole('button', { name: /Local System Administrator|مدير النظام|LA /i }).click();
    }
    await logoutButton.first().click();
    await page.waitForURL(url => /\/$|\/login$/.test(url.pathname), { waitUntil: 'domcontentloaded' });
}

export async function assertAuthenticated(page: Page) {
    if (/\/login/.test(page.url())) {
        const body = (await page.locator('body').innerText()).replace(/\s+/g, ' ').trim().slice(0, 300);
        throw new Error(`Expected authenticated page, got ${page.url()}. Login page says: ${body}`);
    }
    await page.locator('body').waitFor();
}
