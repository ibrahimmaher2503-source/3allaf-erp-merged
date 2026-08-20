import { expect, test } from '@playwright/test';

const remediationDatabase = 'toyjoy_phase1_remediation_20260818';
const remediationBaseUrl = process.env.PLAYWRIGHT_BASE_URL ?? '';
const remediationPassword = process.env.REMEDIATION_FIXTURE_PASSWORD ?? '';
const allowedLoopbackHosts = new Set(['127.0.0.1', 'localhost', '::1']);
const isRemediationTarget = (() => {
    if (process.env.PLAYWRIGHT_REMEDIATION_DATABASE !== remediationDatabase || remediationPassword === '') {
        return false;
    }

    try {
        return allowedLoopbackHosts.has(new URL(remediationBaseUrl).hostname);
    } catch {
        return false;
    }
})();

const actors = {
    requester: 'rem-party',
    reviewer: 'rem-reviewer',
    approver: 'rem-admin',
};

const workflow = {
    assetCode: `REM-UI-ASSET-${Date.now()}`,
    assetName: 'Remediation UI lifecycle asset',
    bookingLocation: `Remediation UI room ${Date.now()}`,
    serviceDescription: 'Remediation UI Party service',
    serviceAmount: '60',
    assetAmount: '90',
    totalAmount: '150',
    partyDate: null,
    bookingUrl: null,
    invoiceUrl: null,
    orderUrl: null,
};

function futurePartyDate() {
    const date = new Date();
    date.setDate(date.getDate() + 21);

    return date.toISOString().slice(0, 10);
}

function dateDaysAfter(dateString, days) {
    const date = new Date(`${dateString}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

async function selectOptionContaining(select, text) {
    const value = await select.locator('option').filter({ hasText: text }).first().getAttribute('value');
    expect(value).not.toBeNull();
    await select.selectOption(value);
}

async function login(page, username) {
    await page.context().clearCookies();
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill(remediationPassword);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);
}

async function expectNoBrowserErrors(page, errors) {
    await page.waitForLoadState('domcontentloaded');
    expect(errors).toEqual([]);
}

test.use({ headless: false });

test.describe.serial('REM Party workflows — verified contracts for US-025 through US-030', () => {
    test.skip(({ browserName }) => browserName !== 'chromium', 'This remediation contract is authorized for headed Chromium only.');
    test.skip(!isRemediationTarget, `Requires loopback Chromium plus PLAYWRIGHT_REMEDIATION_DATABASE=${remediationDatabase} and REMEDIATION_FIXTURE_PASSWORD.`);

    test('US-025 through US-030 complete only through authenticated REM UI actors', async ({ page, context }) => {
        test.setTimeout(240_000);
        const pageErrors = [];
        const consoleErrors = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') {
                consoleErrors.push(message.text());
            }
        });

        // US-028: create a fresh asset through the UI, never by a raw database fixture.
        await login(page, actors.requester);
        await page.goto('/party/assets?mode=workspace', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Add rental asset', { exact: true })).toBeVisible();
        await page.getByLabel('Asset code', { exact: true }).fill(workflow.assetCode);
        await page.getByLabel('Name (English)', { exact: true }).fill(workflow.assetName);
        await page.getByLabel('Name (Arabic)', { exact: true }).fill('أصل دورة حياة المعالجة');
        await selectOptionContaining(page.getByLabel('Branch', { exact: true }), 'REM-BRANCH');
        await selectOptionContaining(page.getByLabel('Store', { exact: true }), 'REM-PARTY');
        await page.getByLabel('Current location', { exact: true }).fill(workflow.bookingLocation);
        await page.getByRole('button', { name: 'Create asset', exact: true }).click();
        await expect(page.getByText('Rental asset created.', { exact: true })).toBeVisible();
        await expect(page.getByText(workflow.assetCode, { exact: true })).toBeVisible();

        // US-025: create a positive-value Party-only booking and working invoice.
        await page.goto('/parties/bookings/create', { waitUntil: 'domcontentloaded' });
        await selectOptionContaining(page.getByLabel('Party store', { exact: true }), 'Remediation Party');
        await selectOptionContaining(page.getByLabel('Customer', { exact: true }), 'Remediation Party Customer');
        workflow.partyDate = futurePartyDate();
        expect(workflow.partyDate).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        await page.getByLabel('Party date', { exact: true }).fill(workflow.partyDate);
        await page.getByLabel('Start time', { exact: true }).fill('14:00');
        await page.getByLabel('End time', { exact: true }).fill('17:00');
        await page.getByLabel('Timezone', { exact: true }).fill('Africa/Cairo');
        await page.getByLabel('Location / room', { exact: true }).fill(workflow.bookingLocation);
        await page.getByLabel('Primary contact', { exact: true }).fill('01000000991');
        await page.locator('[name="lines[0][line_type]"]').selectOption('service');
        await page.locator('[name="lines[0][description]"]').fill(workflow.serviceDescription);
        await page.locator('[name="lines[0][quantity]"]').fill('1');
        await page.locator('[name="lines[0][unit_price]"]').fill(workflow.serviceAmount);
        await page.locator('[name="lines[1][line_type]"]').selectOption('rental_asset');
        await page.locator('[name="lines[1][description]"]').fill(workflow.assetName);
        await page.locator('[name="lines[1][quantity]"]').fill('1');
        await page.locator('[name="lines[1][unit_price]"]').fill(workflow.assetAmount);
        await selectOptionContaining(page.getByLabel('Rental asset (actual reservation)', { exact: true }).nth(1), workflow.assetCode);
        await Promise.all([
            page.waitForURL(/\/parties\/bookings\/\d+$/),
            page.getByRole('button', { name: 'Create booking', exact: true }).click(),
        ]);
        workflow.bookingUrl = page.url();
        expect(workflow.bookingUrl).toMatch(/^https?:\/\/[^/]+\/parties\/bookings\/\d+$/);
        await expect(page.getByText(workflow.serviceDescription, { exact: true })).toBeVisible();
        await expect(page.getByText(/150\.00/)).toBeVisible();
        workflow.invoiceUrl = await page.getByRole('link', { name: 'Open invoice', exact: true }).getAttribute('href');
        expect(workflow.invoiceUrl).toMatch(/^https?:\/\/[^/]+\/parties\/invoices\/\d+$/);
        await expectNoBrowserErrors(page, pageErrors);
        expect(consoleErrors).toEqual([]);

        // The distinct review-only actor must not gain Party-booking access.
        await login(page, actors.reviewer);
        const deniedResponse = await page.goto(workflow.bookingUrl, { waitUntil: 'domcontentloaded' });
        expect(deniedResponse?.status()).toBe(403);
        pageErrors.length = 0;
        consoleErrors.length = 0;

        // A different actor approves the booking; requester then creates the operating order.
        await login(page, actors.approver);
        await page.goto(workflow.bookingUrl, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Confirm booking', exact: true }).click();
        await expect(page.getByText('Party booking confirmed.', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Create operating order', exact: true })).toBeVisible();
        await login(page, actors.requester);
        await page.goto(workflow.bookingUrl, { waitUntil: 'domcontentloaded' });
        await Promise.all([
            page.waitForURL(/\/parties\/orders\/\d+$/),
            page.getByRole('button', { name: 'Create operating order', exact: true }).click(),
        ]);
        workflow.orderUrl = page.url();
        expect(workflow.orderUrl).toMatch(/^https?:\/\/[^/]+\/parties\/orders\/\d+$/);

        // US-027: release separately, then perform the real asset lifecycle through the order UI.
        await login(page, actors.approver);
        await page.goto(workflow.orderUrl, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Release for preparation', exact: true }).click();
        await expect(page.getByText('Party operating order released.', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Check out through asset system', exact: true })).toBeVisible();
        await login(page, actors.requester);
        await page.goto(workflow.orderUrl, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Check out through asset system', exact: true }).click();
        await expect(page.getByText('Party rental asset checked out through the asset reservation.', { exact: true })).toBeVisible();
        await page.getByLabel('Condition after Party use', { exact: true }).fill('good');
        await page.getByRole('button', { name: 'Return for inspection', exact: true }).click();
        await expect(page.getByText('Party rental asset returned for inspection.', { exact: true })).toBeVisible();
        await page.getByLabel('Inspection outcome', { exact: true }).selectOption('available');
        await page.getByLabel('Inspection findings', { exact: true }).fill('Returned in good condition after UI workflow.');
        await page.getByRole('button', { name: 'Record inspection', exact: true }).click();
        await expect(page.getByText('Party rental asset inspection recorded.', { exact: true })).toBeVisible();
        await login(page, actors.approver);
        await page.goto(workflow.orderUrl, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Complete order', exact: true }).click();
        await expect(page.getByText('Party operating order completed.', { exact: true })).toBeVisible();

        // US-026: record the exact positive payment through the Party-only payment UI, then print it.
        await login(page, actors.requester);
        await page.goto(`${workflow.invoiceUrl}/payments`, { waitUntil: 'domcontentloaded' });
        await selectOptionContaining(page.getByLabel('Payment method', { exact: true }), 'Remediation cash');
        await page.getByLabel('Amount', { exact: true }).fill(workflow.totalAmount);
        await page.getByLabel('Reference', { exact: true }).fill(`REM-UI-PAY-${workflow.assetCode}`);
        await page.getByRole('button', { name: 'Record Party payment', exact: true }).click();
        await expect(page.getByText('Party payment recorded.', { exact: true })).toBeVisible();
        await expect(page.getByText(/Balance due/)).toBeVisible();
        const receiptHref = await page.getByRole('link', { name: 'Receipt', exact: true }).getAttribute('href');
        expect(receiptHref).toMatch(/^https?:\/\/[^/]+\/parties\/payments\/\d+\/print$/);
        const receiptPagePromise = context.waitForEvent('page');
        await page.getByRole('link', { name: 'Receipt', exact: true }).click();
        const receiptPage = await receiptPagePromise;
        await receiptPage.waitForLoadState('domcontentloaded');
        await expect(receiptPage.getByText(/Payment on Account for Party Invoice No\./)).toBeVisible();
        await receiptPage.close();
        await login(page, actors.approver);
        await page.goto(`${workflow.invoiceUrl}/settle`, { waitUntil: 'domcontentloaded' });
        await page.getByLabel('Type FINAL CLOSE to confirm', { exact: true }).fill('FINAL CLOSE');
        await page.getByRole('button', { name: 'Finalize and close', exact: true }).click();
        await expect(page.getByText('Party invoice finalized and closed.', { exact: true })).toBeVisible();

        // US-030: a Party quote is created/printed as an offer with no one-click conversion control.
        await login(page, actors.requester);
        await page.goto('/quotations', { waitUntil: 'domcontentloaded' });
        await page.getByLabel('Activity type', { exact: true }).selectOption('party');
        await selectOptionContaining(page.getByLabel('Store', { exact: true }), 'REM-PARTY');
        await page.getByLabel('Compatible line type', { exact: true }).selectOption('service');
        await page.getByLabel('Line description (English)', { exact: true }).fill('Remediation UI Party quotation');
        await page.getByLabel('Line description (Arabic)', { exact: true }).fill('عرض حفلة معالجة للواجهة');
        await page.getByLabel('Quantity', { exact: true }).fill('1');
        await page.getByLabel('Unit price', { exact: true }).fill('75');
        await page.getByRole('button', { name: 'Save non-posting draft', exact: true }).click();
        await expect(page.getByText('Quotation created as a non-posting draft.', { exact: true })).toBeVisible();
        await expect(page.getByText('NON-POSTING: a quotation is an offer only. Phase 1 has no one-click conversion and it never changes inventory or financial records.', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: /convert/i })).toHaveCount(0);
        const quotationPrint = page.getByRole('link', { name: 'Print', exact: true }).first();
        await expect(quotationPrint).toBeVisible();
        await Promise.all([
            page.waitForURL(/\/quotations\/\d+\/print/),
            quotationPrint.click(),
        ]);
        await expect(page.getByText(/Quotation/)).toBeVisible();

        // US-028/029: submit, separately approve, and observe the completed asset-event state.
        await page.goto('/party/assets?mode=workspace', { waitUntil: 'domcontentloaded' });
        const assetRow = page.locator('tr').filter({ hasText: workflow.assetCode });
        await assetRow.getByText('Record damage, loss, maintenance or depreciation', { exact: true }).click();
        await assetRow.getByLabel('Event type', { exact: true }).selectOption('maintenance');
        await assetRow.getByLabel('Assessment', { exact: true }).fill('UI approval contract needs a distinct reviewer control.');
        await assetRow.getByRole('button', { name: 'Submit for approval', exact: true }).click();
        await expect(page.getByText('Asset event submitted for approval.', { exact: true })).toBeVisible();
        await login(page, actors.approver);
        await page.goto('/party/assets?mode=history', { waitUntil: 'domcontentloaded' });
        const pendingEventRow = page.locator('tr').filter({ hasText: workflow.assetCode });
        await expect(pendingEventRow.getByRole('button', { name: 'Approve asset event', exact: true })).toBeVisible();
        await pendingEventRow.getByRole('button', { name: 'Approve asset event', exact: true }).click();
        await expect(page.getByText('Asset event approved.', { exact: true })).toBeVisible();
        await expect(page.locator('tr').filter({ hasText: workflow.assetCode }).getByText('Approved', { exact: true }).first()).toBeVisible();

        // US-025: use the bounded calendar filters to review the completed reservation.
        await page.goto('/parties/calendar', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /Party calendar/i })).toBeVisible();
        const calendarTo = dateDaysAfter(workflow.partyDate, 6);
        await page.getByLabel('From', { exact: true }).fill(workflow.partyDate);
        await page.getByLabel('To', { exact: true }).fill(calendarTo);
        await Promise.all([
            page.waitForURL((url) => url.pathname === '/parties/calendar' && url.searchParams.get('from') === workflow.partyDate && url.searchParams.get('to') === calendarTo),
            page.getByRole('button', { name: 'Show calendar', exact: true }).click(),
        ]);
        await expect(page.getByText(workflow.assetCode, { exact: true })).toBeVisible();

        // Required at both desktop English and mobile Arabic, with no browser/runtime errors.
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(workflow.bookingUrl, { waitUntil: 'domcontentloaded' });
        const localeForm = page.locator('form[action$="/locale"]').first();
        await localeForm.locator('input[name="locale"][value="ar"]').evaluate((input) => input.form.submit());
        await page.waitForLoadState('domcontentloaded');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
        await expectNoBrowserErrors(page, pageErrors);
        expect(consoleErrors).toEqual([]);
    });
});
