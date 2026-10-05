import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback browser only');
const fixture = JSON.parse(readFileSync(process.env.QA_STORAGE + '/cost-browser.json', 'utf8'));
const browser = await chromium.launch({headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox']});
const results = [];
const errors = [];
const field = (page, name) => page.locator(`[wire\\:model="productForm.${name}"], [wire\\:model\\.live="productForm.${name}"]`).first();
function check(ok, label) { if (!ok) throw new Error(label); console.log('PASS ' + label); }
async function open(page, path) {
    const response = await page.goto(base + path, {waitUntil: 'networkidle'});
    check(response.status() === 200, path + ' renders');
}
async function save(page, creating = false) {
    const response = page.waitForResponse(r => r.url().includes('/livewire-') && r.request().method() === 'POST');
    await page.locator('form[wire\\:submit="save"] button[type=submit]').click();
    const r = await response;
    check(r.status() === 200, 'Livewire save HTTP');
    if (creating) await page.waitForURL(/\/catalog\/products\/\d+\/edit/, {timeout: 15000});
    else await page.waitForLoadState('networkidle');
    await page.waitForTimeout(150);
    const validationErrors = (await page.locator('[data-flux-error]:visible').allTextContents()).map(s => s.trim()).filter(Boolean);
    check(validationErrors.length === 0, 'no field validation errors: ' + validationErrors.join('; '));
    check(!/حقل سعر التكلفة مطلوب|Cost price is required/i.test(await page.locator('body').innerText()), 'no mandatory cost error');
    const path = new URL(page.url()).pathname;
    await open(page, path);
    return path;
}
try {
    for (const role of ['Admin', 'Editor']) {
        const context = await browser.newContext({viewport: {width: 1280, height: 900}});
        const page = await context.newPage();
        page.on('pageerror', e => errors.push(e.message));
        page.on('response', r => { if (r.status() >= 500) errors.push('HTTP ' + r.status()); });
        await open(page, '/login');
        await page.locator('input[name=username]').fill(fixture.accounts[role].username);
        await page.locator('input[name=password]').fill(fixture.accounts[role].password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        if (role === 'Admin') {
            for (const [scenario, cost] of [['A', '12.34'], ['B', ''], ['D', '45.67']]) {
                await open(page, `/catalog/products/${fixture.products[scenario]}/edit`);
                check(await field(page, 'average_cost').getAttribute('required') === null, scenario + ' cost has no required attribute');
                await field(page, 'name_en').fill('Browser ' + scenario + ' edited');
                if (scenario === 'D') await field(page, 'average_cost').fill(cost);
                const path = await save(page);
                check(await field(page, 'name_en').inputValue() === 'Browser ' + scenario + ' edited', scenario + ' other-field save/reload');
                check(await field(page, 'average_cost').inputValue() === cost, scenario + ' exact saved cost/null reload');
                results.push({scenario, path, result: 'PASS'});
            }
            // Arabic error path and blank edit of a saved cost.
            await page.evaluate(async () => {const token = document.querySelector('meta[name=csrf-token]').content; await fetch('/locale', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({_token: token, locale: 'ar'})});});
            await open(page, `/catalog/products/${fixture.products.A}/edit`);
            await field(page, 'average_cost').fill('');
            await field(page, 'name_en').fill('Browser A blank preserved');
            await save(page);
            check(await field(page, 'average_cost').inputValue() === '12.34', 'A Arabic blank edit preserves stored cost');
        } else {
            await open(page, `/catalog/products/${fixture.products.E}/edit`);
            check(await field(page, 'average_cost').count() === 0, 'E cost inaccessible');
            await field(page, 'name_en').fill('Browser E edited');
            const path = await save(page);
            check(await field(page, 'name_en').inputValue() === 'Browser E edited', 'E hidden-cost final save/reload');
            results.push({scenario: 'E', path, result: 'PASS'});
        }
        await open(page, '/catalog/products/create');
        check(await field(page, 'average_cost').count() === (role === 'Admin' ? 1 : 0), role + ' create cost visibility');
        const type = page.locator('[wire\\:model\\.live="productForm.barcode_registration_type"]');
        await type.selectOption('local');
        await field(page, 'preferred_supplier_id').selectOption(String(fixture.supplier));
        await field(page, 'name_ar').fill('منتج المتصفح بدون تكلفة');
        await field(page, 'name_en').fill('Browser ' + role + ' new no cost');
        await field(page, 'model_number').fill('BROWSER-' + role.toUpperCase());
        await field(page, 'category_id').selectOption(String(fixture.category));
        await field(page, 'sale_price').fill('25');
        const path = await save(page, true);
        check(await field(page, 'name_en').inputValue() === 'Browser ' + role + ' new no cost', role + ' new no-cost final save/reload');
        results.push({scenario: role === 'Admin' ? 'C' : 'E-create', path, result: 'PASS'});
        await context.close();
    }
    check(errors.length === 0, 'no browser exceptions or HTTP 500 errors');
    writeFileSync(process.env.QA_STORAGE + '/cost-browser-results.json', JSON.stringify(results, null, 2) + '\n');
} finally { await browser.close(); }
