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

async function createBranch(page, identity) {
    await page.getByRole('button', { name: 'Add Branch', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Branch Code', { exact: true }).fill(identity.code);
    await dialog.getByLabel('Arabic Name', { exact: true }).fill(identity.nameAr);
    await dialog.getByLabel('English Name', { exact: true }).fill(identity.nameEn);
    await dialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
    await expect(page.getByText('Branch created successfully.', { exact: true }).first()).toBeVisible();
    await expect(dialog).toBeHidden();
}

test('CF-03 visibly creates five additional distinct branches and preserves all six identities after reload and re-login', async ({ page, context }, testInfo) => {
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
    await expect(page.locator('[data-guide="branches-table"]')).toContainText('MAIN');

    const runToken = Date.now().toString().slice(-6);
    const newBranches = Array.from({ length: 5 }, (_, index) => {
        const sequence = String(index + 1).padStart(2, '0');

        return {
            code: `CF03-${runToken}-${sequence}`,
            nameAr: `\u0641\u0631\u0639 \u0627\u062e\u062a\u0628\u0627\u0631 ${sequence}`,
            nameEn: `CF-03 Capacity ${sequence}`,
        };
    });

    for (const identity of newBranches) {
        await createBranch(page, identity);
    }

    await page.getByRole('button', { name: 'Add Branch', exact: true }).click();
    const duplicateDialog = page.getByRole('dialog');
    await expect(duplicateDialog).toBeVisible();
    await duplicateDialog.getByLabel('Branch Code', { exact: true }).fill(` ${newBranches[0].code.toLowerCase()} `);
    await duplicateDialog.getByLabel('Arabic Name', { exact: true }).fill('\u0641\u0631\u0639 \u0645\u0643\u0631\u0631');
    await duplicateDialog.getByLabel('English Name', { exact: true }).fill('Normalized duplicate branch');
    await duplicateDialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
    await expect(duplicateDialog.getByText(/already been taken/i)).toBeVisible();
    await expect(duplicateDialog.getByLabel('Branch Code', { exact: true })).toHaveValue(newBranches[0].code);
    await duplicateDialog.getByRole('button', { name: 'Cancel', exact: true }).click();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const branchesTable = page.locator('[data-guide="branches-table"]');
    await expect(branchesTable.locator('tbody tr')).toHaveCount(6);
    for (const identity of newBranches) {
        const row = branchesTable.locator('tr').filter({ hasText: identity.code });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText(identity.nameAr);
        await expect(row).toContainText(identity.nameEn);
    }
    await page.screenshot({ path: testInfo.outputPath('cf03-six-branch-identities-reloaded-en-desktop.png'), fullPage: true });

    await page.locator('form[action$="/logout"]').first().evaluate((form) => form.requestSubmit());
    await expect(page).toHaveURL(/\/$/);
    await login(page);
    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    for (const identity of newBranches) {
        const row = page.locator('[data-guide="branches-table"] tr').filter({ hasText: identity.code });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText(identity.nameAr);
        await expect(row).toContainText(identity.nameEn);
    }
    await page.screenshot({ path: testInfo.outputPath('cf03-six-branch-identities-relogin-en-desktop.png'), fullPage: true });

    expect(consoleErrors, 'CF-03 branch creation must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CF-03 branch creation must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CF-03 branch creation must not emit failed requests.').toEqual([]);
});
