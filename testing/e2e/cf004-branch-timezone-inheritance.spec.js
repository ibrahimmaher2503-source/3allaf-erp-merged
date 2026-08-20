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

async function createBranch(page, identity, expectedDefault, explicitTimezone = null) {
    await page.getByRole('button', { name: 'Add Branch', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Timezone', { exact: true })).toHaveValue(expectedDefault);
    await dialog.getByLabel('Branch Code', { exact: true }).fill(identity.code);
    await dialog.getByLabel('Arabic Name', { exact: true }).fill(identity.nameAr);
    await dialog.getByLabel('English Name', { exact: true }).fill(identity.nameEn);
    if (explicitTimezone !== null) {
        await dialog.getByLabel('Timezone', { exact: true }).fill(explicitTimezone);
    }
    await dialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
    await expect(page.getByText('Branch created successfully.', { exact: true }).first()).toBeVisible();
    await expect(dialog).toBeHidden();
}

async function setCompanyTimezone(page, timezone) {
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Timezone', { exact: true }).selectOption(timezone);
    const review = page.getByRole('button', { name: 'Review changes', exact: true });
    await expect(review).toBeEnabled();
    await review.click();
    await expect(page.getByText('Review company changes', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Confirm and save', exact: true }).click();
    await expect(page.getByText('Company settings saved successfully.', { exact: true })).toBeVisible();
}

test('CF-04 inherits the company timezone while preserving explicit create and edit overrides', async ({ page, context }, testInfo) => {
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
    await expect(page.locator('[data-guide="branches-table"] tr').filter({ hasText: 'MAIN' })).toContainText('Africa/Cairo');

    const runToken = Date.now().toString().slice(-6);
    const inherited = {
        code: `CF04-I-${runToken}`,
        nameAr: 'فرع توقيت الشركة',
        nameEn: 'Company Timezone Branch',
    };
    const explicit = {
        code: `CF04-E-${runToken}`,
        nameAr: 'فرع توقيت صريح',
        nameEn: 'Explicit Timezone Branch',
    };

    await createBranch(page, inherited, 'Africa/Cairo');
    await createBranch(page, explicit, 'Africa/Cairo', 'Asia/Riyadh');

    await page.reload({ waitUntil: 'domcontentloaded' });
    let inheritedRow = page.locator('[data-guide="branches-table"] tr').filter({ hasText: inherited.code });
    const explicitRow = page.locator('[data-guide="branches-table"] tr').filter({ hasText: explicit.code });
    await expect(inheritedRow).toContainText('Africa/Cairo');
    await expect(explicitRow).toContainText('Asia/Riyadh');

    await setCompanyTimezone(page, 'UTC');
    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    inheritedRow = page.locator('[data-guide="branches-table"] tr').filter({ hasText: inherited.code });
    await inheritedRow.getByRole('button', { name: 'Edit', exact: true }).click();
    const editDialog = page.getByRole('dialog');
    await expect(editDialog).toBeVisible();
    await expect(editDialog.getByLabel('Timezone', { exact: true })).toHaveValue('Africa/Cairo');
    await editDialog.getByLabel('English Name', { exact: true }).fill('Company Timezone Branch Edited');
    await editDialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
    await expect(page.getByText('Branch updated successfully.', { exact: true }).first()).toBeVisible();

    await page.reload({ waitUntil: 'domcontentloaded' });
    inheritedRow = page.locator('[data-guide="branches-table"] tr').filter({ hasText: inherited.code });
    await expect(inheritedRow).toContainText('Company Timezone Branch Edited');
    await expect(inheritedRow).toContainText('Africa/Cairo');
    await page.screenshot({ path: testInfo.outputPath('cf04-timezone-inheritance-and-overrides-en-desktop.png'), fullPage: true });

    await setCompanyTimezone(page, 'Africa/Cairo');

    expect(consoleErrors, 'CF-04 workflow must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CF-04 workflow must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CF-04 workflow must not emit failed requests.').toEqual([]);
});
