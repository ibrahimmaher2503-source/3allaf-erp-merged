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
    launchOptions: { slowMo: 100 },
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

async function fillLocation(dialog, location, branchLabel) {
    await dialog.getByLabel('Location Code', { exact: true }).fill(location.code);
    await dialog.getByLabel('Location Type', { exact: true }).selectOption('warehouse');
    await dialog.getByLabel('Branch / Location Context (Optional)', { exact: true }).selectOption({ label: branchLabel });
    await dialog.getByLabel('Arabic Name', { exact: true }).fill(location.nameAr);
    await dialog.getByLabel('English Name', { exact: true }).fill(location.nameEn);
}

async function assertNoHeaderCellCollision(table, expectedHeaders) {
    const headers = table.locator('thead th');
    await expect(headers).toHaveCount(expectedHeaders);
    const boxes = await headers.evaluateAll((elements) => elements.map((element) => {
        const rect = element.getBoundingClientRect();

        return { left: rect.left, right: rect.right, width: rect.width };
    }));

    for (let index = 0; index < boxes.length - 1; index += 1) {
        expect(boxes[index].right, `header ${index} must not overlap header ${index + 1}`).toBeLessThanOrEqual(boxes[index + 1].left + 0.5);
    }
}

test('CF-05 branch warehouse count, terminology, and active branch selectors work in headed English Chromium', async ({ page, context }, testInfo) => {
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

    const token = Date.now().toString().slice(-6);
    const activeBranch = `CF05-${token}`;
    const activeBranchName = `CF-05 Active ${token}`;
    const inactiveBranch = `CF05-OFF-${token}`;
    const inactiveBranchName = `CF-05 Inactive ${token}`;
    const activeBranchLabel = `${activeBranch} - ${activeBranchName}`;

    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Branch Masters', exact: true })).toBeVisible();

    for (const branch of [
        { code: activeBranch, name: activeBranchName, status: 'active' },
        { code: inactiveBranch, name: inactiveBranchName, status: 'inactive' },
    ]) {
        await page.getByRole('main').getByRole('button', { name: 'Add Branch', exact: true }).click();
        const branchDialog = page.getByRole('dialog');
        await expect(branchDialog).toBeVisible();
        await branchDialog.getByLabel('Branch Code', { exact: true }).fill(branch.code);
        await branchDialog.getByLabel('Arabic Name', { exact: true }).fill(`فرع ${branch.code}`);
        await branchDialog.getByLabel('English Name', { exact: true }).fill(branch.name);
        await branchDialog.getByLabel('Status', { exact: true }).selectOption(branch.status);
        await branchDialog.getByRole('button', { name: 'Save Branch', exact: true }).click();
        await expect(branchDialog).toBeHidden();
    }

    await page.goto('/admin/stores', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Location Masters & Branch Mapping', exact: true })).toBeVisible();

    await page.getByRole('main').getByRole('button', { name: 'Add Location', exact: true }).click();
    const createDialog = page.getByRole('dialog');
    await expect(createDialog).toBeVisible();
    await expect(createDialog.getByLabel('Location Code', { exact: true })).toBeVisible();
    await expect(createDialog.getByLabel('Location Type', { exact: true }).locator('option[value="selling"]')).toHaveText('Point of Sale (POS)');
    await expect(createDialog.getByLabel('Location Type', { exact: true }).locator('option[value="warehouse"]')).toHaveText('Warehouse');
    const branchSelector = createDialog.getByLabel('Branch / Location Context (Optional)', { exact: true });
    await expect(branchSelector.locator('option', { hasText: `${activeBranch} - ${activeBranchName}` })).toHaveCount(1);
    await expect(branchSelector.locator('option', { hasText: inactiveBranch })).toHaveCount(0);

    const activeLocation = { code: `CF05-WH-${token}`, nameAr: `مخزن ${token}`, nameEn: `CF-05 Warehouse ${token}` };
    await fillLocation(createDialog, activeLocation, activeBranchLabel);
    await expect(branchSelector.locator('option:checked')).toHaveText(activeBranchLabel);
    const createSelectorBox = await branchSelector.boundingBox();
    expect(createSelectorBox?.width ?? 0, 'create branch context selector must use the full modal row').toBeGreaterThan(500);
    await createDialog.screenshot({ path: testInfo.outputPath('cf005-location-create-selected.png'), animations: 'disabled' });
    await createDialog.getByRole('button', { name: 'Save Location', exact: true }).click();
    await expect(createDialog).toBeHidden();
    await expect(page.getByText('Location created successfully.', { exact: true }).first()).toBeVisible();

    await page.getByRole('main').getByRole('button', { name: 'Add Location', exact: true }).click();
    const inactiveLocationDialog = page.getByRole('dialog');
    const inactiveLocation = { code: `CF05-WH-OFF-${token}`, nameAr: `مخزن غير فعال ${token}`, nameEn: `CF-05 Inactive Warehouse ${token}` };
    await fillLocation(inactiveLocationDialog, inactiveLocation, activeBranchLabel);
    await inactiveLocationDialog.getByLabel('Status', { exact: true }).selectOption('inactive');
    await inactiveLocationDialog.getByRole('button', { name: 'Save Location', exact: true }).click();
    await expect(inactiveLocationDialog).toBeHidden();

    const activeLocationRow = page.locator('[data-guide="stores-table"] tr').filter({ hasText: activeLocation.code });
    await expect(activeLocationRow).toHaveCount(1);
    await assertNoHeaderCellCollision(page.locator('[data-guide="stores-table"]'), 9);
    await page.screenshot({ path: testInfo.outputPath('cf005-locations-table-1280x900.png'), fullPage: false, animations: 'disabled' });
    await expect(activeLocationRow).toContainText(activeBranch);
    await expect(activeLocationRow).toContainText(activeBranchName);
    await expect(activeLocationRow.getByTestId(/store-type-/)).toHaveText('Warehouse');

    await activeLocationRow.getByRole('button', { name: 'Edit', exact: true }).click();
    const editDialog = page.getByRole('dialog');
    await expect(editDialog.getByLabel('Location Code', { exact: true })).toBeVisible();
    const editBranchSelector = editDialog.getByLabel('Branch / Location Context (Optional)', { exact: true });
    await expect(editBranchSelector.locator('option:checked')).toHaveText(activeBranchLabel);
    const editSelectorBox = await editBranchSelector.boundingBox();
    expect(editSelectorBox?.width ?? 0, 'edit branch context selector must use the full modal row').toBeGreaterThan(500);
    await editDialog.screenshot({ path: testInfo.outputPath('cf005-location-edit-selected.png'), animations: 'disabled' });
    await editDialog.getByRole('button', { name: 'Cancel', exact: true }).click();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const reloadedLocationRow = page.locator('[data-guide="stores-table"] tr').filter({ hasText: activeLocation.code });
    await reloadedLocationRow.getByRole('button', { name: 'Edit', exact: true }).click();
    const reloadedEditDialog = page.getByRole('dialog');
    const reloadedEditBranchSelector = reloadedEditDialog.getByLabel('Branch / Location Context (Optional)', { exact: true });
    await expect(reloadedEditBranchSelector.locator('option:checked')).toHaveText(activeBranchLabel);
    await reloadedEditDialog.screenshot({ path: testInfo.outputPath('cf005-location-edit-reloaded.png'), animations: 'disabled' });
    await reloadedEditDialog.getByRole('button', { name: 'Cancel', exact: true }).click();

    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    const branchRow = page.locator('[data-guide="branches-table"] tr').filter({ hasText: activeBranch });
    await expect(branchRow).toHaveCount(1);
    await assertNoHeaderCellCollision(page.locator('[data-guide="branches-table"]'), 9);
    await expect(branchRow.getByTestId(/branch-warehouse-count-/)).toHaveText('1');
    await expect(branchRow.getByTestId(/branch-warehouse-label-/)).toHaveText('Warehouse');
    await page.screenshot({ path: testInfo.outputPath('cf005-branch-warehouse-en-headed.png'), fullPage: true });

    expect(consoleErrors, 'CF-05 must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CF-05 must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CF-05 must not emit failed requests.').toEqual([]);
});
