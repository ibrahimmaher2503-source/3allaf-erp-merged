import { test, expect } from '@playwright/test';

const administrator = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

test.use({
    headless: false,
    locale: 'en-US',
    viewport: { width: 1440, height: 960 },
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

async function openAssignmentDialog(page) {
    const branchesTable = page.locator('[data-guide="branches-table"]');
    const mainRow = branchesTable.locator('tr').filter({ hasText: 'MAIN' }).first();
    await expect(mainRow).toContainText('POS selling & stock location');
    await mainRow.getByRole('button', { name: 'Assign POS selling & stock location', exact: true }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText('Assign POS selling & stock location', { exact: true })).toBeVisible();
    await expect(dialog).toContainText('Branch source');
    await expect(dialog).toContainText('POS selling location and stock source');
    await expect(dialog).toContainText('The selected POS selling location is also the stock source for sales.');
    await expect(dialog.getByLabel('POS selling location / stock source', { exact: true })).toBeVisible();
    await expect(dialog).toContainText('Only active selling locations belonging to this branch are shown.');

    const bounds = await dialog.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return {
            withinViewport: rect.left >= 0 && rect.right <= window.innerWidth && rect.top >= 0 && rect.bottom <= window.innerHeight,
            scrollWidth: element.scrollWidth,
            clientWidth: element.clientWidth,
        };
    });
    expect(bounds.withinViewport, 'Assignment dialog must not be clipped in the headed desktop viewport.').toBeTruthy();
    expect(bounds.scrollWidth, 'Assignment dialog must not have horizontal content overflow.').toBeLessThanOrEqual(bounds.clientWidth + 1);

    return dialog;
}

test('CF-06 configures the branch POS source and proves ready and mismatched POS guidance', async ({ page, context }, testInfo) => {
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
    await expect(branchesTable).toBeVisible();
    await expect(branchesTable).toContainText('POS selling & stock location');
    await expect(branchesTable).toContainText('MAIN → MAIN-SALES');
    await expect(branchesTable).toContainText('Stock source: same POS selling location');
    const mainRow = branchesTable.locator('tr').filter({ hasText: 'MAIN' }).first();
    await expect(mainRow).toContainText('Active');
    await expect(mainRow).toContainText('Assign POS selling & stock location');

    const assignmentDialog = await openAssignmentDialog(page);
    const assignmentSelect = assignmentDialog.getByLabel('POS selling location / stock source', { exact: true });
    await expect(assignmentSelect).toHaveValue('11');
    await assignmentSelect.selectOption('11');
    await assignmentDialog.getByRole('button', { name: 'Update Mapping', exact: true }).click();
    await expect(page.getByText('Branch selling store mapped successfully.', { exact: true })).toBeVisible();
    await expect(assignmentDialog).toBeHidden();
    await page.screenshot({ path: testInfo.outputPath('cf06-branches-assignment-en-desktop.png'), fullPage: true });

    await page.goto('/pos', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-pos-readiness="ready"]')).toBeVisible();
    const posHeader = page.locator('body > header');
    const workspaceHeader = page.locator('[data-guide="pos-header"]');
    await expect(workspaceHeader).toContainText('New sale');
    await expect(posHeader).toContainText('Branch');
    await expect(posHeader).toContainText('MAIN · Main Branch');
    await expect(posHeader).toContainText('POS selling location');
    await expect(posHeader).toContainText('MAIN-SALES · Main Sales Store');
    await expect(posHeader).toContainText('Stock source');
    await expect(posHeader).toContainText('Same as POS selling location');
    await expect(posHeader).toContainText('Drawer');
    await expect(posHeader).toContainText('MAIN-01');
    await expect(posHeader).toContainText('Shift');
    await expect(posHeader).toContainText('Open');
    await expect(page.getByPlaceholder('Scan or search by name, SKU, barcode, or option')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Search', exact: true }).first()).toBeVisible();
    await expect(page.getByText('No products available.', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Cart is empty', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('cf06-pos-ready-en-desktop.png'), fullPage: true });

    await page.goto('/admin/branches', { waitUntil: 'domcontentloaded' });
    const mismatchDialog = await openAssignmentDialog(page);
    const mismatchSelect = mismatchDialog.getByLabel('POS selling location / stock source', { exact: true });
    await expect(mismatchSelect.locator('option', { hasText: 'CF06-ALT' })).toHaveCount(1);
    await mismatchSelect.selectOption('13');
    await mismatchDialog.getByRole('button', { name: 'Update Mapping', exact: true }).click();
    await expect(page.getByText('Branch selling store mapped successfully.', { exact: true })).toBeVisible();
    await expect(mismatchDialog).toBeHidden();

    await page.goto('/pos', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-pos-readiness="blocked"]')).toBeVisible();
    await expect(page.getByText('POS is disabled because the active shift uses MAIN-SALES', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('CF06-ALT', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('Open a shift from the assigned location.', { exact: false }).first()).toBeVisible();
    await expect(page.getByPlaceholder('Scan or search by name, SKU, barcode, or option')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Add to cart', exact: true })).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('cf06-pos-mismatch-disabled-en-desktop.png'), fullPage: true });

    expect(consoleErrors, 'CF-06 must not emit console errors.').toEqual([]);
    expect(pageErrors, 'CF-06 must not emit page errors.').toEqual([]);
    expect(failedRequests, 'CF-06 must not emit failed requests.').toEqual([]);
});
