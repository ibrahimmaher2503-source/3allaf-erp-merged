import { test, expect } from '@playwright/test';

const administrator = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

test.use({
    headless: false,
    locale: 'en-US',
    viewport: { width: 1440, height: 960 },
    launchOptions: { slowMo: 100 },
    screenshot: 'on',
    trace: 'on',
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

async function assertDialogFitsViewport(dialog) {
    const bounds = await dialog.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return {
            withinViewport: rect.left >= 0 && rect.right <= window.innerWidth && rect.top >= 0 && rect.bottom <= window.innerHeight,
            scrollWidth: element.scrollWidth,
            clientWidth: element.clientWidth,
        };
    });
    expect(bounds.withinViewport, 'Archive modal must remain visible in the headed desktop viewport.').toBeTruthy();
    expect(bounds.scrollWidth, 'Archive modal must not clip horizontal content.').toBeLessThanOrEqual(bounds.clientWidth + 1);
}

test('CF-08 archives safely, preserves pending state, and blocks mapped POS deactivation', async ({ page, context }, testInfo) => {
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
    await page.goto('/admin/stores', { waitUntil: 'domcontentloaded' });

    const main = page.getByRole('main');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(main.getByRole('heading', { name: 'Location Masters & Branch Mapping', exact: true })).toBeVisible();
    const table = page.locator('[data-guide="stores-table"]');
    await expect(table).toBeVisible();
    await expect(table).toContainText('Warehouse');
    await expect(table).toContainText('Main Branch');
    await expect(table).toContainText('Request archive');
    await expect(table).not.toContainText('Delete store');

    const warehouseRow = table.locator('tr').filter({ hasText: 'MAIN-WAREHOUSE' }).first();
    await expect(warehouseRow).toContainText('Warehouse');
    await expect(warehouseRow).toContainText('Request archive');
    await warehouseRow.getByRole('button', { name: 'Request archive', exact: true }).click();

    const archiveDialog = page.getByRole('dialog');
    await expect(archiveDialog).toBeVisible();
    await expect(archiveDialog.getByText('Request archive approval', { exact: true })).toBeVisible();
    await expect(archiveDialog).toContainText('MAIN-WAREHOUSE');
    await expect(archiveDialog).toContainText('Main Warehouse');
    await expect(archiveDialog).toContainText('Warehouse');
    await expect(archiveDialog).toContainText('MAIN');
    await expect(archiveDialog).toContainText('History is preserved.');
    await expect(archiveDialog).toContainText('A second authorized approver is required.');
    await expect(archiveDialog).not.toContainText('Delete');
    await assertDialogFitsViewport(archiveDialog);

    await archiveDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(archiveDialog).toBeHidden();
    await expect(warehouseRow).toContainText('Active');
    await expect(warehouseRow).not.toContainText('Pending archive approval');

    await warehouseRow.getByRole('button', { name: 'Request archive', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Request archive', exact: true }).click();
    await expect(page.getByText('Archive request submitted for independent approval.', { exact: true })).toBeVisible();
    await expect(page.locator('[data-guide="stores-table"] tr').filter({ hasText: 'MAIN-WAREHOUSE' }).first()).toContainText('Pending archive approval');
    await expect(page.locator('[data-guide="stores-table"] tr').filter({ hasText: 'MAIN-WAREHOUSE' }).first().getByRole('button', { name: 'Request archive', exact: true })).toHaveCount(0);

    await page.goto('/admin/approvals', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Approval inbox', exact: true })).toBeVisible();
    const approvalTable = page.getByRole('table', { name: 'Approval requests' });
    await expect(approvalTable).toContainText('Request archive');
    await expect(approvalTable).not.toContainText('store_archive');
    await expect(approvalTable).not.toContainText('Delete');

    await page.goto('/admin/stores', { waitUntil: 'domcontentloaded' });
    const posRow = page.locator('[data-guide="stores-table"] tr').filter({ hasText: 'MAIN-SALES' }).first();
    await expect(posRow).toContainText('Point of Sale (POS)');
    await expect(posRow).toContainText('MAIN');
    await expect(posRow.getByRole('button', { name: 'Deactivate', exact: true })).toBeVisible();
    await posRow.getByRole('button', { name: 'Deactivate', exact: true }).click();
    await expect(page.getByText('Unmap POS first', { exact: false })).toBeVisible();
    await expect(posRow).toContainText('Active');

    await page.screenshot({ path: testInfo.outputPath('cf008-stores-archive-pending-en-headed.png'), fullPage: true });
    expect(consoleErrors, 'CF-08 must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CF-08 must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CF-08 must not emit failed requests.').toEqual([]);
});
