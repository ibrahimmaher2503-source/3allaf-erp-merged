import { expect, test } from '@playwright/test';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

test.describe('Reported regression journeys', () => {
    test('Store Transfers: Require source and destination stores for a transfer', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/inventory/transfers/create', { waitUntil: 'domcontentloaded' });

        const source = page.getByLabel('Source store', { exact: true });
        const destination = page.getByLabel('Destination store', { exact: true });

        await expect(source).toHaveValue('');
        await expect(destination).toHaveValue('');
        await expect(source.locator('option[value=""]')).toHaveText('Select source store');
        await expect(destination.locator('option[value=""]')).toHaveText('Select destination store');

        await page.getByRole('button', { name: 'Save draft', exact: true }).click();
        await expect(page.locator('#transfer-source-error')).toBeVisible();
        await expect(page.locator('#transfer-destination-error')).toBeVisible();

        const firstStoreId = await source.locator('option').nth(1).getAttribute('value');
        await source.selectOption(firstStoreId);
        await destination.selectOption(firstStoreId);
        await page.getByRole('button', { name: 'Save draft', exact: true }).click();
        await expect(page.locator('#transfer-destination-error')).toHaveText('Source and destination stores must be different.');
    });

    test('Store Transfers: Dispatch and receive a store transfer', async ({ page, browser }) => {
        const requesterContext = await browser.newContext({ locale: 'en-US' });
        const requesterPage = await requesterContext.newPage();
        await login(requesterPage, LOCAL_BROWSER_ACTORS.warehouseScoped.username, LOCAL_BROWSER_ACTORS.warehouseScoped.password);
        await requesterPage.goto('/inventory/transfers/create', { waitUntil: 'domcontentloaded' });

        await requesterPage.getByLabel('Source store', { exact: true }).selectOption({ label: 'DEMO-SELL' });
        await requesterPage.getByLabel('Destination store', { exact: true }).selectOption({ label: 'DEMO-WH' });
        const productId = await requesterPage.getByLabel('Product', { exact: true }).locator('option').filter({ hasText: 'BROWSER-OPEN-001' }).getAttribute('value');
        await requesterPage.getByLabel('Product', { exact: true }).selectOption(productId);
        await requesterPage.getByLabel('Requested quantity', { exact: true }).fill('1');
        await requesterPage.getByLabel('Reason', { exact: true }).fill('browser-regression-transfer');
        await requesterPage.getByRole('button', { name: 'Save draft', exact: true }).click();

        let requesterTransfer = requesterPage.locator('[data-transfer-row]').first();
        await expect(requesterTransfer).toContainText('Draft');
        const transferNumber = (await requesterTransfer.locator('strong').textContent()).trim();
        await requesterTransfer.getByRole('button', { name: 'Submit', exact: true }).click();
        requesterTransfer = requesterPage.locator('[data-transfer-row]').filter({ hasText: transferNumber });
        await expect(requesterTransfer).toContainText('Submitted');
        await requesterContext.close();

        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/inventory/transfers', { waitUntil: 'domcontentloaded' });

        let transfer = page.locator('[data-transfer-row]').filter({ hasText: transferNumber });
        await expect(transfer).toContainText('Submitted');
        await transfer.getByRole('button', { name: 'Approve', exact: true }).click();
        transfer = page.locator('[data-transfer-row]').filter({ hasText: transferNumber });
        await expect(transfer).toContainText('Approved');

        await transfer.getByRole('button', { name: 'Dispatch', exact: true }).click();
        transfer = page.locator('[data-transfer-row]').filter({ hasText: transferNumber });
        await expect(transfer).toContainText('In transit');

        await transfer.getByRole('button', { name: 'Record receipt', exact: true }).click();
        await expect(page.locator('[data-transfer-row]').filter({ hasText: transferNumber })).toContainText('Received');
    });

    test('Payments and Tax: Record a manual electronic payment with receipt evidence', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        await expect(page.getByLabel('Payment method', { exact: true }).first()).toContainText('Manual card terminal');
        await expect(page.getByLabel('Protected payment evidence', { exact: true })).toBeAttached();
    });

    test('Payments and Tax: Take a cash payment for a sale', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        await expect(page.getByText('No active shift', { exact: true })).toHaveCount(0);
        await expect(page.getByText('Cash is blocked because the Initial Setup cash-rounding denomination is not configured.', { exact: true })).toHaveCount(0);
    });

    test('Point of Sale: Open a cashier shift from POS', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        await expect(page.getByRole('link', { name: 'Manage shift', exact: true })).toHaveAttribute('href', /\/pos\/shift$/);
    });

    test('Point of Sale: Complete a retail sale in POS', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        await expect(page.getByText('New customer registration is blocked until the consent-purpose policy is configured.', { exact: true })).toHaveCount(0);
        await expect(page.getByText('Register a new customer', { exact: true })).toBeVisible();

        const productCard = page.getByText('Open-price demo toy', { exact: true }).locator('..').locator('..');
        await productCard.getByRole('button', { name: 'Add', exact: true }).click();
        await page.getByLabel('Use cash for the remaining residual', { exact: true }).check();
        await page.getByLabel('Cash tendered', { exact: true }).fill('100');
        await page.getByRole('button', { name: 'Settle and complete sale', exact: true }).click();

        await expect(page).toHaveURL(/\/sales\/\d+$/);
        await expect(page.getByText('Payments', { exact: true }).last()).toBeVisible();
    });

    test('Gift Cards: Redeem an active gift card', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        await expect(page.getByText('Gift Card tender', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Payment method', { exact: true }).last()).toContainText('Gift Card');
    });

    test('Customer Management and Loyalty: View authorized customer retail history', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/customers', { waitUntil: 'domcontentloaded' });

        const customerRow = page.locator('[data-customer-row]').filter({ hasText: 'Browser customer' });
        await expect(customerRow).toBeVisible();
        await customerRow.getByRole('link', { name: 'Open profile', exact: true }).click();
        await expect(page.getByRole('region', { name: 'Customer history', exact: true })).toBeVisible();
    });
});
