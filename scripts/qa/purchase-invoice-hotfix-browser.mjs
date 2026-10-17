import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
const runtime = process.env.QA_STORAGE;
if (!base || new URL(base).hostname !== '127.0.0.1' || !runtime?.startsWith('/tmp/rajeh-qa-mariadb.')) {
    throw new Error('Disposable loopback runtime required');
}

const fixture = JSON.parse(readFileSync(`${runtime}/purchase-browser.json`, 'utf8'));
const screenshotRoot = `${process.cwd()}/storage/app/frontend-review/purchase-invoice-hotfix`;
mkdirSync(screenshotRoot, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const errors = [];
const check = (condition, label) => {
    if (!condition) throw new Error(label);
    console.log(`PASS ${label}`);
};

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`); });
    let confirmations = 0;
    page.on('dialog', async dialog => { confirmations++; await dialog.accept(); });

    await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name=username]').fill(fixture.username);
    await page.locator('input[name=password]').fill(fixture.password);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/login')),
        page.locator('[data-test=login-button]').click(),
    ]);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar-EG' }) });
    });
    await page.goto(`${base}/purchasing/invoices`, { waitUntil: 'networkidle' });
    check(await page.locator('html').getAttribute('dir') === 'rtl', 'Arabic RTL');

    if (process.env.QA_BROWSER_FULL_ONLY !== '1') {
    const row = page.locator(`[wire\\:key="invoice-card-${fixture.invoice}"]`).first();
    const expectedListLabels = ['رقم الفاتورة', 'الحالة', 'المورد', 'مخزن / منفذ بيع', 'التاريخ', 'الإجمالي', 'المرحلة الحالية', 'الإجراء التالي المطلوب', 'الإجراءات'];
    for (const [label, viewport] of [
        ['desktop', { width: 1440, height: 900 }],
        ['tablet', { width: 1024, height: 768 }],
        ['mobile', { width: 390, height: 844 }],
    ]) {
        if (process.env.QA_BROWSER_MOBILE_ONLY === '1' && label !== 'mobile') continue;
        await page.setViewportSize(viewport);
        await page.goto(`${base}/purchasing/invoices`, { waitUntil: 'networkidle' });
        if (label === 'tablet') {
            await page.locator('.sidebar-desktop-toggle').click();
            await page.waitForTimeout(250);
        }
        await row.waitFor();
        for (const heading of expectedListLabels) check((await row.innerText()).includes(heading), `${label} list label ${heading}`);
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label} no page overflow`);
        if (label === 'mobile') {
            await page.screenshot({ path: `${screenshotRoot}/${label}-${viewport.width}x${viewport.height}.png`, fullPage: true, style: '.app-sidebar { display: none !important; }' });
        } else {
            await page.screenshot({ path: `${screenshotRoot}/${label}-${viewport.width}x${viewport.height}.png`, fullPage: true });
        }
        if (label !== 'mobile') {
            check(await row.evaluate(element => element.parentElement.scrollWidth <= element.parentElement.clientWidth + 1), `${label} all row columns fit card`);
            const layout = await row.evaluate(element => {
                const grid = element.firstElementChild;
                return { columns: getComputedStyle(grid).gridTemplateColumns, tops: [...grid.children].slice(0, 9).map(child => Math.round(child.getBoundingClientRect().top)) };
            });
            check(Math.max(...layout.tops) - Math.min(...layout.tops) <= 24, `${label} one horizontal record: ${JSON.stringify(layout)}`);
        }
        await row.locator('[aria-label="إجراءات إضافية"]').click();
        check(await page.getByRole('menuitem', { name: 'طباعة' }).count() > 0, `${label} print action available`);
        check(await page.getByRole('menuitem', { name: 'عكس' }).count() > 0, `${label} reversal action available`);
        await page.keyboard.press('Escape');
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    if (process.env.QA_BROWSER_CONTINUE !== '1') {
    await page.locator(`[wire\\:click="openReceivingReview(${fixture.invoice})"]`).click();
    const review = page.locator(`[wire\\:click="approveInvoice(${fixture.invoice})"]`).locator('xpath=ancestor::*[@aria-label][1]');
    await review.waitFor();
    const headings = await review.locator('thead th').allInnerTexts();
    check(JSON.stringify(headings.map(text => text.trim())) === JSON.stringify(['المنتج', 'الكمية الأصلية', 'سعر البيع', 'الكمية المستلمة فعليًا']), 'Exact Arabic approval table columns');
    check((await review.locator('[data-money]').first().innerText()).includes('25.00'), 'Current products.sale_price displayed with two decimals');
    check(await review.locator('[data-money] input').count() === 0, 'Selling price read-only');
    const quantity = review.locator(`[wire\\:model="receivedQuantities.${fixture.line}"]`);
    const approve = review.locator(`[wire\\:click="approveInvoice(${fixture.invoice})"]`);
    check(await approve.count() === 1 && await review.locator('form[wire\\:submit]').count() === 0, 'Single approval click path');

    await quantity.fill('-1');
    let response = page.waitForResponse(result => result.url().includes('/livewire') && result.request().method() === 'POST');
    await approve.click();
    check((await response).status() === 200, 'Invalid quantity Livewire response');
    await page.waitForFunction(() => document.body.innerText.includes('يجب أن تكون كميات المنتجات أعدادًا صحيحة.'));
    check((await review.innerText()).includes('يجب أن تكون كميات المنتجات أعدادًا صحيحة.'), 'Visible Arabic validation error');
    check(await approve.isEnabled(), 'Approval button loading cleared after failure');
    check(confirmations === 1, 'Failure confirmation accepted once');

    await quantity.fill('900');
    response = page.waitForResponse(result => result.url().includes('/livewire') && result.request().method() === 'POST');
    await approve.click();
    check((await response).status() === 200, 'Partial quantity Livewire response');
    await review.waitFor({ state: 'hidden' });
    check(confirmations === 2, 'Success confirmation accepted once');
    check((await row.innerText()).includes('معتمد'), 'Partial invoice approved in list');
    } else {
        check((await row.innerText()).includes('معتمد'), 'Previously approved partial invoice remains approved');
    }
    }

    if (process.env.QA_BROWSER_SCREENSHOTS_ONLY !== '1') {
    await page.locator(`[wire\\:click="openReceivingReview(${fixture.englishInvoice})"]`).click();
    const fullReview = page.locator(`[wire\\:click="approveInvoice(${fixture.englishInvoice})"]`).locator('xpath=ancestor::*[@aria-label][1]');
    await fullReview.waitFor();
    const fullApprove = fullReview.locator(`[wire\\:click="approveInvoice(${fixture.englishInvoice})"]`);
    check(await fullApprove.isEnabled(), 'Full quantity approval button enabled before click');
    const fullResponse = page.waitForResponse(result => result.url().includes('/livewire') && result.request().method() === 'POST', { timeout: 15000 }).catch(error => error);
    await fullApprove.click({ timeout: 15000 });
    const fullResult = await fullResponse;
    check(!(fullResult instanceof Error) && fullResult.status() === 200, 'Full quantity Livewire response');
    await fullReview.waitFor({ state: 'hidden' });
    check(confirmations === (process.env.QA_BROWSER_CONTINUE === '1' || process.env.QA_BROWSER_FULL_ONLY === '1' ? 1 : 3), 'Full quantity confirmation accepted once');
    check(errors.length === 0, `No browser errors: ${errors.join('; ')}`);
    } else {
        check(errors.length === 0, `No browser errors: ${errors.join('; ')}`);
    }
} finally {
    await browser.close();
}
