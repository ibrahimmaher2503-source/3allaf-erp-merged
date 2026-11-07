import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../support/auth';
import { capture, recordFailure, step } from '../support/evidence';

test('system administrator can log in and log out through the UI', async ({ page }, testInfo) => {
    const consoleErrors: string[] = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('pageerror', error => consoleErrors.push(error.message));
    try {
        await step(page, 'AUTH-001', 'open login');
        await loginAs(page, 'system-administrator');
        await expect(page).not.toHaveURL(/\/login/);
        await capture(page, 'AUTH-001-authenticated');
        await step(page, 'AUTH-002', 'logout');
        await logout(page);
        await expect(page).toHaveURL(/\/$|\/login/);
        expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
    } catch (error) {
        await recordFailure(testInfo);
        throw error;
    }
});
