import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { login, LOCAL_BROWSER_ACTORS } from '../helpers/auth.js';

test.describe('TSK-007 cash drawer masters', () => {
    test.setTimeout(60_000);

    test('administrator can reach the protected cash drawer master', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        const response = await page.goto('/admin/cash-drawers');
        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Cash Drawer Masters' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Add Cash Drawer', exact: true })).toBeVisible();
    });

    test('open shift blocks drawer deactivation with clear feedback', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/admin/cash-drawers');

        const drawerRow = page.locator('tbody tr').filter({ hasText: 'DEMO-DR-01' });
        await expect(drawerRow).toHaveCount(1);
        await expect(drawerRow.getByText('Active', { exact: true })).toBeVisible();

        await drawerRow.getByRole('button', { name: 'Deactivate', exact: true }).click();

        await expect(page.getByText('Cash drawer change blocked.', { exact: true })).toBeVisible();
        await expect(page.getByText('Cannot deactivate or reassign a cash drawer while it has an active POS shift. Close the shift before trying again.', { exact: true })).toBeVisible();
        await expect(drawerRow.getByText('Active', { exact: true })).toBeVisible();
    });

    test('Arabic mobile view is axe-clean and responsive', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/admin/cash-drawers');
        await page.setViewportSize({ width: 390, height: 844 });
        const token = await page.locator('input[name="_token"]').first().inputValue();
        await page.request.post('/locale', { form: { _token: token, locale: 'ar' } });
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        const results = await new AxeBuilder({ page }).exclude('.phpdebugbar').analyze();
        const serious = results.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
        expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk007-cash-drawers-ar-mobile.png', { fullPage: true });
        }
    });
});
