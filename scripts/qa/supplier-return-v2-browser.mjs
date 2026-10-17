import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
const runtime = process.env.QA_STORAGE;
const output = process.env.QA_REVIEW_DIR;
if (!base || new URL(base).hostname !== '127.0.0.1' || !runtime?.startsWith('/tmp/rajeh-qa-mariadb.') || !output?.endsWith('/storage/app/frontend-review/supplier-return-v2')) {
    throw new Error('An isolated local browser runtime and review directory are required.');
}

const account = JSON.parse(readFileSync(`${runtime}/accounts.json`, 'utf8')).find((item) => item.role === 'Admin');
const fixture = JSON.parse(readFileSync(`${runtime}/supplier-return-v2-fixture.json`, 'utf8'));
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const errors = [];
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    const login = await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
    if (login.status() !== 200) throw new Error(`Login HTTP ${login.status()}`);
    await page.locator('input[name=username]').fill(account.username);
    await page.locator('input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar-EG' }) });
    });
    const createPage = await page.goto(`${base}/purchasing/returns/create`, { waitUntil: 'networkidle' });
    if (createPage.status() !== 200) throw new Error(`Create page HTTP ${createPage.status()}`);
    await page.getByLabel('مخزن الإرجاع', { exact: true }).selectOption(String(fixture.store));
    await page.locator('[data-product-search]').fill('V2-BROWSER-CODE');
    await page.locator('tbody tr').filter({ hasText: 'لعبة تركيب تعليمية' }).waitFor();
    if (await page.getByLabel('المورد', { exact: true }).inputValue() !== String(fixture.supplier)) throw new Error('Unique product supplier was not selected automatically');
    await page.getByLabel('سبب المرتجع', { exact: true }).selectOption(String(fixture.reason));
    for (const [name, width, height] of [['desktop', 1440, 900], ['tablet', 1024, 900], ['mobile', 390, 844]]) {
        await page.setViewportSize({ width, height });
        if (await page.locator('html').getAttribute('dir') !== 'rtl') throw new Error(`${name} is not RTL`);
        if (!(await page.locator('body').innerText()).includes('مرتجع مورد جديد')) throw new Error(`${name} heading missing`);
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`${name} horizontal overflow`);
        if (name === 'mobile') {
            await page.locator('.app-main').screenshot({ path: `${output}/${name}-${width}.png` });
        } else {
            await page.screenshot({ path: `${output}/${name}-${width}.png`, fullPage: true });
        }
        console.log(`PASS ${name} ${width}px, Arabic RTL, no document overflow`);
    }
    await page.getByRole('button', { name: 'حفظ مسودة المرتجع' }).click();
    await page.waitForURL((url) => /\/purchasing\/returns\/\d+$/.test(url.pathname));
    if (!(await page.locator('body').innerText()).includes('التكلفة الحالية للصنف')) throw new Error('Draft detail did not preserve current product cost source');
    console.log('PASS authenticated invoice-free draft creation and cost-source detail');
    if (errors.length) throw new Error(`Console errors: ${errors.join('; ')}`);
    console.log('PASS no browser console errors');
} finally {
    await browser.close();
}
