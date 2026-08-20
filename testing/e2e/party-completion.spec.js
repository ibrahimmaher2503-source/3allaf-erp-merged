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
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);
}

async function selectOptionContaining(select, text) {
    const value = await select.locator('option').filter({ hasText: text }).getAttribute('value');
    expect(value).not.toBeNull();
    await select.selectOption(value);
}

test('Party booking, product selection, asset, wallet, and quotation UI are connected', async ({ page }) => {
    test.setTimeout(180_000);
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await login(page);
    const createResponse = await page.goto('/parties/bookings/create', { waitUntil: 'domcontentloaded' });
    expect(createResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'New Party booking', exact: true })).toBeVisible();
    await page.getByLabel('Party store', { exact: true }).selectOption({ label: 'Party UI Store' });
    await selectOptionContaining(page.getByLabel('Customer', { exact: true }), 'Party Browser Customer');
    await page.getByLabel('Location / room', { exact: true }).fill('Browser verified room');
    await page.getByLabel('Primary contact', { exact: true }).fill('01055500001');
    await page.locator('[name="lines[0][line_type]"]').selectOption('consumable');
    await page.locator('[name="lines[0][description]"]').fill('Browser Party cups');
    await page.locator('[name="lines[0][quantity]"]').fill('5');
    await page.locator('[name="lines[0][unit_price]"]').fill('10');
    await selectOptionContaining(page.locator('[name="lines[0][product_id]"]'), 'PARTY-CUPS-UI');
    await Promise.all([
        page.waitForURL(/\/parties\/bookings\/\d+$/),
        page.getByRole('button', { name: 'Create booking', exact: true }).click(),
    ]);

    await expect(page.getByText('Reschedule booking', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Cancel booking', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Browser Party cups', { exact: true })).toBeVisible();
    const bookingUrl = page.url();

    await page.getByRole('link', { name: 'Open invoice', exact: true }).click();
    await expect(page.getByText('Add another Party line', { exact: true }).first()).toBeVisible();
    await expect(page.locator('[name="lines[1][product_id]"] option', { hasText: 'PARTY-CUPS-UI' })).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'Review final settlement', exact: true })).toBeVisible();

    const assetsResponse = await page.goto('/party/assets?mode=workspace', { waitUntil: 'domcontentloaded' });
    expect(assetsResponse?.status()).toBe(200);
    await expect(page.getByText('PARTY-ASSET-UI', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'History / print', exact: true })).toBeVisible();

    const walletResponse = await page.goto('/wallets/party', { waitUntil: 'domcontentloaded' });
    expect(walletResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Party Wallet', exact: true })).toBeVisible();
    await expect(page.getByText('Party-only customer balance derived from a separate immutable, source-linked ledger.', { exact: true })).toBeVisible();

    const quotationResponse = await page.goto('/quotations', { waitUntil: 'domcontentloaded' });
    expect(quotationResponse?.status()).toBe(200);
    await expect(page.getByLabel('Customer (optional)', { exact: true }).locator('option', { hasText: 'Party Browser Customer' })).toHaveCount(1);
    await expect(page.getByLabel('Store', { exact: true }).locator('option', { hasText: 'PARTY-UI' })).toHaveCount(1);
    await expect(page.getByLabel('Catalog product (retail only)', { exact: true }).first().locator('option', { hasText: 'PARTY-CUPS-UI' })).toHaveCount(1);
    await expect(page.getByText(/Party quotations use service or rental-asset lines/)).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(bookingUrl, { waitUntil: 'domcontentloaded' });
    const localeForm = page.locator('form[action$="/locale"]').first();
    await localeForm.locator('input[name="locale"][value="ar"]').evaluate((input) => input.form.submit());
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    expect(pageErrors).toEqual([]);
});
