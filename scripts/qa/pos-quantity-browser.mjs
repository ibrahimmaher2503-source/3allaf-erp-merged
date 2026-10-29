import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const baseURL = process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') throw new Error('Disposable local browser required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
let checks = 0;
const failures = [];
function check(value, label) { checks++; console.log(`${value ? 'PASS' : 'FAIL'} ${label}`); if (!value) failures.push(label); }
async function waitQuantity(page, expected) {
    await page.locator('[data-cart-line] input[inputmode="numeric"]').first().waitFor();
    await page.waitForFunction(value => document.querySelector('[data-cart-line] input[inputmode="numeric"]')?.value === value, String(expected));
}
async function selectProduct(page, sku) {
    await page.locator('#live-pos-product-search').fill(sku);
    const card = page.locator('[data-pos-product-grid] article').filter({ hasText: sku });
    await card.waitFor();
    return card;
}
async function clearCart(page) {
    await page.goto(baseURL + '/pos', { waitUntil: 'networkidle' });
    const form = page.locator('form[action$="/pos/cart/clear"]').first();
    if (await form.count()) {
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), form.evaluate(element => element.submit())]);
    }
    check(await page.locator('[data-cart-line]').count() === 0, 'disposable cart clear completed');
}
try {
    for (const width of [1440, 390].filter(value => !process.env.QA_ONLY_WIDTH || Number(process.env.QA_ONLY_WIDTH) === value)) {
        const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 900 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('dialog', dialog => dialog.accept());
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('response', response => { if (response.status() >= 500 || response.status() === 422) errors.push(`HTTP ${response.status()}`); });
        await page.goto(baseURL + '/login');
        await page.locator('input[name=username]').fill(admin.username);
        await page.locator('input[name=password]').fill(admin.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        await page.evaluate(async () => { const token = document.querySelector('meta[name=csrf-token]').content; await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) }); });
        await page.goto(baseURL + '/pos', { waitUntil: 'networkidle' });
        const mobileCatalog = page.locator('[data-pos-workstation] > div[aria-label] button').first();
        const mobileCart = page.locator('[data-pos-workstation] > div[aria-label] button').nth(1);
        if (await page.locator('[data-cart-line]').count()) await clearCart(page);
        if (width === 390 && await mobileCatalog.isVisible()) await mobileCatalog.click();
        let card = await selectProduct(page, 'QA-POS-SKU-01');
        for (let quantity = 1; quantity <= 4; quantity++) {
            await card.locator('button[data-pos-add-product]').click();
            if (width === 390) {
                if (await mobileCart.isVisible()) await mobileCart.click();
            }
            await waitQuantity(page, quantity);
            check(await page.locator('[data-cart-line] input[inputmode="numeric"]').first().inputValue() === String(quantity), `${width} repeated add visible ${quantity}`);
            if (width === 390 && quantity < 4 && await mobileCatalog.isVisible()) await mobileCatalog.click();
        }
        const line = page.locator('[data-cart-line]').first();
        check((await line.innerText()).includes('180'), `${width} four-unit line total`);
        await line.locator('.inline-grid button').first().click();
        await waitQuantity(page, 3);
        check((await line.innerText()).includes('135'), `${width} decrement line total`);
        await clearCart(page);
        if (width === 390 && await mobileCatalog.isVisible()) await mobileCatalog.click();
        card = await selectProduct(page, 'QA-POS-SKU-02');
        await card.locator('button[data-pos-add-product]').click();
        if (width === 390) {
            if (await mobileCart.isVisible()) await mobileCart.click();
        }
        await waitQuantity(page, 1);
        if (width === 390 && await mobileCatalog.isVisible()) await mobileCatalog.click();
        await card.locator('button[data-pos-add-product]').click();
        if (width === 390 && await mobileCart.isVisible()) await mobileCart.click();
        await waitQuantity(page, 2);
        await page.waitForLoadState('networkidle');
        check(await card.locator('button[data-pos-add-product]').isDisabled(), `${width} card locked at stock two`);
        check((await card.innerText()).includes('تمت إضافة كامل الكمية المتاحة'), `${width} max-stock label`);
        const plus = page.locator('[data-cart-line]').first().locator('.inline-grid button').last();
        check(await plus.isDisabled(), `${width} plus locked at stock two`);
        const quantityInput = page.locator('[data-cart-line] input[inputmode="numeric"]').first();
        await quantityInput.fill('3');
        await quantityInput.press('Tab');
        await waitQuantity(page, 2);
        check((await page.locator('section[aria-labelledby="pos-cart-heading"]').innerText()).includes('الكمية المتاحة من هذا المنتج هي 2 فقط'), `${width} inline stock-limit message`);
        await page.locator('[data-cart-line]').first().locator('.inline-grid button').first().click();
        await waitQuantity(page, 1);
        check(!(await plus.isDisabled()), `${width} decrement enables plus`);
        await plus.click();
        await waitQuantity(page, 2);
        check(await plus.isDisabled(), `${width} plus returns to max`);
        await page.locator('button[wire\\:click^="startCheckout"]').first().click();
        const checkout = page.locator('dialog[aria-labelledby="pos-checkout-dialog-title"]');
        await checkout.waitFor({ state: 'visible' });
        check(await checkout.locator('[data-pos-payable]').inputValue() === '90.00', `${width} checkout total matches two units`);
        await checkout.evaluate(dialog => dialog.close());
        await clearCart(page);
        if (width === 390) await mobileCatalog.click();
        card = await selectProduct(page, 'QA-POS-SKU-02');
        await card.locator('button[data-pos-add-product]').evaluate(button => {
            for (let click = 0; click < 5; click++) button.click();
        });
        if (width === 390) await mobileCart.click();
        await waitQuantity(page, 2);
        await page.waitForLoadState('networkidle');
        check(await page.locator('[data-cart-line] input[inputmode="numeric"]').first().inputValue() === '2', `${width} five rapid adds capped at two`);
        check(await card.locator('button[data-pos-add-product]').isDisabled(), `${width} rapid adds leave card visible and capped`);
        check(!(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2)), `${width} no horizontal overflow`);
        check((await page.locator('html').getAttribute('dir')) === 'rtl', `${width} RTL`);
        check(errors.length === 0, `${width} console, HTTP 5xx, and 422`);
        await context.close();
    }
} finally { await browser.close(); }
console.log(`${checks - failures.length}/${checks} browser checks passed`);
if (failures.length) throw new Error(failures.join('; '));
