import { test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

async function login(page) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill('admin');
    await page.getByLabel('Password', { exact: true }).fill('ToyJoy!Bootstrap2026');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 }),
        page.getByRole('button', { name: 'Log in', exact: true }).click({ noWaitAfter: true }),
    ]);
}

test.use({ locale: 'en-US', viewport: { width: 1280, height: 900 }, trace: 'on' });

test('focused UI retest records the three failures and Party prerequisite', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const stamp = new Date().toISOString().replace(/[:.]/g, '-');
    const evidenceDirectory = path.resolve(`artifacts/all-user-stories-ui-focused-retest-${stamp}`);
    await mkdir(evidenceDirectory, { recursive: true });
    const result = { startedAt: new Date().toISOString(), baseUrl: testInfo.project.use.baseURL, findings: {} };

    await login(page);

    await page.goto('/catalog/products', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Add Product', exact: true }).click();
    result.findings.product = {
        dialogVisibleAfterFiveSeconds: await page.getByRole('heading', { name: 'Create product identity', exact: true }).isVisible({ timeout: 5_000 }).catch(() => false),
    };
    await page.screenshot({ path: path.join(evidenceDirectory, 'US-002-product-add.png'), fullPage: true });

    await page.goto('/pos', { waitUntil: 'domcontentloaded' });
    const posBody = await page.locator('body').innerText();
    result.findings.pos = {
        branchContext: /Branch Context:\s*MAIN/i.test(posBody),
        sellingStoreContext: /Selling Store:\s*MAIN-SALES/i.test(posBody),
        noOpenShift: /No open shift|Shift not open/i.test(posBody),
        demoProductVisible: /Demo Building Blocks/i.test(posBody),
        demoProductUnpriced: /No approved price/i.test(posBody),
        addEnabled: await page.getByRole('button', { name: 'Add', exact: true }).first().isEnabled().catch(() => false),
    };
    await page.screenshot({ path: path.join(evidenceDirectory, 'US-017-pos-context.png'), fullPage: true });

    await page.goto('/parties/bookings/create', { waitUntil: 'domcontentloaded' });
    const partyStore = page.getByLabel('Party store', { exact: true });
    result.findings.party = {
        selectVisible: await partyStore.isVisible().catch(() => false),
        optionCount: await partyStore.locator('option').count().catch(() => 0),
        options: await partyStore.locator('option').allTextContents().catch(() => []),
    };
    await page.screenshot({ path: path.join(evidenceDirectory, 'US-025-party-prerequisite.png'), fullPage: true });

    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    const guideButton = page.getByRole('button', { name: 'Page Guide', exact: true });
    await guideButton.click();
    await page.waitForTimeout(1_500);
    const guideOpen = await page.locator('#page-guide-drawer').isVisible().catch(() => false);
    if (guideOpen) {
        const close = page.locator('#page-guide-drawer').getByRole('button', { name: 'Close', exact: true });
        if (await close.isVisible().catch(() => false)) await close.click();
    }

    const customizerButton = page.getByRole('button', { name: 'Appearance Customizer', exact: true });
    await customizerButton.click();
    await page.waitForTimeout(1_500);
    const customizerOpen = await page.locator('#appearance-customizer').isVisible().catch(() => false);
    let preferenceResponseStatus = null;
    let darkPersisted = false;
    if (customizerOpen) {
        const responsePromise = page.waitForResponse((response) => new URL(response.url()).pathname === '/ui/preferences', { timeout: 10_000 }).catch(() => null);
        await page.locator('#appearance-customizer select').first().selectOption('dark');
        preferenceResponseStatus = (await responsePromise)?.status() ?? null;
        await page.reload({ waitUntil: 'domcontentloaded' });
        darkPersisted = await page.locator('html').evaluate((root) => root.dataset.appearance === 'dark' || root.classList.contains('dark'));
        await page.getByRole('button', { name: 'Appearance Customizer', exact: true }).click();
        await page.waitForTimeout(750);
        if (await page.locator('#appearance-customizer').isVisible().catch(() => false)) {
            await page.locator('#appearance-customizer select').first().selectOption('system');
            await page.waitForTimeout(750);
        }
    }
    result.findings.assistant = { guideOpen, customizerOpen, preferenceResponseStatus, darkPersisted };
    await page.screenshot({ path: path.join(evidenceDirectory, 'US-046-assistant.png'), fullPage: true });

    result.finishedAt = new Date().toISOString();
    const resultFile = path.join(evidenceDirectory, 'results.json');
    await writeFile(resultFile, JSON.stringify(result, null, 2), 'utf8');
    await testInfo.attach('focused-ui-retest-results', { path: resultFile, contentType: 'application/json' });
});
