import { test, expect } from '@playwright/test';

const administrator = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

test.use({
    headless: false,
    locale: 'en-US',
    viewport: { width: 1280, height: 900 },
    trace: 'on',
    screenshot: 'on',
    launchOptions: { slowMo: 120 },
});

async function login(page) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill(administrator.username);
    await page.getByLabel('Password', { exact: true }).fill(administrator.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);
}

test('CF-02 visibly follows a saved Branch identity from Branches to its linked Stores', async ({ page, context }, testInfo) => {
    test.setTimeout(180_000);
    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText}`));

    await context.addCookies([{ name: 'locale', value: 'en', url: testInfo.project.use.baseURL }]);
    await login(page);
    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    const branchesTable = page.locator('[data-guide="branches-table"]');
    const branchEditAction = branchesTable.getByTitle('Edit').first();
    await expect(branchEditAction).toBeVisible();

    await branchEditAction.click();
    const branchEditDialog = page.getByRole('dialog');
    await expect(branchEditDialog).toBeVisible();
    const previousIdentity = {
        code: await branchEditDialog.getByLabel('Branch Code', { exact: true }).inputValue(),
        nameAr: await branchEditDialog.getByLabel('Arabic Name', { exact: true }).inputValue(),
        nameEn: await branchEditDialog.getByLabel('English Name', { exact: true }).inputValue(),
    };
    const runToken = Date.now().toString().slice(-6);
    const branchIdentity = {
        code: `CF02-${runToken}`,
        nameAr: `فرع تحقق ${runToken}`,
        nameEn: `CF-02 Verification ${runToken}`,
    };
    await branchEditDialog.getByLabel('Branch Code', { exact: true }).fill(branchIdentity.code);
    await branchEditDialog.getByLabel('Arabic Name', { exact: true }).fill(branchIdentity.nameAr);
    await branchEditDialog.getByLabel('English Name', { exact: true }).fill(branchIdentity.nameEn);
    await branchEditDialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
    await expect(page.getByText('Branch updated successfully.', { exact: true })).toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByText(branchIdentity.code, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(branchIdentity.nameAr, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(branchIdentity.nameEn, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(previousIdentity.code, { exact: true })).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('cf02-branches-reloaded-en-desktop.png'), fullPage: true });

    await page.goto('/admin/stores', { waitUntil: 'domcontentloaded' });
    const storesTable = page.locator('[data-guide="stores-table"]');
    await expect(storesTable.locator('tr').filter({ hasText: branchIdentity.code }).filter({ hasText: branchIdentity.nameEn })).toHaveCount(2);
    await expect(storesTable.locator('tr').filter({ hasText: previousIdentity.code })).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('cf02-linked-stores-reloaded-en-desktop.png'), fullPage: true });

    expect(consoleErrors, 'CR-003 browser propagation must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CR-003 browser propagation must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CR-003 browser propagation must not emit failed requests.').toEqual([]);
});
