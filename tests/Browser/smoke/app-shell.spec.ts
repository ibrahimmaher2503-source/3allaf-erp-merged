import { test } from '@playwright/test';
import { loginAs, logout } from '../support/auth';
import { assertAccessibleActions, assertShell } from '../support/ui-assertions';

async function selectArabic(page: import('@playwright/test').Page) {
    const language = page.getByRole('combobox', { name: /language|اللغة/i });
    if (!(await language.isVisible().catch(() => false))) {
        await page.getByRole('button', { name: /Local System Administrator|مدير النظام|LA /i }).click();
    }
    await page.getByRole('combobox', { name: /language|اللغة/i }).selectOption({ label: 'العربية' });
    await page.waitForLoadState('domcontentloaded');
}

test('Arabic desktop shell exposes branded navigation and accessible actions', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/dashboard');
    await selectArabic(page);
    await assertShell(page, 'ar', { width: 1440, height: 1000 });
    const missingNames = await assertAccessibleActions(page);
    await page.evaluate(value => { document.body.dataset.uiAcceptanceMissingNames = String(value); }, missingNames.length);
    await logout(page);
});

test('Arabic mobile shell fits the viewport', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/dashboard');
    await selectArabic(page);
    await assertShell(page, 'ar', { width: 390, height: 844 });
    await logout(page);
});

test('English shell uses LTR after selecting language from the UI', async ({ page }) => {
    await loginAs(page, 'system-administrator');
    await page.goto('/dashboard');
    const language = page.getByRole('combobox', { name: /language|اللغة/i });
    if (await language.count()) await language.selectOption({ label: 'English' });
    await page.waitForLoadState('domcontentloaded');
    await assertShell(page, 'en', { width: 1440, height: 1000 });
    await logout(page);
});
