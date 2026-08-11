import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login, LOCAL_BROWSER_ACTORS } from '../helpers/auth.js';

const MOBILE_VIEWPORT = { width: 390, height: 844 };
const FIXTURE_PHONE = '01002702700';

async function scan(page) {
    return new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .exclude('.phpdebugbar')
        .analyze();
}

function assertNoSeriousViolations(results, label) {
    const blocking = results.violations.filter((violation) => violation.impact === 'critical' || violation.impact === 'serious');
    expect(blocking, `${label}: ${JSON.stringify(blocking)}`).toEqual([]);
}

async function switchLocale(page, locale) {
    const form = page.locator(`form[action$="/locale"]`).first();
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        form.locator(`input[name="locale"][value="${locale}"]`).evaluate((input) => input.form.submit()),
    ]);
}

test.describe('TSK-027 customer master and loyalty browser closure', () => {
    test('administrator can create a bilingual customer and open the real loyalty ledger', async ({ page }) => {
        test.setTimeout(60_000);
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.goto('/customers/create');

        const createdPhone = {
            chromium: '01002702799',
            firefox: '01002702798',
            webkit: '01002702797',
        }[test.info().project.name];
        await page.getByLabel('Primary phone', { exact: true }).fill(createdPhone);
        await page.getByLabel('Arabic name', { exact: true }).fill('عميل متصفح TSK-027');
        await page.getByLabel('English name', { exact: true }).fill('TSK-027 Created Customer');
        await page.getByLabel('Consent purpose', { exact: true }).selectOption('loyalty');
        await page.getByLabel('Consent status', { exact: true }).selectOption('granted');
        await page.getByRole('button', { name: 'Create customer profile', exact: true }).click();

        await expect(page).toHaveURL(/\/customers\/\d+$/);
        await expect(page.getByText('TSK-027 Created Customer', { exact: true })).toBeVisible();
        await expect(page.getByText(createdPhone, { exact: true })).toBeVisible();
        await page.getByRole('link', { name: 'Loyalty ledger', exact: true }).click();
        await expect(page).toHaveURL(/\/customers\/\d+\/loyalty$/);
        await expect(page.getByText('Immutable points history', { exact: true })).toBeVisible();

        if (test.info().project.name === 'chromium') {
            const results = await scan(page);
            assertNoSeriousViolations(results, 'customer loyalty desktop');
        }
    });

    test('store-scoped cashier can search and select the customer in POS', async ({ page }) => {
        test.setTimeout(60_000);
        await login(page, LOCAL_BROWSER_ACTORS.storeScoped.username, LOCAL_BROWSER_ACTORS.storeScoped.password);
        await page.goto(`/pos?customer_q=${FIXTURE_PHONE}`);
        await expect(page.getByText(FIXTURE_PHONE, { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Select', exact: true }).click();
        await expect(page.getByText('TSK-027 Browser Customer', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Clear customer', exact: true })).toBeVisible();
    });

    test('customer screens keep RTL/LTR and 390x844 accessible without horizontal overflow', async ({ page }) => {
        test.setTimeout(60_000);
        test.skip(test.info().project.name !== 'chromium', 'The cross-browser customer flow covers Firefox/WebKit; visual/viewport evidence is intentionally one stable baseline.');

        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.setViewportSize(MOBILE_VIEWPORT);
        await page.goto('/customers');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page).toHaveScreenshot(`tsk027-customers-${test.info().project.name}.png`, { fullPage: true, animations: 'disabled' });

        const englishOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(englishOverflow, 'Customer master must not overflow at 390x844 in LTR').toBeLessThanOrEqual(1);
        assertNoSeriousViolations(await scan(page), 'customer master mobile LTR');

        await switchLocale(page, 'ar');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        const arabicOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(arabicOverflow, 'Customer master must not overflow at 390x844 in RTL').toBeLessThanOrEqual(1);
        assertNoSeriousViolations(await scan(page), 'customer master mobile RTL');

        await switchLocale(page, 'en');
    });
});
