import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback runtime required');
const accounts = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'));
const fixture = JSON.parse(readFileSync(process.env.QA_CASHIER_GIFT_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];
let checks = 0;
const check = (condition, label) => { checks++; if (!condition) failures.push(label); };

async function login(page, role) {
    const account = accounts.find(candidate => candidate.role === role);
    await page.goto(`${base}/login`);
    await page.locator('input[name=username]').fill(account.username);
    await page.locator('input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
}

try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    page.on('pageerror', error => failures.push(`console: ${error.message}`));
    page.on('console', message => { if (message.type() === 'error') failures.push(`console: ${message.text()}`); });
    page.on('response', response => {
        if (response.status() >= 500 || response.status() === 422) failures.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`);
    });
    await login(page, 'Admin');
    await page.goto(`${base}/gift-cards`, { waitUntil: 'networkidle' });
    const warehouse = page.locator('select[name=store_id]');
    check(await warehouse.count() === 1, 'Gift Card warehouse select renders');
    check(await warehouse.locator(`option[value="${fixture.store_id}"]`).count() === 1, 'branch-linked selling warehouse listed');
    await warehouse.click();
    await warehouse.selectOption(String(fixture.store_id));
    check(await warehouse.inputValue() === String(fixture.store_id), 'warehouse can be selected');
    check((await warehouse.locator(`option[value="${fixture.store_id}"]`).innerText()).includes('—'), 'branch and warehouse label');
    await page.goto(`${base}/inventory/balances?q=QA-POS&store_id=${fixture.store_id}`, { waitUntil: 'networkidle' });
    check(await page.locator('[data-stock-balance-row]').count() > 0, 'positive inventory row');
    await page.locator('input[name=include_zero]').check();
    await Promise.all([page.waitForURL(url => url.searchParams.get('include_zero') === '1'), page.locator('[data-inventory-filter] button[type=submit]').click()]);
    check(await page.locator('[data-stock-balance-row]').count() > 0, 'zero checkbox retains positive rows');
    await page.goto(`${base}/inventory/balances?q=QA-POS&stock_state=out&store_id=${fixture.store_id}`, { waitUntil: 'networkidle' });
    check(await page.locator('#inventory-stock-state').inputValue() === 'out', 'out-of-stock state retained');
    await page.locator('[data-inventory-filter] a').last().click();
    check(new URL(page.url()).search === '', 'reset clears filters');
    const cashierPage = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    cashierPage.on('pageerror', error => failures.push(`console: ${error.message}`));
    cashierPage.on('console', message => { if (message.type() === 'error') failures.push(`console: ${message.text()}`); });
    cashierPage.on('response', response => {
        if (response.status() >= 500 || response.status() === 422) failures.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`);
    });
    await login(cashierPage, 'Cashier');
    await cashierPage.goto(`${base}/pos/shift`, { waitUntil: 'networkidle' });
    const openForm = cashierPage.locator('form[action$="/pos/shift/open"]');
    check(await openForm.count() === 1, 'cashier shift opening form');
    if (await openForm.locator('select[name=cash_drawer_id] option[value]').count() === 0) {
        throw new Error(`Shift drawer options missing: form=${await openForm.count()}, select=${await openForm.locator('select[name=cash_drawer_id]').count()}, named=${await openForm.locator('[name=cash_drawer_id]').count()}, visible=${(await cashierPage.locator('body').innerText()).slice(-800)}`);
    }
    const drawerOption = openForm.locator('select[name=cash_drawer_id] option[value]').first();
    await openForm.locator('select[name=cash_drawer_id]').selectOption(await drawerOption.getAttribute('value'));
    await openForm.locator('ui-checkbox[name=confirm_outlet]').click();
    await openForm.locator('input[name=opening_float]').fill('100');
    await Promise.all([cashierPage.waitForNavigation({ waitUntil: 'networkidle' }), openForm.locator('button[type=submit]').click()]);
    check(await cashierPage.locator('form[action$="/pos/shift/open"]').count() === 0, 'cashier shift opened');
    check(await cashierPage.locator('html').getAttribute('dir') === 'rtl' || await cashierPage.locator('html').getAttribute('dir') === 'ltr', 'shift page renders');
    await cashierPage.close();
    await page.close();
} finally {
    await browser.close();
}
if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
console.log(`PASS postdeploy corrective browser smoke: ${checks} checks; HTTP 5xx 0; generic 422 0; console errors 0`);
