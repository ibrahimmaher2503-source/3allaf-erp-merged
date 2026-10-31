import { chromium } from 'playwright';
import fs from 'node:fs';

const base = process.env.QA_BROWSER_URL;
const admin = JSON.parse(fs.readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
const fixture = JSON.parse(fs.readFileSync(process.env.QA_CASHIER_GIFT_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];
let checks = 0;
function check(condition, label) { checks++; if (condition) console.log(`PASS ${label}`); else failures.push(label); }
try {
    for (const width of [1440, 390]) {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(`console ${error.message}`));
        page.on('console', message => { if (message.type() === 'error') errors.push(`console ${message.text()}`); });
        page.on('response', response => { if (response.status() >= 500 || response.status() === 422) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
        await page.goto(`${base}/login`);
        await page.locator('input[name=username]').fill(admin.username);
        await page.locator('input[name=password]').fill(admin.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        await page.evaluate(async () => {
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
        });
        async function visit(path, label) {
            const response = await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
            check(response.status() === 200, `${width} ${label} loads`);
            check(await page.locator('html').getAttribute('dir') === 'rtl', `${width} ${label} RTL`);
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${width} ${label} no page overflow`);
        }
        await visit('/admin/cash-drawers', 'cash drawers');
        await page.locator('[wire\\:click="openCreateDrawerModal"]').first().click();
        await page.locator('select[wire\\:model\\.live="drawerForm.branch_id"]').selectOption(String(fixture.branch_id));
        await page.locator('select[wire\\:model="drawerForm.store_id"]').selectOption(String(fixture.store_id));
        check(await page.locator('select[wire\\:model="drawerForm.assigned_user_id"] option').filter({ hasText: fixture.cashier }).count() > 0, `${width} scoped cashier selectable`);
        await visit('/catalog/product-options', 'filter values');
        await page.locator('button[wire\\:click^="startValue("]').first().click();
        check(await page.locator('input[type=color]').count() === 0, `${width} color field absent`);
        await visit(`/gift-cards/${fixture.card_id}/print`, 'Gift Card print');
        check(await page.locator('svg.barcode-svg rect').count() > 30, `${width} Code 128 bars render`);
        check(await page.locator('p.code').innerText() === fixture.card_code, `${width} exact human-readable code`);
        await visit('/inventory/balances?q=QA-POS-SKU&store_id=', 'inventory balance search');
        check(await page.locator('[data-stock-balance-row]').count() > 0, `${width} inventory product search result`);
        await visit('/gift-receipts', 'Gift Receipts');
        check(await page.locator('form[action$="gift-receipts"]').count() > 0, `${width} Gift Receipt ready form`);
        await visit('/pos', 'POS');
        if (await page.locator('[data-pos-category-grid]').count()) await page.locator('[data-pos-category-grid] button').first().click();
        await page.locator('[data-pos-product-grid] article').first().waitFor();
        await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
        if (width === 390) await page.locator('button[x-on\\:click="mobileZone=\'transaction\'"]').click();
        await page.locator('[data-cart-line]').first().waitFor({ state: 'attached' });
        await page.locator('input[wire\\:model="giftCardScan"]').fill(fixture.card_code);
        await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/update') && (response.request().postData() ?? '').includes('scanGiftCard')),
            page.locator('button[wire\\:click="scanGiftCard"]').click(),
        ]);
        await page.waitForLoadState('networkidle');
        check(await page.locator('[data-gift-card-preview]').filter({ hasText: fixture.card_code }).count() === 1, `${width} Gift Card software scan routes to tender`);
        check(await page.locator('dialog[aria-labelledby=pos-checkout-dialog-title]').isVisible(), `${width} checkout opens with Gift Card`);
        check(await page.locator('input[name="payments[0][gift_card_identifier]"]').count() === 1, `${width} Gift Card remains payment, not product`);
        await page.keyboard.press('Escape');
        await page.keyboard.press('F7');
        await page.locator('[data-pos-refund-wizard] dialog').waitFor();
        await page.locator('button[wire\\:click="openUnverified"]').click();
        await page.locator('[data-unverified-return-cart]').waitFor();
        check(await page.locator('[data-unverified-return-cart] input[type="number"]').count() === 0, `${width} unverified return starts empty`);
        await page.locator('#pos-unverified-product').fill('QA-POS');
        await page.locator('button[wire\\:click^="chooseUnverifiedProduct("]').first().waitFor();
        await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/update') && (response.request().postData() ?? '').includes('chooseUnverifiedProduct')),
            page.locator('button[wire\\:click^="chooseUnverifiedProduct("]').first().click(),
        ]);
        await page.locator('[data-unverified-return-cart] input[type="number"]').first().waitFor();
        check(await page.locator('[data-unverified-return-cart] input[type="number"]').count() === 2, `${width} return line has editable quantity and price`);
        await page.locator('#pos-unverified-product').fill('QA-POS');
        await page.locator('button[wire\\:click^="chooseUnverifiedProduct("]').nth(1).waitFor();
        await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/update') && (response.request().postData() ?? '').includes('chooseUnverifiedProduct')),
            page.locator('button[wire\\:click^="chooseUnverifiedProduct("]').nth(1).click(),
        ]);
        await page.locator('[data-unverified-return-cart] input[type="number"]').nth(3).waitFor();
        check(await page.locator('[data-unverified-return-cart] tbody tr').count() === 2, `${width} two products share one return cart`);
        check(errors.length === 0, `${width} no console, HTTP 5xx or generic 422: ${errors.join('; ')}`);
        await context.close();
    }
} finally {
    await browser.close();
}
if (failures.length) {
    for (const failure of failures) console.error(`FAIL ${failure}`);
    process.exitCode = 1;
} else console.log(`PASS bounded authenticated browser QA ${checks} checks`);
