import { test, expect } from '@playwright/test';

const administrator = {
    username: 'admin',
    password: 'ToyJoy!Bootstrap2026',
};

const identity = {
    code: 'CR002-BROWSER',
    legal_name: 'Toy & Joy Browser Verification Company',
    name_ar: 'شركة ألعاب وفرح متصفح',
    name_en: 'Toy & Joy Browser Verification',
    tax_number: '300000000000003',
    commercial_registration: '1010000000',
    currency_code: 'EGP',
    currency_symbol: 'ج.م',
    timezone: 'Africa/Cairo',
    locale_default: 'ar',
    phone: '+201001234567',
    email: 'cr002-browser@toyjoy.test',
    address: '12 CR-002 Evidence Street, Cairo, Egypt',
    policy_notes: 'Disposable CR-002 headed-browser evidence only.',
};
const invalidCurrencyCode = 'EGP-TOO-LONG';

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

async function fillIdentity(page) {
    await page.getByLabel('Company Code', { exact: true }).fill(identity.code);
    await page.getByLabel('Legal Name', { exact: true }).fill(identity.legal_name);
    await page.getByLabel('Name (Arabic)', { exact: true }).fill(identity.name_ar);
    await page.getByLabel('Name (English)', { exact: true }).fill(identity.name_en);
    await page.getByLabel('Tax Identification Number (TIN)', { exact: true }).fill(identity.tax_number);
    await page.getByLabel('Commercial Registration (CR)', { exact: true }).fill(identity.commercial_registration);
    await page.getByLabel('Currency code', { exact: true }).fill(identity.currency_code);
    await page.getByLabel('Currency symbol', { exact: true }).fill(identity.currency_symbol);
    await page.getByLabel('Timezone', { exact: true }).selectOption(identity.timezone);
    await page.getByLabel('Default Application Locale', { exact: true }).selectOption(identity.locale_default);
    await page.getByLabel('Contact Phone', { exact: true }).fill(identity.phone);
    await page.getByLabel('Contact Email', { exact: true }).fill(identity.email);
    await page.getByLabel('Address', { exact: true }).fill(identity.address);
    await page.getByLabel('Policy & Baseline Notes', { exact: true }).fill(identity.policy_notes);
}

async function expectIdentity(page) {
    await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue(identity.code);
    await expect(page.getByLabel('Legal Name', { exact: true })).toHaveValue(identity.legal_name);
    await expect(page.getByLabel('Name (Arabic)', { exact: true })).toHaveValue(identity.name_ar);
    await expect(page.getByLabel('Name (English)', { exact: true })).toHaveValue(identity.name_en);
    await expect(page.getByLabel('Tax Identification Number (TIN)', { exact: true })).toHaveValue(identity.tax_number);
    await expect(page.getByLabel('Commercial Registration (CR)', { exact: true })).toHaveValue(identity.commercial_registration);
    await expect(page.getByLabel('Currency code', { exact: true })).toHaveValue(identity.currency_code);
    await expect(page.getByLabel('Currency symbol', { exact: true })).toHaveValue(identity.currency_symbol);
    await expect(page.getByLabel('Timezone', { exact: true })).toHaveValue(identity.timezone);
    await expect(page.getByLabel('Default Application Locale', { exact: true })).toHaveValue(identity.locale_default);
    await expect(page.getByLabel('Contact Phone', { exact: true })).toHaveValue(identity.phone);
    await expect(page.getByLabel('Contact Email', { exact: true })).toHaveValue(identity.email);
    await expect(page.getByLabel('Address', { exact: true })).toHaveValue(identity.address);
    await expect(page.getByLabel('Policy & Baseline Notes', { exact: true })).toHaveValue(identity.policy_notes);
}

test('CR-002 reviews dirty company edits, protects internal navigation, and persists confirmed identity', async ({ page, context }, testInfo) => {
    test.setTimeout(180_000);
    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    const beforeUnloadDialogs = [];
    const internalNavigationDialogs = [];
    let acceptBeforeUnload = false;
    let acceptInternalNavigation = false;
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText}`));
    page.on('dialog', async (dialog) => {
        if (dialog.type() === 'beforeunload') {
            beforeUnloadDialogs.push(dialog.message());
            await (acceptBeforeUnload ? dialog.accept() : dialog.dismiss());
            return;
        }
        if (dialog.type() === 'confirm') {
            internalNavigationDialogs.push(dialog.message());
            await (acceptInternalNavigation ? dialog.accept() : dialog.dismiss());
            return;
        }
        await dialog.dismiss();
    });

    await context.addCookies([{ name: 'locale', value: 'en', url: testInfo.project.use.baseURL }]);
    await login(page);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    const originalCode = await page.getByLabel('Company Code', { exact: true }).inputValue();
    const reviewAction = page.getByRole('button', { name: 'Review changes', exact: true });
    await expect(reviewAction).toBeDisabled();

    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('link', { name: 'Dashboard', exact: true }).click(),
    ]);
    expect(internalNavigationDialogs, 'Clean settings must not prompt before an internal Livewire navigation.').toHaveLength(0);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });

    await fillIdentity(page);
    await expect(reviewAction).toBeEnabled();
    await page.getByLabel('Currency code', { exact: true }).fill(invalidCurrencyCode);
    await reviewAction.click();
    await expect(page.getByLabel('Currency code', { exact: true })).toHaveValue(invalidCurrencyCode);
    await expect(page.getByText(/must not be greater than 10 characters/i).first()).toBeVisible();
    await page.getByLabel('Currency code', { exact: true }).fill(identity.currency_code);
    await expect(reviewAction).toBeEnabled();
    await reviewAction.click();
    await expect(page.getByText('Review company changes', { exact: true })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('cr002-review-en-desktop.png'), fullPage: true });
    await expect(page.getByText('Review these values before confirming the save.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Back to edit', exact: true }).click();

    await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/settings$/);
    await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue(identity.code);
    expect(internalNavigationDialogs, 'Dirty internal navigation must show a localized confirmation.').toHaveLength(1);
    expect(internalNavigationDialogs[0]).toBe('You have unsaved company identity changes. Leave this page without saving?');
    await page.screenshot({ path: testInfo.outputPath('cr002-dirty-navigation-cancelled-en-desktop.png'), fullPage: true });

    acceptInternalNavigation = true;
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('link', { name: 'Dashboard', exact: true }).click(),
    ]);
    expect(internalNavigationDialogs, 'Accepting the dirty-navigation confirmation must allow navigation.').toHaveLength(2);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue(originalCode);

    await page.getByLabel('Company Code', { exact: true }).fill(identity.code);
    acceptBeforeUnload = true;
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByLabel('Company Code', { exact: true })).toHaveValue(originalCode);
    expect(beforeUnloadDialogs, 'Dirty edits must trigger a native beforeunload confirmation.').toHaveLength(1);
    await page.screenshot({ path: testInfo.outputPath('cr002-dirty-reload-confirmed-en-desktop.png'), fullPage: true });

    await fillIdentity(page);
    await expect(reviewAction).toBeEnabled();
    await reviewAction.click();
    await page.getByRole('button', { name: 'Confirm and save', exact: true }).click();
    await expect(page.getByText('Company settings saved successfully.', { exact: true })).toBeVisible();
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('link', { name: 'Dashboard', exact: true }).click(),
    ]);
    expect(internalNavigationDialogs, 'A confirmed, clean company identity must not prompt before internal navigation.').toHaveLength(2);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expectIdentity(page);
    await page.screenshot({ path: testInfo.outputPath('cr002-confirmed-persisted-en-desktop.png'), fullPage: true });

    await Promise.all([
        page.waitForURL(/\/login|\/$/),
        page.locator('form[action$="/logout"]').first().evaluate((form) => form.requestSubmit()),
    ]);
    await login(page);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    await expectIdentity(page);

    await context.addCookies([{ name: 'locale', value: 'ar', url: testInfo.project.use.baseURL }]);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByLabel(/Company Code|رمز الشركة/).first()).toBeVisible();
    await page.locator('input[name="companyForm.policy_notes"]').fill(`${identity.policy_notes} mobile`);
    await page.locator('[data-guide="settings-save-button"]').scrollIntoViewIfNeeded();
    await expect(page.locator('[data-guide="settings-save-button"]')).toBeEnabled();
    await page.locator('[data-guide="settings-save-button"]').click();
    const mobileReviewDialog = page.getByRole('dialog');
    await expect(mobileReviewDialog).toBeVisible();
    await expect(mobileReviewDialog).toContainText(/Review company changes|مراجعة تغييرات الشركة/);
    await page.screenshot({ path: testInfo.outputPath('cr002-review-ar-rtl-mobile-390x844.png'), fullPage: true });

    expect(consoleErrors, 'Headed CR-002 workflow must capture no browser console errors.').toEqual([]);
    expect(pageErrors, 'Headed CR-002 workflow must capture no page errors.').toEqual([]);
    expect(failedRequests, 'Headed CR-002 workflow must capture no failed network requests.').toEqual([]);
});
