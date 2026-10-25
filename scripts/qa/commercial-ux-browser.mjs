import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable loopback URL required');
const accounts = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8'));
const fixture = JSON.parse(readFileSync(process.env.QA_COMMERCIAL_FIXTURE, 'utf8'));
const errors = [];
let assertions = 0;
function check(condition, label) {
    assertions++;
    if (!condition) throw new Error(`FAIL ${label}`);
    console.log(`PASS ${label}`);
}
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
async function login(page, role) {
    const account = accounts.find(item => item.role === role);
    await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name=username]').fill(account.username);
    await page.locator('input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
}
async function setArabic(page) {
    await page.evaluate(async () => {
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content, locale: 'ar-EG' }) });
    });
}
async function health(page, label) {
    const state = await page.evaluate(() => ({ rtl: document.documentElement.dir === 'rtl', cairo: /Cairo/i.test(getComputedStyle(document.body).fontFamily), overflow: document.documentElement.scrollWidth > innerWidth + 2 }));
    check(state.rtl && state.cairo && !state.overflow, `${label} Arabic RTL, Cairo and no horizontal overflow`);
}
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`); });
    await login(page, 'Admin');
    await setArabic(page);
    await page.goto(`${base}/purchasing/invoices`, { waitUntil: 'networkidle' });
    await page.evaluate(async branch => {
        await fetch('/ui/work-context', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: document.querySelector('meta[name=csrf-token]').content, branch_id: String(branch) }) });
    }, fixture.branch);
    await page.reload({ waitUntil: 'networkidle' });
    check(!(await page.locator('button[aria-label*="All Branches"]').count()), 'header branch selection persists after navigation');
    await page.locator(`[wire\\:click="openEditModal(${fixture.invoice})"]`).first().evaluate(element => element.click());
    await page.locator('[data-purchase-invoice-editor]').waitFor();
    check((await page.locator('[data-purchase-invoice-editor]').innerText()).includes('460'), 'draft purchase line shows branch List 2 price 460');
    check(await page.locator('[data-purchase-invoice-editor] button[aria-label]').filter({ has: page.locator('svg') }).count() > 0, 'draft purchase line has inline icon');
    await health(page, '1440 purchase');

    await page.goto(`${base}/pricing/labels`, { waitUntil: 'networkidle' });
    const modes = await page.locator('[data-barcode-document-source] select[name=source] option').evaluateAll(options => options.map(option => option.value));
    check(await page.locator('[data-label-workspace]').count() === 1 && modes.includes('purchase_invoice') && modes.includes('stock_transfer'), 'Product workspace and Invoice/Transfer barcode modes remain available');

    await page.goto(`${base}/pos`, { waitUntil: 'networkidle' });
    check(await page.locator('[data-pos-readiness=ready]').count() === 1, 'POS keeps its already-open shift');
    await page.locator('[data-pos-ticket-header] form:has(input[name=creation_token]) button').first().click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${base}/pos?lookup=customers`, { waitUntil: 'networkidle' });
    await page.locator('#pos-customer-search').fill('01011223399');
    const selectedCustomer = page.locator('[data-customer-search-results] form').filter({ hasText: '01011223399' }).first();
    await selectedCustomer.waitFor();
    await selectedCustomer.locator('button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('[data-pos-ticket-header]').innerText()).includes('عميل اختبار نقطة البيع'), 'selected customer is attached to the open order');
    await page.goto(`${base}/pos`, { waitUntil: 'networkidle' });
    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().locator('button').last().click();
    await page.locator('[data-cart-line]').first().waitFor();
    const ticket = await page.locator('[data-pos-ticket-header]').innerText();
    await page.locator('[data-guide=pos-summary-actions] button[wire\\:click^="startCheckout"]').click();
    const checkout = page.locator('dialog[aria-labelledby=pos-checkout-dialog-title]');
    await checkout.waitFor({ state: 'visible' });
    await checkout.locator('button[wire\\:click="switchCheckoutMode(\'split\')"]').click();
    await checkout.locator('[data-payment-part]').nth(1).waitFor();
    check(await checkout.isVisible() && await checkout.locator('[data-payment-part]').count() === 2, 'Single to Split stays open with two portions');
    await checkout.locator('[data-cash-received]').first().fill('50');
    await checkout.locator('[data-cash-received]').first().blur();
    const second = checkout.locator('[data-payment-part]').nth(1);
    const cardOption = second.locator('select[data-payment-method] option').filter({ hasText: /QA Card|بطاقة اختبار/ }).first();
    await second.locator('select[data-payment-method]').selectOption(await cardOption.getAttribute('value'));
    await page.waitForTimeout(400);
    const payable = await checkout.locator('[data-pos-payable]').inputValue();
    const cardAfter50 = await second.locator('[data-noncash-amount]').inputValue();
    check(Number(cardAfter50) === 14, `64 total minus 50 cash defaults card to 14 (payable ${payable}, card ${cardAfter50})`);
    await checkout.locator('[data-cash-received]').first().fill('40');
    await checkout.locator('[data-cash-received]').first().blur();
    await page.waitForTimeout(1800);
    const cashAfter40 = await checkout.locator('[data-cash-received]').first().inputValue();
    const cardAfter40 = await second.locator('[data-noncash-amount]').inputValue();
    const autoRemaining = await second.locator('[data-noncash-amount]').getAttribute('data-auto-remaining');
    check(Number(cardAfter40) === 24, `changing cash to 40 updates card remainder to 24 (cash ${cashAfter40}, card ${cardAfter40}, auto ${autoRemaining !== null})`);
    check(await checkout.isVisible() && await page.locator('[data-pos-ticket-header]').innerText() === ticket && await page.locator('[data-cart-line]').count() === 1, 'payment mode retains modal, order and cart');
    await checkout.locator('button[wire\\:click="switchCheckoutMode(\'cash\')"]').click();
    await page.waitForFunction(() => document.querySelector('dialog[aria-labelledby=pos-checkout-dialog-title] [data-payment-part]:nth-child(2)') === null);
    check(await checkout.isVisible() && (await page.locator('[data-pos-ticket-header]').innerText()).includes('عميل اختبار نقطة البيع'), 'Split to Single stays open and preserves customer');
    await checkout.locator('button[wire\\:click="switchCheckoutMode(\'split\')"]').click();
    await checkout.locator('[data-payment-part]').nth(1).waitFor();
    check(await checkout.isVisible() && await checkout.locator('[data-payment-part]').count() === 2 && Number(await checkout.locator('[data-payment-part]').nth(1).locator('[data-noncash-amount]').inputValue()) === 24, 'Single to Split restores payment portions without closing');
    await health(page, '1440 payment');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}/pos`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Current order|الطلب الحالي/ }).first().click();
    await page.locator('[data-pos-transaction-zone]').waitFor({ state: 'visible' });
    await page.locator('[data-guide=pos-summary-actions] button[wire\\:click^="startCheckout"]').click();
    await checkout.waitFor({ state: 'visible' });
    check(await checkout.isVisible(), '390 payment modal remains usable');
    await health(page, '390 payment');
    await checkout.locator('button[aria-label]').filter({ has: page.locator('svg') }).first().click();
    await page.goto(`${base}/pos?lookup=customers`, { waitUntil: 'networkidle' });
    const input = page.locator('#pos-customer-search');
    const initialNavigation = await page.evaluate(() => performance.getEntriesByType('navigation').length);
    await input.fill('عميل اختبار نقطة البيع');
    await page.locator('[data-customer-search-results]').getByText('عميل اختبار نقطة البيع', { exact: false }).first().waitFor();
    check(await page.evaluate(() => performance.getEntriesByType('navigation').length) === initialNavigation, 'name search is asynchronous without page navigation');
    await input.fill('01011223399');
    await page.locator('[data-customer-search-results]').getByText('01011223399', { exact: false }).first().waitFor();
    check(await page.locator('[data-cart-line]').count() === 1, 'phone search retains cart');
    await page.locator('#pos-new-customer').evaluate(element => element.showModal());
    const customer = page.locator('#pos-new-customer');
    await customer.locator('[name=first_name_ar]').fill('عميل');
    await customer.locator('[name=last_name_ar]').fill('تجاري');
    await customer.locator('[name=phone]').fill('01019997766');
    await customer.locator('[name=governorate_id]').selectOption({ index: 1 });
    await customer.locator('button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${base}/pos?lookup=customers`, { waitUntil: 'networkidle' });
    await page.locator('#pos-customer-search').fill('01019997766');
    await page.locator('[data-customer-search-results]').getByText('01019997766', { exact: false }).first().waitFor();
    check(await page.locator('[data-cart-line]').count() === 1, 'new customer is immediately searchable and cart remains');
    await health(page, '390 customer search');

    const cashierContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const cashierPage = await cashierContext.newPage();
    cashierPage.on('pageerror', error => errors.push(error.message));
    await login(cashierPage, 'Cashier');
    await setArabic(cashierPage);
    await cashierPage.goto(`${base}/pos/shift`, { waitUntil: 'networkidle' });
    const cashierText = await cashierPage.locator('body').innerText();
    check(cashierText.includes('إغلاق') && !cashierText.includes('Opening float') && !cashierText.includes('Expected cash') && !cashierText.includes('Variance'), 'cashier shift screen hides expected, variance and opening float');
    await cashierPage.locator('button[onclick*="blind-close-dialog"]').click();
    const closeDialog = cashierPage.locator('dialog[id^="blind-close-dialog-"]');
    await closeDialog.waitFor({ state: 'visible' });
    await closeDialog.locator('input[name=actual_cash]').fill('100');
    check(await closeDialog.locator('input[type=number]').count() === 1 && !(await closeDialog.innerText()).includes('125'), 'cashier close modal accepts only physical counted cash');
    await health(cashierPage, '390 blind close');
    check(errors.length === 0, `browser console and HTTP 5xx errors: ${errors.join(' | ') || 'none'}`);
    console.log(`PASS commercial browser acceptance: ${assertions} assertions`);
    await cashierContext.close();
    await context.close();
} finally {
    await browser.close();
}
