import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback browser runtime required');
const accounts = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'));
const admin = accounts.find(account => account.role === 'Admin');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];
let checks = 0;
const check = (pass, label) => { checks++; console.log(`${pass ? 'PASS' : 'FAIL'} ${label}`); if (!pass) failures.push(label); };
try {
    for (const width of [1440, 390]) {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('response', response => { if (response.status() >= 500 || response.status() === 422) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
        await page.goto(`${base}/login`);
        await page.locator('input[name=username]').fill(admin.username);
        await page.locator('input[name=password]').fill(admin.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        await page.evaluate(async () => {
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch('/locale', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({_token: token, locale: 'ar'})});
        });
        await page.goto(`${base}/pos/shift`, { waitUntil: 'networkidle' });
        check(await page.locator('html').getAttribute('dir') === 'rtl', `${width} Arabic RTL`);
        check(page.url().endsWith('/pos/shift'), `${width} shift page renders`);
        const receiptsLink = page.locator('a[href*="/receipts"]').first();
        check(await receiptsLink.count() === 1, `${width} active shift receipts link`);
        if (await receiptsLink.count()) { await receiptsLink.click(); check((await page.locator('body').innerText()).includes('QA-SHIFT-RECEIPT-001'), `${width} selected shift receipt listed`); check(await page.locator('a[href*="/sales/"]').count() > 0, `${width} completed receipt view action`); }
        await page.goto(`${base}/admin/audit`, { waitUntil: 'networkidle' });
        check(await page.locator('ol[aria-label]').count() > 0 || (await page.locator('body').innerText()).includes('No audit events found'), `${width} activity timeline renders (${page.url()})`);
        await page.goto(`${base}/quotations`, { waitUntil: 'networkidle' });
        check(await page.locator('[data-add-manual]').count() === 1, `${width} manual quotation action`);
        await page.locator('[data-add-manual]').click();
        check(await page.locator('[name$="[line_type]"]').count() >= 2 && await page.locator('[name$="[line_type]"]').last().inputValue() === 'manual', `${width} manual line added`);
        check(await page.locator('form[action$="/quotations"] [name="discount_type"]').count() === 1 && await page.locator('form[action$="/quotations"] [name="tax_rate"]').count() === 1, `${width} quotation totals controls`);
        const quotationForm = page.locator('form[action$="/quotations"]').first();
        await quotationForm.locator('[name="store_id"]').selectOption({ index: 1 });
        await quotationForm.locator('details').first().locator('summary').click();
        await quotationForm.locator('[data-new-first]').fill('عميل');
        await quotationForm.locator('[data-new-last]').fill('عرض');
        await quotationForm.locator('[data-new-phone]').fill('0101' + String(Math.floor(Math.random() * 10000000)).padStart(7, '0'));
        await quotationForm.locator('[data-new-governorate]').selectOption({ index: 1 });
        await quotationForm.locator('[data-create-quotation-customer]').click();
        await page.waitForTimeout(700);
        check(await quotationForm.locator('[name="customer_id"]').inputValue() !== '', `${width} customer created and automatically selected: ${await quotationForm.locator('[data-customer-error]').innerText()}`);
        const productSearch = quotationForm.locator('[data-product-line]').first().locator('[data-product-search]');
        await productSearch.fill('QA-POS-SKU-01');
        await page.waitForFunction(() => document.querySelector('form[action$="/quotations"] [data-product-line] [data-product-id]')?.value !== '', {timeout: 5000});
        await quotationForm.locator('[name="lines[0][unit_price]"]').fill('40');
        const manual = quotationForm.locator('[data-product-line]').last();
        await manual.locator('[name$="[description_ar]"]').fill('صنف يدوي');
        await manual.locator('[name$="[description_en]"]').fill('Manual item');
        await manual.locator('[name$="[unit_price]"]').fill('100');
        await quotationForm.locator('[name="discount_type"]').selectOption('fixed');
        await quotationForm.locator('[name="discount_value"]').fill('10');
        await quotationForm.locator('[name="tax_rate"]').fill('14');
        check((await quotationForm.locator('[data-quotation-final]').innerText()).includes('148.20'), `${width} live quotation final total`);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), quotationForm.locator('button[type=submit]').click()]);
        check((await page.locator('body').innerText()).includes('148.20'), `${width} mixed quotation saved and listed`);
        const view = page.locator('a[href^="' + base + '/quotations/"]').first();
        if (await view.count()) { await view.click(); check((await page.locator('body').innerText()).includes('صنف يدوي'), `${width} quotation detail includes manual line`); }

        await page.goto(`${base}/pricing/labels`, { waitUntil: 'networkidle' });
        check(page.url().includes('/pricing/labels'), `${width} barcode label workspace`);
        await page.locator('#product-search').fill('QA-POS-SKU-01');
        await page.locator('section[x-show="results.length"] .divide-y button').first().click();
        const selectedBarcode = page.locator('article input[name$="[barcode_id]"]').first();
        check(await selectedBarcode.count() === 1 && (await selectedBarcode.locator('xpath=ancestor::article').innerText()).includes('6224008875178'), `${width} manufacturer barcode selected`);
        await page.locator('form[action$="/pricing/labels/preview"] [name="store_id"]').selectOption({index: 1});
        await page.waitForFunction(() => [...document.querySelectorAll('article')].some(article => article.querySelector('input[name$="[barcode_id]"]') && article.innerText.includes('45.00')), null, {timeout: 5000}).catch(() => {});
        check((await selectedBarcode.locator('xpath=ancestor::article').innerText()).includes('45.00'), `${width} selected outlet price preview`);
        await Promise.all([page.waitForNavigation({waitUntil: 'networkidle'}), page.locator('form[action$="/pricing/labels/preview"] button[type=submit]').click()]);
        check(await page.locator('[data-barcode-symbol]').first().getAttribute('data-barcode-value') === '6224008875178', `${width} manufacturer barcode printed in preview`);
        check((await page.locator('[data-barcode-symbol]').first().innerText()).includes('45.00'), `${width} barcode preview price parity`);

        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${width} no horizontal overflow`);
        await page.goto(`${base}/quotations`, {waitUntil: 'networkidle'});
        await page.evaluate(async () => {
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch('/locale', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({_token: token, locale: 'en'})});
        });
        await page.goto(`${base}/quotations`, { waitUntil: 'networkidle' });
        check(await page.locator('html').getAttribute('dir') === 'ltr', `${width} English LTR`);
        check(errors.length === 0, `${width} no console or HTTP errors: ${errors.join('; ')}`);
        await context.close();
    }
} finally { await browser.close(); }
console.log(`${checks - failures.length}/${checks} browser checks passed`);
if (failures.length) process.exitCode = 1;
