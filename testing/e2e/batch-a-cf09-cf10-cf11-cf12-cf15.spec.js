import { test, expect } from '@playwright/test';

test.use({ locale: 'en-US' });

test('Batch A phone, sidebar, settings, payment, printer, and label readiness workflow', async ({ page }) => {
    test.setTimeout(120_000);
    const pageErrors = [];
    const failedRequests = [];
    const runId = String(Date.now()).slice(-8);
    const customerName = `Batch A Phone Customer ${runId}`;
    const printerName = `Batch A Thermal Printer ${runId}`;
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));
    page.on('response', (response) => {
        if (response.status() >= 500 || (response.url().includes('/livewire-') && response.status() >= 400)) {
            failedRequests.push(`${response.status()} ${response.url()}`);
        }
    });

    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Username', { exact: true }).fill('admin');
    await page.getByLabel('Password', { exact: true }).fill('ToyJoy!Bootstrap2026');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);
    await expect(page.getByRole('navigation', { name: 'Workspace' })).toBeVisible();

    await page.goto('/customers/create', { waitUntil: 'domcontentloaded' });
    const phone = page.getByLabel('Primary phone', { exact: true });
    const uniquePhone = `0101234${String(Date.now()).slice(-4)}`;
    await phone.fill(`+20 ${uniquePhone.slice(1)}`);
    await page.getByLabel('Arabic name', { exact: true }).fill(`عميل دفعة أ ${runId}`);
    await page.getByLabel('English name', { exact: true }).fill(customerName);
    await page.getByLabel('Consent purpose', { exact: true }).selectOption({ label: 'service' });
    await page.getByRole('button', { name: 'Create customer profile', exact: true }).click();
    await expect(page).toHaveURL(/\/customers\/\d+$/);
    await expect(page.locator('input[name="phone"]')).toHaveValue(`+20 ${uniquePhone.slice(1)}`);

    await page.goto('/customers/create', { waitUntil: 'domcontentloaded' });
    const invalidPhone = page.getByLabel('Primary phone', { exact: true });
    await invalidPhone.fill('0101234');
    await page.getByLabel('Arabic name', { exact: true }).fill(`عميل غير صالح ${runId}`);
    await page.getByLabel('English name', { exact: true }).fill(`Invalid Batch A Customer ${runId}`);
    await page.getByLabel('Consent purpose', { exact: true }).selectOption({ label: 'service' });
    await page.getByRole('button', { name: 'Create customer profile', exact: true }).click();
    await expect(page).toHaveURL(/\/customers\/create$/);
    await expect(page.getByRole('alert').filter({ hasText: /Enter a valid Egyptian phone number/ }).first()).toBeVisible();
    await expect(invalidPhone).toHaveValue('0101234');

    await page.goto('/admin/settings?tab=payments', { waitUntil: 'domcontentloaded' });
    const paymentsTab = page.locator('[data-settings-tab="payments"]');
    await expect(paymentsTab).toHaveAttribute('aria-selected', 'true');
    const settingsLink = page.locator('[data-settings-entry="canonical"]');
    await expect(settingsLink).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('[data-settings-section-summary="payments"]')).toContainText('Payment Methods');
    await expect(page.locator('[data-settings-section-summary="payments"]')).toContainText('recognize');
    await expect(page.getByRole('navigation', { name: 'Workspace' }).locator('[data-sidebar-section="administration"]')).toBeVisible();

    await page.getByRole('button', { name: 'Requires payment evidence', exact: true }).waitFor();
    await expect(page.getByRole('button', { name: 'Available for approved offline POS transactions', exact: true })).toBeVisible();
    await expect(page.getByLabel('Underlying payment type', { exact: true })).toHaveValue('manual');
    await expect(page.getByText('This setting does not approve a device or change offline limits.')).toBeVisible();
    const paymentCode = page.getByLabel('Method Code', { exact: true });
    await paymentCode.fill('BATCH-A-CARD');
    await page.getByLabel('Name (Arabic)', { exact: true }).fill('بطاقة دفعة أ');
    await page.getByLabel('Name (English)', { exact: true }).fill('Batch A Card');
    await page.getByLabel('Underlying payment type', { exact: true }).selectOption('card');
    await page.getByRole('button', { name: 'Available for approved offline POS transactions', exact: true }).click();
    await page.getByRole('button', { name: 'Save Method', exact: true }).click();
    await expect(page.getByText('Only cash or electronic-wallet methods can be approved for offline POS use.')).toBeVisible();
    await expect(paymentCode).toHaveValue('BATCH-A-CARD');
    await expect(page.getByLabel('Underlying payment type', { exact: true })).toHaveValue('card');

    await page.goto('/admin/settings?tab=printers', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-settings-tab="printers"]')).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('[data-settings-section-summary="printers"]')).toContainText('Printers & Print Profiles');
    await expect(page.getByText('printer profile describes where a document is sent', { exact: false })).toBeVisible();
    const templateKey = page.getByLabel('Print template key', { exact: true });
    await expect(templateKey).toHaveValue('default_thermal');
    await expect(page.getByRole('button', { name: 'Default printer profile', exact: true })).toBeVisible();
    await page.getByLabel('Printer Name', { exact: true }).fill(printerName);
    await page.getByRole('button', { name: 'Save Printer Profile', exact: true }).click();
    const printerRow = page.getByRole('row').filter({ hasText: printerName });
    await expect(printerRow).toBeVisible();
    await expect(printerRow).toContainText('default_thermal');
    await expect(printerRow).toContainText('Default');
    const preview = page.waitForEvent('popup');
    await printerRow.getByRole('button', { name: 'Preview setup', exact: true }).click();
    const previewPage = await preview;
    await previewPage.waitForLoadState('domcontentloaded');
    await expect(previewPage.getByText('Printer Profile', { exact: true })).toBeVisible();
    await expect(previewPage.getByText('Print Template', { exact: true })).toBeVisible();
    await previewPage.close();

    await page.goto('/pricing/labels', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Label queue readiness', { exact: true })).toBeVisible();
    await expect(page.getByText('0 queued jobs', { exact: true })).toBeVisible();
    await expect(page.getByText('No label jobs are queued yet', { exact: true })).toBeVisible();
    await expect(page.getByText('A job is not created just because a printer profile exists.', { exact: true })).toBeVisible();

    await expect(pageErrors).toEqual([]);
    await expect(failedRequests).toEqual([]);
});
