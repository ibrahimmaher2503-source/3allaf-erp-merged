import { test, expect } from '@playwright/test';

const admin = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill(admin.username);
    await page.getByLabel('Password', { exact: true }).fill(admin.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);
}

test('Phase 1 remediation routes and accessible recovery states render in headed Chromium', async ({ page }) => {
    test.setTimeout(120_000);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await login(page);

    for (const [path, expected] of [
        ['/profile', 'Settings'],
        ['/admin/company', 'System Settings'],
    ]) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: expected, exact: true }).first()).toBeVisible();
    }

    await page.goto('/admin/system/backups', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Backup & Restore', exact: true }).first()).toBeVisible();
    await expect(page.getByText('Backup destination', { exact: true })).toBeVisible();
    await expect(page.getByText('Restore workflow', { exact: true })).toBeVisible();

    await page.goto('/notifications', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Notifications', exact: true }).first()).toBeVisible();
    await expect(page.getByText('No notifications', { exact: true })).toBeVisible();

    await page.goto('/catalog/categories', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Add category', exact: true }).click();
    await expect(page.locator('label[for="category-code"]')).toHaveText('Category code');
    await page.getByRole('button', { name: 'Save category', exact: true }).click();
    await expect(page.getByText('The Category code field is required.', { exact: true }).first()).toBeVisible();

    await page.goto('/catalog/products', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Add Product', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Create product identity', exact: true })).toBeVisible();

    const notFound = await page.goto('/phase1-remediation-missing', { waitUntil: 'domcontentloaded' });
    expect(notFound?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Page not found', exact: true })).toBeVisible();
    expect(pageErrors).toEqual([]);
});
