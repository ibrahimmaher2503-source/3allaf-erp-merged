import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const baseURL = process.env.QA_BROWSER_URL;
const screenshotDirectory = process.env.QA_SCREENSHOTS;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1' || !screenshotDirectory) {
    throw new Error('Focused browser QA requires a disposable loopback server and screenshot directory.');
}
mkdirSync(screenshotDirectory, { recursive: true });
const accounts = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];

function check(condition, label) {
    if (!condition) failures.push(label);
    console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`);
}

try {
    for (const width of [1440, 390]) {
        for (const role of ['Admin']) {
            const account = accounts.find((candidate) => candidate.role === role);
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
            await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
            await page.locator('input[name=username]').fill(account.username);
            await page.locator('input[name=password]').fill(account.password);
            await Promise.all([
                page.waitForURL((url) => !url.pathname.endsWith('/login')),
                page.locator('[data-test=login-button]').click(),
            ]);
            await page.evaluate(async () => {
                const token = document.querySelector('meta[name=csrf-token]').content;
                await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
            });
            const dashboard = await page.goto(`${baseURL}/dashboard`, { waitUntil: 'networkidle' });
            check(dashboard.status() === 200, `${role} dashboard ${width} HTTP 200`);
            const shortcuts = page.locator('[data-dashboard-quick-actions]');
            const pos = shortcuts.getByRole('link', { name: 'نقطة بيع جديدة' });
            const invoice = shortcuts.getByRole('link', { name: 'فاتورة مبيعات جديدة' });
            check(await pos.count() === 1, `${role} ${width} POS shortcut visible`);
            check(await invoice.count() === (role === 'Admin' ? 1 : 0), `${role} ${width} invoice shortcut permission`);
            check(new URL(await pos.getAttribute('href'), baseURL).pathname === '/pos', `${role} ${width} POS target`);
            if (role === 'Admin') {
                check(new URL(await invoice.getAttribute('href'), baseURL).pathname === '/sales/invoices/create', `${role} ${width} invoice target`);
            }
            const metrics = await page.evaluate(() => ({
                direction: document.documentElement.dir,
                clientWidth: document.documentElement.clientWidth,
                scrollWidth: document.documentElement.scrollWidth,
                font: getComputedStyle(document.body).fontFamily,
            }));
            check(metrics.direction === 'rtl', `${role} ${width} RTL`);
            check(metrics.scrollWidth <= metrics.clientWidth, `${role} ${width} no page overflow`);
            check(/Cairo/i.test(metrics.font), `${role} ${width} Cairo`);
            check(errors.length === 0, `${role} ${width} console clean: ${errors.join('; ')}`);
            if (role === 'Admin') {
                await page.screenshot({ path: join(screenshotDirectory, `dashboard-${width}.png`), fullPage: true });
                await Promise.all([page.waitForURL((url) => url.pathname === '/pos'), pos.click()]);
                check(new URL(page.url()).pathname === '/pos', `POS click ${width} opens POS`);
                await page.goto(`${baseURL}/dashboard`, { waitUntil: 'networkidle' });
                await Promise.all([
                    page.waitForURL((url) => url.pathname === '/sales/invoices/create'),
                    page.locator('[data-dashboard-quick-actions]').getByRole('link', { name: 'فاتورة مبيعات جديدة' }).click(),
                ]);
                check(new URL(page.url()).pathname === '/sales/invoices/create', `Invoice click ${width} opens ordinary create`);
                check((await page.locator('form[action$="/sales/invoices/drafts"]').count()) === 1, `Invoice create ${width} form present`);
                check(await page.getByRole('button', { name: 'حفظ مسودة الفاتورة' }).count() === 1, `Invoice draft action ${width}`);
                check(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), `Invoice create ${width} no overflow`);
                check(errors.length === 0, `Invoice create ${width} console clean`);
                await page.screenshot({ path: join(screenshotDirectory, `sales-invoice-create-${width}.png`), fullPage: true });
            }
            await context.close();
        }
    }
} finally {
    await browser.close();
}

if (failures.length > 0) {
    console.error(failures.join('\n'));
    process.exit(1);
}
