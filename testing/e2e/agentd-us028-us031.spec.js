import { test, expect } from '@playwright/test';
import { login, LOCAL_DEMO_PASSWORD } from '../helpers/auth.js';

test.describe('Agent D rental, quotation, report surfaces', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'demo-admin', LOCAL_DEMO_PASSWORD);
    });

    test('opens the four real product surfaces in English', async ({ page }) => {
        for (const [path, heading] of [
            ['/party/assets', 'Rental assets & calendar'],
            ['/quotations', 'Quotations'],
            ['/reports', 'Dashboard & KPI reports'],
            ['/exports', 'Export job center'],
        ]) {
            await page.goto(path, { waitUntil: 'domcontentloaded' });
            await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        }
    });

    test('renders the remediated sidebar and opens every focused destination', async ({ page }) => {
        test.setTimeout(300_000);
        const consoleErrors = [];
        const failedRequests = [];
        page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
        page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText}`));

        await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        const sidebarTargets = new Map([
            ['Loyalty & points', '/customers?mode=loyalty'],
            ['Transaction history', '/customers?mode=history'],
            ['Supplier invoices & cost history', '/purchasing/supplier-history'],
            ['Purchase cost history', '/purchasing/cost-history'],
            ['Price lists & versions', '/pricing/versions'],
            ['Unpriced products', '/pricing/unpriced'],
            ['Price change history', '/pricing/history'],
            ['Balances', '/inventory/balances'],
            ['Party bookings', '/parties/bookings'],
            ['Working invoice', '/parties/invoices?mode=working'],
            ['Party payments', '/parties/invoices?mode=payments'],
            ['Operating orders & consumables', '/parties/orders'],
            ['Final close & settlement', '/parties/invoices?mode=settlement'],
            ['Rental assets & calendar', '/party/assets?mode=workspace'],
            ['Asset reservations & checkout', '/party/assets?mode=reservations'],
            ['Return, condition & damages', '/party/assets?mode=returns'],
            ['Depreciation & asset history', '/party/assets?mode=history'],
            ['Sales reports', '/reports/sales'],
            ['Customer & loyalty reports', '/reports/customers'],
            ['Cash & shift reports', '/reports/cash-shifts'],
            ['Purchasing reports', '/reports/purchasing'],
            ['Inventory reports', '/reports/inventory'],
            ['Party reports', '/reports/parties'],
            ['Rental asset reports', '/reports/rental-assets'],
            ['Override log', '/admin/audit?mode=override'],
            ['Print log', '/admin/audit?mode=print'],
        ]);
        const renderedTargets = await page.locator('[data-flux-sidebar-nav] [data-flux-sidebar-item]').evaluateAll((items) => {
            const result = {};
            for (const item of items) {
                const label = item.textContent.replace(/\s+/g, ' ').trim();
                if (!result[label]) result[label] = [];
                result[label].push(new URL(item.href).pathname + new URL(item.href).search);
            }
            return result;
        });
        for (const [label, path] of sidebarTargets) {
            expect([...new Set(renderedTargets[label] ?? [])], label).toEqual([path]);
        }
        expect(renderedTargets['Bookings calendar']).toBeUndefined();

        for (const [path, heading] of [
            ['/customers?mode=history', 'Customer transaction history'],
            ['/customers?mode=loyalty', 'Loyalty & points'],
            ['/purchasing/supplier-history', 'Supplier invoice history'],
            ['/purchasing/cost-history', 'Purchase cost history'],
            ['/pricing/versions', 'Price lists & versions'],
            ['/pricing/unpriced', 'Unpriced products'],
            ['/pricing/history', 'Price change history'],
            ['/inventory/balances', 'Inventory balances'],
            ['/parties/bookings', 'Party bookings'],
            ['/parties/invoices?mode=working', 'Working invoices'],
            ['/parties/invoices?mode=payments', 'Party payment invoices'],
            ['/parties/invoices?mode=settlement', 'Party settlement invoices'],
            ['/party/assets?mode=workspace', 'Rental assets & calendar'],
            ['/party/assets?mode=reservations', 'Asset reservations & checkout'],
            ['/party/assets?mode=returns', 'Return, condition & damages'],
            ['/party/assets?mode=history', 'Depreciation & asset history'],
            ['/reports/sales', 'Sales reports'],
            ['/reports/customers', 'Customer & loyalty reports'],
            ['/reports/cash-shifts', 'Cash & shift reports'],
            ['/reports/purchasing', 'Purchasing reports'],
            ['/reports/inventory', 'Inventory reports'],
            ['/reports/parties', 'Party reports'],
            ['/reports/rental-assets', 'Rental asset reports'],
            ['/admin/audit?mode=override', 'Override log'],
            ['/admin/audit?mode=print', 'Print log'],
        ]) {
            const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
            expect(response?.status(), path).toBe(200);
            await expect(page.getByRole('heading', { name: heading, exact: true }).first()).toBeVisible();
        }
        expect(consoleErrors).toEqual([]);
        expect(failedRequests).toEqual([]);
    });

    test('exports a focused report through the shared private export center', async ({ page }) => {
        test.setTimeout(120_000);
        await page.goto('/reports/sales', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Sales reports', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Create Excel export', exact: true }).click();
        await expect(page.getByText('Export requested. It will appear in the export job center.', { exact: true })).toBeVisible();

        await page.goto('/exports', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Export job center', exact: true })).toBeVisible();
        await expect.poll(async () => {
            await page.reload({ waitUntil: 'domcontentloaded' });
            return await page.locator('table tbody tr').first().getByRole('link', { name: 'Download' }).count() > 0;
        }, { timeout: 30_000, intervals: [500, 1000, 2000] }).toBe(true);

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('table tbody tr').first().getByRole('link', { name: 'Download' }).click(),
        ]);
        expect(download.suggestedFilename()).toMatch(/sales.*\.xlsx$/i);
    });

    test('renders an accessible advanced visual dashboard for every focused report', async ({ page }) => {
        test.setTimeout(180_000);
        for (const path of [
            '/reports/sales',
            '/reports/customers',
            '/reports/cash-shifts',
            '/reports/purchasing',
            '/reports/inventory',
            '/reports/parties',
            '/reports/rental-assets',
        ]) {
            await page.goto(path, { waitUntil: 'domcontentloaded' });
            const dashboard = page.locator('[data-report-dashboard]');
            await expect(dashboard).toBeVisible();
            await expect(dashboard.locator('[data-report-kpi]').first()).toBeVisible();
            await expect(dashboard.locator('[data-report-visual]').first()).toBeVisible();
            await expect(dashboard.locator('[data-report-chart][aria-label]').first()).toBeVisible();
            await expect(dashboard.locator('[data-report-visual-table]').first()).toBeAttached();
            if (process.env.CAPTURE_REPORT_UI === '1' && path === '/reports/sales') {
                await page.screenshot({ path: 'testing/e2e/results/report-advanced-sales-desktop.png', fullPage: true });
            }
        }

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/reports/inventory', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('[data-report-dashboard]')).toBeVisible();
        const dimensions = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
        if (process.env.CAPTURE_REPORT_UI === '1') {
            await page.screenshot({ path: 'testing/e2e/results/report-advanced-inventory-mobile.png', fullPage: true });
        }

        await Promise.all([
            page.waitForURL(/\/reports\/inventory/),
            page.locator('input[name="locale"][value="ar"]').last().evaluate((input) => input.form.submit()),
        ]);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('[data-report-dashboard]')).toBeVisible();
        await expect(page.getByText('تحليلات المخزون', { exact: true })).toBeVisible();
    });

    test('asset surface fits a 390px viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/party/assets', { waitUntil: 'domcontentloaded' });
        await expect(page.getByText('Asset register', { exact: true })).toBeVisible();
        const dimensions = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
    });

    test('switches the application shell to Arabic RTL', async ({ page }) => {
        await page.goto('/party/assets', { waitUntil: 'domcontentloaded' });
        await page.locator('[data-test="sidebar-menu-button"]').click();
        await Promise.all([
            page.waitForURL(/\/party\/assets/),
            page.locator('input[name="locale"][value="ar"]').last().evaluate((input) => input.form.submit()),
        ]);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    });

    test('runs the asset, quotation, and report workflows in the UI', async ({ page }) => {
        test.setTimeout(120_000);
        await page.goto('/party/assets', { waitUntil: 'domcontentloaded' });
        const branchSelect = page.getByRole('combobox', { name: 'Branch', exact: true });
        const storeSelect = page.getByRole('combobox', { name: 'Store', exact: true });
        const branchId = await branchSelect.locator('option').nth(1).getAttribute('value');
        const storeId = await storeSelect.locator('option').nth(1).getAttribute('value');
        const code = `BROWSER-ASSET-${Date.now()}`;
        await page.getByLabel('Asset code', { exact: true }).fill(code);
        await page.getByLabel('Name (English)', { exact: true }).fill('Browser Rental Asset');
        await page.getByLabel('Name (Arabic)', { exact: true }).fill('أصل اختبار المتصفح');
        await branchSelect.selectOption(branchId);
        await storeSelect.selectOption(storeId);
        await page.getByRole('button', { name: 'Create asset', exact: true }).click();
        await expect(page.getByText(code, { exact: true })).toBeVisible();

        const formatDateTime = (date) => {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };
        const start = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000);
        start.setMinutes(0, 0, 0);
        const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);
        let row = page.locator('tr').filter({ hasText: code });
        const reservationForm = row.locator('form[action*="/reservations"]');
        await reservationForm.locator('input[name="starts_at"]').fill(formatDateTime(start));
        await reservationForm.locator('input[name="ends_at"]').fill(formatDateTime(end));
        await reservationForm.locator('input[name="source_reference"]').fill('BROWSER-PARTY-001');
        await reservationForm.getByRole('button', { name: 'Reserve interval' }).click();
        await expect(page.getByText('Asset reservation created.', { exact: true })).toBeVisible();

        row = page.locator('tr').filter({ hasText: code });
        const checkoutForm = row.locator('form[action*="/checkout"]');
        await checkoutForm.locator('input[name="source_reference"]').fill('BROWSER-BOOKING-001');
        await checkoutForm.getByRole('button', { name: 'Check out' }).click();
        await expect(page.getByText('Asset checked out.', { exact: true })).toBeVisible();

        row = page.locator('tr').filter({ hasText: code });
        const returnForm = row.locator('form[action*="/return"]');
        await returnForm.locator('input[name="condition_after"]').fill('good');
        await returnForm.getByRole('button', { name: 'Return for inspection' }).click();
        await expect(page.getByText('Asset returned for inspection.', { exact: true })).toBeVisible();

        row = page.locator('tr').filter({ hasText: code });
        const inspectForm = row.locator('form[action*="/inspect"]');
        await inspectForm.locator('select[name="resulting_status"]').selectOption('available');
        await inspectForm.locator('textarea[name="assessment"]').fill('Browser inspection passed.');
        await inspectForm.getByRole('button', { name: 'Complete inspection' }).click();
        await expect(page.getByText('Asset inspection recorded.', { exact: true })).toBeVisible();

        await page.goto('/quotations', { waitUntil: 'domcontentloaded' });
        const quotationForm = page.locator('form').filter({ hasText: 'Save non-posting draft' }).first();
        await quotationForm.locator('select[name="activity_type"]').selectOption('retail');
        await quotationForm.locator('input[name="branch_id"]').fill(branchId);
        await quotationForm.locator('input[name="store_id"]').fill(storeId);
        await quotationForm.locator('input[name="valid_until"]').fill('2030-12-31');
        await quotationForm.locator('input[name="lines[0][product_id]"]').fill('2');
        await quotationForm.locator('input[name="lines[0][description_en]"]').fill('Browser quotation line');
        await quotationForm.locator('input[name="lines[0][description_ar]"]').fill('سطر عرض اختبار المتصفح');
        await quotationForm.locator('input[name="lines[0][unit_price]"]').fill('125');
        await quotationForm.getByRole('button', { name: 'Save non-posting draft' }).click();
        await expect(page.getByText('Quotation created as a non-posting draft.', { exact: true })).toBeVisible();
        const quotationRow = page.locator('table tbody tr').filter({ hasText: 'QTN-' }).first();
        await expect(quotationRow).toBeVisible();
        await quotationRow.locator('details summary').click();
        await quotationRow.locator('details').locator('input[name="lines[0][unit_price]"]').fill('130');
        await quotationRow.locator('details').getByRole('button', { name: 'Save edit without posting' }).click();
        await expect(page.getByText('Quotation updated without posting any effect.', { exact: true })).toBeVisible();
        await page.locator('table tbody tr').filter({ hasText: 'QTN-' }).first().getByRole('link', { name: 'Print' }).click();
        await expect(page.getByText(/NON-POSTING QUOTATION/)).toBeVisible();

        await page.goto('/reports', { waitUntil: 'domcontentloaded' });
        const reportToday = new Date();
        const reportFrom = new Date(reportToday.getTime() - 30 * 24 * 60 * 60 * 1000);
        const formatDate = (date) => date.toISOString().slice(0, 10);
        await page.getByRole('textbox', { name: 'From', exact: true }).fill(formatDate(reportFrom));
        await page.getByRole('textbox', { name: 'To', exact: true }).fill(formatDate(reportToday));
        await Promise.all([
            page.waitForURL(/\/reports\?date_from=/),
            page.getByRole('button', { name: 'Apply filters', exact: true }).click(),
        ]);
        await expect(page.getByText('Source reconciliation', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Create Excel export' }).click();
        await expect(page.getByText('Export requested. It will appear in the export job center.', { exact: true })).toBeVisible();
        await page.goto('/exports', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Export job center', exact: true })).toBeVisible();
        await expect.poll(async () => {
            await page.reload({ waitUntil: 'domcontentloaded' });
            return await page.locator('table tbody tr').first().getByRole('link', { name: 'Download' }).count() > 0;
        }, { timeout: 30000, intervals: [500, 1000, 2000] }).toBe(true);
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('table tbody tr').first().getByRole('link', { name: 'Download' }).click(),
        ]);
        expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
    });
});
