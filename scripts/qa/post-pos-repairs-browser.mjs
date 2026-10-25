import { chromium } from 'playwright';
import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const base = process.env.QA_BROWSER_URL;
const screenshots = process.env.QA_POST_POS_SCREENSHOTS;
if (!base || new URL(base).hostname !== '127.0.0.1' || !screenshots?.startsWith('/tmp/')) {
    throw new Error('Disposable loopback browser runtime and /tmp screenshots are required');
}
mkdirSync(screenshots, { recursive: true });
const fixture = JSON.parse(readFileSync(process.env.QA_POST_POS_FIXTURE, 'utf8'));
const admin = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(account => account.role === 'Admin');
if (!admin) throw new Error('Disposable Admin identity missing');

let assertions = 0;
const errors = [];
const failures = [];
function check(condition, label) {
    assertions += 1;
    console.log(`${condition ? 'PASS' : 'FAIL'} ${label}`);
    if (!condition) failures.push(label);
}

const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('response', response => { if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`); });
page.on('dialog', dialog => dialog.accept());

async function visit(path) {
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    check(response?.status() === 200, `${path} HTTP 200`);
}
async function capture(name) {
    await page.screenshot({ path: join(screenshots, name), fullPage: true });
}
async function health(label) {
    const state = await page.evaluate(() => ({
        rtl: document.documentElement.dir === 'rtl',
        cairo: /Cairo/i.test(getComputedStyle(document.body).fontFamily),
        overflow: document.documentElement.scrollWidth > window.innerWidth + 2,
        rawQuantity: /\b\d+\.\d{6}\b/.test(document.body.innerText),
        englishUi: /\b(?:Find and select products|Receiving draft saved|Destination receipt finally confirmed|Transfer approval review|Transfer receiving review|Document source|Review and reconcile)\b/.test(document.body.innerText),
    }));
    check(state.rtl, `${label} RTL`);
    check(state.cairo, `${label} Cairo`);
    check(!state.overflow, `${label} no page overflow`);
    check(!state.rawQuantity, `${label} no raw six-decimal quantities`);
    check(!state.englishUi, `${label} no untranslated touched English UI`);
}
async function setContext(storeId) {
    await page.evaluate(async value => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/ui/work-context', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token, store_id: value ?? '' }),
        });
    }, storeId);
}
async function setArabic() {
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token, locale: 'ar-EG' }),
        });
    });
}

try {
    await visit('/login');
    await page.locator('input[name=username]').fill(admin.username);
    await page.locator('input[name=password]').fill(admin.password);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/login')),
        page.locator('[data-test=login-button]').click(),
    ]);
    await setArabic();

    await visit('/purchasing/invoices');
    await page.locator(`[wire\\:click="openEditModal(${fixture.invoice})"]`).first().evaluate(element => element.click());
    await page.locator('[data-purchase-invoice-editor]').waitFor();
    const quantity = page.locator('[data-purchase-invoice-editor] [data-line-quantity]').first();
    check(await quantity.inputValue() === '5', 'R1 purchase edit formats 5.000000 as 5');
    await capture('r1-purchase-edit-quantity-format.png');
    await capture('r2-purchase-draft-before-edit.png');
    await quantity.fill('10');
    await quantity.blur();
    await page.locator('[data-purchase-invoice-editor] button[type=submit]').first().click();
    await page.locator('[data-purchase-invoice-editor]').waitFor({ state: 'hidden' });
    await page.locator(`[wire\\:click="openEditModal(${fixture.invoice})"]`).first().evaluate(element => element.click());
    await page.locator('[data-purchase-invoice-editor]').waitFor();
    check(await page.locator('[data-purchase-invoice-editor] [data-line-quantity]').first().inputValue() === '10', 'R2 draft quantity persists after reopen');
    await capture('r2-purchase-draft-after-edit.png');
    await health('Purchase edit');

    await visit(`/inventory/transfers/${fixture.approvalTransfer}/approve`);
    check(await page.locator('[data-transfer-approval-review]').count() === 1, 'R3 approval opens line review');
    check((await page.locator('[data-transfer-approval-review]').innerText()).includes(fixture.productCode), 'R3 approval ties quantity to product SKU');
    await capture('r3-transfer-approval-review.png');
    await health('Transfer approval');

    await visit(`/inventory/transfers/${fixture.receivingTransfer}/receive?destination_store_id=${fixture.receivingDestination}`);
    check(await page.locator('[data-transfer-receipt-line]').count() === 1, 'R3 destination sees only allocated line');
    await capture('r3-transfer-receiving-draft.png');
    const receivedInput = page.locator('[data-transfer-receipt-line] input[type=number]').first();
    await receivedInput.fill('7');
    await page.locator('select[name=difference_type]').selectOption('shortage');
    await page.locator('input[name=difference_reason]').fill('QA count difference');
    await page.locator('form[action$="/receive/draft"] button').click();
    await page.waitForURL(url => url.pathname.endsWith('/receive'));
    check(await page.locator('[data-transfer-receipt-line] input[type=number]').first().inputValue() === '7', 'R3 receiving draft reopens at counted quantity');
    check((await page.locator('body').innerText()).includes('تم حفظ مسودة الاستلام'), 'R3 draft confirmation is Arabic');
    await capture('r3-transfer-receiving-difference.png');
    await capture('r3-transfer-final-confirmation.png');
    check(await page.locator('form[action$="/receive"] button').count() === 1, 'R3 final receipt remains a separate action');
    await page.locator('form[action$="/receive"] button').click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('main[data-transfer-receiving-review]').innerText()).includes('تم تأكيد استلام'), 'R3 final receipt confirmed');
    check(!(await page.locator('body').innerText()).includes('Destination receipt finally confirmed'), 'R3 final confirmation is Arabic');
    await health('Transfer receipt');

    await visit('/inventory/transfers');
    await capture('r4-transfer-sidebar-submenu.png');
    const navigation = await page.locator('body').innerText();
    check(navigation.includes('إذن إضافة') && navigation.includes('إذن خصم'), 'R4 Add and Deduct navigation visible');

    await visit('/pricing/labels');
    await capture('r5-barcode-document-source.png');
    const source = page.locator('[data-barcode-document-source]');
    check((await source.innerText()).includes('استدعاء فاتورة شراء') && (await source.innerText()).includes('استدعاء إذن تحويل'), 'R5 document source controls');
    check(!(await page.locator('body').innerText()).includes('Find and select products'), 'R5 Arabic heading localized');
    await source.locator('select[name=source]').selectOption('purchase_invoice');
    await source.locator('input[name=number]').fill(fixture.approvedInvoiceNumber);
    await source.locator('select[name=destination_store_id]').selectOption(String(fixture.receivingStore));
    await source.locator('select[name=selling_store_id]').selectOption(String(fixture.sellingStore));
    await source.locator('button').click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('[data-label-workspace]').innerText()).includes('20'), 'R5 purchase quantity prepares 20 labels');
    check(await page.locator('[data-label-workspace] input[name$="[copies]"]').first().inputValue() === '20', 'R5 purchase label-count default is invoice quantity');
    await capture('r5-barcode-purchase-import.png');
    await visit(`/pricing/labels?source=stock_transfer&number=${encodeURIComponent(fixture.receivingTransferNumber)}&destination_store_id=${fixture.receivingDestination}&selling_store_id=${fixture.sellingStore}`);
    check((await page.locator('[data-label-workspace]').innerText()).includes('8'), 'R5 transfer uses destination allocation of 8');
    check(await page.locator('[data-label-workspace] input[name$="[copies]"]').first().inputValue() === '8', 'R5 transfer label-count default is destination quantity');
    await capture('r5-barcode-transfer-import.png');
    await capture('r5-barcode-destination-price.png');
    check((await page.locator('[data-label-workspace]').innerText()).includes('140.00'), 'R5 destination selling price shown');
    await health('Barcode labels');

    await setContext(fixture.workStore);
    await visit('/dashboard');
    check((await page.locator('[data-dashboard-quick-actions]').innerHTML()).includes(`store_id=${fixture.workStore}`), 'R6 dashboard POS link inherits active store');
    await visit('/pos/shift');
    check(!(await page.locator('body').innerText()).includes(fixture.otherSellingCode), 'R6 other branch drawer excluded');
    await capture('r6-new-pos-inherited-branch.png');
    await setContext(null);

    await visit(`/inventory/counts/${fixture.count}/reconcile?count_filter=differences`);
    check(await page.locator('[data-stocktake-review-table] tbody tr').count() === 3, 'R7 exception report contains shortage, surplus, uncounted');
    await capture('r7-stocktake-difference-report.png');
    for (const [filter, screenshot, sku] of [
        ['shortage', 'r7-stocktake-shortage-filter.png', 'QA-R7-SHORT'],
        ['excess', 'r7-stocktake-surplus-filter.png', 'QA-R7-PLUS'],
        ['not_counted', 'r7-stocktake-not-counted-filter.png', 'QA-R7-MISS'],
    ]) {
        await visit(`/inventory/counts/${fixture.count}/reconcile?count_filter=${filter}`);
        check((await page.locator('[data-stocktake-review-table]').innerText()).includes(sku), `R7 ${filter} filter`);
        await capture(screenshot);
    }
    await health('Stocktake report desktop');
    await page.setViewportSize({ width: 390, height: 844 });
    await health('Stocktake report mobile');
    await visit('/pricing/labels');
    await health('Barcode labels mobile');
    check(errors.length === 0, `No console/page/HTTP 500 errors: ${errors.join('; ')}`);
    console.log(`BROWSER_ASSERTIONS ${assertions} FAILURES ${failures.length} SCREENSHOTS ${screenshots}`);
    if (failures.length) throw new Error(failures.join('; '));
} finally {
    await browser.close();
}
