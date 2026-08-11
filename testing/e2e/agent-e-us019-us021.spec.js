import { test, expect } from '@playwright/test';
import { login, LOCAL_DEMO_PASSWORD } from '../helpers/auth.js';

test.use({ headless: false });

test.describe('Agent E - Gift Receipt, Returns, Gift Cards', () => {
    test('issues a gift card, redeems it through POS, and completes a source-linked return', async ({ page, context, browser }) => {
        test.setTimeout(120000);
        await context.addCookies([{ name: 'locale', value: 'en', domain: '127.0.0.1', path: '/' }]);
        await login(page, 'demo-admin', LOCAL_DEMO_PASSWORD);
        await page.goto('/gift-receipts');
        await expect(page.getByRole('heading', { name: 'Gift Receipts', exact: true })).toBeVisible();
        await expect(page.getByText('Prices, discounts, tax, totals, payment details, and hidden price metadata are intentionally omitted.', { exact: false })).toHaveCount(0);
        await expect(page.getByText('No Gift Receipts yet')).toBeVisible();

        await page.goto('/gift-cards');
        await expect(page.getByRole('heading', { name: 'Gift Cards', exact: true })).toBeVisible();
        const store = page.locator('select[name="store_id"] option').nth(0);
        await page.locator('select[name="store_id"]').selectOption(await store.getAttribute('value'));
        await page.getByLabel('Value').fill('125.00');
        await page.getByRole('button', { name: 'Issue card' }).click();
        await expect(page.locator('tr').filter({ hasText: 'GC-' }).first()).toBeVisible();

        const cardRow = page.locator('tr').filter({ hasText: 'GC-' }).first();
        const cardIdentifier = (await cardRow.locator('td').first().innerText()).trim();
        await cardRow.getByRole('link', { name: 'Print' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('GC-');
        await page.goBack();
        await expect(page.getByRole('heading', { name: 'Gift Cards', exact: true })).toBeVisible();

        const cashierContext = await browser.newContext({ locale: 'en-US' });
        const cashierPage = await cashierContext.newPage();
        await login(cashierPage, 'demo-cashier', LOCAL_DEMO_PASSWORD);
        await cashierPage.goto('/pos');
        await cashierPage.getByText('Open-price demo toy', { exact: true }).locator('..').locator('..').getByRole('button', { name: 'Add', exact: true }).click();
        await cashierPage.getByLabel('Payment method', { exact: true }).last().selectOption({ label: 'Gift Card' });
        await cashierPage.getByLabel('Gift Card identifier', { exact: true }).fill(cardIdentifier);
        await cashierPage.getByLabel('Gift Card amount', { exact: true }).fill('100.00');
        await cashierPage.getByRole('button', { name: 'Settle and complete sale', exact: true }).click();
        await expect(cashierPage).toHaveURL(/\/sales\/\d+$/);
        await expect(cashierPage.getByText('100.00', { exact: false }).first()).toBeVisible();
        await cashierContext.close();

        await page.goto('/gift-cards');
        await page.locator('tr').filter({ hasText: cardIdentifier }).getByRole('link', { name: 'History' }).click();
        await expect(page.getByText('Redeem', { exact: true })).toBeVisible();
        await expect(page.getByText('25.00', { exact: false }).first()).toBeVisible();

        await page.goto('/returns');
        await page.getByLabel('Source completed sale').selectOption({ index: 1 });
        const sourceLineValue = await page.getByLabel('Source line').locator('option').filter({ hasText: 'Open-price demo toy' }).first().getAttribute('value');
        await page.getByLabel('Source line').selectOption(sourceLineValue);
        await page.getByLabel('Settlement').selectOption('gift_card');
        await page.getByLabel('Reason').fill('Browser partial return');
        await page.getByRole('button', { name: 'Create draft', exact: true }).click();
        await page.getByRole('button', { name: 'Submit for approval', exact: true }).click();
        await page.getByRole('button', { name: 'Approve', exact: true }).click();
        await page.getByRole('button', { name: 'Complete settlement and stock movement', exact: true }).click();
        await expect(page.getByText(/completed/i).first()).toBeVisible();
        await expect(page.getByText('Gift Card', { exact: true }).first()).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/returns');
        await expect(page.getByRole('heading', { name: 'Returns & Exchanges', exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await context.addCookies([{ name: 'locale', value: 'ar', domain: '127.0.0.1', path: '/' }]);
        await page.goto('/gift-cards');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
});
