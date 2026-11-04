import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable local browser runtime required');
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find((account) => account.role === 'Admin');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
page.on('response', (response) => { if (response.status() >= 500 || response.status() === 422) errors.push(`HTTP ${response.status()} ${response.url()}`); });
const checks = [];
const check = (condition, label) => { checks.push([Boolean(condition), label]); console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`); };

try {
    await page.goto(`${base}/login`);
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
    });

    const screens = [
        ['/inventory/counts/create', 'inventory count create'],
        ['/catalog/products/create', 'product card create'],
        ['/purchasing/invoices', 'purchase invoices'],
        ['/pricing', 'pricing'],
        ['/pos', 'POS'],
        ['/gift-cards', 'Gift Cards'],
        ['/pos/shift', 'shift close'],
        ['/approvals', 'shift approval inbox'],
    ];
    for (const [path, label] of screens) {
        const response = await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
        check(response?.status() === 200, `${label} HTTP 200`);
        check(await page.locator('html').getAttribute('dir') === 'rtl', `${label} Arabic RTL`);
    }
    await page.goto(`${base}/inventory/counts/create`, { waitUntil: 'networkidle' });
    check(await page.locator('[data-stocktake-settings] select[name=count_type]').count() === 1, 'count type control rendered');
    check(await page.locator('[data-stocktake-execution] input[type=radio]').count() === 3, 'count execution choices rendered');
    await page.goto(`${base}/catalog/products/create`, { waitUntil: 'networkidle' });
    check(!(await page.locator('body').innerText()).includes('طريقة الكود'), 'legacy code method absent');
    check((await page.locator('[data-product-lookup][data-context=catalog]').count()) === 4, 'four inline product identity lookups');
    check(errors.length === 0, `no console, HTTP 5xx, or generic 422 errors: ${errors.slice(0, 2).join('; ')}`);
} finally {
    await browser.close();
}

if (checks.some(([passed]) => !passed)) process.exitCode = 1;
