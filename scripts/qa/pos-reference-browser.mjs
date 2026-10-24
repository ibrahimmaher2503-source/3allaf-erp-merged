import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const baseURL = process.env.QA_BROWSER_URL;
if (!baseURL || new URL(baseURL).hostname !== '127.0.0.1') {
    throw new Error('POS browser QA requires the disposable loopback router');
}
const screenshotDirectory = process.env.QA_POS_SCREENSHOTS;
if (!screenshotDirectory?.startsWith('/tmp/')) {
    throw new Error('QA_POS_SCREENSHOTS must be under /tmp');
}
mkdirSync(screenshotDirectory, { recursive: true });
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find((account) => account.role === 'Admin');
if (!admin) throw new Error('Disposable Admin account unavailable');

let assertions = 0;
const failures = [];
const errors = [];
const check = (condition, label) => {
    assertions += 1;
    if (!condition) failures.push(label);
    console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`);
};
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('pageerror', (error) => errors.push(error.message));
page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
page.on('response', (response) => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });

async function capture(name, fullPage = true) {
    await page.screenshot({ path: join(screenshotDirectory, name), fullPage });
}
async function navigate(path) {
    const response = await page.goto(baseURL + path, { waitUntil: 'networkidle' });
    check(response.status() === 200, `${path} HTTP 200`);
    return response;
}
async function inspect(width, expectedZone) {
    const result = await page.evaluate(() => {
        const products = document.querySelector('[data-pos-products-zone]');
        const categoryGrid = document.querySelector('[data-pos-category-grid]');
        const transaction = document.querySelector('[data-pos-transaction-zone]');
        const main = document.querySelector('main[aria-label]');
        const left = products?.getBoundingClientRect();
        const right = transaction?.getBoundingClientRect();
        const workspace = main?.getBoundingClientRect();
        return {
            dir: document.documentElement.dir,
            font: getComputedStyle(document.body).fontFamily,
            overflow: document.documentElement.scrollWidth > innerWidth + 2,
            nan: /\bNaN\b/.test(document.body.innerText),
            untranslated: /\b(?:Product categories|Current order|Invoice delivery|retail)\b/.test(document.body.innerText),
            left: left && { x: left.x, width: left.width },
            right: right && { x: right.x, width: right.width },
            workspace: workspace && { width: workspace.width, columns: getComputedStyle(main).gridTemplateColumns },
            categoryColumns: categoryGrid && getComputedStyle(categoryGrid).gridTemplateColumns.trim().split(/\s+/).length,
            productsVisible: products && getComputedStyle(products).display !== 'none',
            transactionVisible: transaction && getComputedStyle(transaction).display !== 'none',
        };
    });
    check(result.dir === 'rtl', `${width} Arabic RTL`);
    check(/Cairo/i.test(result.font), `${width} Cairo font`);
    check(!result.overflow, `${width} no page overflow`);
    check(!result.nan, `${width} no NaN`);
    check(!result.untranslated, `${width} no known untranslated POS labels`);
    if (result.productsVisible) check(result.categoryColumns === (width >= 1024 ? 4 : 2), `${width} real category grid has ${width >= 1024 ? 4 : 2} columns`);
    if (width >= 1024) {
        check(result.productsVisible && result.transactionVisible, `${width} both POS zones visible`);
        check(result.left.x < result.right.x, `${width} catalog physically left of transaction`);
        check(result.left.width > result.right.width, `${width} catalog wider than transaction`);
        check(result.workspace.columns.trim().split(/\s+/).length === 2, `${width} exactly two primary grid columns`);
    } else {
        check(result[expectedZone + 'Visible'], `${width} ${expectedZone} mobile zone visible`);
        check(!(result.productsVisible && result.transactionVisible), `${width} mobile zones not squeezed side by side`);
    }
    return result;
}
async function setArabic() {
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
    });
}

try {
    await navigate('/login');
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    check(!page.url().endsWith('/login'), 'disposable Admin authenticated');
    await setArabic();
    await navigate('/pos');
    if (await page.locator('[data-cart-line]').count() > 0) {
        await page.locator('[data-pos-ticket-header] button[aria-label]').first().click();
        await page.waitForLoadState('networkidle');
    }
    await navigate('/pos');
    await inspect(1440);
    check(await page.locator('[data-pos-category-grid] button').count() === 30, 'all 30 eligible canonical QA categories render without truncation');
    check(await page.locator('[data-pos-category-grid] button').evaluateAll((buttons) => buttons.every((button) => button.textContent.trim() && button.hasAttribute('wire:click'))), 'every category tile uses a real dynamic category action');
    for (const categoryNumber of [25, 30]) {
        const categoryName = `فئة اختبار نقطة البيع ${categoryNumber}`;
        const categoryTile = page.locator('[data-pos-category-grid] button').filter({ hasText: categoryName });
        check(await categoryTile.count() === 1, `category ${categoryNumber} is present in the real category grid`);
        await categoryTile.scrollIntoViewIfNeeded();
        check(await categoryTile.isVisible(), `category ${categoryNumber} is reachable by scrolling`);
        await categoryTile.click();
        await page.locator('[data-pos-product-grid] article').first().waitFor();
        check(await page.locator('[data-pos-product-grid] article').count() === 1 && (await page.locator('[data-pos-product-grid] article').first().innerText()).includes(`لعبة اختبار ${categoryNumber}`), `category ${categoryNumber} filters its own canonical product`);
        await navigate('/pos?lookup=products');
    }
    const variableRows = await page.locator('[data-pos-category-grid]').evaluate((grid) => {
        const sample = grid.cloneNode(true);
        sample.removeAttribute('data-pos-category-grid');
        sample.style.cssText = `position:absolute;left:-10000px;top:0;width:${grid.clientWidth}px`;
        document.body.appendChild(sample);
        const rows = (count) => {
            while (sample.children.length > count) sample.lastElementChild.remove();
            const byTop = new Map();
            for (const child of sample.children) {
                const top = Math.round(child.getBoundingClientRect().top);
                byTop.set(top, (byTop.get(top) || 0) + 1);
            }
            return [...byTop.values()];
        };
        const patterns = [rows(12), rows(10), rows(7)];
        sample.remove();
        return patterns;
    });
    check(JSON.stringify(variableRows) === JSON.stringify([[4, 4, 4], [4, 4, 2], [4, 3]]), 'four-column grid adapts to 12, 10, and 7 real-category tiles without filler');
    check(await page.locator('[data-pos-ticket-header] button[aria-label]').count() >= 1, 'new ticket action visible');
    check(await page.locator('[data-pos-transaction-zone]').getByText('الدفع').count() > 0, 'payment area visible');
    await capture('pos-main-1440-v3.png', false);
    await capture('pos-two-column-full-1440-v2.png');

    await page.locator('[data-pos-products-zone] details summary').click();
    check(await page.locator('[data-pos-products-zone] details[open] a').count() === 3, 'lookup shows only supported product, customer, invoice sources');
    await page.locator('.pos-action-menu summary').click();
    check(await page.locator('[data-pos-order-actions]').isVisible(), 'supported transaction action grid opens beside lookup');
    await capture('pos-lookup-menu-1440-v2.png');
    await page.locator('[data-pos-products-zone] details a[href*="lookup=customers"]').click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-pos-customer-lookup]').count() === 1, 'customer lookup renders');
    await page.locator('#pos-customer-search').fill('عميل اختبار');
    await page.waitForURL((url) => url.searchParams.get('customer_q')?.includes('عميل اختبار'));
    check(await page.locator('[data-customer-search-results] form').count() >= 6, 'customer search shows a dense scoped customer grid');
    check(await page.locator('[data-customer-search-results] a[aria-label="تعديل"]').count() >= 6, 'permitted customer cards offer canonical Edit links');
    await page.locator('.pos-action-menu summary').click();
    await capture('pos-customer-search-1440-v3.png');
    const [customerEditor] = await Promise.all([context.waitForEvent('page'), page.locator('[data-customer-search-results] a[aria-label="تعديل"]').first().click()]);
    await customerEditor.waitForLoadState('networkidle');
    check(customerEditor.url().includes('/customers/') && await customerEditor.locator('#customer-identity-heading').count() === 1, 'Customer Edit opens the canonical scoped customer page');
    await customerEditor.close();
    await page.locator('[data-customer-search-results] form button').first().click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-pos-ticket-header]').innerText().then((text) => text.includes('عميل اختبار')), 'customer selection attaches to active ticket');

    await page.locator('[data-pos-customer-lookup] button').filter({ hasText: 'إضافة' }).first().click();
    const modal = page.locator('#pos-new-customer');
    check(await modal.evaluate((element) => element.open), 'new customer modal opens inside POS');
    check(await modal.locator('select[name=governorate_id]').count() === 1, 'governorate is visible in modal');
    check(!/\bretail\b/.test(await modal.innerText()), 'customer modal consent purpose is translated');
    await page.setViewportSize({ width: 1440, height: 480 });
    await capture('pos-customer-create-top-1440-v2.png', false);
    await modal.locator('div.overflow-y-auto').first().evaluate((element) => { element.scrollTop = element.scrollHeight; });
    await capture('pos-customer-create-bottom-1440-v2.png', false);
    await modal.locator('select[name=invoice_delivery_method]').scrollIntoViewIfNeeded();
    check(await modal.locator('select[name=invoice_delivery_method] option').count() === 2, 'print and email delivery choices shown in customer modal');
    await modal.locator('select[name=invoice_delivery_method]').selectOption('email');
    await capture('pos-customer-delivery-method-1440-v2.png', false);
    await page.setViewportSize({ width: 1440, height: 900 });

    await modal.locator('input[name=first_name_ar]').fill('عميل');
    await modal.locator('input[name=last_name_ar]').fill('اختبار جديد');
    await modal.locator('input[name=phone]').fill(`0101${String(Date.now()).slice(-7)}`);
    await modal.locator('input[name=email]').fill(`pos-v2-${Date.now()}@example.test`);
    await modal.locator('select[name=governorate_id]').evaluate((element) => element.removeAttribute('required'));
    await modal.getByRole('button', { name: 'حفظ' }).click();
    await page.waitForLoadState('networkidle');
    check(await page.getByText('يرجى اختيار المحافظة.').count() > 0, 'missing governorate returns Arabic validation');
    check(await page.locator('#pos-new-customer').evaluate((element) => element.open), 'validation retains modal and entered values');
    await capture('pos-customer-validation-ar-v2.png', false);
    await page.locator('#pos-new-customer select[name=governorate_id]').selectOption({ index: 1 });
    await page.locator('#pos-new-customer').getByRole('button', { name: 'حفظ' }).click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-pos-workstation] [role=status]').innerText().then((text) => /CUS-\d+/.test(text)), 'customer code generated on successful quick create');
    check(await page.locator('[data-pos-ticket-header]').innerText().then((text) => text.includes('عميل اختبار جديد')), 'new customer attached to current ticket');

    await navigate('/pos?lookup=invoices&invoice_q=QA-POS-LOOKUP-001');
    check(await page.locator('[data-pos-invoice-lookup]').getByText('QA-POS-LOOKUP-001').count() > 0, 'approved invoice found by exact reference');
    check(await page.locator('[data-pos-invoice-lookup]').getByText('لعبة تركيب عربية').count() > 0, 'invoice lines visible');
    await capture('pos-invoice-lookup-1440-v2.png');

    await navigate('/pos?lookup=products');
    await page.locator('[data-pos-category-grid] button').first().click();
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('#live-pos-product-search').fill('NO-MATCH-QA');
    await page.locator('[data-pos-product-grid] article').first().waitFor({ state: 'detached' });
    check(await page.locator('[data-pos-product-grid] article').count() === 0, 'product search filters selected category');
    await page.locator('#live-pos-product-search').fill('');
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('[data-pos-product-grid] button').filter({ hasText: 'إضافة' }).first().click();
    await page.locator('[data-cart-line]').first().waitFor();
    check(await page.locator('[data-cart-line]').count() === 1, 'category selection and add product work');
    const quantity = page.locator('[data-cart-line] input[aria-label]').first();
    await quantity.fill('5');
    await quantity.press('Enter');
    await page.locator('[data-cart-line]').first().waitFor({ state: 'visible' });
    await page.locator('[data-cart-line]').getByText('225.00').first().waitFor();
    check(await page.locator('[data-cart-line] input[aria-label]').first().inputValue() === '5', 'quantity keyboard Enter commits');
    await capture('pos-quantity-keyboard-edit-v2.png');
    await page.locator('[data-cart-line] button[aria-label]').filter({ hasText: '+' }).count();
    await page.locator('[data-cart-line] button[aria-label]').first().click();
    await page.locator('[data-cart-line]').getByText('180.00').first().waitFor();
    check(await page.locator('[data-cart-line] input[aria-label]').first().inputValue() === '4', 'minus decrements');
    await page.locator('[data-cart-line] button[aria-label]').nth(1).click();
    await page.locator('[data-cart-line]').getByText('225.00').first().waitFor();
    check(await page.locator('[data-cart-line] input[aria-label]').first().inputValue() === '5', 'plus increments');
    check((await page.locator('[data-pos-transaction-zone]').innerText()).includes('225.00'), 'quantity controls recalculate total');
    await page.getByRole('button', { name: 'إنهاء البيع' }).first().click();
    const checkout = page.locator('dialog[aria-labelledby=pos-checkout-dialog-title]');
    await checkout.waitFor({ state: 'visible' });
    check(await checkout.locator('[data-cash-payment]').count() > 0, 'canonical cash payment dialog opens');
    await checkout.locator('header button[aria-label]').last().click();

    const populatedTicket = await page.locator('[data-open-order-tabs] button.bg-sky-600').innerText();
    await page.locator('[data-pos-ticket-header] button[aria-label]').first().click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-open-order-tabs] button').count() >= 2, 'multi-ticket new order persists');
    check(await page.locator('[data-cart-line]').count() === 0, 'new ticket starts with independent empty cart');
    await capture('pos-multi-ticket-1440-v2.png');
    await page.locator('[data-open-order-tabs]').getByRole('button', { name: populatedTicket, exact: true }).click();
    await page.waitForLoadState('networkidle');
    check(await page.locator('[data-cart-line]').count() === 1, 'switching ticket restores cart');
    check(await page.locator('[data-pos-ticket-header]').innerText().then((text) => text.includes('عميل اختبار جديد')), 'switching ticket restores customer');

    await page.setViewportSize({ width: 1024, height: 900 });
    await inspect(1024);
    await capture('pos-main-1024-v3.png');
    await page.setViewportSize({ width: 390, height: 844 });
    await inspect(390, 'products');
    await capture('pos-main-390-v3.png');
    await page.getByRole('button', { name: 'الطلب الحالي' }).first().click();
    await inspect(390, 'transaction');
    await capture('pos-transaction-390-v2.png');
    check(await page.locator('[data-cart-line]').count() === 1, 'mobile transaction switch preserves cart');
    check(await page.locator('[data-pos-ticket-header]').innerText().then((text) => text.includes('عميل اختبار جديد')), 'mobile transaction switch preserves customer');

    check(errors.length === 0, `browser console and page errors: ${errors.join(' | ')}`);
} finally {
    await browser.close();
}
console.log(`POS browser QA: ${assertions} assertions; screenshots ${screenshotDirectory}`);
if (failures.length > 0) {
    console.error(failures.join('\n'));
    process.exitCode = 1;
}
