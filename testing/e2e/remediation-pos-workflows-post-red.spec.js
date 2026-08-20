import { expect, test } from '@playwright/test';

// This file is deliberately independent of the demo browser fixtures. It is
// executable only when the caller explicitly identifies the disposable
// remediation database and a loopback server; it never relies on demo-auth or
// direct database setup to reach an otherwise unavailable workflow.
const REMEDIATION_DATABASE = 'toyjoy_phase1_remediation_20260818';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL ?? '';
const fixturePassword = process.env.REMEDIATION_FIXTURE_PASSWORD;
const remediationDatabaseConfigured = process.env.PLAYWRIGHT_REMEDIATION_DATABASE === REMEDIATION_DATABASE;
const loopbackBaseUrl = /^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?(?:\/|$)/i.test(baseUrl);

const ACTORS = Object.freeze({
    cashier: 'rem-cashier',
    closingCashier: 'rem-close-cashier',
    approver: 'rem-admin',
    crossBranch: 'rem-cross-branch-denied',
});

function remediationGateReason() {
    if (!fixturePassword) return 'REMEDIATION_FIXTURE_PASSWORD is required for the isolated REM actors.';
    if (!loopbackBaseUrl) return 'PLAYWRIGHT_BASE_URL must be an explicit loopback remediation server.';
    if (!remediationDatabaseConfigured) return `PLAYWRIGHT_REMEDIATION_DATABASE must equal ${REMEDIATION_DATABASE}.`;

    return null;
}

async function login(page, username) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(fixturePassword);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: /log in/i }).click(),
    ]);
}

function observe(page, findings, workflow) {
    page.on('pageerror', (error) => findings.push({ workflow, kind: 'pageerror', url: page.url(), message: error.message }));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            findings.push({ workflow, kind: 'console', url: page.url(), message: message.text() });
        }
    });
}

async function firstOptionContaining(select, text) {
    const option = select.locator('option').filter({ hasText: text }).first();
    await expect(option, `Fixture prerequisite missing: an option containing ${text} must be available through the UI.`).toHaveCount(1);
    return option.getAttribute('value');
}

async function addProduct(page, itemCode) {
    const productNames = {
        'REM-NORMAL-001': 'Remediation Normal Product',
        'REM-OPEN-PRICE-001': 'Remediation Open Price Product',
    };
    const heading = page.getByRole('heading', { name: productNames[itemCode], exact: true });
    const card = heading.locator('xpath=ancestor::article[1]');
    await expect(card, `Fixture prerequisite missing: ${itemCode} must be priced, stocked, and visible to this scoped cashier.`).toBeVisible();
    await card.getByRole('button', { name: 'Add to cart', exact: true }).click();
    return page.locator('[data-cart-line]').filter({ hasText: itemCode });
}

function checkoutForm(page) {
    return page.locator('form[action$="/pos/checkout"]:visible');
}

async function checkoutCash(page) {
    const payment = checkoutForm(page);
    await expect(payment, 'REMEDIATION GAP: POS must provide the authorized cash settlement form.').toBeVisible();
    await payment.getByLabel('Cash received', { exact: true }).fill('500.00');
    await Promise.all([
        page.waitForURL(/\/sales\/\d+$/),
        payment.getByRole('button', { name: /Complete sale/ }).click(),
    ]);
    return page.url();
}

test.use({ locale: 'en-US', viewport: { width: 1280, height: 900 }, trace: 'retain-on-failure', screenshot: 'only-on-failure' });

test.describe.serial('REM legitimate POS remediation workflows', () => {
    test.skip(remediationGateReason() !== null, remediationGateReason() ?? 'isolated remediation runtime required');

    const findings = [];
    let returnUrl = '';

    test.afterAll(() => {
        expect(findings, `Unexpected console/page errors: ${JSON.stringify(findings, null, 2)}`).toEqual([]);
    });

    test('US-017 completes a normal REM branch sale through the cashier UI', async ({ page }) => {
        observe(page, findings, 'US-017');
        await login(page, ACTORS.cashier);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('REM-SALES', { exact: false }), 'REMEDIATION GAP: the cashier POS context must identify REM-SALES.').toBeVisible();
        await expect(await addProduct(page, 'REM-NORMAL-001')).toBeVisible();
        await checkoutCash(page);
        await expect(page.getByText('Payments', { exact: true }).last()).toBeVisible();
    });

    test('US-008 and US-018 keep open-price review, tax/discount rules, and settlement in visible scoped UI', async ({ page, browser }) => {
        test.setTimeout(120_000);
        observe(page, findings, 'US-008/018-requester');
        await login(page, ACTORS.cashier);
        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        const line = await addProduct(page, 'REM-OPEN-PRICE-001');
        await line.getByText('Adjust price or discount', { exact: true }).click();
        await expect(line.getByLabel('Open price amount', { exact: true }), 'Fixture prerequisite missing: REM open-price product must expose its approved 80.0000–120.0000 policy through the scoped UI.').toBeVisible();
        await line.getByLabel('Open price amount', { exact: true }).fill('110.0000');
        await line.getByLabel('Required reason', { exact: true }).fill('REM browser open-price exception');
        await line.getByRole('button', { name: 'Set open price', exact: true }).click();
        await expect(page.getByText(/approval is pending/i), 'REMEDIATION GAP: a non-reference open price must create a separate approval.').toBeVisible();

        const approverContext = await browser.newContext({ locale: 'en-US' });
        const approver = await approverContext.newPage();
        observe(approver, findings, 'US-008/018-approver');
        await login(approver, ACTORS.approver);
        await approver.goto('/approvals', { waitUntil: 'domcontentloaded' });
        const openPriceApproval = approver.locator('tr').filter({ hasText: 'approve_open_price' }).first();
        await expect(openPriceApproval, 'REMEDIATION GAP: the requester approval must be visible to a distinct REM approver.').toBeVisible();
        await openPriceApproval.getByRole('button', { name: 'Review', exact: true }).click();
        await approver.getByRole('button', { name: 'Approve', exact: true }).click();
        await approverContext.close();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText(/approval is recorded/i), 'REMEDIATION GAP: approved open price must survive the cashier refresh.').toBeVisible();
        const refreshedLine = page.locator('[data-cart-line]').filter({ hasText: 'REM-OPEN-PRICE-001' });
        await refreshedLine.getByText('Adjust price or discount', { exact: true }).click();
        await refreshedLine.getByLabel('Discount amount', { exact: true }).fill('5.00');
        await refreshedLine.getByLabel('Reason', { exact: true }).fill('REM browser discount exception');
        await refreshedLine.getByRole('button', { name: 'Apply discount', exact: true }).click();
        await expect(page.getByText(/approval is pending/i), 'REMEDIATION GAP: an exception discount must remain pending for a distinct approver.').toBeVisible();

        const discountContext = await browser.newContext({ locale: 'en-US' });
        const discountApprover = await discountContext.newPage();
        observe(discountApprover, findings, 'US-008/018-discount-approver');
        await login(discountApprover, ACTORS.approver);
        await discountApprover.goto('/approvals', { waitUntil: 'domcontentloaded' });
        const discountApproval = discountApprover.locator('tr').filter({ hasText: 'approve_discount' }).first();
        await expect(discountApproval).toBeVisible();
        await discountApproval.getByRole('button', { name: 'Review', exact: true }).click();
        await discountApprover.getByRole('button', { name: 'Approve', exact: true }).click();
        await discountContext.close();

        await page.goto('/pos', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Add tax', exact: true }).click();
        await expect(page.getByText('Tax', { exact: true }).last()).toBeVisible();
        await checkoutCash(page);
        await expect(page.getByRole('link', { name: 'Thermal receipt', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'A4 invoice', exact: true })).toBeVisible();
    });

    test('US-019 issues a price-free gift receipt for a visible completed REM sale', async ({ page }) => {
        observe(page, findings, 'US-019');
        await login(page, ACTORS.cashier);
        await page.goto('/gift-receipts', { waitUntil: 'domcontentloaded' });
        const saleSelect = page.getByLabel('Completed sale', { exact: true });
        const sourceSaleId = await firstOptionContaining(saleSelect, 'REM-SAL-');
        await saleSelect.selectOption(sourceSaleId);
        await page.getByRole('button', { name: 'Load eligible lines', exact: true }).click();
        await page.getByRole('button', { name: 'Issue price-free Gift Receipt', exact: true }).click();
        await expect(page).toHaveURL(/\/gift-receipts\/\d+\/print/);
        const printed = await page.locator('body').innerText();
        expect(printed).toContain('Price-free return reference');
        expect(printed).not.toContain('25.00');
        expect(printed).not.toContain('110.00');
    });

    test('US-020 creates, submits, independently approves, and completes a source-linked return', async ({ page, browser }) => {
        observe(page, findings, 'US-020-requester');
        await login(page, ACTORS.cashier);
        await page.goto('/returns', { waitUntil: 'domcontentloaded' });
        const sourceSale = page.getByLabel('Source completed sale', { exact: true });
        await sourceSale.selectOption(await firstOptionContaining(sourceSale, 'REM-SAL-'));
        const sourceLine = page.getByLabel('Source line', { exact: true });
        await sourceLine.selectOption(await firstOptionContaining(sourceLine, 'Remediation Normal Product'));
        await page.getByLabel('Settlement', { exact: true }).selectOption('cash_refund');
        await page.getByLabel('Inspected condition', { exact: true }).selectOption('sellable');
        await page.getByLabel('Stock disposition', { exact: true }).selectOption('restock');
        await page.getByLabel('Inspection notes / evidence reference', { exact: true }).fill('REM browser inspected sellable item');
        await page.getByLabel('Reason', { exact: true }).fill('REM browser source-linked return');
        await page.getByRole('button', { name: 'Create draft', exact: true }).click();
        returnUrl = page.url();
        await page.getByRole('button', { name: 'Submit for approval', exact: true }).click();
        await expect(page.getByText('Submitted', { exact: true })).toBeVisible();

        const approverContext = await browser.newContext({ locale: 'en-US' });
        const approver = await approverContext.newPage();
        observe(approver, findings, 'US-020-approver');
        await login(approver, ACTORS.approver);
        await approver.goto(returnUrl, { waitUntil: 'domcontentloaded' });
        await expect(approver.getByRole('button', { name: 'Approve', exact: true }), 'REMEDIATION GAP: only the distinct approver must be able to approve the return.').toBeVisible();
        await approver.getByRole('button', { name: 'Approve', exact: true }).click();
        await approverContext.close();

        await page.goto(returnUrl, { waitUntil: 'domcontentloaded' });
        await page.getByLabel('Settlement payment method', { exact: true }).selectOption({ label: /Remediation cash/i });
        await page.getByRole('button', { name: 'Complete settlement and stock movement', exact: true }).click();
        await expect(page.getByText('Completed', { exact: true })).toBeVisible();
    });

    test('US-021 issues a REM gift card and redeems it through the cashier POS settlement UI', async ({ page, browser }) => {
        observe(page, findings, 'US-021-issuer');
        await login(page, ACTORS.approver);
        await page.goto('/gift-cards', { waitUntil: 'domcontentloaded' });
        await page.getByLabel('Value', { exact: true }).fill('25.00');
        const store = page.getByLabel('Issuing store', { exact: true });
        await store.selectOption(await firstOptionContaining(store, 'REM-SALES'));
        await page.getByRole('button', { name: 'Issue card', exact: true }).click();
        const cardRow = page.locator('tr').filter({ hasText: 'GC-' }).first();
        await expect(cardRow).toBeVisible();
        const cardIdentifier = (await cardRow.locator('td').first().innerText()).trim();

        const cashierContext = await browser.newContext({ locale: 'en-US' });
        const cashier = await cashierContext.newPage();
        observe(cashier, findings, 'US-021-redeemer');
        await login(cashier, ACTORS.cashier);
        await cashier.goto('/pos', { waitUntil: 'domcontentloaded' });
        await addProduct(cashier, 'REM-NORMAL-001');
        const payment = checkoutForm(cashier);
        await payment.getByLabel('Payment method', { exact: true }).selectOption({ label: /gift card/i });
        await payment.getByLabel('Gift Card identifier', { exact: true }).fill(cardIdentifier);
        await payment.getByLabel('Gift Card amount', { exact: true }).fill('25.00');
        await payment.getByRole('button', { name: /Complete sale/ }).click();
        await expect(cashier).toHaveURL(/\/sales\/\d+$/);
        await cashierContext.close();
    });

    test('US-023 redeems a source-linked REM loyalty balance through the scoped UI', async ({ page }) => {
        observe(page, findings, 'US-023');
        await login(page, ACTORS.cashier);
        await page.goto('/customers?mode=loyalty&q=Remediation%20Customer', { waitUntil: 'domcontentloaded' });
        const customer = page.locator('[data-customer-row]').filter({ hasText: 'Remediation Customer' });
        await expect(customer, 'Fixture prerequisite missing: the customer-linked REM source sale must be discoverable to the scoped cashier.').toBeVisible();
        await customer.getByRole('link', { name: 'Open loyalty ledger', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Loyalty ledger', exact: true })).toBeVisible();
        const redemption = page.locator('form[action*="/loyalty/redeem"]');
        await expect(redemption, 'Fixture prerequisite missing: REM cashier needs the authorized source-linked loyalty redemption form.').toBeVisible();
        const approvedSale = redemption.getByLabel('Approved sale', { exact: true });
        await approvedSale.selectOption(await firstOptionContaining(approvedSale, 'REM-SAL-'));
        await redemption.getByLabel('Points', { exact: true }).fill('1');
        await redemption.getByRole('button', { name: 'Record redemption', exact: true }).click();
        await expect(page.getByText('Loyalty redemption recorded against the approved sale.', { exact: true })).toBeVisible();
        await expect(page.locator('tbody').first(), 'The redemption must append an immutable visible ledger entry.').toContainText(/redeem|redemption/i);
    });

    test('US-024 blind-closes only the dedicated REM closing shift and keeps review server-authorized', async ({ page, browser }) => {
        observe(page, findings, 'US-024-cashier');
        await login(page, ACTORS.closingCashier);
        const denied = await page.goto('/pos/shift-variance', { waitUntil: 'domcontentloaded' });
        expect(denied?.status(), 'Cashier must be server-denied the variance review.').toBe(403);
        await page.goto('/pos/shift', { waitUntil: 'domcontentloaded' });
        const preSubmitDom = await page.content();
        expect(preSubmitDom).not.toContain('expected_cash');
        expect(preSubmitDom).not.toContain('cash_variance');
        await page.getByLabel('Counted cash', { exact: true }).fill('100.00');
        await page.getByRole('button', { name: 'Submit count', exact: true }).click();
        await expect(page.getByText(/awaiting review/i)).toBeVisible();

        const approverContext = await browser.newContext({ locale: 'en-US' });
        const approver = await approverContext.newPage();
        observe(approver, findings, 'US-024-approver');
        await login(approver, ACTORS.approver);
        await approver.goto('/pos/shift-variance', { waitUntil: 'domcontentloaded' });
        const submission = approver.locator('tr').filter({ hasText: 'REM-DRAWER-02' }).first();
        await expect(submission, 'REMEDIATION GAP: distinct approver must see only submitted REM closing shift for review.').toBeVisible();
        await submission.getByRole('link', { name: 'Open canonical approval request', exact: true }).click();
        approver.once('dialog', (dialog) => dialog.accept());
        await approver.getByRole('button', { name: 'Approve and close', exact: true }).click();
        await approver.goto('/pos/shift-variance', { waitUntil: 'domcontentloaded' });
        const closed = approver.locator('section').filter({ hasText: 'Closed shifts' }).locator('tr').filter({ hasText: 'REM-DRAWER-02' }).first();
        await expect(closed).toBeVisible();
        await expect(closed.getByRole('link', { name: 'Thermal', exact: true })).toBeVisible();
        await expect(closed.getByRole('link', { name: 'Print A4', exact: true })).toBeVisible();
        await approverContext.close();
    });

    test('REM cross-branch cashier cannot retrieve protected REM workflow sources', async ({ page }) => {
        observe(page, findings, 'scope-denial');
        await login(page, ACTORS.crossBranch);
        const response = await page.goto('/gift-receipts', { waitUntil: 'domcontentloaded' });
        if (response?.status() === 403) {
            // This is an explicit authorization result, not evidence of data
            // scoping. Once the fixture grants this actor view permission,
            // the 200 branch below becomes the scope-enforcement check.
            expect(response.status(), 'Cross-branch actor is explicitly unauthorized for gift-receipt viewing.').toBe(403);
            return;
        }
        expect(response?.status(), 'A view-authorized cross-branch actor must receive the workspace with server-scoped source data.').toBe(200);
        await expect(page.locator('body'), 'Cross-branch actor must not receive REM branch source sale data from the server.').not.toContainText('REM-SAL-');
    });
});
