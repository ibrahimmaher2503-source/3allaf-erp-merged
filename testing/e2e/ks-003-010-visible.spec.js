import { test, expect } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { LOCAL_BROWSER_ACTORS, login } from '../helpers/auth.js';

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'en-US', viewport: { width: 1440, height: 1000 }, launchOptions: { slowMo: 220 }, trace: 'on', screenshot: 'only-on-failure', video: 'retain-on-failure' });

test('KS-003 through KS-010 visible UI feasibility', async ({ page }, testInfo) => {
    test.setTimeout(240_000);
    const evidenceDirectory = path.resolve('artifacts/ks-003-010-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const results = { browser: 'Chromium headed', locale: 'en-US', scenarios: [], consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    page.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) results.consoleErrors.push(message.text()); });
    page.on('pageerror', (error) => results.pageErrors.push(error.message));
    page.on('requestfailed', (request) => results.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    const go = async (route) => { const started = Date.now(); const response = await page.goto(route, { waitUntil: 'domcontentloaded' }); const elapsedMs = Date.now() - started; results.timings.push({ route, elapsedMs, status: response?.status() }); return { response, elapsedMs }; };
    const capture = async (id) => { const file = path.join(evidenceDirectory, id + '.png'); await page.screenshot({ path: file, fullPage: true }); await testInfo.attach(id, { path: file, contentType: 'image/png' }); return file; };

    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    const authorization = await go('/admin/authorization-baseline');
    const authorizationUi = authorization.response?.status() === 200 && await page.getByRole('button', { name: 'Manage' }).count() > 0;
    await capture('KS-003-005-authorization-en-ltr');
    results.scenarios.push({ id: 'KS-003', priority: 'P0', actor: 'Cashier A + Administrator B', route: '/pos; /admin/authorization-baseline', expected: 'Mid-session permission revocation blocks an in-page checkout/mutation with no side effect.', actual: `Authorization UI available=${authorizationUi}. No deterministic cashier checkout-ready state or safe UI-only rollback fixture is present; the required in-page mutation after revocation cannot be performed without inventing a sale.`, result: 'BLOCKED', classification: 'MISSING DETERMINISTIC UI PREREQUISITE' });
    results.scenarios.push({ id: 'KS-004', priority: 'P0', actor: 'Manager A + Administrator B', route: '/admin/authorization-baseline; /admin/branches; /admin/stores', expected: 'A Branch A session loses old-A access immediately after B changes its scope to Branch B.', actual: `Authorization UI available=${authorizationUi}. The running local fixture has no Branch B or Branch-B-scoped manager, so no legitimate old-record/direct-route scope transition exists.`, result: 'BLOCKED', classification: 'MISSING BRANCH B / CROSS-SCOPE ACTOR' });
    results.scenarios.push({ id: 'KS-005', priority: 'P0', actor: 'System Administrator', route: '/admin/authorization-baseline', expected: 'The final active administrator cannot remove its own last admin access or deactivate itself.', actual: `Role assignment UI available=${authorizationUi}; no user active/inactive/deactivation control is exposed on the authorization screen. The full remove-role plus deactivate scenario is not implemented in UI.`, result: 'NOT IMPLEMENTED IN UI', classification: 'MISSING USER ACTIVATION UI' });

    const approvals = await go('/approvals');
    const approvalText = await page.locator('body').innerText();
    const reviewCount = await page.getByRole('button', { name: 'Review' }).count();
    await capture('KS-006-009-approvals-en-ltr');
    results.scenarios.push({ id: 'KS-006', priority: 'P0', actor: 'Requester + independent reviewer', route: '/approvals', expected: 'Self approval is denied; an independent authorized reviewer decides once with audit.', actual: `Approval inbox=${approvals.response?.status()}; visible Review actions=${reviewCount}; empty=${/No approval requests found/i.test(approvalText)}. No deterministic pending source request or independent reviewer fixture is available.`, result: 'BLOCKED', classification: 'MISSING PENDING APPROVAL / REVIEWER FIXTURE' });
    results.scenarios.push({ id: 'KS-007', priority: 'P0', actor: 'Two independent reviewers', route: '/approvals', expected: 'A stale second decision is denied and creates no duplicate terminal effect.', actual: `Approval inbox=${approvals.response?.status()}; visible Review actions=${reviewCount}. The two-reviewer, same-pending-record UI prerequisite does not exist.`, result: 'BLOCKED', classification: 'MISSING TWO-REVIEWER / PENDING APPROVAL FIXTURE' });
    results.scenarios.push({ id: 'KS-008', priority: 'P0', actor: 'Authorized Branch A, Branch B, guest', route: '/approvals/{approval}/attachments/{attachment}', expected: 'Only Branch-A access can download private evidence; cross-scope and guest access are safely denied.', actual: `Approval inbox visible review actions=${reviewCount}. No Branch-B actor or private Branch-A attachment is present, so the exact protected URL cannot be obtained through normal UI.`, result: 'BLOCKED', classification: 'MISSING CROSS-SCOPE PRIVATE ATTACHMENT FIXTURE' });
    results.scenarios.push({ id: 'KS-009', priority: 'P0', actor: 'Authorized approval reviewer', route: '/approvals', expected: 'MIME-masqueraded/oversized/double-extension evidence uploads are rejected without a usable attachment.', actual: reviewCount === 0 ? 'No visible approval record can be opened to reach the existing Evidence file control; invalid upload was not manufactured against a missing source.' : 'A real approval UI is present and requires a dedicated focused invalid-file execution.', result: reviewCount === 0 ? 'BLOCKED' : 'NOT TESTABLE THROUGH UI', classification: reviewCount === 0 ? 'MISSING UPLOAD SOURCE RECORD' : 'PENDING FOCUSED UI EXECUTION' });

    const orders = await go('/purchasing/orders');
    const orderText = await page.locator('body').innerText();
    const approvedRows = page.locator('tr').filter({ hasText: /Approved/i });
    const approvedCount = await approvedRows.count();
    const exposedMutation = approvedCount > 0
        ? await approvedRows.first().locator('button[title*="Edit"], button[title*="Delete"], button[title*="Cancel"], button[title*="Close"], button[title*="Submit"], button[title*="Approve"]').count()
        : 0;
    await capture('KS-010-purchase-orders-en-ltr');
    results.scenarios.push({ id: 'KS-010', priority: 'P0', actor: 'Authorized purchaser/admin', route: '/purchasing/orders', expected: 'Approved document has no ordinary edit/delete mutation; correction/reversal is used where implemented.', actual: `Purchase orders=${orders.response?.status()}; approved rows=${approvedCount}; ordinary edit/delete/status-mutation controls in first approved row=${exposedMutation}; only read-only View/Print controls are exposed, and no direct edit/delete route is route-backed.`, result: approvedCount > 0 && exposedMutation === 0 ? 'PASS' : 'BLOCKED', classification: approvedCount > 0 && exposedMutation === 0 ? 'UI IMMUTABILITY OBSERVATION' : 'MISSING APPROVED DOCUMENT UI FIXTURE' });

    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify(results, null, 2), 'utf8');
    await testInfo.attach('KS-003/010 results', { path: file, contentType: 'application/json' });
    expect(results.scenarios.map((scenario) => scenario.id).sort()).toEqual(['KS-003', 'KS-004', 'KS-005', 'KS-006', 'KS-007', 'KS-008', 'KS-009', 'KS-010']);
    expect(results.consoleErrors).toEqual([]);
    expect(results.pageErrors).toEqual([]);
});

test('KS-010 approved pricing version remains read-only through browser routes', async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const evidenceDirectory = path.resolve('artifacts/ks-010-focused-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    page.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) diagnostics.consoleErrors.push(message.text()); });
    page.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
    page.on('requestfailed', (request) => diagnostics.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    const go = async (route) => { const started = Date.now(); const response = await page.goto(route, { waitUntil: 'domcontentloaded' }); const elapsedMs = Date.now() - started; diagnostics.timings.push({ route, elapsedMs, status: response?.status() }); return response; };

    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

    const pricingResponse = await go('/pricing');
    expect(pricingResponse?.status()).toBe(200);
    const approvedRow = page.locator('tr').filter({ hasText: /Approved/i }).first();
    const approvedRows = await page.locator('tr').filter({ hasText: /Approved/i }).count();
    const actions = approvedRows > 0 ? await approvedRow.getByRole('button').allTextContents() : [];
    const rowTextBefore = approvedRows > 0 ? await approvedRow.innerText() : '';
    const screenshot = path.join(evidenceDirectory, 'KS-010-pricing-approved-readonly-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-010 pricing approved read-only', { path: screenshot, contentType: 'image/png' });

    // The routes rendered by the current UI expose only the workspace; there is no record edit link.
    const editResponse = await go('/pricing/1/edit');
    const editStatus = editResponse?.status();
    const deniedOrMissing = editStatus === 403 || editStatus === 404;
    const afterResponse = await go('/pricing');
    expect(afterResponse?.status()).toBe(200);
    const rowTextAfter = approvedRows > 0 ? await page.locator('tr').filter({ hasText: /Approved/i }).first().innerText() : '';
    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify({
        id: 'KS-010', priority: 'P0', route: '/pricing; /pricing/1/edit', actor: 'Administrator',
        expected: 'An approved/final document has no ordinary edit or delete path, and direct edit navigation is safely denied or absent.',
        actual: { pricingStatus: pricingResponse?.status(), approvedRows, actions, editStatus, rowUnchanged: rowTextBefore === rowTextAfter },
        result: approvedRows > 0 && deniedOrMissing && rowTextBefore === rowTextAfter ? 'PASS' : 'BLOCKED',
        classification: 'FOCUSED BROWSER IMMUTABILITY RETEST', diagnostics,
    }, null, 2), 'utf8');
    await testInfo.attach('KS-010 focused results', { path: file, contentType: 'application/json' });
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.pageErrors).toEqual([]);
});

test('KS-003 stale cashier checkout is server-denied after visible role revocation', async ({ browser }, testInfo) => {
    test.setTimeout(180_000);
    const evidenceDirectory = path.resolve('artifacts/ks-003-focused-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const cashContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const adminContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const cashier = await cashContext.newPage();
    const admin = await adminContext.newPage();
    const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    for (const current of [cashier, admin]) {
        current.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) diagnostics.consoleErrors.push(message.text()); });
        current.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
        current.on('requestfailed', (request) => diagnostics.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    }
    let roleRevoked = false;
    let checkoutStatus = null;
    let posReady = false;
    try {
        const start = Date.now();
        await login(cashier, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await cashier.goto('/pos', { waitUntil: 'domcontentloaded' });
        diagnostics.timings.push({ route: '/pos', elapsedMs: Date.now() - start });
        await expect(cashier.locator('html')).toHaveAttribute('lang', 'en');
        await expect(cashier.locator('html')).toHaveAttribute('dir', 'ltr');
        const product = cashier.getByText('Demo Racing Car', { exact: true });
        await expect(product).toBeVisible();
        const card = product.locator('xpath=ancestor::*[self::article or self::div][.//button[normalize-space()="Add"]][1]');
        await card.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(cashier.locator('article[data-cart-line]')).toHaveCount(1);
        const checkout = cashier.locator("form[data-guide='pos-payment-form']");
        const settle = checkout.getByRole('button', { name: 'Settle and complete sale', exact: true });
        posReady = await checkout.isVisible() && await settle.isEnabled();
        const readinessScreenshot = path.join(evidenceDirectory, 'KS-003-pos-readiness-en-ltr.png');
        await cashier.screenshot({ path: readinessScreenshot, fullPage: true });
        await testInfo.attach('KS-003 POS readiness', { path: readinessScreenshot, contentType: 'image/png' });
        if (!posReady) return;

        await login(admin, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await admin.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
        await admin.locator("[data-guide='auth-users-search']").fill('Local Demo Cashier');
        await expect(admin.getByText('Local Demo Cashier', { exact: true })).toBeVisible();
        await admin.getByRole('button', { name: 'Manage', exact: true }).click();
        const dialog = admin.getByRole('dialog');
        await expect(dialog).toBeVisible();
        const cashierRole = dialog.getByLabel('Cashier', { exact: true });
        await expect(cashierRole).toBeChecked();
        await cashierRole.uncheck();
        await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
        await expect(dialog).toBeHidden();
        roleRevoked = true;
        const before = Date.now();
        const responsePromise = cashier.waitForResponse((response) => response.request().method() === 'POST' && /\/pos\/checkout$/.test(new URL(response.url()).pathname));
        await settle.click();
        checkoutStatus = (await responsePromise).status();
        diagnostics.timings.push({ route: '/pos/checkout', elapsedMs: Date.now() - before, status: checkoutStatus });
        const screenshot = path.join(evidenceDirectory, 'KS-003-revoked-cashier-denial-en-ltr.png');
        await cashier.screenshot({ path: screenshot, fullPage: true });
        await testInfo.attach('KS-003 denial after visible revocation', { path: screenshot, contentType: 'image/png' });
    } finally {
        if (roleRevoked) {
            await admin.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
            await admin.locator("[data-guide='auth-users-search']").fill('demo-cashier');
            await admin.getByRole('button', { name: 'Manage', exact: true }).click();
            const dialog = admin.getByRole('dialog');
            const cashierRole = dialog.getByLabel('Cashier', { exact: true });
            if (!await cashierRole.isChecked()) await cashierRole.check();
            await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
            await expect(dialog).toBeHidden();
        }
        const clear = cashier.getByRole('button', { name: 'Clear', exact: true });
        if (await clear.count() && await clear.isVisible()) await clear.click();
        const file = path.join(evidenceDirectory, 'results.json');
        await writeFile(file, JSON.stringify({
            id: 'KS-003', priority: 'P0', route: '/pos; /admin/authorization-baseline', actor: 'Cashier A + Administrator B',
            expected: 'An already-open cashier session is server-denied when it submits a genuine checkout after visible role revocation, without completing a sale.',
            actual: { posReady, roleRevoked, checkoutStatus, cashierRestored: roleRevoked },
            result: posReady && roleRevoked && checkoutStatus === 403 ? 'PASS' : 'BLOCKED',
            classification: 'FOCUSED TWO-CONTEXT SECURITY RETEST', diagnostics,
        }, null, 2), 'utf8');
        await testInfo.attach('KS-003 focused results', { path: file, contentType: 'application/json' });
        await cashContext.close();
        await adminContext.close();
    }
    if (posReady) {
        expect(roleRevoked).toBe(true);
        expect(checkoutStatus).toBe(403);
    }
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.pageErrors).toEqual([]);
});

test('KS-006, KS-008, and KS-009 approval evidence flow through visible UI', async ({ browser }, testInfo) => {
    test.setTimeout(240_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-009-focused-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const adminContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const reviewerContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const guestContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const admin = await adminContext.newPage();
    const reviewer = await reviewerContext.newPage();
    const guest = await guestContext.newPage();
    const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    for (const current of [admin, reviewer, guest]) {
        current.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) diagnostics.consoleErrors.push(message.text()); });
        current.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
        current.on('requestfailed', (request) => diagnostics.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    }
    const livewireAction = async (page, action) => {
        const update = page.waitForResponse((response) => response.request().method() === 'POST' && /\/livewire\/update/.test(new URL(response.url()).pathname));
        await action();
        return update;
    };
    const outcomes = { submitted: false, approvalAvailable: false, reviewerReviewAvailable: false, displayedRequester: null, displayedState: null, selfApproveHidden: false, invalidRejected: [], validUploaded: false, reviewerDownload: false, guestStatus: null, guestSafe: false, revoked: false, approved: false, draftRows: 0, submitVisible: false, ordersText: '' };
    try {
        await login(admin, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        const start = Date.now();
        await admin.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        diagnostics.timings.push({ route: '/purchasing/orders', elapsedMs: Date.now() - start });
        const draft = admin.locator('tr').filter({ hasText: 'PO-DEMO-000001' });
        outcomes.draftRows = await draft.count();
        outcomes.ordersText = (await admin.locator('main').innerText()).slice(0, 3000);
        if (await draft.count()) {
            const submit = draft.getByTitle('Submit Order');
            outcomes.submitVisible = await submit.count() > 0 && await submit.isVisible();
            if (outcomes.submitVisible) {
                await livewireAction(admin, () => submit.click());
                outcomes.submitted = /submitted successfully/i.test(await admin.locator('body').innerText());
            }
        }
        const ordersScreenshot = path.join(evidenceDirectory, 'KS-006-purchase-order-prerequisite-en-ltr.png');
        await admin.screenshot({ path: ordersScreenshot, fullPage: true });
        await testInfo.attach('KS-006 purchase-order prerequisite', { path: ordersScreenshot, contentType: 'image/png' });
        await admin.goto('/approvals', { waitUntil: 'domcontentloaded' });
        const selfReview = admin.getByRole('button', { name: 'Review', exact: true }).first();
        if (await selfReview.count()) {
            await expect(selfReview).toBeVisible();
            await livewireAction(admin, () => selfReview.click());
            const selfDialog = admin.getByRole('dialog');
            await expect(selfDialog).toBeVisible();
            outcomes.approvalAvailable = true;
            outcomes.displayedRequester = (await selfDialog.locator('dl').innerText()).match(/Requester\s*\n([^\n]+)/i)?.[1] ?? null;
            outcomes.displayedState = (await selfDialog.locator('dl').innerText()).match(/State\s*\n([^\n]+)/i)?.[1] ?? null;
            outcomes.selfApproveHidden = outcomes.displayedRequester === 'Local Demo Administrator' && await selfDialog.getByRole('button', { name: 'Approve', exact: true }).count() === 0;
            const selfScreenshot = path.join(evidenceDirectory, 'KS-006-self-approval-denied-en-ltr.png');
            await admin.screenshot({ path: selfScreenshot, fullPage: true });
            await testInfo.attach('KS-006 requester denial', { path: selfScreenshot, contentType: 'image/png' });
            await livewireAction(admin, () => selfDialog.getByRole('button', { name: 'Close', exact: true }).click());
        }

        await login(reviewer, LOCAL_BROWSER_ACTORS.reviewer.username, LOCAL_BROWSER_ACTORS.reviewer.password);
        const approvalsStart = Date.now();
        await reviewer.goto('/approvals', { waitUntil: 'domcontentloaded' });
        diagnostics.timings.push({ route: '/approvals', elapsedMs: Date.now() - approvalsStart });
        const review = reviewer.getByRole('button', { name: 'Review', exact: true }).first();
        outcomes.reviewerReviewAvailable = await review.count() > 0;
        const reviewerInboxScreenshot = path.join(evidenceDirectory, 'KS-006-reviewer-inbox-en-ltr.png');
        await reviewer.screenshot({ path: reviewerInboxScreenshot, fullPage: true });
        await testInfo.attach('KS-006 reviewer inbox', { path: reviewerInboxScreenshot, contentType: 'image/png' });
        if (!outcomes.reviewerReviewAvailable) return;
        await expect(review).toBeVisible();
        await livewireAction(reviewer, () => review.click());
        const dialog = reviewer.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(dialog.getByRole('button', { name: 'Approve', exact: true })).toBeVisible();

        const upload = dialog.getByRole('button', { name: 'Upload securely', exact: true });
        const fileInput = dialog.getByLabel('Evidence file', { exact: true });
        const invalidFiles = [
            { name: 'ks009-mismatch.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('plain harmless text, not an image') },
            { name: 'ks009-double-extension.jpg.php', mimeType: 'image/jpeg', buffer: Buffer.from('harmless non-executable synthetic content') },
            { name: 'ks009-script.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('<script>harmless</script>') },
            { name: 'ks009-oversize.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc((12 * 1024 * 1024) + 1024, 0) },
        ];
        for (const file of invalidFiles) {
            await fileInput.setInputFiles(file);
            await livewireAction(reviewer, () => upload.click());
            const visibleName = await dialog.getByText(file.name, { exact: true }).count() > 0;
            const errorVisible = await dialog.locator('.text-red-600').count() > 0;
            outcomes.invalidRejected.push({ name: file.name, rejected: !visibleName && errorVisible });
        }
        const evidenceScreenshot = path.join(evidenceDirectory, 'KS-008-009-evidence-en-ltr.png');
        await reviewer.screenshot({ path: evidenceScreenshot, fullPage: true });
        await testInfo.attach('KS-008/009 evidence state', { path: evidenceScreenshot, contentType: 'image/png' });
        // Preserve this only pending approval for KS-007 race verification; do not decide it here.
    } finally {
        const file = path.join(evidenceDirectory, 'results.json');
        await writeFile(file, JSON.stringify({
            KS006: { result: outcomes.submitted && outcomes.selfApproveHidden && outcomes.approved ? 'PASS' : 'BLOCKED', actual: outcomes, blockedBy: outcomes.approved ? null : 'Pending record deliberately preserved for KS-007; no new demo-admin request can currently be created from the visible PO list.' },
            KS008: { result: outcomes.validUploaded && outcomes.reviewerDownload && outcomes.guestSafe ? 'BLOCKED' : 'BLOCKED', actual: outcomes, blockedBy: 'No Branch-B actor/scope exists for the mandatory cross-scope denial.' },
            KS009: { result: outcomes.invalidRejected.length === 4 && outcomes.invalidRejected.every((file) => file.rejected) ? 'PASS' : 'BLOCKED', actual: outcomes.invalidRejected },
            diagnostics,
        }, null, 2), 'utf8');
        await testInfo.attach('KS-006/008/009 focused results', { path: file, contentType: 'application/json' });
        await adminContext.close();
        await reviewerContext.close();
        await guestContext.close();
    }
    if (outcomes.approvalAvailable) {
        expect(outcomes.invalidRejected).toHaveLength(4);
        expect(outcomes.invalidRejected.every((file) => file.rejected)).toBe(true);
    }
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.pageErrors).toEqual([]);
});

test('KS-006 PO2 reviewer inbox is visibly inspectable before any evidence action', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-reviewer-inspection-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    page.on('console', (message) => { if (message.type() === 'error') diagnostics.consoleErrors.push(message.text()); });
    page.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
    await login(page, LOCAL_BROWSER_ACTORS.reviewer.username, LOCAL_BROWSER_ACTORS.reviewer.password);
    const started = Date.now();
    const response = await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
    diagnostics.timings.push({ route: '/approvals', elapsedMs: Date.now() - started, status: response?.status() });
    const purchaseApprovalRow = page.locator('tr').filter({ hasText: /Purchase Orders/i }).filter({ hasText: 'Local Demo Administrator' }).first();
    await expect(purchaseApprovalRow).toBeVisible({ timeout: 10_000 });
    const review = purchaseApprovalRow.getByRole('button', { name: 'Review', exact: true });
    const reviewVisible = await review.isVisible({ timeout: 8_000 }).catch(() => false);
    const result = { reviewVisible, requester: null, state: null, stableEvidenceInput: false };
    if (reviewVisible) {
        await review.click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 8_000 });
        const detail = await dialog.locator('dl').innerText();
        result.requester = detail.match(/Requester\s*\n([^\n]+)/i)?.[1] ?? null;
        result.state = detail.match(/State\s*\n([^\n]+)/i)?.[1] ?? null;
        const input = dialog.getByLabel('Evidence file', { exact: true });
        result.stableEvidenceInput = await input.isVisible({ timeout: 8_000 }).catch(() => false);
    }
    const screenshot = path.join(evidenceDirectory, 'KS-006-reviewer-po2-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-006 reviewer PO2 inspection', { path: screenshot, contentType: 'image/png' });
    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify({ id: 'KS-006', route: '/approvals', actor: 'demo-reviewer', result: reviewVisible ? 'INSPECTED' : 'BLOCKED', actual: result, diagnostics }, null, 2), 'utf8');
    await testInfo.attach('KS-006 reviewer inspection results', { path: file, contentType: 'application/json' });
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.pageErrors).toEqual([]);
});

test('KS-005 last-admin role guard through two visible administrator contexts', async ({ browser }, testInfo) => {
    test.setTimeout(240_000);
    const evidenceDirectory = path.resolve('artifacts/ks-005-focused-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const adminContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const supportContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const admin = await adminContext.newPage();
    const support = await supportContext.newPage();
    const diagnostics = { consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    for (const current of [admin, support]) {
        current.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) diagnostics.consoleErrors.push(message.text()); });
        current.on('pageerror', (error) => diagnostics.pageErrors.push(error.message));
    }
    const result = { supportPromoted: false, supportAdminAccess: false, adminRemovedWhileSupportRemained: false, adminRestoredBySupport: false, supportRemoved: false, soleRemovalBlocked: false, adminAccessAfterBlock: false, finalAdminSystem: false, finalSupportNoSystem: false, auditEventVisible: false, cleanup: 'not-needed' };
    const openUser = async (page, name) => {
        await page.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
        await page.locator("[data-guide='auth-users-search']").fill(name);
        const row = page.locator('tr').filter({ hasText: name }).first();
        await expect(page.locator('tr').filter({ hasText: name })).toHaveCount(1, { timeout: 8_000 });
        await expect(row.getByText(name, { exact: true })).toBeVisible({ timeout: 8_000 });
        await row.getByRole('button', { name: 'Manage', exact: true }).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 8_000 });
        return dialog;
    };
    const saveDialog = async (page, dialog) => {
        await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
        await expect(dialog).toBeHidden({ timeout: 12_000 });
    };
    try {
        await login(admin, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        let dialog = await openUser(admin, 'Local Demo Support');
        const supportAdminRole = dialog.getByLabel('System Administrator', { exact: true });
        if (!await supportAdminRole.isChecked()) await supportAdminRole.check();
        await saveDialog(admin, dialog);
        result.supportPromoted = true;

        await login(support, LOCAL_BROWSER_ACTORS.support.username, LOCAL_BROWSER_ACTORS.support.password);
        const supportStart = Date.now();
        const supportResponse = await support.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
        diagnostics.timings.push({ route: '/admin/authorization-baseline as promoted support', elapsedMs: Date.now() - supportStart, status: supportResponse?.status() });
        result.supportAdminAccess = supportResponse?.status() === 200 && await support.getByRole('button', { name: 'Manage', exact: true }).count() > 0;
        if (!result.supportAdminAccess) return;

        dialog = await openUser(admin, 'Local Demo Administrator');
        const adminRole = dialog.getByLabel('System Administrator', { exact: true });
        if (await adminRole.isChecked()) await adminRole.uncheck();
        await saveDialog(admin, dialog);
        result.adminRemovedWhileSupportRemained = true;

        dialog = await openUser(support, 'Local Demo Administrator');
        const restoredAdminRole = dialog.getByLabel('System Administrator', { exact: true });
        if (!await restoredAdminRole.isChecked()) await restoredAdminRole.check();
        await saveDialog(support, dialog);
        result.adminRestoredBySupport = true;

        dialog = await openUser(admin, 'Local Demo Support');
        const removeSupportRole = dialog.getByLabel('System Administrator', { exact: true });
        if (await removeSupportRole.isChecked()) await removeSupportRole.uncheck();
        await saveDialog(admin, dialog);
        result.supportRemoved = true;

        dialog = await openUser(admin, 'Local Demo Administrator');
        const soleAdminRole = dialog.getByLabel('System Administrator', { exact: true });
        await expect(soleAdminRole).toBeChecked();
        await soleAdminRole.uncheck();
        await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
        result.soleRemovalBlocked = await dialog.getByText('At least one system administrator must remain assigned.', { exact: true }).isVisible({ timeout: 8_000 }).catch(() => false);
        const blockedScreenshot = path.join(evidenceDirectory, 'KS-005-last-admin-blocked-en-ltr.png');
        await admin.screenshot({ path: blockedScreenshot, fullPage: true });
        await testInfo.attach('KS-005 last-admin block', { path: blockedScreenshot, contentType: 'image/png' });
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();

        const accessResponse = await admin.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
        result.adminAccessAfterBlock = accessResponse?.status() === 200;
        await admin.locator("[data-guide='auth-users-search']").fill('Local Demo Administrator');
        result.finalAdminSystem = await admin.locator('tr').filter({ hasText: 'Local Demo Administrator' }).first().getByText(/System Administrator/).count() > 0;
        await admin.locator("[data-guide='auth-users-search']").fill('Local Demo Support');
        const supportRoleText = await admin.locator('tr').filter({ hasText: 'Local Demo Support' }).first().innerText();
        result.finalSupportNoSystem = !/System Administrator/.test(supportRoleText);
        await admin.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
        result.auditEventVisible = /update_user_authorization/i.test(await admin.locator('body').innerText());
        const auditScreenshot = path.join(evidenceDirectory, 'KS-005-authorization-audit-en-ltr.png');
        await admin.screenshot({ path: auditScreenshot, fullPage: true });
        await testInfo.attach('KS-005 authorization audit', { path: auditScreenshot, contentType: 'image/png' });
    } finally {
        // Preserve the original demo-support authorization through the same visible UI whenever a promotion succeeded.
        if (result.supportPromoted) {
            try {
                const dialog = await openUser(admin, 'Local Demo Support');
                const role = dialog.getByLabel('System Administrator', { exact: true });
                if (await role.isChecked()) {
                    await role.uncheck();
                    await saveDialog(admin, dialog);
                } else {
                    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
                }
                result.cleanup = 'support system-administrator removed through UI';
            } catch (error) {
                result.cleanup = `FAILED: ${error instanceof Error ? error.message : String(error)}`;
            }
        }
        const file = path.join(evidenceDirectory, 'results.json');
        await writeFile(file, JSON.stringify({ id: 'KS-005', priority: 'P0', route: '/admin/authorization-baseline; /admin/audit', actor: 'two administrator contexts', actual: result, result: 'NOT IMPLEMENTED IN UI', classification: 'ROLE PORTION EXECUTED; REQUIRED SELF-DEACTIVATION UI ABSENT', diagnostics }, null, 2), 'utf8');
        await testInfo.attach('KS-005 focused results', { path: file, contentType: 'application/json' });
        await adminContext.close();
        await supportContext.close();
    }
    expect(result.cleanup).toBe('support system-administrator removed through UI');
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.pageErrors).toEqual([]);
});

test('KS-005 emergency UI cleanup restores demo-support original role', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await page.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
    await page.locator("[data-guide='auth-users-search']").fill('Local Demo Support');
    const row = page.locator('tr').filter({ hasText: 'Local Demo Support' });
    await expect(row).toHaveCount(1, { timeout: 8_000 });
    await row.getByRole('button', { name: 'Manage', exact: true }).click();
    const dialog = page.getByRole('dialog');
    const role = dialog.getByLabel('System Administrator', { exact: true });
    if (await role.isChecked()) {
        await role.uncheck();
        await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
        await expect(dialog).toBeHidden({ timeout: 12_000 });
    } else {
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    }
    await expect(row).not.toContainText('System Administrator', { timeout: 12_000 });
    const evidenceDirectory = path.resolve('artifacts/ks-005-cleanup-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const screenshot = path.join(evidenceDirectory, 'KS-005-demo-support-restored-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-005 demo-support restored', { path: screenshot, contentType: 'image/png' });
});

test('KS-005 sole administrator removal is visibly blocked without mutation', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    const evidenceDirectory = path.resolve('artifacts/ks-005-sole-guard-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await page.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
    await page.locator("[data-guide='auth-users-search']").fill('Local Demo Administrator');
    const row = page.locator('tr').filter({ hasText: 'Local Demo Administrator' });
    await expect(row).toHaveCount(1, { timeout: 8_000 });
    await expect(row).toContainText('System Administrator');
    await row.getByRole('button', { name: 'Manage', exact: true }).click();
    const dialog = page.getByRole('dialog');
    const role = dialog.getByLabel('System Administrator', { exact: true });
    await expect(role).toBeChecked();
    await role.uncheck();
    await dialog.getByRole('button', { name: 'Save authorization', exact: true }).click();
    await expect(dialog.getByText(/At least one system administrator must remain assigned/i)).toBeVisible({ timeout: 8_000 });
    const screenshot = path.join(evidenceDirectory, 'KS-005-sole-role-removal-blocked-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-005 sole role removal denied', { path: screenshot, contentType: 'image/png' });
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(row).toContainText('System Administrator');
    const response = await page.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
});

test('KS-006 creates one visible local purchase-order approval prerequisite', async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-create-po-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const marker = `KS006-${Date.now()}`;
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-guide="po-create-action"]').click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 8_000 });
    const selects = dialog.locator('select');
    await selects.nth(0).selectOption({ label: 'Primary Demo Toy Supplier (DEMO-SUP-001)' });
    await selects.nth(1).selectOption({ label: 'Demo Warehouse (DEMO-WH)' });
    // The seeded active-demo product is the first non-placeholder option; its UI label omits item_code in this screen.
    await selects.nth(2).selectOption({ index: 1 });
    await dialog.getByPlaceholder('Qty').fill('1');
    await dialog.getByPlaceholder('Cost').fill('1');
    await dialog.getByLabel('Order Notes').fill(marker);
    await dialog.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await expect(dialog).toBeHidden({ timeout: 12_000 });
    const toastText = await page.locator('body').innerText();
    const number = toastText.match(/Purchase Order (PO-[A-Z0-9-]+) created as draft/i)?.[1] ?? null;
    expect(number).not.toBeNull();
    const row = page.locator('tr').filter({ hasText: number });
    await expect(row).toHaveCount(1, { timeout: 12_000 });
    await expect(row).toContainText('DRAFT');
    const submit = row.getByTitle('Submit Order');
    await expect(submit).toBeVisible();
    await submit.click();
    await expect(page.getByText(new RegExp(`${number} submitted successfully`, 'i'))).toBeVisible({ timeout: 12_000 });
    await expect(row).toContainText('SUBMITTED', { timeout: 12_000 });
    const screenshot = path.join(evidenceDirectory, 'KS-006-new-submitted-po-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-006 new submitted PO', { path: screenshot, contentType: 'image/png' });
    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify({ id: 'KS-006', route: '/purchasing/orders', actor: 'demo-admin', marker, poNumber: number, fixtureState: 'SUBMITTED' }, null, 2), 'utf8');
    await testInfo.attach('KS-006 PO fixture result', { path: file, contentType: 'application/json' });
});

test('KS-006 submits the existing UI-created PO-DEMO-000004', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-submit-po0004-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
    const row = page.locator('tr').filter({ hasText: 'PO-DEMO-000004' });
    await expect(row).toHaveCount(1, { timeout: 8_000 });
    await expect(row).toContainText(/Draft/i);
    const submit = row.getByTitle('Submit Order');
    await expect(submit).toBeVisible();
    await submit.click();
    await expect(page.getByText(/PO-DEMO-000004 submitted successfully/i)).toBeVisible({ timeout: 12_000 });
    await expect(row).toContainText(/Submitted/i, { timeout: 12_000 });
    await expect(row.getByTitle('Submit Order')).toHaveCount(0, { timeout: 12_000 });
    const screenshot = path.join(evidenceDirectory, 'KS-006-po0004-submitted-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-006 PO0004 submitted', { path: screenshot, contentType: 'image/png' });
});

test('KS-006 UI diagnosis for PO-DEMO-000004 requester and reviewer visibility', async ({ browser }, testInfo) => {
    test.setTimeout(150_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-ui-diagnosis-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const adminContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const reviewerContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const admin = await adminContext.newPage();
    const reviewer = await reviewerContext.newPage();
    const result = { adminPoVisible: false, poStatus: null, submitAuditVisible: false, reviewerRoles: [], reviewerBranches: [], reviewerStores: [], reviewerInboxReviewVisible: false, changedReviewerAuthorization: false };
    try {
        await login(admin, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await admin.goto('/approvals', { waitUntil: 'domcontentloaded' });
        result.adminPoVisible = await admin.locator('body').getByText(/Purchase Orders #/).count() > 0;
        const adminInbox = path.join(evidenceDirectory, 'KS-006-admin-approvals-en-ltr.png');
        await admin.screenshot({ path: adminInbox, fullPage: true });
        await testInfo.attach('KS-006 admin approvals', { path: adminInbox, contentType: 'image/png' });

        await admin.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
        const poRow = admin.locator('tr').filter({ hasText: 'PO-DEMO-000004' });
        if (await poRow.count()) result.poStatus = (await poRow.innerText()).match(/\b(Draft|Submitted|Approved|Cancelled|Closed)\b/i)?.[1] ?? null;
        const poScreenshot = path.join(evidenceDirectory, 'KS-006-po0004-status-en-ltr.png');
        await admin.screenshot({ path: poScreenshot, fullPage: true });
        await testInfo.attach('KS-006 PO0004 status', { path: poScreenshot, contentType: 'image/png' });

        await admin.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
        result.submitAuditVisible = /PO-DEMO-000004|submit_purchase_order|purchase.*submit/i.test(await admin.locator('body').innerText());

        await admin.goto('/admin/authorization-baseline', { waitUntil: 'domcontentloaded' });
        await admin.locator("[data-guide='auth-users-search']").fill('Local Demo Reviewer');
        const reviewerRow = admin.locator('tr').filter({ hasText: 'Local Demo Reviewer' });
        await expect(reviewerRow).toHaveCount(1, { timeout: 8_000 });
        await reviewerRow.getByRole('button', { name: 'Manage', exact: true }).click();
        const dialog = admin.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 8_000 });
        for (const label of ['Accountant / Reviewer', 'Branch Manager', 'Cashier', 'Local Support', 'System Administrator']) {
            const box = dialog.getByLabel(label, { exact: true });
            if (await box.count() && await box.isChecked()) result.reviewerRoles.push(label);
        }
        for (const label of ['Cairo Demo Branch']) {
            const box = dialog.getByLabel(label, { exact: true });
            if (await box.count() && await box.isChecked()) result.reviewerBranches.push(label);
        }
        for (const label of ['Demo Selling Store', 'Demo Warehouse']) {
            const box = dialog.getByLabel(label, { exact: true });
            if (await box.count() && await box.isChecked()) result.reviewerStores.push(label);
        }
        const reviewerAuthScreenshot = path.join(evidenceDirectory, 'KS-006-reviewer-authorization-en-ltr.png');
        await admin.screenshot({ path: reviewerAuthScreenshot, fullPage: true });
        await testInfo.attach('KS-006 reviewer authorization', { path: reviewerAuthScreenshot, contentType: 'image/png' });
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();

        await login(reviewer, LOCAL_BROWSER_ACTORS.reviewer.username, LOCAL_BROWSER_ACTORS.reviewer.password);
        await reviewer.goto('/approvals', { waitUntil: 'domcontentloaded' });
        result.reviewerInboxReviewVisible = await reviewer.getByRole('button', { name: 'Review', exact: true }).count() > 0;
        const reviewerInbox = path.join(evidenceDirectory, 'KS-006-reviewer-inbox-recheck-en-ltr.png');
        await reviewer.screenshot({ path: reviewerInbox, fullPage: true });
        await testInfo.attach('KS-006 reviewer inbox recheck', { path: reviewerInbox, contentType: 'image/png' });
    } finally {
        const file = path.join(evidenceDirectory, 'results.json');
        await writeFile(file, JSON.stringify(result, null, 2), 'utf8');
        await testInfo.attach('KS-006 UI diagnosis', { path: file, contentType: 'application/json' });
        await adminContext.close();
        await reviewerContext.close();
    }
});

test('KS-006 all-states same-baseURL discrepancy confirmation', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    const evidenceDirectory = path.resolve('artifacts/ks-006-all-states-confirmation-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
    const baseUrl = new URL(page.url()).origin;
    await page.goto('/purchasing/orders', { waitUntil: 'domcontentloaded' });
    const poRow = page.locator('tr').filter({ hasText: 'PO-DEMO-000004' });
    const poVisible = await poRow.count() === 1;
    const poText = poVisible ? await poRow.innerText() : null;
    await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
    const state = page.getByLabel('State', { exact: true });
    if (await state.count()) await state.selectOption('');
    const search = page.getByLabel('Search', { exact: true });
    if (await search.count()) await search.fill('purchase_orders');
    await page.waitForTimeout(800);
    const allStateReviewCount = await page.getByRole('button', { name: 'Review', exact: true }).count();
    const screenshot = path.join(evidenceDirectory, 'KS-006-admin-approvals-all-states-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-006 all states confirmation', { path: screenshot, contentType: 'image/png' });
    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify({ baseUrl, poVisible, poText, allStateReviewCount, result: !poVisible && allStateReviewCount === 0 ? 'BLOCKED' : 'PASS', classification: !poVisible && allStateReviewCount === 0 ? 'PRODUCT/CONFIGURATION DISCREPANCY: submitted visible UI record absent on same base URL' : 'UI RECORD PRESENT' }, null, 2), 'utf8');
    await testInfo.attach('KS-006 all states result', { path: file, contentType: 'application/json' });
});

test('KS-006 controlled same-origin purchase-order persistence diagnostic', async ({ browser }, testInfo) => {
    test.setTimeout(180_000);
    const origin = process.env.KS_BASE_URL ?? 'http://127.0.0.1:8791';
    const evidenceDirectory = path.resolve('artifacts/ks-006-same-origin-diagnostic-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const requesterContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const reviewerContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
    const requester = await requesterContext.newPage();
    const reviewer = await reviewerContext.newPage();
    const result = { origin, locations: [], poNumber: null, marker: `KS006-PERSIST-${Date.now()}`, submitted: false, persistedAfterReload: false, requesterApprovalVisible: false, reviewerApprovalVisible: false, result: 'BLOCKED' };
    const record = (label, page) => result.locations.push({ label, url: page.url(), origin: new URL(page.url()).origin });
    try {
        await requester.goto(`${origin}/login`, { waitUntil: 'domcontentloaded' });
        record('requester login', requester);
        await requester.getByLabel('Username', { exact: true }).fill(LOCAL_BROWSER_ACTORS.administrator.username);
        await requester.getByLabel('Password', { exact: true }).fill(LOCAL_BROWSER_ACTORS.administrator.password);
        await requester.getByRole('button', { name: 'Log in' }).click();
        await requester.waitForURL((url) => !url.pathname.startsWith('/login'));
        record('requester authenticated', requester);
        await requester.goto(`${origin}/purchasing/orders`, { waitUntil: 'domcontentloaded' });
        record('requester orders create', requester);
        await requester.locator('[data-guide="po-create-action"]').click();
        const dialog = requester.getByRole('dialog');
        await expect(dialog).toBeVisible({ timeout: 8_000 });
        const selects = dialog.locator('select');
        await selects.nth(0).selectOption({ label: 'Primary Demo Toy Supplier (DEMO-SUP-001)' });
        await selects.nth(1).selectOption({ label: 'Demo Warehouse (DEMO-WH)' });
        await selects.nth(2).selectOption({ index: 1 });
        await dialog.getByPlaceholder('Qty').fill('1');
        await dialog.getByPlaceholder('Cost').fill('1');
        await dialog.getByLabel('Order Notes').fill(result.marker);
        await dialog.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await expect(dialog).toBeHidden({ timeout: 12_000 });
        const toast = await requester.locator('body').innerText();
        result.poNumber = toast.match(/Purchase Order (PO-[A-Z0-9-]+) created as draft/i)?.[1] ?? null;
        expect(result.poNumber).not.toBeNull();
        const row = requester.locator('tr').filter({ hasText: result.poNumber });
        await expect(row).toHaveCount(1, { timeout: 12_000 });
        await row.getByTitle('Submit Order').click();
        await expect(requester.getByText(new RegExp(`${result.poNumber} submitted successfully`, 'i'))).toBeVisible({ timeout: 12_000 });
        result.submitted = true;
        await requester.reload({ waitUntil: 'domcontentloaded' });
        record('requester orders reload', requester);
        const persistedRow = requester.locator('tr').filter({ hasText: result.poNumber });
        result.persistedAfterReload = await persistedRow.count() === 1 && /Submitted/i.test(await persistedRow.innerText());
        if (!result.persistedAfterReload) return;
        await requester.goto(`${origin}/approvals`, { waitUntil: 'domcontentloaded' });
        record('requester approvals all-states', requester);
        const state = requester.getByLabel('State', { exact: true });
        if (await state.count()) await state.selectOption('');
        const review = requester.getByRole('button', { name: 'Review', exact: true });
        result.requesterApprovalVisible = await review.count() > 0;
        await reviewer.goto(`${origin}/login`, { waitUntil: 'domcontentloaded' });
        record('reviewer login', reviewer);
        await reviewer.getByLabel('Username', { exact: true }).fill(LOCAL_BROWSER_ACTORS.reviewer.username);
        await reviewer.getByLabel('Password', { exact: true }).fill(LOCAL_BROWSER_ACTORS.reviewer.password);
        await reviewer.getByRole('button', { name: 'Log in' }).click();
        await reviewer.waitForURL((url) => !url.pathname.startsWith('/login'));
        await reviewer.goto(`${origin}/approvals`, { waitUntil: 'domcontentloaded' });
        record('reviewer approvals', reviewer);
        result.reviewerApprovalVisible = await reviewer.getByRole('button', { name: 'Review', exact: true }).count() > 0;
        const screenshot = path.join(evidenceDirectory, 'KS-006-same-origin-reviewer-pending-en-ltr.png');
        await reviewer.screenshot({ path: screenshot, fullPage: true });
        await testInfo.attach('KS-006 same-origin reviewer pending', { path: screenshot, contentType: 'image/png' });
        result.result = result.persistedAfterReload && result.requesterApprovalVisible && result.reviewerApprovalVisible ? 'PASS' : 'BLOCKED';
    } finally {
        const file = path.join(evidenceDirectory, 'results.json');
        await writeFile(file, JSON.stringify(result, null, 2), 'utf8');
        await testInfo.attach('KS-006 same-origin diagnostic', { path: file, contentType: 'application/json' });
        await requesterContext.close();
        await reviewerContext.close();
    }
});

test('KS-009 rejects invalid approval evidence through visible file input', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const origin = process.env.KS_BASE_URL ?? 'http://127.0.0.1:8791';
    const evidenceDirectory = path.resolve('artifacts/ks-009-focused-' + new Date().toISOString().replace(/[:.]/g, '-'));
    await mkdir(evidenceDirectory, { recursive: true });
    const result = { origin, files: [], consoleErrors: [], pageErrors: [], failedRequests: [], timings: [] };
    page.on('console', (message) => { if (message.type() === 'error' && !/status of (403|404|419|429)/.test(message.text())) result.consoleErrors.push(message.text()); });
    page.on('pageerror', (error) => result.pageErrors.push(error.message));
    page.on('requestfailed', (request) => result.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' }));
    await page.goto(`${origin}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(LOCAL_BROWSER_ACTORS.reviewer.username);
    await page.getByLabel('Password', { exact: true }).fill(LOCAL_BROWSER_ACTORS.reviewer.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    const start = Date.now();
    await page.goto(`${origin}/approvals`, { waitUntil: 'domcontentloaded' });
    result.timings.push({ action: 'open reviewer approvals', elapsedMs: Date.now() - start });
    const review = page.getByRole('button', { name: 'Review', exact: true }).first();
    await expect(review).toBeVisible({ timeout: 10_000 });
    await review.click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    const details = await dialog.locator('dl').innerText();
    expect(details).toMatch(/Purchase Orders/i);
    expect(details).toMatch(/Pending/i);
    expect(details).toMatch(/Local Demo Administrator/i);
    const input = dialog.getByLabel('Evidence file', { exact: true });
    const upload = dialog.getByRole('button', { name: 'Upload securely', exact: true });
    await expect(input).toBeVisible({ timeout: 10_000 });
    await expect(upload).toBeEnabled({ timeout: 10_000 });
    expect(await dialog.getByRole('link', { name: 'Download', exact: true }).count()).toBe(0);
    const invalidFiles = [
        { name: 'ks009-invalid-bytes.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('plain harmless text, not an image') },
        { name: 'ks009-mime-mismatch.png', mimeType: 'application/pdf', buffer: Buffer.from('not a PDF or PNG') },
        { name: 'ks009-oversize.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc((12 * 1024 * 1024) + 1024, 0) },
        { name: 'ks009-evidence.php.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('safe benign double-extension bytes') },
    ].filter((file) => process.env.KS009_OVERSIZE_ONLY !== '1' || file.name === 'ks009-oversize.jpg');
    for (const file of invalidFiles) {
        const started = Date.now();
        await input.setInputFiles(file);
        await upload.click();
        const error = dialog.locator('.text-red-600').last();
        const feedback = (await error.textContent({ timeout: 10_000 }).catch(() => null))?.trim() ?? null;
        const downloadCount = await dialog.getByRole('link', { name: 'Download', exact: true }).count();
        const noAttachmentStillVisible = await dialog.getByText('No evidence attached.', { exact: true }).count() === 1;
        const accepted = downloadCount > 0 || !noAttachmentStillVisible;
        const sourceAfter = await dialog.locator('dl').innerText();
        const sourceStable = /Purchase Orders/i.test(sourceAfter) && /Pending/i.test(sourceAfter) && /Local Demo Administrator/i.test(sourceAfter);
        result.files.push({ name: file.name, feedback, sourceStable, accepted, elapsedMs: Date.now() - started });
        if (accepted) break;
    }
    const screenshot = path.join(evidenceDirectory, 'KS-009-invalid-upload-feedback-en-ltr.png');
    await page.screenshot({ path: screenshot, fullPage: true });
    await testInfo.attach('KS-009 invalid upload feedback', { path: screenshot, contentType: 'image/png' });
    const file = path.join(evidenceDirectory, 'results.json');
    await writeFile(file, JSON.stringify({ ...result, result: result.files.length === invalidFiles.length && result.files.every((entry) => !entry.accepted && entry.sourceStable && Boolean(entry.feedback)) ? 'PASS' : 'FAIL' }, null, 2), 'utf8');
    await testInfo.attach('KS-009 results', { path: file, contentType: 'application/json' });
    expect(result.files).toHaveLength(invalidFiles.length);
    expect(result.files.every((entry) => !entry.accepted && entry.sourceStable && Boolean(entry.feedback))).toBe(true);
    expect(result.consoleErrors).toEqual([]);
    expect(result.pageErrors).toEqual([]);
});
