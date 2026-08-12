import { expect, test } from '@playwright/test';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

test.describe('US-008/017/018 POS closure', () => {
    test('POS cart quick actions update quantity and require confirmation before removal', async ({ page }) => {
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });

        const productCard = page.getByText('Open-price demo toy', { exact: true }).locator('..').locator('..');
        await productCard.getByRole('button', { name: 'Add', exact: true }).click();

        const cartLine = page.locator('[data-cart-line]').filter({ hasText: 'Open-price demo toy' });
        const quantity = cartLine.getByLabel('Quantity', { exact: true });
        await expect(quantity).toHaveValue('1');

        await cartLine.getByRole('button', { name: 'Increase quantity for Open-price demo toy', exact: true }).click();
        await expect(quantity).toHaveValue('2.000000');

        const remove = cartLine.getByRole('button', { name: 'Remove Open-price demo toy from cart', exact: true });
        let dismissedMessage = '';
        page.once('dialog', async (dialog) => {
            dismissedMessage = dialog.message();
            await dialog.dismiss();
        });
        await remove.click();
        expect(dismissedMessage).toContain('Remove Open-price demo toy from the cart?');
        await expect(cartLine).toBeVisible();

        page.once('dialog', (dialog) => dialog.accept());
        await remove.click();
        await expect(page.getByText('Cart is empty', { exact: true })).toBeVisible();
    });

    test('POS Sales and Checkout: Apply payment tax and discount rules', async ({ page, browser }) => {
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
        await cartLine.getByText('Line adjustments', { exact: true }).click();
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

        const a4Href = await page.getByRole('link', { name: 'A4 invoice', exact: true }).getAttribute('href');
        const a4Page = await page.context().newPage();
        observe(a4Page);
        await a4Page.goto(a4Href, { waitUntil: 'domcontentloaded' });
        await expect(a4Page.getByText('Selling price', { exact: true })).toBeVisible();
        await expect(a4Page.getByText('Payments', { exact: true })).toBeVisible();
        await a4Page.close();

        const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'en-US' });
        const mobilePage = await mobileContext.newPage();
        observe(mobilePage);
        await login(mobilePage, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await mobilePage.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(mobilePage.getByText('POS Checkout', { exact: true })).toBeVisible();
        const mobileOverflow = await mobilePage.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(mobileOverflow).toBeLessThanOrEqual(1);
        await mobilePage.screenshot({ path: test.info().outputPath('us008-017-018-pos-390-en.png'), fullPage: true });
        await mobileContext.close();

        const rtlContext = await browser.newContext({ locale: 'en-US' });
        const rtlPage = await rtlContext.newPage();
        observe(rtlPage);
        await login(rtlPage, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await rtlPage.goto('/pos', { waitUntil: 'domcontentloaded' });
        await rtlPage.locator('form[action$="/locale"] button').click();
        await rtlPage.waitForLoadState('domcontentloaded');
        await expect(rtlPage.locator('html[dir="rtl"]')).toHaveCount(1);
        await expect(rtlPage.getByText('البحث عن المنتجات', { exact: true })).toBeVisible();
        await expect(rtlPage.getByText('السلة الحالية', { exact: true })).toBeVisible();
        await expect(rtlPage.getByText('السلة فارغة', { exact: true })).toBeVisible();
        const rtlOverflow = await rtlPage.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(rtlOverflow).toBeLessThanOrEqual(1);
        await rtlPage.screenshot({ path: test.info().outputPath('us008-017-018-pos-rtl.png'), fullPage: true });
        await rtlContext.close();

        expect(consoleErrors, `Unexpected browser console errors: ${consoleErrors.join('\n')} HTTP: ${httpErrors.join('\n')}`).toEqual([]);
        expect(failedRequests, `Unexpected failed requests: ${failedRequests.join('\n')} HTTP: ${httpErrors.join('\n')}`).toEqual([]);
    });

    test('POS operational matrix preserves the cart through search, customer, tender, and suspended-sale flows', async ({ page }) => {
        test.setTimeout(180000);

        const timings = {};
        const timed = async (name, work) => {
            const startedAt = performance.now();
            const result = await work();
            timings[name] = Math.round(performance.now() - startedAt);
            return result;
        };
        const consoleErrors = [];
        const failedRequests = [];
        page.on('console', (message) => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));

        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await timed('initial_pos_load_ms', () => page.goto('/pos', { waitUntil: 'domcontentloaded' }));
        await expect(page.getByText('BROWSER-POS-DR', { exact: true }).first()).toBeVisible();

        const productSearch = page.locator('[data-pos-product-search]');
        await productSearch.getByLabel('Search or scan product', { exact: true }).fill('Open-price demo toy');
        await timed('product_name_search_ms', async () => {
            await Promise.all([
                page.waitForURL(/product_q=Open-price/),
                productSearch.getByRole('button', { name: 'Search', exact: true }).click(),
            ]);
        });
        await expect(page.getByText('Open-price demo toy', { exact: true })).toBeVisible();

        await productSearch.getByLabel('Search or scan product', { exact: true }).fill('BROWSER-OPEN-001');
        await timed('product_code_search_ms', async () => {
            await Promise.all([
                page.waitForURL(/product_q=BROWSER-OPEN-001/),
                productSearch.getByRole('button', { name: 'Search', exact: true }).click(),
            ]);
        });
        await expect(page.getByText('Open-price demo toy', { exact: true })).toBeVisible();

        await productSearch.getByLabel('Search or scan product', { exact: true }).fill('BROWSER-OPEN-001-BC');
        await timed('barcode_search_ms', async () => {
            await Promise.all([
                page.waitForURL(/product_q=BROWSER-OPEN-001-BC/),
                productSearch.getByRole('button', { name: 'Search', exact: true }).click(),
            ]);
        });
        await expect(page.getByText('Open-price demo toy', { exact: true })).toBeVisible();

        await productSearch.getByLabel('Search or scan product', { exact: true }).fill('NO-SUCH-POS-PRODUCT');
        await Promise.all([
            page.waitForURL(/product_q=NO-SUCH-POS-PRODUCT/),
            productSearch.getByRole('button', { name: 'Search', exact: true }).click(),
        ]);
        await expect(page.getByText('No product matched in this store. If you have visibility, other store availability appears here.', { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Clear', exact: true }).click();
        await expect(page.getByLabel('Search or scan product', { exact: true })).toHaveValue('');

        const addProduct = async () => {
            const productCard = page.getByText('Open-price demo toy', { exact: true }).locator('..').locator('..');
            await timed('add_to_cart_ms', () => productCard.getByRole('button', { name: 'Add', exact: true }).click());
            return page.locator('[data-cart-line]').filter({ hasText: 'Open-price demo toy' });
        };
        let cartLine = await addProduct();
        const quantity = cartLine.getByLabel('Quantity', { exact: true });
        await cartLine.getByRole('button', { name: 'Increase quantity for Open-price demo toy', exact: true }).click();
        await expect(quantity).toHaveValue('2.000000');
        await timed('quantity_decrement_ms', () => cartLine.getByRole('button', { name: 'Decrease quantity for Open-price demo toy', exact: true }).click());
        await expect(quantity).toHaveValue('1.000000');
        await quantity.fill('3');
        await timed('quantity_direct_update_ms', async () => {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
                cartLine.getByRole('button', { name: 'Update', exact: true }).click(),
            ]);
        });
        await expect(quantity).toHaveValue('3');

        const clearCart = page.getByRole('button', { name: 'Clear cart', exact: true });
        let clearPrompt = '';
        page.once('dialog', async (dialog) => {
            clearPrompt = dialog.message();
            await dialog.dismiss();
        });
        await clearCart.click();
        expect(clearPrompt).toContain('Clear every item from this cart?');
        await expect(cartLine).toBeVisible();
        page.once('dialog', (dialog) => dialog.accept());
        await clearCart.click();
        await expect(page.getByText('Cart is empty', { exact: true })).toBeVisible();

        const customerContext = page.locator('[data-guide="pos-customer-context"]');
        await customerContext.locator(':scope > summary').click();
        const customerSearch = customerContext.locator('form[method="GET"]');
        await customerSearch.getByLabel('Search customer', { exact: true }).fill('Browser customer');
        await timed('customer_lookup_ms', async () => {
            await Promise.all([
                page.waitForURL(/customer_q=Browser/),
                customerSearch.getByRole('button', { name: 'Search', exact: true }).click(),
            ]);
        });
        await expect(customerContext.getByText('Browser customer', { exact: true })).toBeVisible();
        await customerContext.getByRole('button', { name: 'Select', exact: true }).click();
        await expect(customerContext.getByRole('button', { name: 'Clear customer', exact: true })).toBeVisible();
        await customerContext.getByRole('button', { name: 'Clear customer', exact: true }).click();
        await expect(customerContext.getByText('No customer selected', { exact: true })).toBeVisible();

        const registerCustomer = customerContext.getByText('Register a new customer', { exact: true });
        await registerCustomer.click();
        const registration = customerContext.locator('form[action$="/pos/customer/create"]');
        const phone = `010${String(Date.now()).slice(-8)}`;
        await registration.getByLabel('Phone', { exact: true }).fill(phone);
        await registration.getByLabel('Consent purpose', { exact: true }).selectOption('service');
        await registration.getByLabel('Arabic name', { exact: true }).fill('عميل اختبار المتصفح');
        await registration.getByLabel('English name', { exact: true }).fill('Browser registered customer');
        await registration.getByRole('button', { name: 'Register and select', exact: true }).click();
        await expect(customerContext.locator(':scope > summary')).toContainText('Browser registered customer');
        await customerContext.getByRole('button', { name: 'Clear customer', exact: true }).click();

        cartLine = await addProduct();
        await page.getByRole('button', { name: 'Suspend sale', exact: true }).click();
        await expect(page.getByText('Sale suspended. Resume code:', { exact: false })).toBeVisible();
        await expect(page.getByText('Cart is empty', { exact: true })).toBeVisible();
        await page.getByRole('link', { name: /Suspended/ }).click();
        await expect(page.getByText('Suspended Sales', { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Resume and checkout', exact: true }).first().click();
        await expect(page.getByText('Complete suspended sale', { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Back to suspended sales', exact: true }).click();
        await expect(page.getByText('Suspended Sales', { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Resume and checkout', exact: true }).first().click();
        await page.getByLabel('Use cash for the remaining residual', { exact: true }).check();
        await page.getByLabel('Cash tendered', { exact: true }).fill('100');
        await timed('suspended_sale_finalize_ms', async () => {
            await Promise.all([
                page.waitForURL(/\/sales\/\d+$/),
                page.getByRole('button', { name: 'Complete sale', exact: true }).click(),
            ]);
        });
        await expect(page.getByText('Payments', { exact: true }).last()).toBeVisible();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        cartLine = await addProduct();
        const paymentForm = page.locator('[data-guide="pos-payment-form"]');
        await paymentForm.getByRole('group', { name: 'Manual electronic payment', exact: true }).getByLabel('Payment method', { exact: true }).selectOption({ label: 'Manual card terminal · evidence required' });
        await paymentForm.getByLabel('Electronic amount', { exact: true }).fill('100');
        await paymentForm.getByRole('button', { name: 'Settle and complete sale', exact: true }).click();
        await expect(page.getByText('This electronic payment requires protected payment evidence before it can be recorded.', { exact: true })).toBeVisible();
        await expect(cartLine).toBeVisible();
        await paymentForm.getByLabel('Protected payment evidence', { exact: true }).setInputFiles({
            name: 'evidence.png',
            mimeType: 'image/png',
            buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL1hgAAAABJRU5ErkJggg==', 'base64'),
        });
        await timed('electronic_checkout_ms', async () => {
            await Promise.all([
                page.waitForURL(/\/sales\/\d+$/),
                paymentForm.getByRole('button', { name: 'Settle and complete sale', exact: true }).click(),
            ]);
        });
        await expect(page.getByText('Payments', { exact: true }).last()).toBeVisible();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        cartLine = await addProduct();
        const underpaymentForm = page.locator('[data-guide="pos-payment-form"]');
        await underpaymentForm.getByLabel('Use cash for the remaining residual', { exact: true }).check();
        await underpaymentForm.getByLabel('Cash tendered', { exact: true }).fill('5');
        await timed('cash_underpayment_rejection_ms', () => underpaymentForm.getByRole('button', { name: 'Settle and complete sale', exact: true }).click());
        await expect(page.getByText(/Cash tendered.*outstanding|Cash payment.*outstanding/i)).toBeVisible();
        await expect(cartLine).toBeVisible();

        console.log(`POS operational matrix timings (ms): ${JSON.stringify(timings)}`);
        expect(consoleErrors, `Unexpected browser console errors: ${consoleErrors.join('\n')}`).toEqual([]);
        expect(failedRequests, `Unexpected failed requests: ${failedRequests.join('\n')}`).toEqual([]);
    });
});
