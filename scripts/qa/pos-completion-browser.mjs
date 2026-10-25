import { chromium } from 'playwright';
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
    check(await checkout.locator('input[name=payment_mode]').inputValue() === 'cash', 'single remaining cash row returns to cash mode');
    check(await checkout.isVisible(), 'payment workspace stays open after row removal');

    await page.setViewportSize({ width: 390, height: 844 });
    check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), '390 payment workspace has no page overflow');
    console.log('DIALOG_STATE', await checkout.evaluate((dialog) => ({ open: dialog.open, display: getComputedStyle(dialog).display, buttonDisplay: getComputedStyle(dialog.querySelector('button[aria-label="Clear"]')).display, buttonRect: dialog.querySelector('button[aria-label="Clear"]').getBoundingClientRect().toJSON() })));
    check(await checkout.locator('button[aria-label="Clear"]').isVisible(), 'keypad remains visible after row removal');
    await checkout.locator('button[aria-label="Clear"]').click();
    await checkout.locator('button[aria-label="5"]').click();
    await checkout.locator('button[aria-label="0"]').click();
    check(await checkout.locator('[data-pos-amount-input]').first().inputValue() === '50', 'numeric keypad enters cash tender');
    check(await checkout.locator('[x-text="changeDue()"]').last().innerText() === '5.00', 'live change recalculates');
    check(await checkout.locator('[x-text="amountRemaining()"]').last().innerText() === '0.00', 'live remaining reaches zero');
    await checkout.getByRole('button', { name: 'Complete sale' }).click();
    await page.locator('#pos-sale-success').waitFor({ state: 'visible' });
    check(await page.locator('[data-cart-line]').count() === 0, 'successful sale clears completed ticket cart');
    check(errors.length === 0, `no browser or HTTP 500 errors (${errors.join('; ')})`);
    console.log(`POS_COMPLETION_BROWSER=PASS assertions=${assertions}`);
} finally {
    await browser.close();
}
