import { test, expect } from '@playwright/test';

test('Product Add Product opens, validates, saves, closes, and reopens its creation dialog', async ({ page }) => {
    test.setTimeout(60_000);
    const pageErrors = [];
    const failedResponses = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('response', (response) => {
        if (response.url().includes('/livewire-') && response.status() >= 400) {
            failedResponses.push(`${response.status()} ${response.url()}`);
        }
    });

    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill('admin');
    await page.getByLabel('Password', { exact: true }).fill('ToyJoy!Bootstrap2026');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);

    await page.goto('/catalog/products', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Add Product', exact: true }).click();
    expect(failedResponses).toEqual([]);

    const dialog = page.getByRole('dialog');
    const itemCode = page.getByLabel('Immutable item code', { exact: true });

    await expect(dialog.getByText('Create product identity', { exact: true })).toBeVisible();
    await expect(itemCode).toBeFocused();
    await expect(page.getByLabel('Arabic product name', { exact: true })).toBeVisible();
    await expect(page.getByLabel('English product name', { exact: true })).toBeVisible();
    await expect(dialog.getByLabel('Product type', { exact: true })).toBeVisible();
    await expect(dialog.getByLabel('Category', { exact: true })).toBeVisible();

    await dialog.getByRole('button', { name: 'Save product', exact: true }).click();
    await expect(itemCode).toHaveAttribute('aria-invalid', 'true');

    const productCode = `US002-MODAL-${Date.now()}`;
    await itemCode.fill(productCode);
    await page.getByLabel('Arabic product name', { exact: true }).fill('منتج معالجة النافذة');
    await page.getByLabel('English product name', { exact: true }).fill('Modal remediation product');
    await dialog.getByLabel('Category', { exact: true }).selectOption({ index: 1 });
    await dialog.getByRole('button', { name: 'Save product', exact: true }).click();

    await expect(dialog).toBeHidden();
    await expect(page.getByRole('cell', { name: productCode, exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Add Product', exact: true }).click();
    await expect(dialog.getByText('Create product identity', { exact: true })).toBeVisible();
    await expect(itemCode).toHaveValue('');
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('button', { name: 'Add Product', exact: true })).toBeFocused();

    expect(pageErrors).toEqual([]);
});
