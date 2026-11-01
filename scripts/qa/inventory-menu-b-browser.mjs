import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback browser runtime required');
const accounts = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'));
const fixture = JSON.parse(readFileSync(process.env.QA_CASHIER_GIFT_FIXTURE, 'utf8'));
const admin = accounts.find(account => account.role === 'Admin');
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
let checks = 0;
const failures = [];
const check = (okay, label) => { checks++; if (okay) console.log(`PASS ${label}`); else { console.log(`FAIL ${label}`); failures.push(label); } };

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
            await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
        });

        await page.goto(`${base}/dashboard`, { waitUntil: 'networkidle' });
        check(await page.locator('html').getAttribute('dir') === 'rtl', `${width} Arabic RTL`);
        await page.evaluate(() => window.dispatchEvent(new Event('open-appearance-customizer')));
        let selector = page.locator('#appearance-customizer select[x-model="preferences.menu_variant"]');
        if (await selector.inputValue() !== 'a') {
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), selector.selectOption('a')]);
            await page.evaluate(() => window.dispatchEvent(new Event('open-appearance-customizer')));
            selector = page.locator('#appearance-customizer select[x-model="preferences.menu_variant"]');
        }
        check(await page.locator('[data-navigation-group=inventory]').count() === 1 && await page.locator('[data-navigation-group=products]').count() === 0, `${width} Menu A retained`);
        check(await selector.inputValue() === 'a', `${width} existing user defaults to Menu A`);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), selector.selectOption('b')]);
        check(await page.locator('[data-navigation-group=products]').count() === 1, `${width} Menu B products group`);
        check(await page.locator('[data-navigation-group=reports]').count() === 1, `${width} Menu B reports group`);
        await page.reload({ waitUntil: 'networkidle' });
        check(await page.locator('[data-navigation-group=products]').count() === 1, `${width} Menu B persists on reload`);
        await page.evaluate(async () => {
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': token } });
        });
        await page.goto(`${base}/login`);
        await page.locator('input[name=username]').fill(admin.username);
        await page.locator('input[name=password]').fill(admin.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
        check(await page.locator('[data-navigation-group=products]').count() === 1, `${width} Menu B persists after logout/login`);
        await page.keyboard.press('Control+k');
        await page.locator('#navigation-search-query').fill('تقرير أرصدة المخزون');
        check(await page.locator('.navigation-search__results a').count() === 1, `${width} command search uses Menu B only`);
        await page.keyboard.press('Escape');

        await page.goto(`${base}/inventory/balances`, { waitUntil: 'networkidle' });
        await page.locator('#inventory-q').fill('QA-POS');
        await page.locator('#inventory-store').selectOption('');
        await Promise.all([page.waitForURL(url => url.searchParams.get('q') === 'QA-POS'), page.locator('[data-inventory-filter] button[type=submit]').click()]);
        check(await page.locator('[data-stock-balance-row]').count() > 0, `${width} actual Apply searches partial code across stores`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${width} Inventory no page overflow`);

        await page.goto(`${base}/pos`, { waitUntil: 'networkidle' });
        if (await page.locator('[data-pos-category-grid]').count()) await page.locator('[data-pos-category-grid] button').first().click();
        await page.locator('[data-pos-product-grid] article').first().waitFor();
        await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
        await page.locator('[data-cart-line]').first().waitFor({ state: 'attached' });
        if (width === 390) await page.locator('button[x-on\:click="mobileZone=\'products\'"]').first().click().catch(() => {});
        const scan = page.locator('#live-pos-product-search');
        await scan.fill('QA-POS-BAR-01');
        await scan.press('Enter');
        await page.waitForFunction(() => document.querySelector('#live-pos-product-search')?.value === '');
        check(await page.locator('[data-cart-line]').count() === 1 && await scan.inputValue() === '', `${width} normal POS product barcode updates cart`);
        await scan.fill(fixture.card_code);
        await scan.press('Enter');
        await page.locator('[data-gift-card-preview]').filter({ hasText: fixture.card_code }).waitFor({ state: 'attached' });
        check(await page.locator('[data-gift-card-preview]').filter({ hasText: fixture.card_code }).count() === 1, `${width} normal POS scanner routes Gift Card to tender`);
        check(await page.locator('[data-cart-line]').count() === 1, `${width} Gift Card not added as product`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), `${width} POS no page overflow`);
        check(errors.length === 0, `${width} no console errors, 5xx, or generic 422: ${errors.join('; ')}`);
        await context.close();
    }
} finally {
    await browser.close();
}
console.log(`Inventory/Menu B browser checks: ${checks}, failures: ${failures.length}`);
if (failures.length) process.exitCode = 1;
