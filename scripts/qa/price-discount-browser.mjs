import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback browser required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
const fixture = JSON.parse(readFileSync(process.env.QA_PRICE_DISCOUNT_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1200, height: 850 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 500 || response.status() === 422) errors.push(`${response.status()} ${response.url()}`); });
let checks = 0;
const check = (condition, label) => { checks++; console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`); if (!condition) process.exitCode = 1; };
try {
    await page.goto(`${base}/login`);
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    async function inspect(productCode, outletId, expectedPrice, expectedBarcode, label) {
        await page.goto(`${base}/pricing/labels`, { waitUntil: 'networkidle' });
        await page.locator('#product-search').fill(productCode);
        await page.locator('section[x-show="results.length"] .divide-y button').first().click();
        await page.waitForSelector('article input[name$="[barcode_id]"]', { state: 'attached' });
        const selected = page.locator('article input[name$="[barcode_id]"]').first().locator('xpath=ancestor::article');
        const store = page.locator('form[action$="/pricing/labels/preview"] [name="store_id"]');
        await store.selectOption(String(outletId));
        await page.waitForFunction(expected => [...document.querySelectorAll('article')].some(article => article.querySelector('input[name$="[barcode_id]"]') && article.innerText.includes(expected)), expectedPrice, { timeout: 5000 });
        check((await selected.innerText()).includes(expectedPrice), `${label} outlet preview ${expectedPrice}`);
        check((await selected.innerText()).includes(expectedBarcode), `${label} barcode priority`);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('form[action$="/pricing/labels/preview"] button[type=submit]').click()]);
        const printed = page.locator('[data-barcode-symbol]').first();
        check(await printed.getAttribute('data-barcode-value') === expectedBarcode && (await printed.innerText()).includes(expectedPrice), `${label} software print parity`);
    }
    await inspect(fixture.product, fixture.outlets.base, '300.00', '6224008875185', 'base');
    await inspect(fixture.product, fixture.outlets.up15, '345.00', '6224008875185', '+15%');
    await inspect(fixture.product, fixture.outlets.up10, '330.00', '6224008875185', '+10%');
    await inspect(fixture.product, fixture.outlets.down10, '270.00', '6224008875185', '-10%');
    await inspect(fixture.product, fixture.outlets.fixed, '420.00', '6224008875185', 'fixed');
    await inspect(fixture.local, fixture.outlets.base, '300.00', '0002-000001', 'local fallback');
    check(errors.length === 0, `no HTTP 5xx, generic 422 or page errors: ${errors.join('; ')}`);
    console.log(`${checks} bounded browser checks complete`);
} finally {
    await browser.close();
}
