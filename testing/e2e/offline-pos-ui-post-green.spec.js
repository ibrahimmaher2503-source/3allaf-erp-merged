import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth.js';

const REMEDIATION_DATABASE = 'toyjoy_phase1_remediation_20260818';
const REMEDIATION_PASSWORD = process.env.REMEDIATION_FIXTURE_PASSWORD;
const REMEDIATION_BASE_URL = process.env.PLAYWRIGHT_BASE_URL ?? '';
const REMEDIATION_DATABASE_CONFIGURED = process.env.PLAYWRIGHT_REMEDIATION_DATABASE === REMEDIATION_DATABASE;
const LOOPBACK_BASE_URL = /^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?(?:\/|$)/i.test(REMEDIATION_BASE_URL);

const ACTORS = Object.freeze({
    cashier: 'rem-cashier',
    closingCashier: 'rem-close-cashier',
    pricingInspector: 'rem-admin',
    reviewer: 'rem-reviewer',
});

let acceptedFixture = null;

function fixtureGate() {
    if (!REMEDIATION_PASSWORD) return 'REMEDIATION_FIXTURE_PASSWORD is required for the isolated remediation actors.';
    if (!LOOPBACK_BASE_URL) return 'PLAYWRIGHT_BASE_URL must be an explicit loopback remediation server.';
    if (!REMEDIATION_DATABASE_CONFIGURED) return `PLAYWRIGHT_REMEDIATION_DATABASE must equal ${REMEDIATION_DATABASE}.`;

    return null;
}

function attachDiagnostics(page, diagnostics, allowExpectedForbidden = false) {
    page.on('pageerror', (error) => diagnostics.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        const expectedForbidden = allowExpectedForbidden && /Failed to load resource: the server responded with a status of 403 \(Forbidden\)/.test(message.text());
        if (message.type() === 'error' && !expectedForbidden) diagnostics.push(`console: ${message.text()}`);
    });
}

function uniqueReference(prefix) {
    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function parseMoney(value) {
    const match = value.replace(/,/g, '').match(/\d+(?:\.\d{1,3})?/);
    expect(match, `Expected a numeric amount in ${value}`).not.toBeNull();

    return Number(match[0]);
}

function assertNoForbiddenKeys(value, path = 'record') {
    const forbidden = /cost|margin|wallet|loyalty|customer|expected.?cash|evidence|document|sale.?number/i;
    if (Array.isArray(value)) {
        value.forEach((item, index) => assertNoForbiddenKeys(item, `${path}[${index}]`));

        return;
    }
    if (!value || typeof value !== 'object') return;

    for (const [key, nested] of Object.entries(value)) {
        expect(forbidden.test(key), `Forbidden local queue key at ${path}.${key}`).toBeFalsy();
        assertNoForbiddenKeys(nested, `${path}.${key}`);
    }
}

async function locale(page, value) {
    const cookies = await page.context().cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    expect(xsrf, 'Authenticated locale POST requires the XSRF cookie').toBeTruthy();
    const response = await page.request.post('/locale', {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
        form: { locale: value },
        failOnStatusCode: false,
    });
    expect(response.ok(), `Locale ${value} POST must succeed`).toBeTruthy();
}

async function csrfHeaders(page) {
    const cookies = await page.context().cookies();
    const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
    expect(xsrf, 'Authenticated queue request requires the XSRF cookie').toBeTruthy();

    return { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value), 'X-Requested-With': 'XMLHttpRequest' };
}

async function postForm(page, url, form) {
    return page.request.post(url, {
        headers: await csrfHeaders(page),
        form,
        failOnStatusCode: false,
    });
}

async function discoverApprovedPrice(browser, diagnostics) {
    const context = await browser.newContext({ locale: 'en-US' });
    const page = await context.newPage();
    attachDiagnostics(page, diagnostics);
    await login(page, ACTORS.pricingInspector, REMEDIATION_PASSWORD);
    await page.goto('/pricing', { waitUntil: 'domcontentloaded' });

    const search = page.locator('input[placeholder*="Search list"]').first();
    await search.fill('REM-NORMAL-001');
    const row = page.locator('tr').filter({ hasText: 'REM-NORMAL-001' }).first();
    await expect(row).toBeVisible();
    await expect(row).toContainText(/approved/i);
    const key = await row.getAttribute('wire:key');
    const versionId = key?.match(/^price-version-(\d+)$/)?.[1];
    expect(versionId, 'The approved fixture price must expose its Livewire row key').toBeTruthy();
    const amount = parseMoney(await row.locator('td').nth(2).innerText());
    await context.close();

    return { versionId, amount };
}

async function discoverCashierCheckoutInputs(page) {
    await page.goto('/pos?product_q=REM-NORMAL-001', { waitUntil: 'domcontentloaded' });
    const productCard = page.locator('article[data-product-family]').filter({ hasText: 'REM-NORMAL-001' }).first();
    await expect(productCard).toBeVisible();
    const productId = await productCard.getAttribute('data-product-family');
    expect(productId, 'The visible fixture product card must expose its runtime product reference').toBeTruthy();
    await productCard.getByRole('button', { name: 'Add to cart', exact: true }).click();
    await expect(page.locator('[data-cart-line]')).toBeVisible();
    const paymentMethodId = await page.locator('input[name="payments[0][method_id]"]').inputValue();

    return { productId, paymentMethodId };
}

async function enrollDevice(page, name, token) {
    await page.goto('/pos/shift', { waitUntil: 'domcontentloaded' });
    const drawerText = await page.locator('dl').first().locator('div').filter({ hasText: 'Drawer' }).innerText();
    const drawerCode = drawerText.match(/REM-DRAWER-\d+/)?.[0];
    expect(drawerCode, 'The current cashier shift must expose its assigned remediation drawer').toBeTruthy();
    await page.goto('/pos/offline-readiness', { waitUntil: 'domcontentloaded' });
    const form = page.locator('form[action$="/pos/offline/devices"]');
    await expect(form).toBeVisible();
    const shift = form.locator('select[name="shift_id"]');
    const shiftId = await shift.locator('option').filter({ hasText: drawerCode }).getAttribute('value');
    expect(shiftId, 'The fixture cashier must have an open, scoped shift').toBeTruthy();
    await shift.selectOption(shiftId);
    await form.locator('input[name="name"]').fill(name);
    await form.locator('input[name="token"]').fill(token);
    await form.getByRole('button', { name: /Enroll browser device/i }).click();
    await expect(page.getByText(/Offline device enrolled/i)).toBeVisible();
    await page.goto('/pos/offline/queue', { waitUntil: 'domcontentloaded' });
    const option = page.locator('select[name="offline_device_id"] option').filter({ hasText: name }).first();
    await expect(option).toBeAttached();
    const deviceId = await option.getAttribute('value');
    expect(deviceId, 'The newly enrolled device must be selectable for sync').toBeTruthy();

    return deviceId;
}

function serverPayload({ localUuid, productId, priceVersionId, paymentMethodId, amount, quantity = '1' }) {
    const capturedAt = new Date().toISOString();

    return {
        'payload[local_uuid]': localUuid,
        'payload[captured_at]': capturedAt,
        'payload[price_cached_at]': capturedAt,
        'payload[lines][0][product_id]': productId,
        'payload[lines][0][quantity]': quantity,
        'payload[lines][0][unit_price]': amount.toFixed(3),
        'payload[lines][0][price_version_id]': priceVersionId,
        'payload[payment][payment_method_id]': paymentMethodId,
        'payload[payment][amount]': (amount * Number(quantity)).toFixed(2),
    };
}

test.describe.serial('restricted offline POS UI — Local/Dev remediation fixture only', () => {
    test.skip(fixtureGate() !== null, fixtureGate() ?? '');

    test('enrolls a real device, preserves a minimal IndexedDB record, accepts one sync exactly once, and renders desktop/mobile LTR/RTL safely', async ({ page, browser }) => {
        test.setTimeout(180_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.cashier, REMEDIATION_PASSWORD);

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/pos/offline-readiness', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('[data-offline-policy]')).toHaveAttribute('data-offline-enabled', 'true');
        await expect(page.getByText('OFF-01..OFF-05-local-dev-v1', { exact: true })).toBeVisible();

        await locale(page, 'ar');
        await page.goto('/pos/offline-readiness', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/pos/offline/queue', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('[data-offline-policy]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBeTruthy();

        await locale(page, 'en');
        await page.setViewportSize({ width: 1280, height: 900 });
        const { versionId, amount } = await discoverApprovedPrice(browser, diagnostics);
        const { productId, paymentMethodId } = await discoverCashierCheckoutInputs(page);
        acceptedFixture = { versionId, amount, productId, paymentMethodId };
        const token = uniqueReference('offline-token').padEnd(48, 'x');
        const deviceName = uniqueReference('offline-browser');
        const deviceId = await enrollDevice(page, deviceName, token);
        const localUuid = uniqueReference('offline-accepted');

        await expect(page.locator('body')).toHaveJSProperty('ToyJoyOfflinePos');
        await page.evaluate(async (record) => window.ToyJoyOfflinePos.queue(record), {
            localUuid,
            deviceReference: deviceName,
            policyVersion: 'OFF-01..OFF-05-local-dev-v1',
            schemaVersion: '1',
            capturedAt: new Date().toISOString(),
            priceCachedAt: new Date().toISOString(),
            lines: [{ productId: Number(productId), quantity: '1', unitPrice: amount.toFixed(3), priceVersionId: Number(versionId) }],
            payment: { paymentMethodId: Number(paymentMethodId), amount: amount.toFixed(2) },
        });
        const queuedRecords = await page.evaluate(() => window.ToyJoyOfflinePos.records());
        expect(queuedRecords).toHaveLength(1);
        assertNoForbiddenKeys(queuedRecords);
        expect(queuedRecords[0]).toMatchObject({
            localUuid,
            deviceReference: deviceName,
            policyVersion: 'OFF-01..OFF-05-local-dev-v1',
            schemaVersion: '1',
        });

        const queueForm = {
            offline_device_id: deviceId,
            token,
            ...serverPayload({ localUuid, productId, priceVersionId: versionId, paymentMethodId, amount }),
        };
        const queued = await postForm(page, '/pos/offline/queue', queueForm);
        expect(queued.ok(), 'The authenticated provisional queue POST must succeed').toBeTruthy();
        const replayedQueue = await postForm(page, '/pos/offline/queue', queueForm);
        expect(replayedQueue.ok(), 'A matching queue replay must be idempotent').toBeTruthy();

        await page.goto('/pos/offline/queue', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText(localUuid, { exact: true })).toHaveCount(1);
        await expect(page.getByText('queued', { exact: true })).toBeVisible();
        const syncForm = page.locator('form[action$="/pos/offline/sync"]');
        await syncForm.locator('select[name="offline_device_id"]').selectOption(deviceId);
        await syncForm.locator('input[name="token"]').fill(token);
        await syncForm.getByRole('button', { name: /Sync this device/i }).click();
        await expect(page.getByText(/Sync completed: 1 accepted, 0 require review\./i)).toBeVisible();
        await expect(page.locator('tr').filter({ hasText: localUuid }).getByText('accepted', { exact: true })).toBeVisible();
        await page.goto(`/sales?q=${encodeURIComponent(`OFFLINE:${deviceId}:${localUuid}`)}`, { waitUntil: 'domcontentloaded' });
        const acceptedSaleRows = page.locator('tbody tr');
        await expect(acceptedSaleRows).toHaveCount(1);
        const acceptedSale = acceptedSaleRows.first().getByRole('link').first();
        const acceptedSaleHref = await acceptedSale.getAttribute('href');
        expect(acceptedSaleHref, 'The accepted offline transaction must have one visible final sale document').toBeTruthy();
        expect(new URL(acceptedSaleHref).origin).toBe(new URL(REMEDIATION_BASE_URL).origin);
        expect(new URL(acceptedSaleHref).pathname).toMatch(/^\/sales\/\d+$/);

        await page.goto('/pos/offline/queue', { waitUntil: 'domcontentloaded' });
        const replaySyncForm = page.locator('form[action$="/pos/offline/sync"]');
        await replaySyncForm.locator('input[name="token"]').fill(token);
        await replaySyncForm.getByRole('button', { name: /Sync this device/i }).click();
        await expect(page.getByText(/Sync completed: 0 accepted, 0 require review\./i)).toBeVisible();
        await page.goto(`/sales?q=${encodeURIComponent(`OFFLINE:${deviceId}:${localUuid}`)}`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await expect(page.locator('tbody tr').first().getByRole('link').first()).toHaveAttribute('href', acceptedSaleHref);

        const deniedContext = await browser.newContext({ locale: 'en-US' });
        const deniedPage = await deniedContext.newPage();
        attachDiagnostics(deniedPage, diagnostics, true);
        await login(deniedPage, ACTORS.cashier, REMEDIATION_PASSWORD);
        const denied = await deniedPage.goto('/offline/conflicts');
        expect(denied.status(), 'A cashier cannot enter conflict review by direct URL').toBe(403);
        await deniedContext.close();
        expect(diagnostics).toEqual([]);
    });

    test('creates a closed-shift conflict through visible cashier UI and permits only the scoped reviewer to reject it with a reason', async ({ page, browser }) => {
        test.setTimeout(180_000);
        const diagnostics = [];
        attachDiagnostics(page, diagnostics);
        await login(page, ACTORS.closingCashier, REMEDIATION_PASSWORD);
        expect(acceptedFixture, 'The serial acceptance scenario must establish runtime product, price, and payment references first.').toBeTruthy();
        const { versionId, amount, productId, paymentMethodId } = acceptedFixture;
        await page.goto('/pos?product_q=REM-NORMAL-001', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('article[data-product-family]').filter({ hasText: 'REM-NORMAL-001' }).first()).toBeVisible();
        const token = uniqueReference('offline-close-token').padEnd(48, 'x');
        const deviceName = uniqueReference('offline-close-browser');
        const deviceId = await enrollDevice(page, deviceName, token);
        const localUuid = uniqueReference('offline-closed-shift');
        const queued = await postForm(page, '/pos/offline/queue', {
            offline_device_id: deviceId,
            token,
            ...serverPayload({ localUuid, productId, priceVersionId: versionId, paymentMethodId, amount }),
        });
        expect(queued.ok(), 'The provisional transaction must queue before the shift is closed').toBeTruthy();

        await page.goto('/pos/shift', { waitUntil: 'domcontentloaded' });
        const shiftDetails = page.locator('dl').first();
        const openingFloat = parseMoney(await shiftDetails.locator('div').filter({ hasText: 'Opening float' }).innerText());
        const closeForm = page.locator('form[action*="/blind-close"]');
        await expect(closeForm).toBeVisible();
        await closeForm.locator('input[name="actual_cash"]').fill(openingFloat.toFixed(2));
        await closeForm.getByRole('button', { name: 'Submit count', exact: true }).click();
        await expect(page.getByText(/count has been submitted and is awaiting review/i)).toBeVisible();

        await page.goto('/pos/offline/queue', { waitUntil: 'domcontentloaded' });
        const syncForm = page.locator('form[action$="/pos/offline/sync"]');
        await syncForm.locator('select[name="offline_device_id"]').selectOption(deviceId);
        await syncForm.locator('input[name="token"]').fill(token);
        await syncForm.getByRole('button', { name: /Sync this device/i }).click();
        await expect(page.getByText(/Sync completed: 0 accepted, 1 require review\./i)).toBeVisible();
        await expect(page.locator('tr').filter({ hasText: localUuid }).getByText('conflict', { exact: true })).toBeVisible();

        const reviewerContext = await browser.newContext({ locale: 'en-US' });
        const reviewer = await reviewerContext.newPage();
        attachDiagnostics(reviewer, diagnostics);
        await login(reviewer, ACTORS.reviewer, REMEDIATION_PASSWORD);
        await reviewer.goto('/offline/conflicts', { waitUntil: 'domcontentloaded' });
        const row = reviewer.locator('tr').filter({ hasText: localUuid }).first();
        await expect(row).toBeVisible();
        await row.getByRole('link', { name: 'Review conflict', exact: true }).click();
        await expect(reviewer.getByText(/does not silently replace/i)).toBeVisible();
        const form = reviewer.locator('form[action*="/offline/conflicts/"][action$="/resolve"]');
        await form.locator('textarea[name="reason"]').fill('Closed-shift conflict rejected after reviewer verification.');
        await form.getByRole('button', { name: 'Record disposition', exact: true }).click();
        await expect(reviewer.getByText(/disposition was recorded in the audit trail/i)).toBeVisible();
        await expect(reviewer.getByText(localUuid, { exact: true })).toHaveCount(0);
        await reviewerContext.close();
        expect(diagnostics).toEqual([]);
    });
});
