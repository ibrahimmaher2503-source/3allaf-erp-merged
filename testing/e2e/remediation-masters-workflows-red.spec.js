import { expect, test } from '@playwright/test';

// This specification is intentionally isolated from the general demo suite.
// It may mutate only the explicitly named disposable remediation fixture.
const REMEDIATION_DATABASE = 'toyjoy_phase1_remediation_20260818';
const REMEDIATION_BASE_URL = process.env.PLAYWRIGHT_BASE_URL ?? '';
const REMEDIATION_PASSWORD = process.env.REMEDIATION_FIXTURE_PASSWORD ?? '';
const REMEDIATION_DATABASE_CONFIGURED = process.env.PLAYWRIGHT_REMEDIATION_DATABASE === REMEDIATION_DATABASE;
const LOOPBACK_BASE_URL = /^https?:\/\/(?:127\.0\.0\.1|localhost|\[::1\])(?::\d+)?(?:\/|$)/i.test(REMEDIATION_BASE_URL);

const ACTORS = Object.freeze({
    administrator: 'rem-admin',
    importReviewer: 'rem-reviewer',
    pricingRequester: 'rem-pricing',
});

const workflow = {
    productCode: `REM-UI-MASTER-${Date.now()}`,
    productName: 'Remediation master workflow product',
    customerPhone: `010${String(Date.now()).slice(-8)}`,
    supplierCode: `REM-UI-SUP-${String(Date.now()).slice(-8)}`,
    priceListCode: `REM-UI-PRICE-${String(Date.now()).slice(-8)}`,
};

function fixtureGate() {
    if (!REMEDIATION_PASSWORD) return 'REMEDIATION_FIXTURE_PASSWORD is required for remediation-only actors.';
    if (!REMEDIATION_DATABASE_CONFIGURED) return `PLAYWRIGHT_REMEDIATION_DATABASE must equal ${REMEDIATION_DATABASE}.`;
    if (!LOOPBACK_BASE_URL) return 'PLAYWRIGHT_BASE_URL must be an explicit loopback remediation server.';

    return null;
}

function attachDiagnostics(page, diagnostics) {
    page.on('pageerror', (error) => diagnostics.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') diagnostics.push(`console: ${message.text()}`);
    });
}

async function login(page, username) {
    await page.context().clearCookies();
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(REMEDIATION_PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);
}

async function selectOptionContaining(select, text) {
    const option = select.locator('option').filter({ hasText: text }).first();
    await expect(option).toBeAttached();
    const value = await option.getAttribute('value');
    expect(value, `Expected a ${text} option`).toBeTruthy();
    await select.selectOption(value);
}

async function setLocale(page, locale) {
    const cookies = await page.context().cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    expect(xsrf, 'Locale change requires the authenticated XSRF token').toBeTruthy();
    const response = await page.request.post('/locale', {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
        form: { locale },
    });
    expect(response.ok(), `Locale ${locale} must be accepted`).toBeTruthy();
    await page.reload({ waitUntil: 'domcontentloaded' });
}

async function assertResponsiveArabic(page, route) {
    await page.setViewportSize({ width: 390, height: 844 });
    await setLocale(page, 'ar');
    await page.goto(route, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBeTruthy();
    await setLocale(page, 'en');
    await page.setViewportSize({ width: 1280, height: 900 });
}

test.use({ headless: false });

test.describe.serial('REM master workflows — RED contracts for US-001/003/004/005/006/007/009', () => {
    test.skip(({ browserName }) => browserName !== 'chromium', 'This remediation contract is authorized for headed Chromium only.');
    // No authenticated application route exposes the exact database name; the
    // explicit operator environment gate above is intentionally retained
    // instead of inventing a diagnostic endpoint that would disclose it.
    test.skip(fixtureGate() !== null, fixtureGate() ?? '');

    test('US-001 governs settings through preview, validation, persistence, and desktop/mobile LTR/RTL', async ({ page }) => {
        test.setTimeout(120_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.administrator);
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });

        await page.getByLabel('Company Code', { exact: true }).fill('REM-FIXTURE-COMPANY');
        await page.getByRole('button', { name: 'Save Company Baseline', exact: true }).click();
        const preview = page.getByRole('dialog').filter({ hasText: 'Review company baseline' });
        await expect(preview).toBeVisible();
        await expect(preview.getByText('REM-FIXTURE-COMPANY', { exact: true })).toBeVisible();
        await preview.getByRole('button', { name: 'Confirm and save', exact: true }).click();
        await expect(page.getByText('Company settings saved successfully.', { exact: true })).toBeVisible();
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue('REM-FIXTURE-COMPANY');
        await assertResponsiveArabic(page, '/admin/settings');
        expect(diagnostics).toEqual([]);
    });

    test('US-003 creates one consented customer profile and rejects a duplicate phone through the visible form', async ({ page }) => {
        test.setTimeout(120_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.administrator);
        await page.goto('/customers/create', { waitUntil: 'domcontentloaded' });
        const firstIdempotencyKey = page.locator('input[name="idempotency_key"]');
        await expect(firstIdempotencyKey).toHaveAttribute('type', 'hidden');
        await expect(firstIdempotencyKey).toHaveValue(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
        await page.getByLabel('Primary phone', { exact: true }).fill(workflow.customerPhone);
        await page.getByLabel('Arabic name', { exact: true }).fill('عميل المعالجة');
        await page.getByLabel('English name', { exact: true }).fill('Remediation Customer');
        await page.getByLabel('Consent purpose', { exact: true }).selectOption('service');
        await page.getByLabel('Consent status', { exact: true }).selectOption('granted');
        await page.getByRole('button', { name: 'Create customer profile', exact: true }).click();
        await expect(page).toHaveURL(/\/customers\/\d+$/);
        await expect(page.getByText(workflow.customerPhone, { exact: true })).toBeVisible();

        await page.goto('/customers/create', { waitUntil: 'domcontentloaded' });
        const duplicateIdempotencyKey = page.locator('input[name="idempotency_key"]');
        const duplicateKeyValue = await duplicateIdempotencyKey.inputValue();
        expect(duplicateKeyValue).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
        await page.getByLabel('Primary phone', { exact: true }).fill(workflow.customerPhone);
        await page.getByLabel('Arabic name', { exact: true }).fill('عميل مكرر');
        await page.getByLabel('English name', { exact: true }).fill('Duplicate remediation customer');
        await page.getByLabel('Consent purpose', { exact: true }).selectOption('service');
        await page.getByLabel('Consent status', { exact: true }).selectOption('granted');
        await page.getByRole('button', { name: 'Create customer profile', exact: true }).click();
        await expect(page.getByText('A customer already exists for this phone number. Review the existing profile instead of creating a duplicate.', { exact: true })).toBeVisible();
        await expect(page.locator('input[name="idempotency_key"]')).toHaveValue(duplicateKeyValue);
        await assertResponsiveArabic(page, '/customers');
        expect(diagnostics).toEqual([]);
    });

    test('US-004 stages a mixed-validity import through the real guided UI, exposes rejected-row export, and requires mapping', async ({ page }) => {
        test.setTimeout(150_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.administrator);
        await page.goto('/catalog/products/import', { waitUntil: 'domcontentloaded' });
        const importSection = page.locator('[data-guide="import-upload-section"]');
        await expect(importSection).toBeVisible();
        const csv = [
            'item_code,name_ar,name_en,category_code',
            `REM-UI-IMPORT-${Date.now()},منتج استيراد,Remediation imported product,REM-TOYS`,
            'REM-UI-IMPORT-INVALID,منتج غير صالح,Invalid import product,UNKNOWN-CATEGORY',
        ].join('\n');
        await importSection.getByLabel('Excel or CSV file', { exact: true }).setInputFiles({ name: 'remediation-product-import.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) });
        await importSection.getByLabel('Import mode', { exact: true }).selectOption('create_only');
        await importSection.locator('[data-guide="import-stage-button"]').click();
        await expect(page.getByText('File staged. Review all rows before approval.', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Review', exact: true }).first().click();
        await expect(page.locator('[data-guide="import-review-section"]')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Download errors', exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Approve valid rows', exact: true })).toBeDisabled();

        // RED: StageProductImportAction supports mapping, yet the real UI has
        // no visible mapping step. This assertion must fail until the user can
        // map staged columns before review/approval.
        await expect(page.getByRole('button', { name: 'Map columns', exact: true })).toBeVisible();
        expect(diagnostics).toEqual([]);
    });

    test('US-004 gives a distinct scoped remediation reviewer an importer handoff queue and approval/export controls', async ({ page }) => {
        test.setTimeout(90_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.importReviewer);
        const response = await page.goto('/catalog/products/import', { waitUntil: 'domcontentloaded' });

        // RED fixture/production contract: rem-reviewer is intentionally
        // distinct from rem-admin. The eventual review queue must be scoped
        // and expose the importer's staged batch, rather than silently
        // elevating the importer or relying on a same-identity approval.
        expect(response?.status()).toBe(200);
        await expect(page.locator('[data-guide="import-review-queue"]')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Approve valid rows', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Download errors', exact: true })).toBeVisible();
        expect(diagnostics).toEqual([]);
    });

    test('US-005 creates a fresh dynamic Standard product card while exposing the supported product-type choices', async ({ page }) => {
        test.setTimeout(150_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.administrator);

        await page.goto('/catalog/products/create', { waitUntil: 'domcontentloaded' });
        const productType = page.getByLabel('Product type', { exact: true });
        await expect(productType.locator('option[value="standard"]')).toBeAttached();
        await expect(productType.locator('option[value="composite"]')).toBeAttached();
        await expect(productType.locator('option[value="service"]')).toBeAttached();
        await page.getByLabel('Immutable item code', { exact: true }).fill(workflow.productCode);
        await page.getByLabel('Arabic product name', { exact: true }).fill('منتج تسعير المعالجة');
        await page.getByLabel('English product name', { exact: true }).fill(workflow.productName);
        await page.getByLabel('Category', { exact: true }).selectOption({ index: 1 });
        await productType.selectOption('standard');
        await page.getByRole('button', { name: 'Create product card', exact: true }).click();
        await expect(page).toHaveURL(/\/catalog\/products\/\d+$/);
        await expect(page.getByText('Product card saved successfully.', { exact: true })).toBeVisible();
        await expect(page.getByText(workflow.productCode, { exact: true })).toBeVisible();
        await assertResponsiveArabic(page, '/catalog/products/create');
        expect(diagnostics).toEqual([]);
    });

    test('US-006 and US-007 submit a bounded price proposal under one actor, approve under another, then require a real label queue/preview', async ({ page, browser }) => {
        test.setTimeout(150_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        const requesterContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1280, height: 900 } });
        const requester = await requesterContext.newPage();
        attachDiagnostics(requester, diagnostics);
        try {
            await login(requester, ACTORS.pricingRequester);
            await requester.goto('/pricing', { waitUntil: 'domcontentloaded' });
            await requester.getByRole('button', { name: 'New proposal', exact: true }).click();
            const dialog = requester.getByRole('dialog');
            await dialog.getByLabel('Price list code', { exact: true }).fill(workflow.priceListCode);
            await dialog.getByLabel('Price list name', { exact: true }).fill('Remediation UI prices');
            await dialog.getByLabel('Price list name (Arabic)', { exact: true }).fill('أسعار واجهة المعالجة');
            await selectOptionContaining(dialog.getByLabel('Product', { exact: true }), workflow.productCode);
            await selectOptionContaining(dialog.getByLabel('Store', { exact: true }), 'REM-SALES');
            await dialog.getByLabel('Proposed amount', { exact: true }).fill('37.500');
            await dialog.getByLabel('Source reference', { exact: true }).fill(`UI-${workflow.productCode}`);
            await dialog.getByLabel('Proposal reason / audit note', { exact: true }).fill('Independent approval required; cost is not exposed or coupled.');
            await dialog.getByRole('button', { name: 'Save draft', exact: true }).click();
            const row = requester.locator('tr').filter({ hasText: workflow.priceListCode }).first();
            await expect(row).toContainText('draft');
            await row.getByRole('button', { name: 'Submit', exact: true }).click();
            await expect(requester.getByText('Price proposal submitted for approval.', { exact: true })).toBeVisible();
        } finally {
            await requesterContext.close();
        }

        await login(page, ACTORS.administrator);
        await page.goto('/pricing/approvals', { waitUntil: 'domcontentloaded' });
        const approvalRow = page.locator('tr').filter({ hasText: workflow.priceListCode }).first();
        await expect(approvalRow).toContainText('submitted');
        await approvalRow.getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(page.getByText('Price version approved and effective where its date allows.', { exact: true })).toBeVisible();
        await expect(approvalRow).toContainText('approved');

        await page.goto('/pricing/labels', { waitUntil: 'domcontentloaded' });
        // RED: a capability-boundary page is not a label workflow. This must
        // become a queueable, printer-selectable preview before US-007 passes.
        await expect(page.getByRole('button', { name: 'Generate label queue', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Print preview', exact: true })).toBeVisible();
        await assertResponsiveArabic(page, '/pricing/labels');
        expect(diagnostics).toEqual([]);
    });

    test('US-009 edits supplier data, shows product/purchase history, and exposes no unsafe delete path', async ({ page }) => {
        test.setTimeout(120_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.administrator);
        await page.goto('/catalog/suppliers', { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Add supplier', exact: true }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Supplier code', { exact: true }).fill(workflow.supplierCode);
        await dialog.getByLabel('Arabic name', { exact: true }).fill('مورد واجهة المعالجة');
        await dialog.getByLabel('English name', { exact: true }).fill('Remediation UI Supplier');
        await dialog.getByLabel('Contact name', { exact: true }).fill('Remediation contact');
        await dialog.getByRole('button', { name: 'Create Supplier', exact: true }).click();
        await expect(dialog).toBeHidden();
        const row = page.locator('tr').filter({ hasText: workflow.supplierCode }).first();
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: 'Edit supplier master', exact: true }).click();
        await dialog.getByLabel('Payment terms text', { exact: true }).fill('30 days');
        await dialog.getByRole('button', { name: 'Update Supplier', exact: true }).click();
        await expect(row).toContainText('30 days');
        await row.getByRole('button', { name: 'View supplier details & history', exact: true }).click();
        await dialog.getByRole('button', { name: /^Linked Products/ }).click();
        await expect(dialog.getByText('Products supplied by this supplier', { exact: true })).toBeVisible();
        await dialog.getByRole('button', { name: 'Purchase History', exact: true }).click();
        await expect(dialog.getByText('Purchase History', { exact: true })).toBeVisible();
        await dialog.getByRole('button', { name: 'Close', exact: true }).click();
        await expect(row.getByRole('button', { name: /delete supplier/i })).toHaveCount(0);
        await assertResponsiveArabic(page, '/catalog/suppliers');
        expect(diagnostics).toEqual([]);
    });
});
