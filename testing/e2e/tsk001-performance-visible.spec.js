import { test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { LOCAL_DEMO_PASSWORD } from '../helpers/auth.js';

test.use({
    locale: 'ar-EG',
    viewport: { width: 1440, height: 1000 },
    trace: 'on',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
});

test('TSK-001 visible performance baseline in Arabic RTL', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    testInfo.setTimeout(180_000);

    const evidenceDirectory = path.resolve('artifacts/tsk-001-performance-before-20260809');
    await mkdir(evidenceDirectory, { recursive: true });
    const requestStarts = new Map();
    const network = [];
    const consoleErrors = [];
    const pageErrors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push({ url: page.url(), text: message.text() });
    });
    page.on('pageerror', (error) => pageErrors.push({ url: page.url(), text: error.message }));
    page.on('request', (request) => {
        const key = `${request.method()} ${request.url()}`;
        const queue = requestStarts.get(key) ?? [];
        queue.push(performance.now());
        requestStarts.set(key, queue);
    });
    page.on('response', (response) => {
        const key = `${response.request().method()} ${response.url()}`;
        const queue = requestStarts.get(key) ?? [];
        const startedAt = queue.shift();
        requestStarts.set(key, queue);
        if (startedAt !== undefined) {
            network.push({ key, status: response.status(), durationMs: Math.round(performance.now() - startedAt) });
        }
    });

    async function setArabic() {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        const arabicButton = page.getByRole('button', { name: 'عربي', exact: true });
        if (await arabicButton.isVisible().catch(() => false)) {
            await arabicButton.click();
            await page.waitForLoadState('domcontentloaded').catch(() => {});
        }
    }

    async function waitForTarget(selector) {
        await page.locator(selector).first().waitFor({ state: 'visible', timeout: 15_000 });
        await page.waitForTimeout(800);
    }

    async function measureNavigation(route, selector, repetitions = 2) {
        const samples = [];
        for (let index = 0; index < repetitions; index += 1) {
            const startedAt = performance.now();
            await page.goto(route, { waitUntil: 'domcontentloaded' });
            await waitForTarget(selector);
            const elapsedMs = Math.round(performance.now() - startedAt);
            const navigation = await page.evaluate(() => {
                const entry = performance.getEntriesByType('navigation')[0];
                return entry ? {
                    responseEndMs: Math.round(entry.responseEnd),
                    domContentLoadedMs: Math.round(entry.domContentLoadedEventEnd),
                    loadEventMs: Math.round(entry.loadEventEnd),
                } : null;
            });
            samples.push({ elapsedMs, navigation });
            await page.waitForTimeout(900);
        }
        return samples;
    }

    await setArabic();
    await page.locator('input[name="username"]').fill('demo-admin');
    await page.locator('input[name="password"]').fill(LOCAL_DEMO_PASSWORD);
    const loginRedirect = page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 });
    await page.locator('button[data-test="login-button"]').click();
    await loginRedirect;
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(900);

    const locale = await page.locator('html').evaluate((html) => ({ lang: html.lang, dir: html.dir }));
    const authorization = await measureNavigation('/admin/authorization-baseline', '[data-guide="auth-users-table"]');
    const audit = await measureNavigation('/admin/audit', '[data-guide="audit-table"]');
    const health = await measureNavigation('/admin/system/health', '[data-guide="health-grid"]');

    await page.goto('/admin/system/health', { waitUntil: 'domcontentloaded' });
    await waitForTarget('[data-guide="health-grid"]');
    const refreshTimings = [];
    for (let index = 0; index < 2; index += 1) {
        const startedAt = performance.now();
        const responsePromise = page.waitForResponse((response) => response.url().includes('/livewire-') && response.url().includes('/update'), { timeout: 15_000 });
        await page.locator('button[wire\\:click="refreshStatus"]').click();
        const response = await responsePromise;
        await page.locator('[data-guide="health-banner"]').waitFor({ state: 'visible' });
        refreshTimings.push({
            elapsedMs: Math.round(performance.now() - startedAt),
            responseStatus: response.status(),
            responseUrl: response.url(),
        });
        await page.waitForTimeout(900);
    }

    const results = {
        startedAt: new Date().toISOString(),
        baseUrl: testInfo.project.use.baseURL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'unknown',
        locale,
        routes: { authorization, audit, health },
        livewire: { refreshTimings },
        network: network.filter(({ key }) => key.includes('/livewire-') || key.includes('authorization') || key.includes('/admin/audit') || key.includes('/admin/system/health')),
        consoleErrors,
        pageErrors,
    };
    await writeFile(path.join(evidenceDirectory, 'results.json'), JSON.stringify(results, null, 2), 'utf8');
    await page.screenshot({ path: path.join(evidenceDirectory, 'health-ar-rtl-before.png'), fullPage: true });
    console.log(JSON.stringify(results, null, 2));
});
