import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';

// RED contract for the missing legitimate remediation workflows. This file does
// not prepare fixtures, call Artisan, or inspect the database: every business
// transition is deliberately driven through the authenticated browser UI.
const REMEDIATION_DATABASE = 'toyjoy_phase1_remediation_20260818';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL ?? '';
const password = process.env.REMEDIATION_FIXTURE_PASSWORD;
const remediationDatabaseConfigured = process.env.PLAYWRIGHT_REMEDIATION_DATABASE === REMEDIATION_DATABASE;
const loopbackBaseUrl = /^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?(?:\/|$)/i.test(baseUrl);

const ACTORS = Object.freeze({
    requester: 'rem-requester',
    // These deliberately do not fall back to the super administrator. The
    // remediation seeder must supply distinct, store-scoped identities.
    approver: 'rem-approver',
    warehouse: 'rem-warehouse',
    receiver: 'rem-receiver',
    counter: 'rem-counter',
    cashier: 'rem-cashier',
});

function gateReason() {
    if (!password) return 'REMEDIATION_FIXTURE_PASSWORD is required for isolated REM actors.';
    if (!loopbackBaseUrl) return 'PLAYWRIGHT_BASE_URL must be an explicit loopback remediation server.';
    if (!remediationDatabaseConfigured) return `PLAYWRIGHT_REMEDIATION_DATABASE must equal ${REMEDIATION_DATABASE}.`;

    return null;
}

async function login(page, username) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: /log in/i }).click(),
    ]);
}

function observe(page, diagnostics, workflow) {
    page.on('pageerror', (error) => diagnostics.push({ workflow, kind: 'pageerror', url: page.url(), message: error.message }));
    page.on('console', (message) => {
        if (message.type() === 'error') diagnostics.push({ workflow, kind: 'console', url: page.url(), message: message.text() });
    });
}

async function optionValue(select, text, requirement) {
    const option = select.locator('option').filter({ hasText: text }).first();
    await expect(option, `REMEDIATION FIXTURE GAP: ${requirement}`).toHaveCount(1);
    const value = await option.getAttribute('value');
    expect(value, `REMEDIATION FIXTURE GAP: ${requirement} must expose a selectable runtime id.`).toBeTruthy();

    return value;
}

async function chooseRemProduct(select) {
    return optionValue(select, 'REM-NORMAL-001', 'approved REM-NORMAL-001 product must be visible through the scoped UI');
}

async function chooseRemSupplier(select) {
    return optionValue(select, 'Remediation Supplier', 'active Remediation Supplier must be visible through the scoped UI');
}

async function chooseRemStore(select, code = 'REM-WAREHOUSE') {
    return optionValue(select, code, `scoped ${code} store must be visible through the inventory UI`);
}

async function chooseInvoiceStore(select, name = 'Remediation Warehouse') {
    return optionValue(select, name, `scoped ${name} store must be rendered by the invoice UI`);
}

async function closeContext(context) {
    await context.close();
}

test.use({ locale: 'en-US', viewport: { width: 1280, height: 900 }, trace: 'retain-on-failure', screenshot: 'only-on-failure' });

test.describe.serial('REM purchasing and inventory workflows — Local/Dev RED contract', () => {
    test.skip(gateReason() !== null, gateReason() ?? '');

    const diagnostics = [];
    const runtime = { poNumber: null, invoiceReference: null, invoiceNumber: null, transferNumber: null, adjustmentNumber: null, countNumber: null };

    test.afterAll(() => {
        expect(diagnostics, `Unexpected console/page errors: ${JSON.stringify(diagnostics, null, 2)}`).toEqual([]);
    });

    test('US-010 creates, submits, independently approves, receives, closes, and prints one dynamic REM purchase order', async ({ page, browser }) => {
        test.setTimeout(180_000);
        observe(page, diagnostics, 'US-010-requester');
        await login(page, ACTORS.requester);
        await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: /new purchase order/i }).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog, 'REMEDIATION UI GAP: purchase order creation dialog must be available to the scoped requester.').toBeVisible();
        await dialog.getByLabel(/supplier/i).selectOption(await chooseRemSupplier(dialog.getByLabel(/supplier/i)));
        await dialog.getByLabel(/receiving store/i).selectOption(await chooseRemStore(dialog.getByLabel(/receiving store/i)));
        const product = dialog.locator('select').filter({ has: dialog.locator('option', { hasText: 'REM-NORMAL-001' }) }).first();
        await product.selectOption(await chooseRemProduct(product));
        await dialog.getByPlaceholder('Qty', { exact: true }).fill('2');
        await dialog.getByPlaceholder('Cost', { exact: true }).fill('10.00');
        await dialog.getByRole('button', { name: /save draft|create draft/i }).click();
        const draft = page.locator('tr').filter({ hasText: 'REM-SUPPLIER' }).first();
        await expect(draft, 'REMEDIATION UI GAP: the UI-created PO draft must remain visible to its requester.').toBeVisible();
        runtime.poNumber = (await draft.locator('td').first().innerText()).trim();
        expect(runtime.poNumber, 'The PO must have a server-issued document number after the visible draft action.').toMatch(/^PO-/);
        await draft.getByTitle(/submit order/i).click();
        await expect(draft).toContainText(/submitted/i);

        const reviewerContext = await browser.newContext({ locale: 'en-US' });
        const reviewer = await reviewerContext.newPage();
        observe(reviewer, diagnostics, 'US-010-approver');
        await login(reviewer, ACTORS.approver);
        await reviewer.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        const submitted = reviewer.locator('tr').filter({ hasText: runtime.poNumber }).first();
        await expect(submitted, 'REMEDIATION SCOPE GAP: a distinct scoped approver must see the submitted REM PO.').toBeVisible();
        await expect(submitted.getByTitle(/approve order/i), 'REMEDIATION SEPARATION GAP: distinct approver action must be visible.').toBeVisible();
        await submitted.getByTitle(/approve order/i).click();
        await expect(submitted).toContainText(/approved/i);
        await closeContext(reviewerContext);

        // Receiving has to occur from the approved order in the UI; an approved
        // PO alone must not create a stock movement.
        await page.goto('/purchasing/invoices', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: /new draft invoice/i }), 'REMEDIATION UI GAP: PO receiving must be reachable from a visible invoice draft workflow.').toBeVisible();
        await page.getByRole('button', { name: /new draft invoice/i }).click();
        const invoiceDialog = page.getByRole('dialog');
        await invoiceDialog.getByLabel('Supplier', { exact: true }).selectOption(await chooseRemSupplier(invoiceDialog.getByLabel('Supplier', { exact: true })));
        await invoiceDialog.getByLabel('Receiving store', { exact: true }).selectOption(await chooseInvoiceStore(invoiceDialog.getByLabel('Receiving store', { exact: true })));
        runtime.invoiceReference = `REM-PO-RECEIVE-${Date.now()}`;
        await invoiceDialog.getByLabel('Supplier invoice reference', { exact: true }).fill(runtime.invoiceReference);
        await invoiceDialog.getByLabel('Product', { exact: true }).selectOption(await chooseRemProduct(invoiceDialog.getByLabel('Product', { exact: true })));
        await invoiceDialog.getByLabel('Quantity', { exact: true }).fill('2');
        await invoiceDialog.getByLabel('Unit cost', { exact: true }).fill('10.00');
        const purchaseOrderLinkage = invoiceDialog.getByLabel(/purchase order/i);
        await expect(purchaseOrderLinkage, 'REMEDIATION UI RED: invoice form must expose an accessible purchase-order linkage selector before receipt can be saved.').toBeVisible();
        await purchaseOrderLinkage.selectOption({ label: new RegExp(runtime.poNumber) });
        await invoiceDialog.getByRole('button', { name: /save draft/i }).click();
        const invoice = page.locator('tr').filter({ hasText: runtime.invoiceReference }).first();
        await expect(invoice, 'REMEDIATION UI GAP: receipt invoice must be visible after the draft action.').toBeVisible();
        await invoice.getByRole('button', { name: 'Submit', exact: true }).click();

        const invoiceApproverContext = await browser.newContext({ locale: 'en-US' });
        const invoiceApprover = await invoiceApproverContext.newPage();
        observe(invoiceApprover, diagnostics, 'US-010-receipt-approver');
        await login(invoiceApprover, ACTORS.approver);
        await invoiceApprover.goto('/purchasing/invoices', { waitUntil: 'domcontentloaded' });
        const submittedInvoice = invoiceApprover.locator('tr').filter({ hasText: runtime.invoiceReference }).first();
        await expect(submittedInvoice.getByRole('button', { name: /approve & post/i }), 'REMEDIATION SEPARATION GAP: receipt posting requires a distinct approver.').toBeVisible();
        await submittedInvoice.getByRole('button', { name: /approve & post/i }).click();
        await expect(submittedInvoice).toContainText(/approved/i);
        runtime.invoiceNumber = (await submittedInvoice.locator('td').first().innerText()).trim();
        await closeContext(invoiceApproverContext);

        await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        const received = page.locator('tr').filter({ hasText: runtime.poNumber }).first();
        await expect(received, 'REMEDIATION FLOW GAP: approved receipt must update the source PO visibly.').toContainText(/received|partially received/i);
        await expect(received.getByTitle(/close order/i), 'REMEDIATION UI GAP: the received PO must expose a visible close control.').toBeVisible();
        await received.getByTitle(/close order/i).click();
        await expect(received).toContainText(/closed/i);
        const print = received.locator('a[href*="/purchasing/orders/"][href$="/print"]');
        await expect(print, 'REMEDIATION PRINT GAP: closed PO must expose its print preview.').toBeVisible();
        const printPagePromise = page.waitForEvent('popup');
        await print.click();
        const printPage = await printPagePromise;
        await printPage.waitForLoadState('domcontentloaded');
        await expect(printPage.locator('body')).toContainText(runtime.poNumber);
        await printPage.close();
    });

    test('US-011 exposes a visible duplicate purchase-invoice conflict without silently posting or replacing the source reference', async ({ page }) => {
        test.setTimeout(120_000);
        observe(page, diagnostics, 'US-011-requester');
        await login(page, ACTORS.requester);
        await page.goto('/purchasing/invoices', { waitUntil: 'domcontentloaded' });
        await expect(runtime.invoiceReference, 'Serial prerequisite missing: US-010 must create a UI-visible invoice reference.').toBeTruthy();
        await page.getByRole('button', { name: /new draft invoice/i }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Supplier', { exact: true }).selectOption(await chooseRemSupplier(dialog.getByLabel('Supplier', { exact: true })));
        await dialog.getByLabel('Receiving store', { exact: true }).selectOption(await chooseInvoiceStore(dialog.getByLabel('Receiving store', { exact: true })));
        await dialog.getByLabel('Supplier invoice reference', { exact: true }).fill(runtime.invoiceReference);
        await dialog.getByLabel('Product', { exact: true }).selectOption(await chooseRemProduct(dialog.getByLabel('Product', { exact: true })));
        await dialog.getByLabel('Quantity', { exact: true }).fill('2');
        await dialog.getByLabel('Unit cost', { exact: true }).fill('10.00');
        await dialog.getByRole('button', { name: /save draft/i }).click();
        await expect(dialog.getByRole('alert'), 'REMEDIATION ACCESSIBILITY RED: duplicate supplier invoice reference must fail in an accessible alert.').toContainText(/duplicate|already exists|unique/i);
        await expect(page.locator('tr').filter({ hasText: runtime.invoiceReference })).toHaveCount(1);
    });

    test('US-012 creates a source-linked supplier return, submits it, independently approves it, and opens its print preview', async ({ page, browser }) => {
        test.setTimeout(120_000);
        observe(page, diagnostics, 'US-012-requester');
        await login(page, ACTORS.requester);
        await page.goto('/purchasing/returns', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: /new supplier return/i }).click();
        const dialog = page.getByRole('dialog');
        const invoiceSelect = dialog.getByLabel('Approved purchase invoice', { exact: true });
        await invoiceSelect.selectOption(await optionValue(invoiceSelect, runtime.invoiceNumber ?? runtime.invoiceReference, 'approved REM receipt invoice must be selectable as return source'));
        const reasonSelect = dialog.getByLabel('Return reason', { exact: true });
        await reasonSelect.selectOption(await optionValue(reasonSelect, 'REM-', 'seeded active REM supplier-return reason must be selectable through the UI'));
        const quantity = dialog.getByLabel('Return quantity', { exact: true }).first();
        await expect(quantity, 'REMEDIATION UI GAP: a source invoice with received stock must expose a returnable line.').toBeVisible();
        await quantity.fill('1');
        await dialog.getByRole('button', { name: /save draft/i }).click();
        const row = page.locator('tr').filter({ hasText: runtime.invoiceNumber ?? runtime.invoiceReference }).first();
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: 'Submit', exact: true }).click();

        const reviewerContext = await browser.newContext({ locale: 'en-US' });
        const reviewer = await reviewerContext.newPage();
        observe(reviewer, diagnostics, 'US-012-approver');
        await login(reviewer, ACTORS.approver);
        await reviewer.goto('/purchasing/returns', { waitUntil: 'domcontentloaded' });
        const submitted = reviewer.locator('tr').filter({ hasText: runtime.invoiceNumber ?? runtime.invoiceReference }).first();
        await expect(submitted.getByRole('button', { name: /approve & post/i }), 'REMEDIATION SEPARATION GAP: supplier-return posting needs distinct approval.').toBeVisible();
        await submitted.getByRole('button', { name: /approve & post/i }).click();
        await expect(submitted).toContainText(/approved/i);
        const detail = submitted.getByRole('link').first();
        await detail.click();
        const print = reviewer.getByRole('link', { name: 'Print', exact: true });
        await expect(print, 'REMEDIATION PRINT GAP: approved supplier return must have a print preview.').toBeVisible();
        const printPagePromise = reviewer.waitForEvent('popup');
        await print.click();
        const printPage = await printPagePromise;
        await printPage.waitForLoadState('domcontentloaded');
        await expect(printPage.locator('body')).toContainText(/supplier return/i);
        await printPage.close();
        await closeContext(reviewerContext);
    });

    test('US-013 exports only the scoped REM inventory balances from the visible workspace', async ({ page }) => {
        observe(page, diagnostics, 'US-013-export');
        await login(page, ACTORS.approver);
        await page.goto('/inventory/balances', { waitUntil: 'domcontentloaded' });
        const store = page.getByLabel('Store', { exact: true });
        await store.selectOption(await chooseRemStore(store));
        await page.getByRole('button', { name: 'Apply', exact: true }).click();
        const exportLink = page.getByRole('link', { name: /export balances/i });
        await expect(exportLink, 'REMEDIATION EXPORT GAP: authorized scoped actor needs visible inventory export.').toBeVisible();
        const download = page.waitForEvent('download');
        await exportLink.click();
        const file = await download;
        expect(await file.suggestedFilename()).toMatch(/^inventory-balances-.*\.csv$/);
        const path = await file.path();
        expect(path, 'REMEDIATION EXPORT GAP: Playwright must retain the scoped CSV download for verification.').toBeTruthy();
        const content = await readFile(path, 'utf8');
        expect(content, 'REMEDIATION EXPORT GAP: scoped inventory export must contain data rows.').toContain('REM-WAREHOUSE');
        expect(content, 'REMEDIATION SCOPE GAP: scoped export must not leak alternate-branch stock.').not.toContain('REM-ALT-SALES');
    });

    test('US-014 creates, submits, independently approves, dispatches, and receives a dynamic REM store transfer', async ({ page, browser }) => {
        test.setTimeout(150_000);
        observe(page, diagnostics, 'US-014-warehouse');
        await login(page, ACTORS.warehouse);
        await page.goto('/inventory/transfers/create', { waitUntil: 'domcontentloaded' });
        await page.locator('#transfer-source').selectOption(await chooseRemStore(page.locator('#transfer-source'), 'REM-WAREHOUSE'));
        await page.locator('#transfer-destination').selectOption(await chooseRemStore(page.locator('#transfer-destination'), 'REM-SALES'));
        await page.locator('#transfer-product').selectOption(await chooseRemProduct(page.locator('#transfer-product')));
        await page.locator('#transfer-quantity').fill('1');
        await page.locator('#transfer-reason').fill('remediation-ui-transfer');
        await page.getByRole('button', { name: /save draft/i }).click();
        const draft = page.locator('[data-transfer-row]').filter({ hasText: 'REM-NORMAL-001' }).first();
        await expect(draft).toBeVisible();
        runtime.transferNumber = (await draft.locator('strong').innerText()).trim();
        await draft.getByRole('button', { name: 'Submit', exact: true }).click();

        const approverContext = await browser.newContext({ locale: 'en-US' });
        const approver = await approverContext.newPage();
        observe(approver, diagnostics, 'US-014-approver');
        await login(approver, ACTORS.approver);
        await approver.goto('/inventory/transfers', { waitUntil: 'domcontentloaded' });
        const submitted = approver.locator('[data-transfer-row]').filter({ hasText: runtime.transferNumber }).first();
        await expect(submitted.getByRole('button', { name: 'Approve', exact: true }), 'REMEDIATION SEPARATION GAP: transfer requires a distinct approver.').toBeVisible();
        await submitted.getByRole('button', { name: 'Approve', exact: true }).click();
        await closeContext(approverContext);

        await page.goto('/inventory/transfers', { waitUntil: 'domcontentloaded' });
        const approved = page.locator('[data-transfer-row]').filter({ hasText: runtime.transferNumber }).first();
        await expect(approved.getByRole('button', { name: 'Dispatch', exact: true }), 'REMEDIATION UI GAP: approved transfer requires a visible dispatch action.').toBeVisible();
        await approved.getByRole('button', { name: 'Dispatch', exact: true }).click();

        const receiverContext = await browser.newContext({ locale: 'en-US' });
        const receiver = await receiverContext.newPage();
        observe(receiver, diagnostics, 'US-014-destination-receiver');
        await login(receiver, ACTORS.receiver);
        await receiver.goto('/inventory/transfers', { waitUntil: 'domcontentloaded' });
        const inTransit = receiver.locator('[data-transfer-row]').filter({ hasText: runtime.transferNumber }).first();
        await expect(inTransit.getByRole('button', { name: 'Record receipt', exact: true }), 'REMEDIATION SCOPE RED: a distinct REM-SALES destination receiver must be able to receive an in-transit transfer.').toBeVisible();
        await inTransit.getByRole('button', { name: 'Record receipt', exact: true }).click();
        await expect(inTransit).toContainText(/received/i);
        await closeContext(receiverContext);
    });

    test('US-015 creates an adjustment, submits it, independently posts it, and reverses it through visible controls', async ({ page, browser }) => {
        test.setTimeout(150_000);
        observe(page, diagnostics, 'US-015-warehouse');
        await login(page, ACTORS.warehouse);
        await page.goto('/inventory/adjustments/create', { waitUntil: 'domcontentloaded' });
        await page.locator('#adjustment-store').selectOption(await chooseRemStore(page.locator('#adjustment-store'), 'REM-WAREHOUSE'));
        await page.locator('#adjustment-type').selectOption('entry');
        await page.locator('#adjustment-reason').fill('remediation-ui-adjustment');
        await page.locator('#adjustment-product').selectOption(await chooseRemProduct(page.locator('#adjustment-product')));
        await page.locator('#adjustment-quantity').fill('1');
        await page.locator('#adjustment-cost').fill('10.00');
        await page.getByRole('button', { name: /save draft/i }).click();
        const draft = page.locator('section[data-guide="inventory-adjustments"] > div > div').filter({ hasText: 'remediation-ui-adjustment' }).first();
        await expect(draft).toBeVisible();
        runtime.adjustmentNumber = (await draft.locator('strong').innerText()).trim();
        await draft.getByRole('button', { name: 'Submit', exact: true }).click();

        const approverContext = await browser.newContext({ locale: 'en-US' });
        const approver = await approverContext.newPage();
        observe(approver, diagnostics, 'US-015-approver');
        await login(approver, ACTORS.approver);
        await approver.goto('/inventory/adjustments', { waitUntil: 'domcontentloaded' });
        const submitted = approver.locator('section[data-guide="inventory-adjustments"] > div > div').filter({ hasText: runtime.adjustmentNumber }).first();
        await expect(submitted.getByRole('button', { name: /approve and post/i }), 'REMEDIATION SEPARATION GAP: adjustment posting must be approved by a distinct actor.').toBeVisible();
        await submitted.getByRole('button', { name: /approve and post/i }).click();
        await expect(submitted.getByRole('button', { name: 'Reverse', exact: true }), 'REMEDIATION IMMUTABILITY GAP: approved adjustment must offer a corrective reversal, not editing.').toBeVisible();
        await submitted.getByRole('button', { name: 'Reverse', exact: true }).click();
        await expect(approver.locator('section[data-guide="inventory-adjustments"]')).toContainText(/reversal|reversed/i);
        await closeContext(approverContext);
    });

    test('US-016 assigns a stock count to a distinct counter, permits a separate cashier sale, then has a manager reconcile visibly', async ({ page, browser }) => {
        test.setTimeout(180_000);
        observe(page, diagnostics, 'US-016-manager');
        await login(page, ACTORS.approver);
        await page.goto('/inventory/counts/create', { waitUntil: 'domcontentloaded' });
        await page.locator('#count-store').selectOption(await chooseRemStore(page.locator('#count-store'), 'REM-WAREHOUSE'));
        const assigned = page.locator('#count-assigned');
        await assigned.selectOption(await optionValue(assigned, 'Rem Counter', 'a distinct scoped Rem Counter actor must be assignable through the count UI'));
        await page.getByRole('checkbox', { name: /REM-NORMAL-001/i }).check();
        await page.getByRole('button', { name: /create count/i }).click();
        const count = page.locator('section').filter({ hasText: 'REM-WAREHOUSE' }).filter({ hasText: /count|جرد/i }).first();
        runtime.countNumber = (await count.locator('strong').first().innerText()).trim();
        expect(runtime.countNumber, 'REMEDIATION UI GAP: the created count must receive a visible number.').toBeTruthy();

        const counterContext = await browser.newContext({ locale: 'en-US' });
        const counter = await counterContext.newPage();
        observe(counter, diagnostics, 'US-016-counter');
        await login(counter, ACTORS.counter);
        await counter.goto('/inventory/counts', { waitUntil: 'domcontentloaded' });
        const assignedCount = counter.locator('section').filter({ hasText: runtime.countNumber }).first();
        await expect(assignedCount.getByRole('link', { name: /enter count/i }), 'REMEDIATION SCOPE GAP: only assigned counter needs entry UI.').toBeVisible();
        await assignedCount.getByRole('link', { name: /enter count/i }).click();
        await counter.locator('input[name^="counted_quantities"]').first().fill('1');
        await counter.getByRole('button', { name: /save count/i }).click();
        await counter.getByRole('button', { name: 'Submit', exact: true }).click();
        await closeContext(counterContext);

        // The separate retail sale is intentionally made after count entry and
        // before reconciliation: reconciliation must show the source variance,
        // not silently hide an intervening sale.
        const cashierContext = await browser.newContext({ locale: 'en-US' });
        const cashier = await cashierContext.newPage();
        observe(cashier, diagnostics, 'US-016-cashier-sale');
        await login(cashier, ACTORS.cashier);
        await cashier.goto('/pos?product_q=REM-NORMAL-001', { waitUntil: 'domcontentloaded' });
        const card = cashier.locator('article[data-product-family]').filter({ hasText: 'REM-NORMAL-001' }).first();
        await expect(card, 'REMEDIATION FIXTURE GAP: separate cashier must have an active shift and sellable REM stock.').toBeVisible();
        await card.getByRole('button', { name: /add to cart/i }).click();
        const payment = cashier.locator('[data-guide="pos-payment-form"]');
        await payment.getByLabel('Cash received', { exact: true }).fill('500.00');
        await payment.getByRole('button', { name: /settle and complete sale/i }).click();
        await expect(cashier).toHaveURL(/\/sales\/\d+$/);
        await closeContext(cashierContext);

        await page.goto('/inventory/counts', { waitUntil: 'domcontentloaded' });
        const submitted = page.locator('section').filter({ hasText: runtime.countNumber }).first();
        await expect(submitted.getByRole('button', { name: /reconcile and approve/i }), 'REMEDIATION UI GAP: manager must see a separate reconciliation action after counter submission.').toBeVisible();
        await submitted.getByRole('button', { name: /reconcile and approve/i }).click();
        await expect(submitted).toContainText(/approved|reconciled/i);
    });
});
