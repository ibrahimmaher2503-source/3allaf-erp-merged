import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const base = process.env.QA_BROWSER_URL;
if (!base || new URL(base).hostname !== '127.0.0.1') throw new Error('Disposable localhost browser required');
const account = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(row => row.role === 'Admin');
const fixture = JSON.parse(readFileSync(process.env.QA_BATCH_E2E_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM, args: ['--no-sandbox'] });
const viewportWidth = Number(process.env.QA_VIEWPORT_WIDTH || 1440);
const page = await browser.newPage({ viewport: { width: viewportWidth, height: 900 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('response', response => { if (response.status() >= 500 || response.status() === 422) errors.push(`HTTP ${response.status()} ${new URL(response.url()).pathname}`); });
function check(condition, label) { if (!condition) throw new Error(`FAIL ${label}`); console.log(`PASS ${label}`); }
async function open(path) { const response = await page.goto(base + path, { waitUntil: 'networkidle' }); check(response.status() === 200, `${path} HTTP 200`); }
async function responsiveCheck(label) {
    check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `${label} ${viewportWidth}px no horizontal overflow`);
    check(await page.locator('html').getAttribute('dir') === 'rtl', `${label} Arabic RTL`);
}
async function selectProduct(container, barcode) {
    const lookup = container.locator('[data-product-lookup]').first();
    await lookup.locator('[data-product-search]').fill(barcode);
    const overlay = page.locator(`#${await lookup.locator('[data-product-search]').getAttribute('aria-controls')}`);
    for (let attempt = 0; attempt < 30; attempt++) {
        if (Number(await lookup.locator('[data-product-id]').inputValue()) > 0 || await overlay.locator('[role=option]').first().isVisible()) break;
        await page.waitForTimeout(250);
    }
    if (Number(await lookup.locator('[data-product-id]').inputValue()) === 0) {
        console.log(`LOOKUP DEBUG ${barcode} ready=${await lookup.getAttribute('data-product-lookup-ready')} result=${(await overlay.innerText()).slice(0, 160)} errors=${errors.join(';')}`);
        await overlay.locator('[role=option]').first().waitFor({ state: 'visible' });
        await overlay.locator('[role=option]').first().click();
    }
    check(Number(await lookup.locator('[data-product-id]').inputValue()) > 0, `${barcode} selected from browser lookup`);
}
async function login() {
    await open('/login');
    await page.locator('input[name=username]').fill(account.username);
    await page.locator('input[name=password]').fill(account.password);
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test=login-button]').click()]);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        await fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, locale: 'ar' }) });
    });
}
async function inventoryDocument(kind, quantity) {
    const path = kind === 'addition' ? '/inventory/additions' : '/inventory/deductions';
    await open(path);
    await responsiveCheck(`${kind} document`);
    check(await page.locator('#inventory-filters-heading, #adjustment-type').count() === 0, `${kind} dedicated document page`);
    check((await page.locator(`#adjustment-store option[value="${fixture.source}"]`).innerText()).includes('بيع اختبار'), `${kind} human store name`);
    await page.locator('#adjustment-store').selectOption(String(fixture.source));
    await page.locator('#adjustment-reason').fill(`QA-E2E-${kind.toUpperCase()}`);
    const line = page.locator('[data-product-line]').first();
    await selectProduct(line, fixture.products[0].barcode);
    await line.locator('[data-line-quantity]').fill(String(quantity));
    await line.locator('[data-line-price]').fill('10');
    await Promise.all([page.waitForURL(url => /\/inventory\/adjustments\/\d+\/edit$/.test(url.pathname)), page.locator('form[data-product-line-editor] button[type=submit]').click()]);
    const serial = (await page.locator('[data-inventory-document-number]').innerText()).trim();
    check(serial.length > 0, `${kind} serial ${serial}`);
    await page.locator('form[action$="/approve"] button[type=submit]').first().click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('body').innerText()).includes(serial), `${kind} serial in history`);
    check(await page.locator('form[action$="/reverse"]').count() > 0, `${kind} reversal action remains available`);
    return serial;
}
async function stockQuantity(store, productCode) {
    await open(`/reports/current-inventory?store_id=${store}&supplier_ids%5B%5D=${fixture.supplier}`);
    const row = page.locator('[data-report-row]').filter({ hasText: productCode }).first();
    await row.waitFor();
    return Number((await row.locator('td').nth(2).innerText()).replaceAll(',', ''));
}
async function transferAndReceive(existingId = null) {
    let transferId = existingId;
    const initialDestinationStock = process.env.QA_E2E_START === 'transfer-only' ? await stockQuantity(fixture.destination_a, fixture.products[0].code) : null;
    if (!transferId) {
    await open('/inventory/transfers/create');
    await page.locator('#transfer-source').selectOption(String(fixture.source));
    for (const destination of [fixture.destination_a, fixture.destination_b]) {
        await page.locator(`input[name="destination_store_ids[]"][value="${destination}"]`).check();
    }
    const first = page.locator('[data-transfer-allocation-editor] [data-product-line]').first();
    await selectProduct(first, fixture.products[0].barcode);
    await first.locator(`input[name="lines[0][allocations][${fixture.destination_a}]"]`).fill('2');
    await first.locator(`input[name="lines[0][allocations][${fixture.destination_b}]"]`).fill('1');
    await page.locator('[data-transfer-allocation-editor] button[data-add-line]').click();
    const second = page.locator('[data-transfer-allocation-editor] [data-product-line]').nth(1);
    await selectProduct(second, fixture.products[1].barcode);
    await second.locator(`input[name*="[allocations][${fixture.destination_a}]"]`).fill('1');
    await second.locator(`input[name*="[allocations][${fixture.destination_b}]"]`).fill('2');
    check((await first.locator('[data-line-transfer-total]').innerText()).trim() === '3', 'transfer quantity derived from allocations');
    await page.locator('#transfer-reason').fill('QA-E2E-TRANSFER');
    await Promise.all([page.waitForURL(url => /\/inventory\/transfers\/\d+\/edit$/.test(url.pathname)), page.locator('[data-transfer-allocation-editor] button[type=submit]').click()]);
    transferId = Number(page.url().match(/\/transfers\/(\d+)\/edit$/)?.[1]);
    check(transferId > 0, `transfer ${transferId} saved through UI`);
    }
    await open('/inventory/transfers');
    const submit = page.locator(`form[action$="/transfers/${transferId}/submit"] button[type=submit]`);
    if (await submit.count()) {
        await submit.click();
        await page.waitForLoadState('networkidle');
    }
    if (process.env.QA_E2E_START !== 'transfer-continue' || await page.locator(`a[href$="/transfers/${transferId}/approve"]`).count()) {
    await open(`/inventory/transfers/${transferId}/approve`);
    await responsiveCheck('transfer final review');
    const reviewLines = page.locator('[data-transfer-approval-review] [data-product-line]');
    check(await reviewLines.count() === 2, 'two compact transfer review rows');
    check(await page.locator('[data-transfer-approval-review] input[name*="[allocations]"]:visible').count() === 4, 'only two selected destinations per product in review');
    check(await page.locator('[data-transfer-approval-review] input[name*="[allocations]"][value="0"]:visible').count() === 0, 'zero destinations hidden in review');
    await page.getByRole('button', { name: 'اعتماد التحويل' }).click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('body').innerText()).includes('قيد النقل'), 'one approval automatically dispatches to In Transit');
    }
    for (const [destination, partial] of (process.env.QA_E2E_START === 'transfer-continue' ? [[fixture.destination_b, false]] : [[fixture.destination_a, true], [fixture.destination_b, false]])) {
        await open('/inventory/transfers/receiving');
        check(await page.locator(`[data-incoming-transfer="${transferId}"]`).count() === 2, 'receiving inbox lists exactly two allocated destinations');
        await open(`/inventory/transfers/${transferId}/receive?destination_store_id=${destination}`);
        await responsiveCheck('destination receiving');
        check(await page.locator('[data-transfer-receipt-line]').count() === 2, 'two compact destination receipt rows');
        check(await page.locator('select[name=destination_store_id] option').count() === 3, 'only allocated destinations selectable');
        if (partial) {
            await page.locator('[data-transfer-receipt-line] input[name^="received_quantities"]').first().fill('1');
            await page.locator('select[name=difference_type]').selectOption('shortage');
            await page.locator('input[name=difference_reason]').fill('QA E2E partial receipt');
        }
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'تأكيد الاستلام' }).click();
        await page.waitForLoadState('networkidle');
        check((await page.locator('body').innerText()).includes('تم تأكيد استلام هذه الوجهة'), `destination ${destination} Received directly`);
        check(await page.getByRole('button', { name: 'تأكيد الاستلام' }).count() === 0, `destination ${destination} duplicate receipt blocked`);
        await open('/inventory/transfers');
        console.log(`PARENT STATUS VIEW ${(await page.locator('[data-transfer-row]').first().innerText()).slice(0, 260)}`);
    }
    if (process.env.QA_E2E_START === 'transfer-only') {
        const transferRow = page.locator('[data-transfer-row]').first();
        check((await transferRow.innerText()).includes('مستلم'), 'shortage receipt completes parent after both destinations confirm');
        check(await page.locator('form[action*="differences/resolve"]').count() === 0, 'no extra difference resolution action');
        const variance = page.locator('[data-transfer-variance]').first();
        await variance.locator('summary').click();
        check(await variance.locator('[data-transfer-variance-row]').count() === 1, 'shortage remains visible per product and destination');
        check((await variance.locator('[data-transfer-variance-row]').first().innerText()).includes('QA E2E partial receipt'), 'shortage reason remains visible');
        check(await stockQuantity(fixture.destination_a, fixture.products[0].code) === initialDestinationStock + 1, 'short destination stock increases by actual received quantity only');
    }
    return transferId;
}
async function zeroDestination() {
    await open('/inventory/transfers/create');
    await page.locator('#transfer-source').selectOption(String(fixture.source));
    for (const destination of [fixture.destination_a, fixture.destination_b]) {
        await page.locator(`input[name="destination_store_ids[]"][value="${destination}"]`).check();
    }
    const line = page.locator('[data-transfer-allocation-editor] [data-product-line]').first();
    await selectProduct(line, fixture.products[0].barcode);
    await line.locator(`input[name="lines[0][allocations][${fixture.destination_a}]"]`).fill('1');
    await line.locator(`input[name="lines[0][allocations][${fixture.destination_b}]"]`).fill('0');
    await page.locator('#transfer-reason').fill('QA-E2E-ZERO-DESTINATION');
    await Promise.all([page.waitForURL(url => /\/inventory\/transfers\/\d+\/edit$/.test(url.pathname)), page.locator('[data-transfer-allocation-editor] button[type=submit]').click()]);
    const transferId = Number(page.url().match(/\/transfers\/(\d+)\/edit$/)?.[1]);
    await open('/inventory/transfers');
    await page.locator(`form[action$="/transfers/${transferId}/submit"] button[type=submit]`).click();
    await open(`/inventory/transfers/${transferId}/approve`);
    check(await page.locator(`[data-transfer-approval-review] input[name*="[allocations][${fixture.destination_b}]"]:visible`).count() === 0, 'zero destination omitted from final review');
    await page.getByRole('button', { name: 'اعتماد التحويل' }).click();
    await open('/inventory/transfers/receiving');
    check(await page.locator(`[data-incoming-transfer="${transferId}"]`).count() === 1, 'zero destination creates no receiving allocation');
    await open(`/inventory/transfers/${transferId}/receive?destination_store_id=${fixture.destination_a}`);
    check(await page.locator('select[name=destination_store_id] option').count() === 2, 'only nonzero destination selectable for receipt');
}
async function completeExactTransfer(transferId = 1) {
    await open(`/inventory/transfers/${transferId}/receive?destination_store_id=${fixture.destination_a}`);
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'تأكيد الاستلام' }).click();
    await page.waitForLoadState('networkidle');
    check((await page.locator('body').innerText()).includes('تم تأكيد استلام هذه الوجهة'), 'exact destination receipt confirmed once');
    await open('/inventory/transfers');
    check((await page.locator('[data-transfer-row]').first().innerText()).includes('مستلم'), 'exact final receipt completes parent transfer');
}
async function purchaseImport() {
    await open('/purchasing/orders/create');
    await responsiveCheck('purchase order import');
    const template = await page.request.get(`${base}/purchasing/orders/import-template`);
    check(template.status() === 200 && (template.headers()['content-type'] ?? '').includes('spreadsheet'), 'Purchase Excel template downloads');
    await page.locator('select[wire\\:model\\.live="orderForm.supplier_id"]').selectOption(String(fixture.supplier));
    await page.locator('select[wire\\:model\\.live="orderForm.branch_id"]').selectOption(String(fixture.branch));
    await page.locator('select[wire\\:model="orderForm.store_id"]').selectOption(String(fixture.destination_a));
    await page.getByRole('button', { name: 'استيراد من Excel' }).click();
    await page.locator('input[type=file][wire\\:model="importFile"]').setInputFiles(fixture.workbook_invalid);
    await page.waitForTimeout(1800);
    await page.getByRole('button', { name: 'معاينة الصفوف' }).click();
    console.log(`PO INVALID VIEW ${(await page.locator('[data-po-import-preview]').innerText()).slice(0, 500)}`);
    await page.locator('[data-po-import-row]').first().waitFor();
    check((await page.locator('[data-po-import-row]').innerText()).includes('QA-UNKNOWN-CODE'), 'invalid Excel row shown inline');
    await page.locator('input[type=file][wire\\:model="importFile"]').setInputFiles(fixture.workbook_valid);
    await page.waitForTimeout(1800);
    await page.getByRole('button', { name: 'معاينة الصفوف' }).click();
    await page.locator('[data-po-import-row]').nth(1).waitFor();
    check(await page.locator('[data-po-import-row]').count() === 2, 'two valid Excel rows previewed');
    check((await page.locator('[data-po-import-row]').nth(1).innerText()).includes('0009001002'), 'leading-zero barcode preserved in preview');
    await page.getByRole('button', { name: 'تأكيد إضافة البنود' }).click();
    await page.locator('[data-order-lines-scroll] [data-product-line]').nth(1).waitFor();
    check(await page.locator('[data-order-lines-scroll] [data-product-line]').count() === 2, 'Excel confirm inserted two PO draft lines');
}
async function productCodes() {
    for (const [method, code] of [['international', `0009${String(Date.now()).slice(-8)}`], ['local', null]]) {
        await open('/catalog/products/create');
        const form = page.locator('form[wire\\:submit="save"]').first();
        await page.locator('select[wire\\:model\\.live="productForm.barcode_registration_type"]').selectOption(method);
        await page.locator('select[wire\\:model\\.live="productForm.preferred_supplier_id"]').first().selectOption(String(fixture.supplier));
        if (code) await page.locator('input[wire\\:model="supplierBarcode"]').fill(code);
        await page.locator('#product-field-model_number').fill(`QA-E2E-${method}-${Date.now()}`);
        await page.locator('#product-field-name_ar').fill(`منتج اختبار ${method}`);
        await page.locator('#product-field-category_id').selectOption(String(fixture.category));
        await page.locator('#product-field-sale_price').fill('25');
        check(await page.getByText('باركود دولي', { exact: true }).count() === 0, 'no Local/International product choice');
        await form.locator('button[type=submit]').last().click();
        await page.waitForURL(url => /\/catalog\/products\/\d+\/edit$/.test(url.pathname), { timeout: 12000 });
        const editUrl = page.url();
        await page.goto(editUrl, { waitUntil: 'networkidle' });
        check((await page.locator('body').innerText()).includes(code ?? 'SUP-9001'), `${code ? 'Existing' : 'Generate'} code persisted on reopen`);
    }
}
async function posDiscount() {
    await open('/pos');
    await page.locator('[data-pos-category-grid] button').nth(1).click();
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('[data-pos-product-grid] article').first().getByRole('button', { name: /إضافة/ }).click();
    await page.locator('[data-cart-line]').first().waitFor();
    await page.locator('[data-cart-discount] summary').click();
    await page.locator('select[wire\\:model="invoiceDiscountType"]').selectOption('fixed');
    await page.locator('input[wire\\:model="invoiceDiscountValue"]').fill('5');
    await page.locator('button[wire\\:click="saveInvoiceDiscount"]').click();
    await page.locator('button[wire\\:click="removeInvoiceDiscount"]').waitFor();
    check(await page.locator('button[wire\\:click="removeInvoiceDiscount"]').count() === 1, 'fixed POS discount applied');
    const fixedDiscountText = await page.locator('[data-cart-discount] p').innerText();
    await page.locator('select[wire\\:model="invoiceDiscountType"]').selectOption('percentage');
    await page.locator('input[wire\\:model="invoiceDiscountValue"]').fill('20');
    await page.locator('button[wire\\:click="saveInvoiceDiscount"]').click();
    await page.waitForFunction(previous => document.querySelector('[data-cart-discount] p')?.innerText !== previous, fixedDiscountText);
    check(await page.locator('button[wire\\:click="removeInvoiceDiscount"]').count() === 1, 'percentage POS discount editable');
    const percentageDiscountText = await page.locator('[data-cart-discount] p').innerText();
    await page.locator('input[wire\\:model="invoiceDiscountValue"]').fill('101');
    await page.locator('button[wire\\:click="saveInvoiceDiscount"]').click();
    await page.waitForTimeout(500);
    check((await page.locator('[data-cart-discount] p').innerText()) === percentageDiscountText, 'over-100 percentage rejected without changing totals');
    await page.locator('button[wire\\:click="removeInvoiceDiscount"]').click();
    await page.locator('button[wire\\:click="removeInvoiceDiscount"]').waitFor({ state: 'detached' });
    check(await page.locator('[data-cart-line]').count() === 1, 'line remains after cart discount removal');
    await page.locator('button[wire\\:click^="startCheckout"]').click();
    await page.locator('dialog[aria-labelledby="pos-checkout-dialog-title"]').waitFor({ state: 'visible' });
    check(await page.locator('button[wire\\:click^="switchCheckoutMode"]').count() >= 3, 'Complete Sale payment methods unaffected');
}
async function discountedSaleAndReturn() {
    await open('/pos');
    await page.locator('[data-pos-category-grid] button').nth(1).click();
    await page.locator('[data-pos-product-grid] article').first().waitFor();
    await page.locator('[data-pos-product-grid] article').first().getByRole('button', { name: /إضافة/ }).click();
    await page.locator('[data-cart-line]').first().waitFor();
    await page.locator('[data-cart-discount] summary').click();
    await page.locator('select[wire\\:model="invoiceDiscountType"]').selectOption('percentage');
    await page.locator('input[wire\\:model="invoiceDiscountValue"]').fill('20');
    await page.locator('button[wire\\:click="saveInvoiceDiscount"]').click();
    await page.locator('button[wire\\:click="removeInvoiceDiscount"]').waitFor();
    const discountText = await page.locator('[data-cart-discount] p').innerText();
    check(discountText.includes('ج.م'), 'discounted sale cart amount displayed');
    await page.locator('button[wire\\:click^="startCheckout"]').click();
    const checkout = page.locator('dialog[aria-labelledby="pos-checkout-dialog-title"]');
    await checkout.waitFor({ state: 'visible' });
    await checkout.locator('button[wire\\:click="switchCheckoutMode(\'cash\')"]').click();
    await checkout.locator('[data-cash-received]').waitFor();
    await checkout.locator('button').filter({ hasText: 'المبلغ المحدد' }).first().click().catch(async () => {
        await checkout.locator('[data-cash-received]').fill(await checkout.locator('[data-pos-payable]').inputValue());
    });
    const submit = checkout.locator('form[action$="/pos/checkout"] button[type=submit]');
    await submit.waitFor();
    await Promise.all([page.waitForLoadState('networkidle'), submit.click()]);
    await page.locator('#pos-sale-success').waitFor({ state: 'visible' });
    check(await page.locator('#pos-sale-success').count() === 1, 'discounted sale completed through browser');
    const saleReference = (await page.locator('#pos-sale-success p.font-mono').innerText()).trim();
    const receiptPath = await page.locator('#pos-sale-success a[href*="/receipt"]').getAttribute('href');
    await open(new URL(receiptPath, base).pathname);
    check((await page.locator('body').innerText()).includes('خصم'), 'completed sale receipt shows discount');
    const receiptReference = await page.locator('[data-receipt-barcode]').first().getAttribute('data-receipt-reference');
    check(receiptReference === saleReference, 'receipt matches completed discounted sale');
    check(Boolean(receiptReference), `discounted sale reference ${receiptReference}`);
    await open('/pos');
    await page.keyboard.press('F7');
    await page.locator('#pos-refund-lookup').waitFor();
    await page.locator('#pos-refund-lookup').fill(receiptReference);
    await page.locator('[data-pos-refund-wizard] form[wire\\:submit="lookup"] button[type=submit]').click();
    await page.locator('[data-pos-refund-wizard]').getByText(receiptReference).first().waitFor();
    await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
    const quantity = page.locator('[data-pos-refund-wizard] input[wire\\:model^="quantities."]').first();
    await quantity.waitFor();
    await quantity.fill('1');
    await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
    await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
    await page.locator('[data-pos-refund-wizard] select[wire\\:model="reason"]').selectOption('defective');
    await page.locator('[data-pos-refund-wizard] button[wire\\:click="next"]').click();
    await page.locator('[data-pos-refund-wizard] button[wire\\:click="submit"]').click();
    const returnReceipt = page.locator('[data-pos-refund-wizard] a[href*="/pos/refunds/"][href$="/receipt"]');
    await returnReceipt.waitFor({ state: 'visible' });
    check(await returnReceipt.count() === 1, 'linked discounted return posted through browser');
}
async function supplierReturn(store) {
    await open('/purchasing/returns/create');
    await page.getByLabel('المورد', { exact: true }).selectOption(String(fixture.supplier));
    await page.getByLabel('مخزن الإرجاع', { exact: true }).selectOption(String(store));
    await page.getByLabel('سبب المرتجع', { exact: true }).selectOption(String(fixture.reason));
    await selectProduct(page, fixture.products[0].barcode);
    await page.locator('input[aria-label="الكمية المرتجعة"]').first().fill('1');
    await page.getByRole('button', { name: 'حفظ مسودة المرتجع' }).click();
    await page.waitForURL(url => /\/purchasing\/returns\/\d+$/.test(url.pathname), { timeout: 15000 });
    check(!(await page.locator('body').innerText()).includes('الفرع المحدد غير نشط أو غير موجود'), `Supplier Return ${store} accepts selected warehouse`);
    console.log(`SUPPLIER RETURN ${store} ${page.url()}`);
}
async function postSupplierReturns() {
    await open('/purchasing/returns');
    for (const id of [1, 2]) {
        const row = page.locator('tr').filter({ has: page.locator(`a[href$="/purchasing/returns/${id}"]`) }).first();
        await row.locator(`button[wire\\:click="submitReturn(${id})"]`).click();
        await row.locator(`button[wire\\:click="approveReturn(${id})"]`).click();
        await open(`/purchasing/returns/${id}`);
        check(!(await page.locator('body').innerText()).includes('No stock movement posted yet.'), `Supplier Return ${id} stock movement posted`);
        await open('/purchasing/returns');
    }
}
async function verifySupplierReturns() {
    const branchStock = await stockQuantity(fixture.source, fixture.products[0].code);
    check(branchStock < 30, `branch-linked warehouse stock reduced (${branchStock})`);
    check(await stockQuantity(fixture.main, fixture.products[0].code) === 29, 'branchless Main Warehouse Supplier Return reduced selected warehouse stock to 29');
    await open('/purchasing/returns/2');
    check((await page.locator('body').innerText()).includes('المخزن الرئيسي للاختبار'), 'branchless Supplier Return shows actual Main Warehouse');
    await open('/purchasing/returns/create');
    check(await page.getByLabel('مخزن الإرجاع', { exact: true }).locator(`option[value="${fixture.inactive}"]`).count() === 0, 'inactive warehouse unavailable in Supplier Return UI');
}
async function reports() {
    const today = new Date().toISOString().slice(0, 10);
    await open(`/reports/product-sales?date_from=${today}&date_to=${today}`);
    await responsiveCheck('product sales report');
    check(await page.locator('select[name="store_ids[]"][multiple], select[name="supplier_ids[]"][multiple], select[name="category_ids[]"][multiple]').count() === 3, 'sales report multi-select filters');
    check(await page.locator('[data-report-results] tfoot tr').count() === 1, 'sales report one grand total');
    await open(`/reports/current-inventory?supplier_ids%5B%5D=${fixture.supplier}`);
    await responsiveCheck('current inventory report');
    check(await page.locator('input[name=date_from], input[name=date_to]').count() === 0, 'inventory report current snapshot has no dates');
    const allRows = await page.locator('[data-report-row]').filter({ hasText: fixture.products[0].code }).count();
    check(allRows === 1, 'all-store inventory aggregates product once');
    await open(`/reports/current-inventory?store_id=${fixture.source}&supplier_ids%5B%5D=${fixture.supplier}&category_ids%5B%5D=${fixture.category}`);
    check(await page.locator('[data-report-row]').filter({ hasText: fixture.products[0].code }).count() === 1, 'inventory scoped filters show product once');
    await open(`/reports/daily-branch-sales?date_from=${today}&date_to=${today}&branch_id=${fixture.branch}`);
    await responsiveCheck('daily sales report');
    check(await page.locator('select[name=branch_id]').count() === 1, 'daily report one branch filter');
    check(await page.locator('[data-report-results] tfoot tr').count() === 1, 'daily report one period total');
    const dailyRows = page.locator('[data-report-row]');
    check(await dailyRows.count() === 1, 'posted sales aggregate into one calendar-day row');
    const dailyCells = await dailyRows.first().locator('td').allInnerTexts();
    check(Number(dailyCells[1]) > 0 && Number(dailyCells[2]) > 0, 'daily gross and cash columns show posted sale economics');
    check(Number(dailyCells[4]) > 0 && Number(dailyCells[5]) > 0 && Number(dailyCells[6]) > 0, 'daily discounts, refunds, and net sales reflect posted transactions');
}
async function reportAuthorization() {
    const cashier = JSON.parse(readFileSync(process.env.QA_BROWSER_ACCOUNTS, 'utf8')).find(row => row.role === 'Cashier');
    const restricted = await browser.newPage();
    await restricted.goto(base + '/login');
    await restricted.locator('input[name=username]').fill(cashier.username);
    await restricted.locator('input[name=password]').fill(cashier.password);
    await Promise.all([restricted.waitForURL(url => !url.pathname.endsWith('/login')), restricted.locator('[data-test=login-button]').click()]);
    for (const path of ['/reports/product-sales', '/reports/current-inventory', '/reports/daily-branch-sales']) {
        const response = await restricted.goto(`${base}${path}?store_id=${fixture.main}&branch_id=${fixture.branch}`);
        check(response.status() === 403, `restricted cashier cannot access ${path} data by filter URL`);
    }
    await restricted.close();
}
try {
    await login();
    if (process.env.QA_E2E_START === 'reports') { await reports(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'report-auth') { await reportAuthorization(); process.exit(0); }
    if (process.env.QA_E2E_START === 'zero-destination') { await zeroDestination(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'zero-complete') { await completeExactTransfer(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'supplier-post') { await postSupplierReturns(); process.exit(0); }
    if (process.env.QA_E2E_START === 'supplier-verify') { await verifySupplierReturns(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'supplier') {
        await supplierReturn(fixture.source);
        await supplierReturn(fixture.main);
        check(errors.length === 0, `no browser errors: ${errors.join('; ')}`);
        process.exit(0);
    }
    if (process.env.QA_E2E_START === 'pos') { await posDiscount(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'sale-return') { await discountedSaleAndReturn(); check(errors.length === 0, `no browser errors: ${errors.join('; ')}`); process.exit(0); }
    if (process.env.QA_E2E_START === 'product') { await productCodes(); process.exit(0); }
    if (process.env.QA_E2E_START === 'purchase') {
        await purchaseImport();
        check(errors.length === 0, `no browser errors: ${errors.join('; ')}`);
        process.exit(0);
    }
    if (process.env.QA_E2E_START === 'status-only') {
        await open('/inventory/transfers');
        console.log(`PARENT STATUS VIEW ${(await page.locator('[data-transfer-row]').first().innerText()).slice(0, 260)}`);
        check((await page.locator('[data-transfer-row]').first().innerText()).includes('مستلم'), 'exact receipt parent status Received');
        process.exit(0);
    }
    if (!['transfer-continue', 'transfer-only'].includes(process.env.QA_E2E_START)) {
    check(await stockQuantity(fixture.source, fixture.products[0].code) === 30, 'initial source stock 30');
    const addition = await inventoryDocument('addition', 2);
    check(await stockQuantity(fixture.source, fixture.products[0].code) === 32, 'Addition posts +2 stock');
    const deduction = await inventoryDocument('deduction', -1);
    check(await stockQuantity(fixture.source, fixture.products[0].code) === 31, 'Deduction posts -1 stock');
    check(addition !== deduction, 'Addition and Deduction serials independent');
    }
    await transferAndReceive(process.env.QA_E2E_START === 'transfer-continue' ? 1 : null);
    check(errors.length === 0, `no browser errors: ${errors.join('; ')}`);
} finally {
    await browser.close();
}
