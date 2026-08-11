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
    await form.locator(`input[name="locale"][value="${locale}"]`).evaluate((input) => input.form.submit());
    await page.waitForLoadState('domcontentloaded');
}

test.describe('TSK-028 separate wallet browser closure', () => {
    test('administrator can inspect separate Product and Party Wallet routes and customer links', async ({ page }) => {
        test.setTimeout(180_000);
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);

        const productResponse = await page.goto('/wallets/product', { waitUntil: 'domcontentloaded' });
        expect(productResponse?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Product Wallet', exact: true })).toBeVisible();
        await expect(page.getByText('product_wallet_ledger', { exact: true })).toBeVisible();
        await expect(page.getByText('Product Wallet and Party Wallet never share a table, balance query, source type, approval route, or transfer endpoint.', { exact: true })).toBeVisible();

        const partyResponse = await page.goto('/wallets/party', { waitUntil: 'domcontentloaded' });
        expect(partyResponse?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Party Wallet', exact: true })).toBeVisible();
        await expect(page.getByText('party_wallet_ledger', { exact: true })).toBeVisible();

        await page.goto(`/customers?q=${FIXTURE_PHONE}`, { waitUntil: 'domcontentloaded' });
        await expect(page.getByText(FIXTURE_PHONE, { exact: true })).toBeVisible();
        const profileButton = page.getByRole('link', { name: 'Open profile', exact: true });
        const profileHref = await profileButton.getAttribute('href');
        expect(new URL(profileHref, page.url()).pathname).toMatch(/^\/customers\/\d+$/);
        await page.goto(profileHref, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('a[href$="/product-wallet"]').filter({ hasText: 'Product Wallet' }).first()).toBeVisible();
        await expect(page.locator('a[href$="/party-wallet"]').filter({ hasText: 'Party Wallet' }).first()).toBeVisible();

        await page.locator('a[href$="/product-wallet"]').filter({ hasText: 'Product Wallet' }).first().click();
        await expect(page).toHaveURL(/\/customers\/\d+\/product-wallet$/);
        await expect(page.getByText('Derived balance', { exact: true })).toBeVisible();
        await expect(page.getByText('Immutable ledger history', { exact: true })).toBeVisible();
    });

    test('wallet screens keep RTL/LTR, mobile layout, accessibility, and visual baseline', async ({ page }) => {
        test.setTimeout(180_000);
        await login(page, LOCAL_BROWSER_ACTORS.administrator.username, LOCAL_BROWSER_ACTORS.administrator.password);
        await page.setViewportSize(MOBILE_VIEWPORT);
        await page.goto('/wallets/product', { waitUntil: 'domcontentloaded' });

        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
        assertNoSeriousViolations(await scan(page), 'wallet mobile LTR');
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk028-product-wallet-mobile-en.png', { fullPage: true, animations: 'disabled' });
        }

        await switchLocale(page, 'ar');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
        assertNoSeriousViolations(await scan(page), 'wallet mobile RTL');
        if (test.info().project.name === 'chromium') {
            await expect(page).toHaveScreenshot('tsk028-product-wallet-mobile-ar.png', { fullPage: true, animations: 'disabled' });
        }

        await switchLocale(page, 'en');
        await page.goto('/wallets/party', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Party Wallet', exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    });
});
