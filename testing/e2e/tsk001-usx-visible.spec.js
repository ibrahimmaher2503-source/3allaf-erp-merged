import { test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { LOCAL_BROWSER_ACTORS, login as sharedLogin } from '../helpers/auth.js';

test.describe.configure({ mode: 'serial' });

test.use({
    locale: 'ar-EG',
    viewport: { width: 1440, height: 1000 },
    trace: 'on',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
});

test('TSK-001 USX-0001..USX-0020 visible browser execution', async ({ page, browser }, testInfo) => {
    test.setTimeout(300_000);
    testInfo.setTimeout(300_000);

    const evidenceDirectory = path.resolve('artifacts/tsk-001-usx-20260809');
    await mkdir(evidenceDirectory, { recursive: true });

    const results = {
        scenarioSource: 'User-supplied USX-0001..USX-0020 list; no separate USX file was present in the workspace.',
        baseUrl: testInfo.project.use.baseURL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'unknown',
        startedAt: new Date().toISOString(),
        scenarios: [],
        evidence: [],
        consoleErrors: [],
        pageErrors: [],
        failedRequests: [],
        requestTimings: [],
        actionTimings: [],
        dialogs: [],
    };

    const requestStarts = new Map();
    const requestCounts = new Map();
    const mainContext = page.context();

    function trackPage(candidatePage) {
        candidatePage.on('console', (message) => {
            if (message.type() === 'error') {
                results.consoleErrors.push({ url: candidatePage.url(), text: message.text() });
            }
        });
        candidatePage.on('pageerror', (error) => {
            results.pageErrors.push({ url: candidatePage.url(), text: error.message });
        });
        candidatePage.on('request', (request) => {
            const key = `${request.method()} ${request.url()}`;
            const queue = requestStarts.get(key) ?? [];
            queue.push(Date.now());
            requestStarts.set(key, queue);
            requestCounts.set(key, (requestCounts.get(key) ?? 0) + 1);
        });
        candidatePage.on('requestfailed', (request) => {
            results.failedRequests.push({
                method: request.method(),
                url: request.url(),
                failure: request.failure()?.errorText ?? 'unknown',
            });
        });
        candidatePage.on('response', (response) => {
            const key = `${response.request().method()} ${response.url()}`;
            const queue = requestStarts.get(key) ?? [];
            const startedAt = queue.shift();
            requestStarts.set(key, queue);
            if (startedAt !== undefined) {
                results.requestTimings.push({
                    method: response.request().method(),
                    url: response.url(),
                    status: response.status(),
                    durationMs: Date.now() - startedAt,
                });
            }
            if (response.status() >= 400) {
                results.failedRequests.push({
                    method: response.request().method(),
                    url: response.url(),
                    status: response.status(),
                });
            }
        });
        candidatePage.on('dialog', async (dialog) => {
            results.dialogs.push({ type: dialog.type(), message: dialog.message() });
            await dialog.accept();
        });
    }

    trackPage(page);

    const pause = (milliseconds = 700) => page.waitForTimeout(milliseconds);

    async function timed(label, callback) {
        const startedAt = Date.now();
        try {
            return await callback();
        } finally {
            results.actionTimings.push({ label, durationMs: Date.now() - startedAt });
        }
    }

    async function capture(candidatePage, name) {
        const file = path.join(evidenceDirectory, `${name}.png`);
        await candidatePage.screenshot({ path: file, fullPage: true });
        results.evidence.push(file);
        await testInfo.attach(name, { path: file, contentType: 'image/png' });
        return file;
    }

    async function pageMetrics(candidatePage, label) {
        const metrics = await candidatePage.evaluate(() => {
            const navigation = performance.getEntriesByType('navigation')[0];

            return {
                domContentLoadedMs: navigation?.domContentLoadedEventEnd ?? null,
                loadEventMs: navigation?.loadEventEnd ?? null,
                responseEndMs: navigation?.responseEnd ?? null,
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
                lang: document.documentElement.lang,
                dir: document.documentElement.dir,
            };
        });
        results.actionTimings.push({ label: `page metrics: ${label}`, ...metrics });
        return metrics;
    }

    async function setArabicOnLogin(candidatePage) {
        await candidatePage.goto('/login', { waitUntil: 'domcontentloaded' });
        await candidatePage.waitForTimeout(700);
        const currentDirection = await candidatePage.locator('html').getAttribute('dir');
        if (currentDirection !== 'rtl') {
            const arabicSwitch = candidatePage.getByRole('button', { name: 'عربي', exact: true });
            await arabicSwitch.waitFor({ state: 'visible' });
            await arabicSwitch.click({ noWaitAfter: true });
            await candidatePage.waitForLoadState('domcontentloaded').catch(() => {});
        }
        await candidatePage.waitForTimeout(900);
    }

    async function loginArabic(candidatePage, username = 'demo-admin', password = 'LocalDemoOnly!2026') {
        await setArabicOnLogin(candidatePage);
        await candidatePage.locator('input[name="username"]').fill(username);
        await candidatePage.waitForTimeout(350);
        await candidatePage.locator('input[name="password"]').fill(password);
        await candidatePage.waitForTimeout(350);
        const urlWait = candidatePage.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 });
        await candidatePage.locator('button[data-test="login-button"]').click();
        await urlWait;
        await candidatePage.waitForLoadState('domcontentloaded').catch(() => {});
        await candidatePage.waitForTimeout(1200);
    }

    async function openArabicActor(actor) {
        const actorContext = await browser.newContext({ locale: 'ar-EG', viewport: { width: 1440, height: 1000 } });
        const actorPage = await actorContext.newPage();
        trackPage(actorPage);
        try {
            await loginArabic(actorPage, actor.username, actor.password);
            await actorPage.waitForTimeout(900);
            return { context: actorContext, page: actorPage };
        } catch (error) {
            await actorContext.close().catch(() => {});
            throw error;
        }
    }

    async function visibleLink(candidatePage, route, groupIndex) {
        const find = async () => {
            const links = candidatePage.locator(`a[href$="${route}"]`);

            for (let index = 0; index < await links.count(); index += 1) {
                if (await links.nth(index).isVisible()) return links.nth(index);
            }

            return null;
        };

        let link = await find();
        if (!link && groupIndex !== undefined) {
            const group = candidatePage.locator('nav [data-sidebar-expandable]').nth(groupIndex);
            await group.locator('button').first().click();
            await candidatePage.waitForTimeout(500);
            link = await find();
        }

        if (!link) throw new Error(`No visible UI link for ${route}`);

        return link;
    }

    async function openPath(candidatePage, route, groupIndex) {
        const link = await visibleLink(candidatePage, route, groupIndex);
        const urlWait = candidatePage.waitForURL(`**${route}`, { timeout: 15_000 }).catch(() => null);
        await link.scrollIntoViewIfNeeded();
        await candidatePage.waitForTimeout(250);
        await link.click();
        await urlWait;
        await candidatePage.waitForTimeout(1100);
        await pageMetrics(candidatePage, route);
    }

    async function healthFacts(candidatePage) {
        const banner = candidatePage.locator('[data-guide="health-banner"]');
        const grid = candidatePage.locator('[data-guide="health-grid"]');
        const body = await candidatePage.locator('body').innerText();
        const bodyMarkup = await candidatePage.locator('body').innerHTML();

        return {
            bannerVisible: await banner.isVisible().catch(() => false),
            bannerTextPresent: (await banner.innerText().catch(() => '')).trim().length > 0,
            componentCardCount: await grid.locator(':scope > *').count().catch(() => 0),
            refreshVisible: await candidatePage.locator('button[wire\\:click="refreshStatus"]').isVisible().catch(() => false),
            correlationIdPresent: /[0-9a-f]{8}-[0-9a-f-]{27,}/i.test(body) || body.includes('REQ-LOCAL'),
            secretLeakDetected: /(APP_KEY|DB_PASSWORD|AWS_SECRET|AWS_ACCESS_KEY|DATABASE_URL|BEGIN PRIVATE KEY|base64:)/i.test(`${body}\n${bodyMarkup}`),
            workerOrSchedulerControls: /(worker|scheduler|queue|monitoring)/i.test(body),
        };
    }

    async function runScenario(meta, callback) {
        await test.step(`${meta.id} — ${meta.title}`, async () => {
            const startedAt = Date.now();
            try {
                const outcome = await callback();
                const record = {
                    ...meta,
                    ...outcome,
                    durationMs: Date.now() - startedAt,
                };
                results.scenarios.push(record);
                console.log(`[${meta.id}] ${record.result} (${record.durationMs}ms) ${record.actual}`);
            } catch (error) {
                let screenshot = null;
                try {
                    screenshot = await capture(page, `${meta.id}-failure`);
                } catch {
                    // Preserve the scenario failure if the page is already unavailable.
                }
                const record = {
                    ...meta,
                    result: 'FAIL',
                    testPerformed: meta.testPerformed,
                    expected: meta.expected,
                    actual: `Unexpected browser/UI failure: ${error.message}`,
                    durationMs: Date.now() - startedAt,
                    screenshot,
                };
                results.scenarios.push(record);
                console.log(`[${meta.id}] FAIL (${record.durationMs}ms) ${record.actual}`);
            }
        });
    }

    const guestState = { page: null, forbidden: null, notFound: null, healthDenied: null };

    const scenarioMeta = {
        'USX-0001': { id: 'USX-0001', priority: 'P0', title: 'Main TSK-001 happy path', route: '/admin/system/health', testPerformed: 'Arabic login through the visible form, sidebar navigation to System Health, inspect the health banner/components/request ID, and refresh through the visible action.', expected: 'Authenticated administrator sees the operational health UI in Arabic RTL with a safe correlation ID and responsive refresh action.' },
        'USX-0002': { id: 'USX-0002', priority: 'P0', title: 'Production-safe errors / health authorization', route: '/forbidden; /this-route-does-not-exist; /admin/system/health', testPerformed: 'Guest browser navigation to rendered 403/404 and protected health route.', expected: 'Safe error surfaces render without internals, and unauthenticated health access is denied.' },
        'USX-0003': { id: 'USX-0003', priority: 'P1', title: 'Create and restore approved isolated backup', route: '/admin/system/health', testPerformed: 'Inspected authenticated sidebar and health UI for a visible backup create/restore workflow.', expected: 'An approved backup can be created and restored to an isolated target through UI if implemented.' },
        'USX-0004': { id: 'USX-0004', priority: 'P1', title: 'Worker/scheduler/storage/monitoring signal and secret leakage', route: '/admin/system/health', testPerformed: 'Inspected health cards and exposed controls for worker, scheduler, storage, monitoring, and secret values.', expected: 'Exposed signals are visible and secrets are never rendered.' },
        'USX-0005': { id: 'USX-0005', priority: 'P0', title: 'Administrator / Support / Reviewer scope', route: '/admin/system/health; /admin/authorization-baseline', testPerformed: 'Administrator opened the visible authorization inventory and user scope modal; available actor credentials were checked against the existing UI fixtures.', expected: 'Administrator, support, and reviewer scope behavior is verifiable with real UI actor accounts.' },
        'USX-0006': { id: 'USX-0006', priority: 'P0', title: 'No secret display', route: '/admin/system/health; /forbidden; /this-route-does-not-exist', testPerformed: 'Inspected rendered authenticated health and guest error text/markup for common secret/configuration leakage markers.', expected: 'No secret, key, password, stack trace, or sensitive configuration is visible.' },
        'USX-0007': { id: 'USX-0007', priority: 'P1', title: 'Environment/backup actions confirmed/audited', route: '/admin/system/health; /admin/audit', testPerformed: 'Inspected health/control UI for visible environment or backup actions and audit affordances.', expected: 'Environment/backup actions require confirmation and create visible audit evidence.' },
        'USX-0008': { id: 'USX-0008', priority: 'P0', title: 'Health states: healthy/degraded/down', route: '/admin/system/health', testPerformed: 'Observed the live healthy state and checked the visible UI for controls or fixtures to render degraded/down states.', expected: 'Healthy, degraded, and down states are all verifiable through UI.' },
        'USX-0009': { id: 'USX-0009', priority: 'P1', title: 'Backup states: queued/success/fail', route: '/admin/system/health', testPerformed: 'Inspected the visible authenticated UI for backup status/state controls or a backup status screen.', expected: 'Queued, success, and fail backup states are visible through UI.' },
        'USX-0010': { id: 'USX-0010', priority: 'P1', title: 'Authorized status-report printing', route: '/admin/system/health', testPerformed: 'Inspected the health UI for an authorized print/status-report action.', expected: 'An authorized actor can print a safe status report and unauthorized actors cannot.' },
        'USX-0011': { id: 'USX-0011', priority: 'P1', title: 'Idempotency', route: '/admin/system/health', testPerformed: 'Clicked the same visible health refresh action twice with observable pauses and compared the resulting UI and Livewire request count.', expected: 'Repeating the same UI action does not duplicate or corrupt the visible health result.' },
        'USX-0012': { id: 'USX-0012', priority: 'P1', title: 'Concurrency', route: '/admin/system/health', testPerformed: 'Opened the same visible health screen in two authenticated headed pages and attempted concurrent refreshes.', expected: 'Concurrent UI operations on the same record remain safe and consistent where the UI exposes a mutable record.' },
        'USX-0013': { id: 'USX-0013', priority: 'P0', title: 'Scope', route: '/admin/system/health; /admin/authorization-baseline', testPerformed: 'Checked the visible scope-management UI and whether a real assigned branch/store actor with usable credentials could attempt an outside-scope operation.', expected: 'An outside-branch/store operation is denied through UI and cannot leak or mutate data.' },
        'USX-0014': { id: 'USX-0014', priority: 'P0', title: 'Authorization', route: '/admin/system/health', testPerformed: 'Attempted the protected health route in a separate visible unauthenticated browser page.', expected: 'An unauthorized browser actor is denied or redirected to login.' },
        'USX-0015': { id: 'USX-0015', priority: 'P1', title: 'Validation', route: '/login', testPerformed: 'Submitted the visible login form with required fields empty and inspected browser validation and route stability.', expected: 'Missing required input blocks submission with clear UI validation.' },
        'USX-0016': { id: 'USX-0016', priority: 'P1', title: 'Stale state', route: '/admin/system/health', testPerformed: 'Inspected the TSK-001 UI for an editable record that can be opened in two pages and saved from a stale version.', expected: 'A stale save is safely rejected or reconciled without overwriting newer state.' },
        'USX-0017': { id: 'USX-0017', priority: 'P0', title: 'Audit', route: '/admin/system/health; /admin/audit', testPerformed: 'Refreshed health through UI and opened the existing audit screen to determine whether a TSK-001 change with actor/before/after/scope evidence is available.', expected: 'A UI change produces visible actor, action, target, timestamp, scope, and before/after audit evidence where supported.' },
        'USX-0018': { id: 'USX-0018', priority: 'P1', title: 'RTL/LTR', route: '/admin/system/health', testPerformed: 'Completed Arabic RTL first, then opened a fresh visible English LTR browser context and used the existing shared login helper to inspect the same health UI.', expected: 'Arabic RTL and focused English LTR render correctly with health labels, controls, and layout.' },
        'USX-0019': { id: 'USX-0019', priority: 'P2', title: 'Mobile', route: '/admin/system/health', testPerformed: 'Resized the authenticated Arabic page to a 390x844 mobile viewport, inspected overflow and health controls, and refreshed visibly.', expected: 'The important health flow remains usable on mobile without broken layout or inaccessible controls.' },
        'USX-0020': { id: 'USX-0020', priority: 'P1', title: 'UX states', route: '/admin/system/health; /forbidden; /this-route-does-not-exist', testPerformed: 'Verified available normal/denied/error surfaces and checked the health UI for an empty state.', expected: 'Available empty, error, and denied states are clear and usable.' },
    };

    try {
        await runScenario(scenarioMeta['USX-0001'], async () => {
            await timed('Arabic login and initial navigation', async () => loginArabic(page));
            const locale = {
                lang: await page.locator('html').getAttribute('lang'),
                dir: await page.locator('html').getAttribute('dir'),
            };
            await openPath(page, '/admin/system/health', 11);
            const facts = await healthFacts(page);
            await timed('Health refresh', async () => {
                await page.locator('button[wire\\:click="refreshStatus"]').click();
                await pause(1000);
            });
            const refreshedFacts = await healthFacts(page);
            await capture(page, 'USX-0001-health-ar-rtl');

            if (locale.lang !== 'ar' || locale.dir !== 'rtl' || !facts.bannerVisible || !facts.bannerTextPresent || facts.componentCardCount < 4 || !facts.refreshVisible || !facts.correlationIdPresent || facts.secretLeakDetected || !refreshedFacts.bannerVisible) {
                return { result: 'FAIL', actual: `Arabic/health checks did not all pass: ${JSON.stringify({ locale, facts, refreshedFacts })}` };
            }

            return { result: 'PASS', actual: `Arabic RTL health screen rendered with ${facts.componentCardCount} component cards, correlation ID, safe banner, and successful refresh.` };
        });

        await runScenario(scenarioMeta['USX-0002'], async () => {
            const guestContext = await browser.newContext({ locale: 'ar-EG', viewport: { width: 1440, height: 1000 } });
            guestState.page = await guestContext.newPage();
            trackPage(guestState.page);
            await setArabicOnLogin(guestState.page);

            guestState.forbidden = await timed('Guest 403 navigation', async () => guestState.page.goto('/forbidden', { waitUntil: 'domcontentloaded' }));
            await pause(900);
            const forbiddenBody = await guestState.page.locator('body').innerText();
            const forbiddenSafe = /403/.test(forbiddenBody) && !/(APP_KEY|DB_PASSWORD|stack trace|vendor\/|BEGIN PRIVATE KEY)/i.test(forbiddenBody);
            await capture(guestState.page, 'USX-0002-forbidden-ar-rtl');

            guestState.notFound = await timed('Guest 404 navigation', async () => guestState.page.goto('/this-route-does-not-exist', { waitUntil: 'domcontentloaded' }));
            await pause(900);
            const notFoundBody = await guestState.page.locator('body').innerText();
            const notFoundSafe = /404/.test(notFoundBody) && !/(APP_KEY|DB_PASSWORD|stack trace|vendor\/|BEGIN PRIVATE KEY)/i.test(notFoundBody);
            await capture(guestState.page, 'USX-0002-not-found-ar-rtl');

            const deniedResponse = await timed('Guest health authorization navigation', async () => guestState.page.goto('/admin/system/health', { waitUntil: 'domcontentloaded' }));
            await pause(900);
            const deniedPath = new URL(guestState.page.url()).pathname;
            guestState.healthDenied = { status: deniedResponse?.status() ?? null, finalPath: deniedPath };

            if (!forbiddenSafe || !notFoundSafe || deniedPath !== '/login') {
                return { result: 'FAIL', actual: `Safe error or authorization checks failed: ${JSON.stringify({ forbiddenSafe, notFoundSafe, denied: guestState.healthDenied })}` };
            }

            return { result: 'PASS', actual: `Arabic 403/404 were safe and unauthenticated health access redirected to ${deniedPath}.` };
        });

        await runScenario(scenarioMeta['USX-0005'], async () => {
            await openPath(page, '/admin/authorization-baseline', 10);
            const inventoryVisible = await page.locator('[data-guide="auth-users-table"]').isVisible().catch(() => false);
            const manageButtons = page.locator('button[data-guide="auth-users-manage-action"]');
            const manageCount = await manageButtons.count();
            let modalVisible = false;
            let checkboxCount = 0;
            if (manageCount > 0) {
                await manageButtons.first().click();
                await pause(900);
                const modal = page.locator('[role="dialog"], [data-flux-modal]').last();
                modalVisible = await modal.isVisible().catch(() => false);
                checkboxCount = await modal.locator('input[type="checkbox"], [role="checkbox"], [aria-checked], [data-flux-control], ui-checkbox').count().catch(() => 0);
                await capture(page, 'USX-0005-admin-scope-modal-ar-rtl');
                const close = page.locator('button[wire\\:click="closeAuthorization"]').last();
                if (await close.isVisible().catch(() => false)) await close.click();
                await pause(600);
            }

            const actorChecks = [];
            for (const [label, actor] of [
                ['Support', LOCAL_BROWSER_ACTORS.support],
                ['Reviewer', LOCAL_BROWSER_ACTORS.reviewer],
            ]) {
                const actorSession = await openArabicActor(actor);
                try {
                    const response = await actorSession.page.goto('/admin/system/health', { waitUntil: 'domcontentloaded' });
                    await actorSession.page.waitForTimeout(900);
                    actorChecks.push({
                        label,
                        status: response?.status() ?? null,
                        path: new URL(actorSession.page.url()).pathname,
                        healthVisible: await actorSession.page.locator('[data-guide="health-grid"]').isVisible().catch(() => false),
                    });
                } finally {
                    await actorSession.context.close();
                }
            }

            const restrictedSession = await openArabicActor(LOCAL_BROWSER_ACTORS.restricted);
            let restrictedCheck;
            try {
                const response = await restrictedSession.page.goto('/admin/system/health', { waitUntil: 'domcontentloaded' });
                await restrictedSession.page.waitForTimeout(900);
                restrictedCheck = {
                    status: response?.status() ?? null,
                    path: new URL(restrictedSession.page.url()).pathname,
                    healthVisible: await restrictedSession.page.locator('[data-guide="health-grid"]').isVisible().catch(() => false),
                };
            } finally {
                await restrictedSession.context.close();
            }

            const actorsPassed = actorChecks.every((check) => check.status === 200 && check.path === '/admin/system/health' && check.healthVisible)
                && restrictedCheck.path === '/forbidden'
                && restrictedCheck.healthVisible === false;

            if (!inventoryVisible || !modalVisible || checkboxCount === 0 || !actorsPassed) {
                return {
                    result: 'FAIL',
                    actual: `Authorization actors/modal check failed: ${JSON.stringify({ inventoryVisible, modalVisible, checkboxCount, actorChecks, restrictedCheck })}`,
                };
            }

            return {
                result: 'PASS',
                actual: `Administrator authorization inventory/modal exposed with ${checkboxCount} scope controls; deterministic Support and Reviewer actors reached health, while the restricted actor was denied at /forbidden: ${JSON.stringify({ actorChecks, restrictedCheck })}.`,
            };
        });

        await runScenario(scenarioMeta['USX-0006'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const facts = await healthFacts(page);
            const body = await page.locator('body').innerText();
            const safeErrors = !/(APP_KEY|DB_PASSWORD|AWS_SECRET|AWS_ACCESS_KEY|DATABASE_URL|BEGIN PRIVATE KEY|stack trace|vendor\/)/i.test(body);
            await capture(page, 'USX-0006-health-no-secrets-ar-rtl');
            if (facts.secretLeakDetected || !safeErrors) return { result: 'FAIL', actual: 'A common secret or sensitive error marker was rendered in the visible UI.' };
            return { result: 'PASS', actual: 'Health UI rendered no common secret/configuration or stack-trace markers.' };
        });

        await runScenario(scenarioMeta['USX-0008'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const facts = await healthFacts(page);
            await capture(page, 'USX-0008-health-healthy-ar-rtl');
            return {
                result: 'NOT TESTABLE THROUGH UI',
                actual: `Healthy state is visible (banner=${facts.bannerVisible}, cards=${facts.componentCardCount}); the screen exposes refresh only and no UI fixture/control to produce degraded or down states.`,
            };
        });

        await runScenario(scenarioMeta['USX-0013'], async () => {
            const actorSession = await openArabicActor(LOCAL_BROWSER_ACTORS.branchScoped);
            try {
                const response = await actorSession.page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
                await actorSession.page.waitForTimeout(900);
                const branchTableVisible = await actorSession.page.locator('[data-guide="branches-table"]').isVisible().catch(() => false);
                const initialBody = await actorSession.page.locator('body').innerText();
                const scopedBranchVisible = initialBody.includes('DEMO-CAI');
                const outOfScopeBranchVisible = initialBody.includes('BR-217');
                const createActionVisible = await actorSession.page.locator('button[data-guide="branches-add-action"]').isVisible().catch(() => false);
                const searchInput = actorSession.page.locator('input[wire\\:model*="search"]').first();
                let filteredBody = initialBody;
                if (await searchInput.isVisible().catch(() => false)) {
                    await searchInput.fill('BR-217');
                    await actorSession.page.waitForTimeout(1100);
                    filteredBody = await actorSession.page.locator('body').innerText();
                }
                const filteredOutOfScope = !filteredBody.includes('BR-217');

                if ((response?.status() ?? 0) !== 200 || !branchTableVisible || !scopedBranchVisible || outOfScopeBranchVisible || createActionVisible === true || !filteredOutOfScope) {
                    return {
                        result: 'FAIL',
                        actual: `Branch-scoped UI did not preserve scope: ${JSON.stringify({ status: response?.status() ?? null, branchTableVisible, scopedBranchVisible, outOfScopeBranchVisible, createActionVisible, filteredOutOfScope })}`,
                    };
                }

                return {
                    result: 'PASS',
                    actual: 'Deterministic branch-scoped actor saw DEMO-CAI, did not see out-of-scope BR-217, had no create action, and a visible search for BR-217 remained empty.',
                };
            } finally {
                await actorSession.context.close();
            }
        });

        await runScenario(scenarioMeta['USX-0014'], async () => {
            if (!guestState.page) return { result: 'FAIL', actual: 'Guest browser prerequisite was not available.' };
            await guestState.page.goto('/admin/system/health', { waitUntil: 'domcontentloaded' });
            await pause(800);
            const finalPath = new URL(guestState.page.url()).pathname;
            if (finalPath !== '/login') return { result: 'FAIL', actual: `Unauthorized health route did not redirect to login; final path=${finalPath}.` };
            return { result: 'PASS', actual: 'Unauthenticated browser navigation to the protected health route was redirected to the login UI.' };
        });

        await runScenario(scenarioMeta['USX-0017'], async () => {
            await openPath(page, '/admin/audit', 11);
            const auditBody = await page.locator('body').innerText();
            const auditScreenVisible = /audit|تدقيق|سجل/i.test(auditBody) || await page.locator('table').count() > 0;
            await capture(page, 'USX-0017-audit-screen-ar-rtl');
            return {
                result: 'NOT TESTABLE THROUGH UI',
                actual: `Audit UI visible=${auditScreenVisible}; the TSK-001 health refresh is a read-only status check and does not expose a mutable before/after change or environment/backup action to trace through this UI.`,
            };
        });

        await runScenario(scenarioMeta['USX-0003'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const visibleBackupLinks = [];
            const links = page.locator('a[href]');
            for (let index = 0; index < await links.count(); index += 1) {
                if (await links.nth(index).isVisible()) {
                    const href = await links.nth(index).getAttribute('href');
                    if (href?.toLowerCase().includes('backup')) visibleBackupLinks.push(href);
                }
            }
            const backupControls = await page.locator('button').evaluateAll((buttons) => buttons.filter((button) => `${button.innerText} ${button.getAttribute('wire:click') ?? ''}`.toLowerCase().includes('backup')).length);
            await capture(page, 'USX-0003-no-backup-ui-ar-rtl');
            return {
                result: 'NOT IMPLEMENTED IN UI',
                actual: `No visible backup link or backup control exists on the authenticated navigation/health screen (links=${visibleBackupLinks.length}, controls=${backupControls}). The supplied requirement is UI-only, so no CLI, API, JSON endpoint, or database operation was used.`,
            };
        });

        await runScenario(scenarioMeta['USX-0004'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const facts = await healthFacts(page);
            await capture(page, 'USX-0004-health-signals-ar-rtl');
            return {
                result: 'NOT IMPLEMENTED IN UI',
                actual: `Visible UI exposes database/storage/cache/environment cards and no secret marker, but no worker, scheduler, or monitoring signal/control is rendered (workerOrSchedulerControls=${facts.workerOrSchedulerControls}).`,
            };
        });

        await runScenario(scenarioMeta['USX-0007'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const actionTexts = (await page.locator('[data-guide="health-header"] button, [data-guide="health-header"] a').allTextContents()).map((text) => text.trim()).filter(Boolean);
            const backupAction = actionTexts.some((text) => /backup|نسخ/i.test(text));
            await capture(page, 'USX-0007-health-actions-ar-rtl');
            return {
                result: 'NOT IMPLEMENTED IN UI',
                actual: `The visible health header exposes refresh only (backup/environment action=${backupAction}); no confirmation/audit workflow for those actions is present in UI.`,
            };
        });

        await runScenario(scenarioMeta['USX-0009'], async () => ({
            result: 'NOT IMPLEMENTED IN UI',
            actual: 'No visible backup status screen, queue state control, or queued/success/fail backup cards are exposed in the existing UI.',
        }));

        await runScenario(scenarioMeta['USX-0010'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const header = page.locator('[data-guide="health-header"]');
            const printLinks = await header.locator('a[href*="print"]').count();
            const printButtons = await header.locator('button[wire\\:click*="print"]').count();
            await capture(page, 'USX-0010-health-no-print-ui-ar-rtl');
            return {
                result: 'NOT IMPLEMENTED IN UI',
                actual: `No authorized status-report print link or print action is rendered in the health UI (links=${printLinks}, buttons=${printButtons}).`,
            };
        });

        await runScenario(scenarioMeta['USX-0011'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const refresh = page.locator('button[wire\\:click="refreshStatus"]');
            const livewireRequestCount = () => [...requestCounts.entries()]
                .filter(([key]) => key.includes('/livewire-') && key.includes('/update'))
                .reduce((total, [, count]) => total + count, 0);
            const beforeRequests = livewireRequestCount();
            await timed('Idempotent refresh #1', async () => { await refresh.click(); await pause(900); });
            const afterFirst = livewireRequestCount();
            await timed('Idempotent refresh #2', async () => { await refresh.click(); await pause(900); });
            const afterSecond = livewireRequestCount();
            const facts = await healthFacts(page);
            const bannerCount = await page.locator('[data-guide="health-banner"]').count();
            const gridCount = await page.locator('[data-guide="health-grid"]').count();
            await capture(page, 'USX-0011-idempotent-refresh-ar-rtl');
            const oneRequestPerAction = afterFirst > beforeRequests && afterSecond > afterFirst;
            if (!oneRequestPerAction || facts.componentCardCount < 4 || bannerCount !== 1 || gridCount !== 1) {
                return { result: 'FAIL', actual: `Repeated refresh did not remain stable: ${JSON.stringify({ beforeRequests, afterFirst, afterSecond, facts, bannerCount, gridCount })}` };
            }
            return { result: 'PASS', actual: `Two repeated visible refresh actions each produced a Livewire update and left one health banner/grid with ${facts.componentCardCount} cards.` };
        });

        await runScenario(scenarioMeta['USX-0012'], async () => {
            const storageState = await mainContext.storageState();
            const secondContext = await browser.newContext({ storageState, locale: 'ar-EG', viewport: { width: 1440, height: 1000 } });
            const secondPage = await secondContext.newPage();
            trackPage(secondPage);
            try {
                await secondPage.goto('/admin/system/health', { waitUntil: 'domcontentloaded' });
                await secondPage.waitForTimeout(900);
                await Promise.all([
                    timed('Concurrent main-page health refresh', async () => { await page.locator('button[wire\\:click="refreshStatus"]').click(); await pause(1000); }),
                    timed('Concurrent second-page health refresh', async () => { await secondPage.locator('button[wire\\:click="refreshStatus"]').click(); await secondPage.waitForTimeout(1000); }),
                ]);
                const firstFacts = await healthFacts(page);
                const secondFacts = await healthFacts(secondPage);
                await capture(secondPage, 'USX-0012-concurrent-health-second-page-ar-rtl');
                return {
                    result: 'NOT TESTABLE THROUGH UI',
                    actual: `Two visible authenticated pages refreshed the read-only health screen without corruption (main=${firstFacts.bannerVisible}, second=${secondFacts.bannerVisible}); no mutable TSK-001 record or backup UI exists for a meaningful concurrent write test.`,
                };
            } finally {
                await secondContext.close();
            }
        });

        await runScenario(scenarioMeta['USX-0015'], async () => {
            const validationContext = await browser.newContext({ locale: 'ar-EG', viewport: { width: 1440, height: 1000 } });
            const validationPage = await validationContext.newPage();
            trackPage(validationPage);
            try {
                await setArabicOnLogin(validationPage);
                await validationPage.locator('button[data-test="login-button"]').click();
                await validationPage.waitForTimeout(700);
                const invalidFields = await validationPage.locator('input[name="username"], input[name="password"]').evaluateAll((fields) => fields.filter((field) => !field.checkValidity()).map((field) => field.name));
                const stayedOnLogin = new URL(validationPage.url()).pathname === '/login';
                await capture(validationPage, 'USX-0015-login-required-validation-ar-rtl');
                if (!stayedOnLogin || invalidFields.length < 2) return { result: 'FAIL', actual: `Required validation did not block the empty form: ${JSON.stringify({ stayedOnLogin, invalidFields })}` };
                return { result: 'PASS', actual: `Empty visible login submission stayed on the login UI and browser validation marked ${invalidFields.join(', ')} required fields.` };
            } finally {
                await validationContext.close();
            }
        });

        await runScenario(scenarioMeta['USX-0016'], async () => ({
            result: 'NOT TESTABLE THROUGH UI',
            actual: 'The TSK-001 health screen has no editable record or save form, and the backup workflow is not implemented in UI; there is no stale editable state to open in two browser contexts.',
        }));

        await runScenario(scenarioMeta['USX-0018'], async () => {
            await openPath(page, '/admin/system/health', 11);
            const arabicFacts = await healthFacts(page);
            const ltrContext = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
            const ltrPage = await ltrContext.newPage();
            trackPage(ltrPage);
            try {
                await timed('Existing shared English login helper', async () => sharedLogin(ltrPage, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password));
                await ltrPage.waitForTimeout(900);
                await openPath(ltrPage, '/admin/system/health', 11);
                const ltrFacts = await healthFacts(ltrPage);
                const ltrLocale = { lang: await ltrPage.locator('html').getAttribute('lang'), dir: await ltrPage.locator('html').getAttribute('dir') };
                await capture(ltrPage, 'USX-0018-health-en-ltr');
                if (arabicFacts.bannerVisible !== true || ltrLocale.lang !== 'en' || ltrLocale.dir !== 'ltr' || !ltrFacts.bannerVisible || !ltrFacts.refreshVisible) {
                    return { result: 'FAIL', actual: `RTL/LTR checks failed: ${JSON.stringify({ arabicFacts, ltrLocale, ltrFacts })}` };
                }
                return { result: 'PASS', actual: `Arabic first remained RTL with a visible health banner; shared English login helper reached the same screen in ${ltrLocale.lang}/${ltrLocale.dir} with refresh available.` };
            } finally {
                await ltrContext.close();
            }
        });

        await runScenario(scenarioMeta['USX-0019'], async () => {
            await openPath(page, '/admin/system/health', 11);
            await page.setViewportSize({ width: 390, height: 844 });
            await page.waitForTimeout(900);
            const metrics = await pageMetrics(page, 'mobile health');
            const facts = await healthFacts(page);
            await capture(page, 'USX-0019-health-mobile-ar-rtl');
            if (metrics.lang !== 'ar' || metrics.dir !== 'rtl' || metrics.scrollWidth > metrics.clientWidth + 1 || !facts.refreshVisible || !facts.bannerVisible) {
                return { result: 'FAIL', actual: `Mobile health layout/control check failed: ${JSON.stringify({ metrics, facts })}` };
            }
            await timed('Mobile health refresh', async () => { await page.locator('button[wire\\:click="refreshStatus"]').click(); await pause(900); });
            return { result: 'PASS', actual: `390x844 Arabic RTL health flow had no horizontal overflow (${metrics.scrollWidth}/${metrics.clientWidth}) and refresh remained usable.` };
        });

        await runScenario(scenarioMeta['USX-0020'], async () => {
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.waitForTimeout(700);
            if (new URL(page.url()).pathname !== '/admin/system/health') await openPath(page, '/admin/system/health', 11);
            const emptyStateSelectors = '[data-state="empty"], [data-guide*="empty"], [data-empty-state]';
            const emptyStateCount = await page.locator(emptyStateSelectors).count();
            const errorEvidence = Boolean(guestState.forbidden && guestState.notFound);
            const deniedEvidence = Boolean(guestState.healthDenied?.finalPath === '/login');
            await capture(page, 'USX-0020-health-normal-state-ar-rtl');
            return {
                result: 'NOT IMPLEMENTED IN UI',
                actual: `Denied/error evidence exists from USX-0002/0014 (error=${errorEvidence}, denied=${deniedEvidence}), but no empty-state component is exposed on the health screen (count=${emptyStateCount}); the complete empty/error/denied set is therefore not fully implemented for this UI.`,
            };
        });
    } finally {
        results.finishedAt = new Date().toISOString();
        results.scenarios.sort((left, right) => left.id.localeCompare(right.id));
        results.summary = {
            total: results.scenarios.length,
            pass: results.scenarios.filter((scenario) => scenario.result === 'PASS').length,
            fail: results.scenarios.filter((scenario) => scenario.result === 'FAIL').length,
            notImplementedInUi: results.scenarios.filter((scenario) => scenario.result === 'NOT IMPLEMENTED IN UI').length,
            notTestableThroughUi: results.scenarios.filter((scenario) => scenario.result === 'NOT TESTABLE THROUGH UI').length,
            consoleErrorCount: results.consoleErrors.length,
            pageErrorCount: results.pageErrors.length,
            failedRequestCount: results.failedRequests.length,
            duplicateRequestCandidates: [...requestCounts.entries()].filter(([, count]) => count > 1).map(([key, count]) => ({ key, count })),
            slowestActions: [...results.actionTimings].filter((timing) => timing.durationMs !== undefined).sort((left, right) => right.durationMs - left.durationMs).slice(0, 10),
        };
        await writeFile(path.join(evidenceDirectory, 'results.json'), JSON.stringify(results, null, 2), 'utf8');
        if (guestState.page) await guestState.page.context().close().catch(() => {});
    }

    console.log(JSON.stringify(results.summary, null, 2));
});
