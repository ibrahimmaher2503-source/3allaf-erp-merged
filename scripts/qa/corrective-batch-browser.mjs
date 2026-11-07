import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
const fixture = JSON.parse(readFileSync(process.env.QA_CASHIER_GIFT_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const failures = [];
let checks = 0;
function check(value, label) { checks++; if (!value) failures.push(label); }

try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    page.on('pageerror', error => failures.push(`Browser error: ${error.message}`));
    page.on('response', response => { if (response.status() >= 500) failures.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
    await page.goto(`${base}/login`);
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
    });
    const screens = [
        ['/gift-cards', 'Gift Card issuance'],
        ['/pos/shift', 'Cashier shifts'],
        ['/approvals', 'Close approvals'],
        [`/inventory/balances?q=QA-POS-SKU&store_id=${fixture.store_id}&include_zero=1`, 'Inventory balances'],
        ['/pos', 'POS Gift Card scan'],
    ];
    for (const [path, label] of (process.env.QA_CORRECTIVE_ONLY_APPROVAL ? screens.filter(([, name]) => name === 'Close approvals') : screens)) {
        const response = await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
        check(response.status() === 200, `${label} HTTP`);
        check(await page.locator('html').getAttribute('dir') === 'rtl', `${label} RTL`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${label} width`);
    }
    if (!process.env.QA_CORRECTIVE_ONLY_APPROVAL) check(await page.locator('input[wire\\:model="giftCardScan"]').count() === 1, 'POS Gift Card scan input');
    await page.close();
} finally {
    await browser.close();
}
if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
console.log(`PASS: corrective changed-screen browser smoke ${checks} checks`);
