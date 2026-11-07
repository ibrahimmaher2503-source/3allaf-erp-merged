import { expect, type Page } from '@playwright/test';
import { loginAs, logout } from '../support/auth';

export const roles = [
    'system-administrator', 'branch-manager', 'cashier', 'purchasing-officer',
    'warehouse-manager', 'pricing-officer', 'party-manager', 'stock-counter', 'accountant-reviewer',
] as const;

export async function openAs(page: Page, role: string, path: string) {
    await loginAs(page, role);
    const response = await page.goto(path);
    return { response, status: response?.status() ?? 0 };
}

export async function assertDenied(page: Page, path: string) {
    const response = await page.goto(path);
    const status = response?.status() ?? 0;
    const body = await page.locator('body').innerText();
    expect(status === 403 || /unauthorized|forbidden|غير مصرح|ليس لديك صلاحية|403/i.test(body)).toBeTruthy();
    return status;
}

export { logout };
