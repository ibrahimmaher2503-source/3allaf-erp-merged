import { expect, test } from '@playwright/test';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

test.describe('US-008/017/018 POS closure', () => {
    test('cashier requests approved open price, manager decides, and cashier completes the real sale', async ({ page, browser }) => {
        test.setTimeout(120000);
        const consoleErrors = [];
        const failedRequests = [];
        const httpErrors = [];
        const observe = (observedPage) => {
            observedPage.on('console', (message) => {
                if (message.type() === 'error') consoleErrors.push(message.text());
            });
            observedPage.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));
            observedPage.on('response', (response) => {
                if (response.status() >= 400) httpErrors.push(`${response.status()} ${response.url()}`);
            });
        };
        observe(page);
        page.on('dialog', (dialog) => dialog.accept());

        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('POS Checkout', { exact: true })).toBeVisible();

        const productCard = page.getByText('Open-price demo toy', { exact: true }).locator('..').locator('..');
        await productCard.getByRole('button', { name: 'Add', exact: true }).click();
        const cartLine = page.locator('[data-cart-line]').filter({ hasText: 'Open-price demo toy' });
        await expect(cartLine).toBeVisible();
        await expect(page.getByText('Manager approval is required when the requested price differs from the reference by more than', { exact: false })).toBeVisible();
        await cartLine.getByLabel('Open price amount', { exact: true }).fill('115');
        await cartLine.getByLabel('Required reason', { exact: true }).fill('Browser customer exception');
        await cartLine.getByRole('button', { name: 'Set open price', exact: true }).click();
        await expect(page.getByText('Independent manager approval is pending', { exact: false })).toBeVisible();

        const managerContext = await browser.newContext({ locale: 'en-US' });
        const managerPage = await managerContext.newPage();
        observe(managerPage);
        managerPage.on('dialog', (dialog) => dialog.accept());
        await login(managerPage, LOCAL_BROWSER_ACTORS.branchScoped.username, LOCAL_BROWSER_ACTORS.branchScoped.password);
        await managerPage.goto('/approvals', { waitUntil: 'domcontentloaded' });
        await expect(managerPage.getByText('Approval inbox', { exact: true })).toBeVisible();
        await managerPage.getByRole('button', { name: 'Review', exact: true }).first().click();
        await expect(managerPage.getByText('approve_open_price', { exact: true })).toBeVisible();
        await managerPage.getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(managerPage.getByText('approved', { exact: false }).first()).toBeVisible();
        await managerContext.close();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Manager approval is recorded', { exact: false })).toBeVisible();
        const discountForm = page.locator('[data-cart-line]').filter({ hasText: 'Open-price demo toy' }).locator('form[action$="/pos/cart/discount"]');
        await discountForm.getByLabel('Discount amount', { exact: true }).fill('20');
        await discountForm.getByRole('button', { name: 'Apply discount', exact: true }).click();
        await expect(page.getByText('Independent manager approval is pending', { exact: false })).toBeVisible();

        const discountManagerContext = await browser.newContext({ locale: 'en-US' });
        const discountManagerPage = await discountManagerContext.newPage();
        observe(discountManagerPage);
        discountManagerPage.on('dialog', (dialog) => dialog.accept());
        await login(discountManagerPage, LOCAL_BROWSER_ACTORS.branchScoped.username, LOCAL_BROWSER_ACTORS.branchScoped.password);
        await discountManagerPage.goto('/approvals', { waitUntil: 'domcontentloaded' });
        await expect(discountManagerPage.getByRole('button', { name: 'Review', exact: true }).first()).toBeVisible();
        await discountManagerPage.getByRole('button', { name: 'Review', exact: true }).first().click();
        await expect(discountManagerPage.getByText('approve_discount', { exact: true })).toBeVisible();
        await discountManagerPage.getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(discountManagerPage.getByText('approved', { exact: false }).first()).toBeVisible();
        await discountManagerContext.close();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Manager approval is recorded', { exact: false })).toHaveCount(2);
        await page.getByRole('button', { name: 'Enable tax', exact: true }).click();
        await expect(page.getByText('Tax', { exact: true }).last()).toBeVisible();
        await page.getByLabel('Use cash for the remaining residual', { exact: true }).check();
        await page.getByLabel('Cash tendered', { exact: true }).fill('108.30');
        await page.getByRole('button', { name: 'Settle and complete sale', exact: true }).click();
        await expect(page).toHaveURL(/\/sales\/\d+$/);
        await expect(page.getByText('Cash rounding', { exact: true })).toBeVisible();
        await expect(page.getByText('Payments', { exact: true }).last()).toBeVisible();
        await expect(page.getByText('Selling price', { exact: true })).toBeVisible();

        const thermalHref = await page.getByRole('link', { name: 'Thermal receipt', exact: true }).getAttribute('href');
        const receiptPage = await page.context().newPage();
        observe(receiptPage);
        await receiptPage.goto(thermalHref, { waitUntil: 'domcontentloaded' });
        await expect(receiptPage.getByText('Selling price', { exact: false })).toBeVisible();
        await expect(receiptPage.getByText('Payments', { exact: true })).toBeVisible();
        await expect(receiptPage.getByText('115.00', { exact: false }).first()).toBeVisible();
        await expect(receiptPage.getByText('Discount', { exact: false }).first()).toBeVisible();
        await receiptPage.close();

        const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'en-US' });
        const mobilePage = await mobileContext.newPage();
        observe(mobilePage);
        await login(mobilePage, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await mobilePage.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(mobilePage.getByText('POS Checkout', { exact: true })).toBeVisible();
        const mobileOverflow = await mobilePage.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(mobileOverflow).toBeLessThanOrEqual(1);
        await mobilePage.screenshot({ path: 'testing/e2e/results/us008-017-018-pos-390-en.png', fullPage: true });
        await mobileContext.close();

        const rtlContext = await browser.newContext({ locale: 'en-US' });
        const rtlPage = await rtlContext.newPage();
        observe(rtlPage);
        await login(rtlPage, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await rtlPage.goto('/pos', { waitUntil: 'domcontentloaded' });
        await rtlPage.locator('form[action$="/locale"] button').click();
        await rtlPage.waitForLoadState('domcontentloaded');
        await expect(rtlPage.locator('html[dir="rtl"]')).toHaveCount(1);
        const rtlOverflow = await rtlPage.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(rtlOverflow).toBeLessThanOrEqual(1);
        await rtlPage.screenshot({ path: 'testing/e2e/results/us008-017-018-pos-rtl.png', fullPage: true });
        await rtlContext.close();

        expect(consoleErrors, `Unexpected browser console errors: ${consoleErrors.join('\n')} HTTP: ${httpErrors.join('\n')}`).toEqual([]);
        expect(failedRequests, `Unexpected failed requests: ${failedRequests.join('\n')} HTTP: ${httpErrors.join('\n')}`).toEqual([]);
    });
});
