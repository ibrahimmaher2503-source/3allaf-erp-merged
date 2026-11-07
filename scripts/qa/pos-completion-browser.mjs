import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';

const baseURL = process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') {
    throw new Error('POS completion QA requires the disposable loopback router');
}
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find((account) => account.role === 'Admin');
if (!admin) throw new Error('Disposable Admin account unavailable');

let assertions = 0;
const check = (condition, label) => {
    assertions += 1;
    if (!condition) throw new Error(`FAIL ${label}`);
    console.log(`PASS ${label}`);
};

const errors = [];
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', (response) => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });

    await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async () => {
        await fetch('/locale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content, locale: 'en' }),
        });
    });
    await page.goto(`${baseURL}/pos`, { waitUntil: 'networkidle' });
    check(await page.locator('[data-pos-readiness=ready]').count() === 1, 'assigned disposable cashier shift is ready');
    await page.evaluate(() => { window.Flux.appearance = 'light'; });
    await page.waitForFunction(() => !document.documentElement.classList.contains('dark'));
    await page.locator('[data-pos-theme-toggle]').click();
    await page.waitForFunction(() => document.documentElement.classList.contains('dark') && localStorage.getItem('flux.appearance') === 'dark');
    check(await page.evaluate(() => document.documentElement.classList.contains('dark')), 'R8 theme toggle switches to dark');
    await page.locator('[data-pos-theme-toggle]').click();
    await page.waitForFunction(() => !document.documentElement.classList.contains('dark') && localStorage.getItem('flux.appearance') === 'light');
    check(!await page.evaluate(() => document.documentElement.classList.contains('dark')), 'R8 theme toggle switches to light');
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
        for (const appearance of ['light', 'dark']) {
            await page.evaluate((value) => { window.Flux.appearance = value; }, appearance);
            await page.waitForFunction((value) => document.documentElement.classList.contains('dark') === (value === 'dark') && localStorage.getItem('flux.appearance') === value, appearance);
            await page.reload({ waitUntil: 'networkidle' });
            check(await page.evaluate((value) => document.documentElement.classList.contains('dark') === (value === 'dark') && localStorage.getItem('flux.appearance') === value, appearance), `${width} ${appearance} theme persists`);
            check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), `${width} ${appearance} has no horizontal overflow`);
            check(await page.locator('[data-pos-theme-toggle]').count() === 1, `${width} ${appearance} theme toggle present`);
        }
    }
    await page.goto(`${baseURL}/pos?lookup=customers`, { waitUntil: 'networkidle' });
    const contrast = await new AxeBuilder({ page }).include('[data-pos-customer-lookup] button').withRules(['color-contrast']).analyze();
    check(contrast.violations.length === 0, 'dark Add New Customer action has readable contrast');
    await page.locator('[data-pos-customer-lookup] button').first().click();
    const customerDialog = page.locator('#pos-new-customer');
    await customerDialog.waitFor({ state: 'visible' });
    check(await customerDialog.locator('[name=consent_purpose]').count() === 0, 'Quick Create hides technical consent purpose');
    check(!await customerDialog.innerText().then((value) => value.includes('service_delivery')), 'Quick Create has no raw service_delivery');
    await customerDialog.locator('[name=first_name_ar]').fill('عميل');
    await customerDialog.locator('[name=last_name_ar]').fill('مراجعة');
    await customerDialog.locator('[name=phone]').fill('01019998877');
    await customerDialog.locator('[name=governorate_id]').selectOption({ index: 1 });
    await customerDialog.locator('button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    check(!await customerDialog.isVisible(), 'customer Quick Create completes without consent setting');
    await page.goto(`${baseURL}/pos`, { waitUntil: 'networkidle' });
    await page.evaluate(async () => {
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content, locale: 'ar' }) });
    });
    await page.reload({ waitUntil: 'networkidle' });
    check(await page.evaluate(() => document.documentElement.dir === 'rtl' && /Cairo/i.test(getComputedStyle(document.body).fontFamily)), 'Arabic RTL and Cairo remain active');
    check(!await page.locator('body').innerText().then((text) => text.includes('service_delivery') || text.includes('loyalty.retail_rule')), 'technical consent and loyalty keys are absent from POS UI');
    await page.evaluate(async () => {
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content, locale: 'en' }) });
        window.Flux.appearance = 'light';
    });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.reload({ waitUntil: 'networkidle' });
    await page.locator('[data-pos-ticket-header] button[aria-label="New order"]').click();
    await page.waitForLoadState('networkidle');

    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
    await page.locator('[data-cart-line]').first().waitFor();
    const ticket = await page.locator('[data-pos-ticket-header]').innerText();
    check(await page.locator('[data-cart-line]').count() === 1, 'one canonical cart line added');

    const checkoutButton = page.locator('[data-guide=pos-summary-actions] button').filter({ hasText: 'Checkout' }).first();
    await checkoutButton.click();
    const checkout = page.locator('dialog[aria-labelledby=pos-checkout-dialog-title]');
    await checkout.waitFor({ state: 'visible' });
    check(await checkout.locator('[data-cash-payment]').count() === 1, 'cash checkout opens');
    check(await checkout.getByText('Numeric keypad').count() === 1, 'cashier keypad is visible');

    await checkout.getByRole('button', { name: 'Back to order' }).click();
    check(!await checkout.isVisible(), 'Back closes only payment workspace');
    check(await page.locator('[data-pos-ticket-header]').innerText() === ticket && await page.locator('[data-cart-line]').count() === 1, 'Back preserves ticket and cart');

    await checkoutButton.click();
    await checkout.waitFor({ state: 'visible' });
    await checkout.getByRole('button', { name: 'Split payment' }).click();
    await checkout.waitFor({ state: 'visible' });
    await checkout.locator('[data-payment-part]').nth(1).waitFor();
    check(await checkout.locator('[data-payment-part]').count() === 2, 'split mode has two payment rows');
    await checkout.getByRole('button', { name: 'Remove portion' }).last().click();
    await checkout.waitFor({ state: 'visible' });
    await checkout.locator('[data-payment-part]').nth(1).waitFor({ state: 'detached' });
    check(await checkout.locator('[data-payment-part]').count() === 1, 'payment row can be removed');
    check(await checkout.getByRole('button', { name: 'Add payment portion' }).count() === 0, 'single row returns to simple cash workspace');
    check(await checkout.locator('input[name=payment_mode]').inputValue() === 'cash', 'single remaining cash row returns to cash mode');
    check(await checkout.isVisible(), 'payment workspace stays open after row removal');

    await checkout.getByRole('button', { name: 'Back to order' }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Current order' }).click();
    await checkoutButton.click();
    await checkout.waitFor({ state: 'visible' });
    check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), '390 payment workspace has no page overflow');
    check(await checkout.locator('button[aria-label="Clear"]').isVisible(), 'keypad remains visible after row removal');
    await checkout.locator('[data-pos-amount-input]').first().fill('');
    await checkout.locator('[data-pos-amount-input]').first().press('4');
    await checkout.locator('[data-pos-amount-input]').first().press('0');
    check(await checkout.locator('[data-pos-amount-input]').first().inputValue() === '40', 'physical keyboard enters cash tender');
    check(await checkout.locator('[data-pos-payable]').inputValue() === '45.00', 'live total equals canonical payable');
    check(await checkout.locator('[x-text="paidAmount()"]').innerText() === '40.00', 'live paid tracks entered tender');
    check(await checkout.getByRole('button', { name: 'Complete sale' }).isDisabled(), 'insufficient cash cannot finalize');
    check(await checkout.locator('[x-text="amountRemaining()"]').last().innerText() === '5.00', 'insufficient cash shows live remaining');
    await checkout.locator('button[aria-label="Clear"]').click();
    await checkout.locator('button[aria-label="5"]').click();
    await checkout.locator('button[aria-label="0"]').click();
    check(await checkout.locator('[data-pos-amount-input]').first().inputValue() === '50', 'numeric keypad enters cash tender');
    check(await checkout.locator('[x-text="changeDue()"]').last().innerText() === '5.00', 'live change recalculates');
    check(await checkout.locator('[x-text="amountRemaining()"]').last().innerText() === '0.00', 'live remaining reaches zero');
    await checkout.getByRole('button', { name: 'Complete sale' }).click();
    await page.locator('#pos-sale-success').waitFor({ state: 'visible' });
    check(await page.locator('[data-cart-line]').count() === 0, 'successful sale clears completed ticket cart');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.locator('#pos-sale-success').getByRole('button', { name: 'New order' }).click();
    await page.waitForLoadState('networkidle');
    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
    await page.locator('[data-cart-line]').first().waitFor();
    const preservedTicket = await page.locator('[data-pos-ticket-header]').innerText();
    await page.locator('[data-pos-ticket-header] button[aria-label="New order"]').click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-open-order-tabs] button').count() >= 2, 'multi-ticket tabs preserve another open order');
    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
    await page.locator('[data-cart-line]').first().waitFor();
    await page.locator('[data-guide=pos-summary-actions] button').filter({ hasText: 'Checkout' }).first().click();
    await checkout.waitFor({ state: 'visible' });
    await checkout.getByRole('button', { name: 'Split payment' }).click();
    await checkout.locator('[data-payment-part]').nth(1).waitFor();
    const splitRows = checkout.locator('[data-payment-part]');
    check(await splitRows.count() === 2, 'split checkout shows cash and electronic portions');
    await splitRows.nth(1).locator('[data-noncash-amount]').fill('10');
    await splitRows.first().locator('[data-cash-received]').fill('35');
    check(await checkout.locator('[x-text="amountRemaining()"]').last().innerText() === '0.00', 'split allocations reach zero remaining');
    check(await checkout.getByRole('button', { name: 'Complete sale' }).isEnabled(), 'valid split payment is ready to finalize');
    await checkout.getByRole('button', { name: 'Complete sale' }).click();
    await page.locator('#pos-sale-success').waitFor({ state: 'visible' });
    check(await page.locator('[data-open-order-tabs] button').count() >= 1, 'successful checkout preserves the other ticket');
    check(await page.locator('[data-cart-line]').count() === 1, 'other ticket cart remains after split sale');
    check(await page.locator('[data-pos-ticket-header]').innerText() === preservedTicket, 'other ticket becomes active after successful sale');
    await page.evaluate(() => document.getElementById('pos-sale-success').close());
    await page.locator('[data-guide=pos-summary-actions] button').filter({ hasText: 'Checkout' }).first().click();
    await checkout.waitFor({ state: 'visible' });
    await checkout.getByRole('button', { name: 'Cash', exact: true }).last().click();
    await checkout.getByRole('button', { name: 'Exact amount' }).click();
    check(await checkout.locator('[data-cash-received]').inputValue() === '45.00', 'exact cash fills canonical payable');
    check(await checkout.getByRole('button', { name: 'Complete sale' }).isEnabled(), 'exact cash is ready to finalize');
    await checkout.getByRole('button', { name: 'Complete sale' }).click();
    await page.locator('#pos-sale-success').waitFor({ state: 'visible' });
    check(await page.locator('[data-cart-line]').count() === 0, 'exact cash sale completes once and clears its ticket');
    check(errors.length === 0, `no browser or HTTP 500 errors (${errors.join('; ')})`);
    console.log(`POS_COMPLETION_BROWSER=PASS assertions=${assertions}`);
} finally {
    await browser.close();
}
