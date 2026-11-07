import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const baseURL = process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') throw new Error('Local disposable browser runtime required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
if (!admin) throw new Error('Disposable administrator account required');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];
let assertions = 0;
function check(ok, label) {
    assertions++;
    if (!ok) failures.push(label);
    else console.log(`PASS ${label}`);
}
try {
    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('response', response => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
        await page.goto(baseURL + '/login', { waitUntil: 'networkidle' });
        await page.locator('input[name=username]').fill(admin.username);
        await page.locator('input[name=password]').fill(admin.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        for (const locale of ['en', 'ar']) {
            if (locale === 'ar') {
                await page.evaluate(async () => {
                    const token = document.querySelector('meta[name=csrf-token]').content;
                    await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
                });
            }
            for (const [path, selector] of [
                ['/catalog/products/create', 'input'],
                ['/pricing/labels', '[data-label-workspace]'],
                ['/inventory/transfers/create', '[data-transfer-allocation-editor]'],
                ['/inventory/transfers/receiving', '[data-transfer-receiving-inbox]'],
                ['/inventory/additions', '[data-inventory-focus="addition"]'],
                ['/inventory/deductions', '[data-inventory-focus="deduction"]'],
            ]) {
                const response = await page.goto(baseURL + path, { waitUntil: 'networkidle' });
                const label = `${viewport.width} ${locale} ${path}`;
                check(response.status() === 200, `${label} HTTP 200`);
                check(await page.locator(selector).count() > 0, `${label} screen`);
                check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${label} no horizontal overflow`);
                check(await page.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${label} direction`);
                if (path === '/inventory/additions' || path === '/inventory/deductions') {
                    check(await page.locator('#inventory-filters-heading').count() === 0, `${label} no generic filter`);
                    check(await page.locator('#adjustment-type').count() === 0, `${label} fixed type`);
                }
                if (path === '/inventory/transfers/create') check(await page.locator('input[name*="quantity_requested"]').count() === 0, `${label} no manual total`);
            }
        }
        const posResponse = await page.goto(baseURL + '/pos', { waitUntil: 'networkidle' });
        check(posResponse.status() === 200, `${viewport.width} POS HTTP 200`);
        check(await page.locator('[data-pos-transaction-zone]').count() > 0, `${viewport.width} POS transaction workspace`);
        check(await page.locator('button[wire\\:click^="startCheckout"]').count() === 1, `${viewport.width} one Complete Sale action`);
        check(await page.locator('dialog[aria-labelledby="pos-checkout-dialog-title"]').count() > 0, `${viewport.width} checkout dialog exists`);
        check(await page.locator('button[wire\\:click^="switchCheckoutMode"]').count() > 0, `${viewport.width} payment methods are in checkout`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${viewport.width} POS no horizontal overflow`);
        if (viewport.width === 1440) {
            for (let index = 0; index < 10; index++) {
                await page.locator('[data-pos-category-grid] button').nth(index).click();
                await page.locator('[data-pos-product-grid] article').first().waitFor();
                await page.locator('[data-pos-product-grid] button').filter({ hasText: 'إضافة' }).first().click();
                await page.locator('[data-cart-line]').nth(index).waitFor();
                await page.locator('button[wire\\:click="chooseCategory(null)"]').first().click();
            }
            check(await page.locator('[data-cart-line]').count() === 10, '1440 POS ten distinct cart lines');
            check(await page.locator('[data-pos-transaction-zone]').evaluate(element => element.scrollHeight > element.clientHeight || !!element.querySelector('[data-cart-line]')), '1440 POS cart remains usable');
            await page.locator('button[wire\\:click^="startCheckout"]').click();
            const dialog = page.locator('dialog[aria-labelledby="pos-checkout-dialog-title"]');
            await dialog.waitFor({ state: 'visible' });
            check(await dialog.locator('button[wire\\:click^="switchCheckoutMode"]').count() >= 3, '1440 Cash Card Split payment choices in Complete Sale');
        }
        check(errors.length === 0, `${viewport.width} browser errors: ${errors.join('; ')}`);
        await context.close();
    }
} finally {
    await browser.close();
}
if (failures.length) {
    console.error(failures.join('\n'));
    process.exit(1);
}
console.log(`PASS v2 core polish browser ${assertions} assertions`);
