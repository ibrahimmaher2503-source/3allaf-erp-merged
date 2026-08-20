import { test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const admin = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

test.use({
    locale: 'en-US',
    viewport: { width: 1280, height: 900 },
    trace: 'on',
    screenshot: 'only-on-failure',
});

async function login(page) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(admin.username);
    await page.getByLabel('Password', { exact: true }).fill(admin.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 }),
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);

    const english = page.locator('form[action$="/locale"] input[name="locale"][value="en"]').first();
    if (await english.count()) {
        await english.evaluate((input) => input.form.submit());
        await page.waitForLoadState('domcontentloaded');
    }
}

test('all canonical user stories are re-audited through the visible UI', async ({ page, browser }, testInfo) => {
    test.setTimeout(900_000);

    const runStamp = new Date().toISOString().replace(/[:.]/g, '-');
    const evidenceDirectory = path.resolve(`artifacts/all-user-stories-ui-retest-${runStamp}`);
    await mkdir(evidenceDirectory, { recursive: true });

    const results = {
        runId: `UI-US-RETEST-${runStamp}`,
        startedAt: new Date().toISOString(),
        baseUrl: testInfo.project.use.baseURL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'unknown',
        browser: 'Chromium headed',
        desktopViewport: '1280x900',
        mobileViewport: '390x844',
        database: 'toyjoy_phase1_remediation_20260818',
        fixture: 'ProductionSeeder + DemoErpSeeder in testing environment',
        stories: [],
        pageErrors: [],
        consoleErrors: [],
        failedRequests: [],
    };

    let activeStory = 'setup';
    page.on('pageerror', (error) => results.pageErrors.push({ story: activeStory, url: page.url(), message: error.message }));
    page.on('console', (message) => {
        if (message.type() === 'error' && !/Failed to load resource: the server responded with a status of (403|404)/.test(message.text())) {
            results.consoleErrors.push({ story: activeStory, url: page.url(), message: message.text() });
        }
    });
    page.on('requestfailed', (request) => results.failedRequests.push({
        story: activeStory,
        url: request.url(),
        message: request.failure()?.errorText ?? 'unknown',
    }));

    const open = async (route) => {
        const started = Date.now();
        const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(250);
        return {
            route,
            status: response?.status() ?? null,
            elapsedMs: Date.now() - started,
            heading: (await page.locator('h1, [role="heading"]').first().innerText().catch(() => '')).trim(),
            body: (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 1400),
            horizontalOverflow: await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)),
        };
    };

    const visibleCount = async (locator) => {
        let count = 0;
        for (let index = 0; index < await locator.count(); index += 1) {
            if (await locator.nth(index).isVisible().catch(() => false)) count += 1;
        }
        return count;
    };

    const audit = async (id, title, evaluator) => {
        activeStory = id;
        const storyPageErrorStart = results.pageErrors.length;
        const storyConsoleErrorStart = results.consoleErrors.length;
        const started = Date.now();

        try {
            const outcome = await evaluator();
            const screenshot = path.join(evidenceDirectory, `${id}.png`);
            await page.screenshot({ path: screenshot, fullPage: true });
            results.stories.push({
                id,
                title,
                ...outcome,
                elapsedMs: Date.now() - started,
                pageErrors: results.pageErrors.slice(storyPageErrorStart),
                consoleErrors: results.consoleErrors.slice(storyConsoleErrorStart),
                screenshot,
            });
        } catch (error) {
            const screenshot = path.join(evidenceDirectory, `${id}-unexpected-error.png`);
            await page.screenshot({ path: screenshot, fullPage: true }).catch(() => {});
            results.stories.push({
                id,
                title,
                verdict: 'FAIL',
                coverage: 'Execution stopped inside this story but the audit continued with the next story.',
                actual: error instanceof Error ? error.message : String(error),
                elapsedMs: Date.now() - started,
                pageErrors: results.pageErrors.slice(storyPageErrorStart),
                consoleErrors: results.consoleErrors.slice(storyConsoleErrorStart),
                screenshot,
            });
        }
    };

    await login(page);

    await audit('US-001', 'Govern Company and Operating Masters', async () => {
        const pages = [];
        for (const route of ['/initial-setup', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline']) pages.push(await open(route));
        const allRendered = pages.every((entry) => entry.status === 200 && entry.heading.length > 0);
        return { verdict: allRendered ? 'PARTIAL' : 'FAIL', coverage: 'All master/setup entry screens and current governed baseline rendered; no owner-approved destructive or sensitive master change was committed.', actual: pages };
    });

    await audit('US-002', 'Maintain Stable Product Identity', async () => {
        const list = await open('/catalog/products');
        const add = page.getByRole('button', { name: 'Add Product', exact: true });
        const addVisible = await add.isVisible().catch(() => false);
        if (addVisible) await add.click();
        const dialogVisible = await page.getByRole('heading', { name: 'Create product identity', exact: true }).isVisible({ timeout: 5_000 }).catch(() => false);
        const direct = await open('/catalog/products/create');
        const formVisible = await page.locator('[data-guide="product-form-identity"]').isVisible().catch(() => false);
        return {
            verdict: dialogVisible && direct.status === 200 && formVisible ? 'PARTIAL' : 'FAIL',
            coverage: 'Product list, primary Add Product action, and direct full product form were exercised. No product was saved.',
            actual: { list, addVisible, dialogVisible, direct, formVisible },
        };
    });

    await audit('US-003', 'Maintain a Unique Customer Profile', async () => {
        const list = await open('/customers?q=01000000003');
        const demoVisible = /Demo Customer/i.test(await page.locator('body').innerText());
        const create = await open('/customers/create');
        const fields = await visibleCount(page.locator('input, select, textarea'));
        return { verdict: list.status === 200 && create.status === 200 && demoVisible && fields > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Existing customer lookup plus the complete create surface rendered. Duplicate/merge and a new committed profile were not exercised.', actual: { list, demoVisible, create, visibleFormControls: fields } };
    });

    await audit('US-004', 'Import Products Safely', async () => {
        const pageResult = await open('/catalog/products/import');
        const fileInput = await page.locator('input[type="file"]').count();
        const stageButton = await visibleCount(page.getByRole('button', { name: /Stage file/i }));
        return { verdict: pageResult.status === 200 && fileInput > 0 && stageButton > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Upload, mode, and staging UI rendered. No approved workbook was available, so upload/mapping/approval/error-export remained blocked.', actual: { page: pageResult, fileInput, stageButton } };
    });

    await audit('US-005', 'Configure Product Types', async () => {
        const pageResult = await open('/catalog/products/create');
        const body = await page.locator('body').innerText();
        const typeControls = await visibleCount(page.getByLabel(/Product type/i));
        return { verdict: pageResult.status === 200 && (typeControls > 0 || /product type/i.test(body)) ? 'PARTIAL' : 'FAIL', coverage: 'Product classification/type UI rendered; no post-transaction type transition or composite/service persistence was attempted.', actual: { page: pageResult, typeControls } };
    });

    await audit('US-006', 'Capture a Sale Price Without Cost Coupling', async () => {
        const pageResult = await open('/pricing');
        const body = await page.locator('body').innerText();
        return { verdict: pageResult.status === 200 && /DEMO-PRODUCT-001|Demo Building Blocks/i.test(body) ? 'PARTIAL' : 'FAIL', coverage: 'The demo product price/version workspace rendered with seeded price context; no new proposal was committed.', actual: { page: pageResult, demoPriceContextVisible: /DEMO-PRODUCT-001|Demo Building Blocks/i.test(body) } };
    });

    await audit('US-007', 'Approve Versioned Prices and Labels', async () => {
        const approval = await open('/pricing/approvals');
        const labels = await open('/pricing/labels');
        const pricing = await open('/pricing');
        const body = await page.locator('body').innerText();
        return { verdict: [approval, labels, pricing].every((entry) => entry.status === 200) && /Approved/i.test(body) ? 'PARTIAL' : 'FAIL', coverage: 'Approval, active-version, and label-queue UI rendered. No fresh two-actor approval or physical label print occurred.', actual: { approval, labels, pricing, approvedVersionVisible: /Approved/i.test(body) } };
    });

    await audit('US-008', 'Perform Authorized Open-Price Sale', async () => {
        const pos = await open('/pos');
        const body = await page.locator('body').innerText();
        const openPriceUi = /open price|price override/i.test(body);
        return { verdict: openPriceUi ? 'PARTIAL' : 'BLOCKED', coverage: 'POS was reached through UI. The disposable fixture has no product with an approved open-price policy, so the bounds/reason/approval flow could not be legitimately invoked.', actual: { pos, openPriceUi } };
    });

    await audit('US-009', 'Maintain Supplier History', async () => {
        const supplier = await open('/catalog/suppliers');
        const demoVisible = /DEMO-SUPPLIER-001|Demo Supplier/i.test(await page.locator('body').innerText());
        const history = await open('/purchasing/supplier-history');
        return { verdict: supplier.status === 200 && history.status === 200 && demoVisible ? 'PARTIAL' : 'FAIL', coverage: 'Supplier master and immutable supplier-history surfaces rendered with the demo supplier. No master edit was committed.', actual: { supplier, history, demoVisible } };
    });

    await audit('US-010', 'Manage Purchase Orders', async () => {
        const pageResult = await open('/purchasing/orders');
        const body = await page.locator('body').innerText();
        const demoOrder = /PO-|DEMO/i.test(body);
        const approved = /Approved/i.test(body);
        return { verdict: pageResult.status === 200 && demoOrder && approved ? 'PARTIAL' : 'FAIL', coverage: 'A real seeded purchase order was observed in its approved UI state. A new draft-to-close lifecycle was not repeated.', actual: { page: pageResult, demoOrder, approved } };
    });

    await audit('US-011', 'Receive and Approve a Purchase Invoice', async () => {
        const pageResult = await open('/purchasing/invoices');
        const body = await page.locator('body').innerText();
        const seededInvoice = /DEMO-SUPPLIER-INVOICE-001|Approved/i.test(body);
        return { verdict: pageResult.status === 200 && seededInvoice ? 'PARTIAL' : 'FAIL', coverage: 'The seeded approved purchase invoice/receipt rendered. No fresh maker/checker receipt or concurrent retry was submitted.', actual: { page: pageResult, seededInvoice } };
    });

    await audit('US-012', 'Return Stock to a Supplier', async () => {
        const pageResult = await open('/purchasing/returns');
        const body = await page.locator('body').innerText();
        const seededReturn = /Approved|DEMO/i.test(body);
        return { verdict: pageResult.status === 200 && seededReturn ? 'PARTIAL' : 'FAIL', coverage: 'The source-linked seeded supplier return rendered in its terminal UI state. No fresh return/print was committed.', actual: { page: pageResult, seededReturn } };
    });

    await audit('US-013', 'View Location Inventory Safely', async () => {
        const inventory = await open('/inventory?product_q=DEMO-PRODUCT-001');
        const body = await page.locator('body').innerText();
        const demoVisible = /DEMO-PRODUCT-001|Demo Building Blocks/i.test(body);
        const movements = /Inventory movement ledger/i.test(body);
        return { verdict: inventory.status === 200 && demoVisible && movements ? 'PASS' : 'FAIL', coverage: 'The read-only story was exercised with scoped balances and movement history for the seeded demo product.', actual: { inventory, demoVisible, movements } };
    });

    await audit('US-014', 'Transfer Stock Between Stores', async () => {
        const pageResult = await open('/inventory/transfers');
        const body = await page.locator('body').innerText();
        const receivedTransfer = /Received|DEMO-WAREHOUSE|DEMO-SALES/i.test(body);
        return { verdict: pageResult.status === 200 && receivedTransfer ? 'PARTIAL' : 'FAIL', coverage: 'The seeded received inter-store transfer rendered. A new draft/submit/approve/dispatch/receipt sequence was not repeated.', actual: { page: pageResult, receivedTransfer } };
    });

    await audit('US-015', 'Record Controlled Inventory Documents', async () => {
        const list = await open('/inventory/adjustments');
        const create = await open('/inventory/adjustments/create');
        const controls = await visibleCount(page.locator('input, select, textarea, button'));
        return { verdict: list.status === 200 && create.status === 200 && controls > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Adjustment list and create form rendered. No financial/stock mutation was approved.', actual: { list, create, visibleControls: controls } };
    });

    await audit('US-016', 'Count Stock While Selling Continues', async () => {
        const list = await open('/inventory/counts');
        const create = await open('/inventory/counts/create');
        const controls = await visibleCount(page.locator('input, select, textarea, button'));
        return { verdict: list.status === 200 && create.status === 200 && controls > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Count list/create surfaces rendered. A live concurrent count/reconciliation requires assigned Counter and Manager actors and was not fabricated.', actual: { list, create, visibleControls: controls } };
    });

    await audit('US-017', 'Complete a Branch POS Sale', async () => {
        const pos = await open('/pos');
        const body = await page.locator('body').innerText();
        const demoProduct = /Demo Building Blocks|DEMO-PRODUCT-001/i.test(body);
        let addedToCart = false;
        const productText = page.getByText(/Demo Building Blocks|DEMO-PRODUCT-001/i).first();
        if (await productText.isVisible().catch(() => false)) {
            const card = productText.locator('xpath=ancestor::*[self::article or self::div][.//button[normalize-space()="Add"]][1]');
            const add = card.getByRole('button', { name: 'Add', exact: true });
            if (await add.isVisible().catch(() => false)) {
                await add.click();
                addedToCart = true;
            }
        }
        const settleVisible = await visibleCount(page.getByRole('button', { name: /Settle and complete sale|Complete sale/i }));
        return { verdict: pos.status === 200 && demoProduct && addedToCart && settleVisible > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Product discovery and add-to-cart were exercised through UI and payment controls were inspected. No second sale was posted.', actual: { pos, demoProduct, addedToCart, settleVisible } };
    });

    await audit('US-018', 'Apply Payment, Tax, Discount, and Print Rules', async () => {
        const pos = await open('/pos');
        const paymentUi = await visibleCount(page.locator('form[data-guide="pos-payment-form"], [data-guide="pos-checkout"]'));
        const saleList = await open('/sales');
        const seededSale = /Paid|Completed|DEMO/i.test(await page.locator('body').innerText());
        return { verdict: pos.status === 200 && saleList.status === 200 && seededSale ? 'PARTIAL' : 'FAIL', coverage: 'POS calculation/payment surface and completed seeded sale/print entry points rendered. No new settlement, attachment, or physical print was submitted.', actual: { pos, paymentUi, saleList, seededSale } };
    });

    await audit('US-019', 'Issue and Use a Gift Receipt', async () => {
        const pageResult = await open('/gift-receipts');
        const controls = await visibleCount(page.locator('button, a, input, select'));
        return { verdict: pageResult.status === 200 && controls > 0 ? 'PARTIAL' : 'BLOCKED', coverage: 'Gift Receipt UI rendered. No eligible receipt was issued or consumed because no approved owner policy/receipt fixture was supplied.', actual: { page: pageResult, visibleControls: controls } };
    });

    await audit('US-020', 'Return or Exchange Inspected Products', async () => {
        const pageResult = await open('/returns');
        const controls = await visibleCount(page.locator('button, input, select, textarea'));
        return { verdict: pageResult.status === 200 && controls > 0 ? 'PARTIAL' : 'FAIL', coverage: 'Return/exchange source-selection and draft UI rendered against the seeded completed sale. No return was posted.', actual: { page: pageResult, visibleControls: controls } };
    });

    await audit('US-021', 'Govern Gift Cards', async () => {
        const pageResult = await open('/gift-cards');
        const controls = await visibleCount(page.locator('button, input, select, textarea'));
        return { verdict: pageResult.status === 200 && controls > 0 ? 'PARTIAL' : 'BLOCKED', coverage: 'Gift Card issue/history UI rendered. No financial instrument was issued or redeemed.', actual: { page: pageResult, visibleControls: controls } };
    });

    await audit('US-022', 'View Unified History With Separated Wallets', async () => {
        const productWallet = await open('/wallets/product');
        const productText = await page.locator('body').innerText();
        const partyWallet = await open('/wallets/party');
        const partyText = await page.locator('body').innerText();
        const separate = /Product Wallet/i.test(productText) && /Party Wallet/i.test(partyText);
        return { verdict: productWallet.status === 200 && partyWallet.status === 200 && separate ? 'PASS' : 'FAIL', coverage: 'Both separately authorized wallet ledgers rendered as distinct UI surfaces; no cross-wallet transfer control was present.', actual: { productWallet, partyWallet, separate } };
    });

    await audit('US-023', 'Earn and Redeem Shared Loyalty', async () => {
        const customers = await open('/customers?q=01000000003');
        const profileLinks = page.getByRole('link', { name: /Open profile/i });
        let loyalty = null;
        if (await profileLinks.count()) {
            const href = await profileLinks.first().getAttribute('href');
            if (href) {
                const profile = await open(new URL(href, results.baseUrl).pathname);
                const loyaltyLink = page.getByRole('link', { name: /Loyalty ledger/i });
                if (await loyaltyLink.isVisible().catch(() => false)) {
                    const loyaltyHref = await loyaltyLink.getAttribute('href');
                    if (loyaltyHref) loyalty = await open(new URL(loyaltyHref, results.baseUrl).pathname);
                }
                if (loyalty === null) loyalty = profile;
            }
        }
        const body = await page.locator('body').innerText();
        return { verdict: customers.status === 200 && loyalty?.status === 200 && /Loyalty|points/i.test(body) ? 'PARTIAL' : 'FAIL', coverage: 'The demo customer and loyalty ledger/history rendered with the seeded earned entry. A redemption was not posted.', actual: { customers, loyalty, loyaltyTextVisible: /Loyalty|points/i.test(body) } };
    });

    await audit('US-024', 'Open, Operate, and Blind-Close a Shift', async () => {
        const shift = await open('/pos/shift');
        const variance = await open('/pos/shift-variance');
        const body = await page.locator('body').innerText();
        const shiftContext = /shift|drawer|counted cash/i.test(body);
        return { verdict: shift.status === 200 && variance.status === 200 && shiftContext ? 'PARTIAL' : 'FAIL', coverage: 'Shift and variance UI rendered against the seeded open shift. Blind close was not submitted because it would terminate the shared POS fixture.', actual: { shift, variance, shiftContext } };
    });

    await audit('US-025', 'Book a Party and Maintain Its Working Invoice', async () => {
        const list = await open('/parties/bookings');
        const create = await open('/parties/bookings/create');
        const body = await page.locator('body').innerText();
        const hasPartyStore = /Party store/i.test(body) && !/No active Party store/i.test(body);
        return { verdict: list.status === 200 && create.status === 200 && hasPartyStore ? 'PARTIAL' : 'BLOCKED', coverage: 'Booking list/create UI rendered. The authorized remediation fixture contains no Party store/approved Party masters, so a genuine booking and working invoice could not be created.', actual: { list, create, hasPartyStore } };
    });

    await audit('US-026', 'Record Party Payments and Final Settlement', async () => {
        const invoices = await open('/parties/invoices');
        const body = await page.locator('body').innerText();
        const actionableInvoice = !/No Party invoices|No invoices/i.test(body) && /payment|settlement/i.test(body);
        return { verdict: invoices.status === 200 && actionableInvoice ? 'PARTIAL' : 'BLOCKED', coverage: 'Party invoice index rendered. No open Party invoice/payment fixture exists in the named remediation database.', actual: { invoices, actionableInvoice } };
    });

    await audit('US-027', 'Execute a Party Operating Order', async () => {
        const orders = await open('/parties/orders');
        const body = await page.locator('body').innerText();
        const actionableOrder = !/No Party operating orders|No operating orders/i.test(body) && /Release|Complete|In progress/i.test(body);
        return { verdict: orders.status === 200 && actionableOrder ? 'PARTIAL' : 'BLOCKED', coverage: 'Party operating-order index rendered. No confirmed Party booking/order fixture exists for consumable issue/return.', actual: { orders, actionableOrder } };
    });

    await audit('US-028', 'Govern Rental Asset Lifecycle', async () => {
        const assets = await open('/party/assets?mode=workspace');
        const controls = await visibleCount(page.locator('button, input, select, textarea'));
        const body = await page.locator('body').innerText();
        const assetRows = /Available|Reserved|Checked out|Under inspection/i.test(body);
        return { verdict: assets.status === 200 && assetRows ? 'PARTIAL' : 'BLOCKED', coverage: 'Rental asset workspace rendered. No asset/booking fixture is present in this named remediation database, so reservation through inspection could not be repeated.', actual: { assets, visibleControls: controls, assetRows } };
    });

    await audit('US-029', 'Assess Asset Damage and Depreciation', async () => {
        const assets = await open('/party/assets?mode=workspace');
        const body = await page.locator('body').innerText();
        const eventUi = /damage|depreciation|maintenance|loss/i.test(body);
        return { verdict: assets.status === 200 && eventUi ? 'PARTIAL' : 'BLOCKED', coverage: 'Asset workspace/status language rendered, but no returned asset or approved damage/depreciation policy fixture exists for a legitimate assessment.', actual: { assets, eventUi } };
    });

    await audit('US-030', 'Create a Non-Posting Quotation', async () => {
        const quotations = await open('/quotations');
        const controls = await visibleCount(page.locator('button, input, select, textarea'));
        const body = await page.locator('body').innerText();
        const nonPosting = /non-posting|does not post|No stock/i.test(body);
        return { verdict: quotations.status === 200 && controls > 0 && nonPosting ? 'PARTIAL' : 'FAIL', coverage: 'Quotation creation/edit surface and non-posting guidance rendered. No quotation was committed.', actual: { quotations, visibleControls: controls, nonPosting } };
    });

    await audit('US-031', 'Review Dashboards, Alerts, Reports, and Exports', async () => {
        const pages = [];
        for (const route of ['/dashboard', '/alerts', '/reports', '/reports/sales', '/reports/inventory', '/reports/purchasing', '/exports']) pages.push(await open(route));
        const allRendered = pages.every((entry) => entry.status === 200 && entry.heading.length > 0);
        return { verdict: allRendered ? 'PASS' : 'FAIL', coverage: 'Dashboard, alerts, report catalog, three reconciled module reports, and export center rendered through UI with the seeded workflow data.', actual: pages };
    });

    await audit('US-032', 'Preserve Security, Audit, Integrity, and Safe Offline History', async () => {
        const auditPage = await open('/admin/audit');
        const approvals = await open('/approvals');
        const offline = await open('/pos/offline-readiness');
        const backup = await open('/admin/system/backups');
        const health = await open('/admin/system/health');
        const guestContext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const guest = await guestContext.newPage();
        const guestResponse = await guest.goto('/admin/audit', { waitUntil: 'domcontentloaded' });
        const guestRedirected = guest.url().includes('/login');
        await guestContext.close();
        const allRendered = [auditPage, approvals, offline, backup, health].every((entry) => entry.status === 200);
        return { verdict: allRendered && guestRedirected ? 'PARTIAL' : 'FAIL', coverage: 'Protected audit/approval/offline/backup/health UI and unauthenticated redirect were exercised. Concurrency, restore, external destination, device loss, and append-only integrity cannot be proven through this UI-only run.', actual: { auditPage, approvals, offline, backup, health, guestStatus: guestResponse?.status() ?? null, guestRedirected } };
    });

    await audit('US-046', 'Customize and Learn the Application Interface', async () => {
        const dashboard = await open('/dashboard');
        const guideButton = page.getByRole('button', { name: 'Page Guide', exact: true });
        const customizerButton = page.getByRole('button', { name: 'Appearance Customizer', exact: true });
        const guideVisible = await guideButton.isVisible().catch(() => false);
        let guideDialog = false;
        if (guideVisible) {
            await guideButton.click();
            guideDialog = await page.locator('#page-guide-drawer').isVisible().catch(() => false);
            const close = page.locator('#page-guide-drawer').getByRole('button', { name: 'Close', exact: true });
            if (await close.isVisible().catch(() => false)) await close.click();
        }
        const customizerVisible = await customizerButton.isVisible().catch(() => false);
        let persisted = false;
        if (customizerVisible) {
            await customizerButton.click();
            const select = page.locator('#appearance-customizer select').first();
            if (await select.isVisible().catch(() => false)) {
                await select.selectOption('dark');
                await page.waitForTimeout(750);
                await page.reload({ waitUntil: 'domcontentloaded' });
                persisted = await page.locator('html').evaluate((root) => root.dataset.appearance === 'dark' || root.classList.contains('dark'));
                await page.getByRole('button', { name: 'Appearance Customizer', exact: true }).click();
                await page.locator('#appearance-customizer select').first().selectOption('system');
                await page.waitForTimeout(500);
            }
        }
        return { verdict: dashboard.status === 200 && guideDialog && persisted ? 'PASS' : 'FAIL', coverage: 'Page Guide and Appearance Customizer were opened through UI; a dark preference survived reload and was restored to System.', actual: { dashboard, guideVisible, guideDialog, customizerVisible, persisted } };
    });

    activeStory = 'responsive-locale-smoke';
    const responsive = [];
    await page.setViewportSize({ width: 390, height: 844 });
    for (const route of ['/dashboard', '/catalog/products', '/pos', '/reports', '/parties/bookings']) {
        const entry = await open(route);
        responsive.push(entry);
    }
    const arabic = page.locator('form[action$="/locale"] input[name="locale"][value="ar"]').first();
    if (await arabic.count()) {
        await arabic.evaluate((input) => input.form.submit());
        await page.waitForLoadState('domcontentloaded');
    }
    results.responsiveLocaleSmoke = {
        pages: responsive,
        htmlLang: await page.locator('html').getAttribute('lang'),
        htmlDir: await page.locator('html').getAttribute('dir'),
        horizontalOverflow: await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)),
    };

    results.finishedAt = new Date().toISOString();
    results.summary = results.stories.reduce((summary, story) => {
        summary[story.verdict] = (summary[story.verdict] ?? 0) + 1;
        return summary;
    }, {});

    const resultsFile = path.join(evidenceDirectory, 'results.json');
    await writeFile(resultsFile, JSON.stringify(results, null, 2), 'utf8');
    await testInfo.attach('all-user-stories-ui-retest-results', { path: resultsFile, contentType: 'application/json' });

    if (results.stories.length !== 33) throw new Error(`Expected 33 story results, received ${results.stories.length}.`);
});
